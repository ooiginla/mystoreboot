<?php

declare(strict_types=1);

namespace Modules\Inventory\Actions;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Actions\PostJournalEntryAction;
use Modules\Finance\Models\FinanceJournalEntry;
use Modules\Inventory\Enums\InventoryMovementType;
use Modules\Inventory\Models\InventoryBatch;
use Modules\Inventory\Models\InventoryMovement;
use Modules\Inventory\Models\InventoryMovementBatch;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Inventory\Support\Quantity;

final class ReverseInventoryMovementAction
{
    /** @var list<InventoryMovementType> */
    private const REVERSIBLE_TYPES = [
        InventoryMovementType::OpeningStock,
        InventoryMovementType::StockIn,
        InventoryMovementType::StockOut,
        InventoryMovementType::AdjustmentIn,
        InventoryMovementType::AdjustmentOut,
        InventoryMovementType::Damaged,
        InventoryMovementType::TransferOut,
    ];

    public function __construct(
        private readonly PostInventoryMovementAction $postMovement,
        private readonly PostJournalEntryAction $postJournalEntry,
    ) {}

    public static function canReverse(InventoryMovement $movement): bool
    {
        return $movement->reference_type === null
            && $movement->reversal_of_movement_id === null
            && in_array($movement->movement_type, self::REVERSIBLE_TYPES, true)
            && (! $movement->relationLoaded('reversal') || $movement->reversal === null);
    }

    public function execute(InventoryMovement $movement, User $user): InventoryMovement
    {
        return DB::transaction(function () use ($movement, $user): InventoryMovement {
            $original = InventoryMovement::query()
                ->with(['reversal', 'batchAllocations.batch'])
                ->lockForUpdate()
                ->findOrFail($movement->id);

            if (! self::canReverse($original)) {
                throw ValidationException::withMessages([
                    'movement' => 'This movement cannot be reversed or has already been reversed.',
                ]);
            }

            $before = InventoryStockLevel::query()
                ->where('tenant_id', $original->tenant_id)
                ->where('inventory_location_id', $original->inventory_location_id)
                ->where('product_variant_id', $original->product_variant_id)
                ->lockForUpdate()
                ->first();
            $beforeQuantity = (float) ($before?->quantity_on_hand ?? 0);
            $beforeAverageMinor = (int) ($before?->average_cost_minor ?? 0);

            $sourceBatches = InventoryBatch::query()
                ->where('source_inventory_movement_id', $original->id)
                ->lockForUpdate()
                ->get();

            if ((float) $original->quantity > 0
                && ($original->batch_number || $original->expiry_date)
                && $sourceBatches->isEmpty()) {
                throw ValidationException::withMessages([
                    'movement' => 'This older batch movement cannot be reversed safely. Post a corrective adjustment instead.',
                ]);
            }

            if ((float) $original->quantity > 0
                && $sourceBatches->isNotEmpty()
                && (float) $sourceBatches->sum('quantity_remaining') < abs((float) $original->quantity)) {
                throw ValidationException::withMessages([
                    'movement' => 'This movement cannot be reversed because some of its batch stock has already been used.',
                ]);
            }

            $reversal = $this->postMovement->executeReversal($this->reversalData($original, $user));

            $this->reverseBatchEffects($original, $reversal, $sourceBatches);

            if ((float) $original->quantity > 0 && $original->movement_type !== InventoryMovementType::TransferIn) {
                $this->restoreAverageCostAfterIncomingReversal(
                    $original,
                    $beforeQuantity,
                    $beforeAverageMinor,
                );
            }

            $this->reverseJournals($original, $reversal);

            return $reversal;
        });
    }

    /** @return array<string, mixed> */
    private function reversalData(InventoryMovement $movement, User $user): array
    {
        $isTransfer = $movement->movement_type === InventoryMovementType::TransferOut;
        $type = $isTransfer
            ? InventoryMovementType::TransferOut
            : ((float) $movement->quantity > 0 ? InventoryMovementType::AdjustmentOut : InventoryMovementType::AdjustmentIn);

        return [
            'tenant_id' => $movement->tenant_id,
            'inventory_location_id' => $isTransfer ? $movement->destination_inventory_location_id : $movement->inventory_location_id,
            'destination_inventory_location_id' => $isTransfer ? $movement->inventory_location_id : null,
            'product_variant_id' => $movement->product_variant_id,
            'movement_type' => $type->value,
            'stock_condition' => $movement->stock_condition->value,
            'quantity' => abs((float) $movement->quantity),
            'entered_quantity' => $movement->entered_quantity !== null ? abs((float) $movement->entered_quantity) : null,
            'entered_unit_id' => $movement->entered_unit_id,
            'entered_unit_code' => $movement->entered_unit_code,
            'entered_unit_breakdown' => $movement->entered_unit_breakdown,
            'unit_cost_minor' => (int) $movement->unit_cost_minor,
            'movement_value_minor' => (int) $movement->movement_value_minor,
            'reference_type' => 'inventory_reversal',
            'reference_id' => $movement->id,
            'reference_number' => 'REV-'.$movement->id,
            'notes' => 'Automatic reversal of inventory movement #'.$movement->id.'.',
            'occurred_at' => now(),
            'reversal_of_movement_id' => $movement->id,
            'reversed_by_user_id' => $user->id,
            'skip_batch_depletion' => ! $isTransfer && (float) $movement->quantity > 0,
            'skip_batch_creation' => ! $isTransfer,
        ];
    }

    /** @param  Collection<int, InventoryBatch>  $sourceBatches */
    private function reverseBatchEffects(
        InventoryMovement $original,
        InventoryMovement $reversal,
        Collection $sourceBatches,
    ): void {
        if ($original->movement_type === InventoryMovementType::TransferOut) {
            return;
        }

        if ((float) $original->quantity > 0) {
            $remaining = abs((float) $original->quantity);

            foreach ($sourceBatches as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                $take = min($remaining, (float) $batch->quantity_remaining);
                $batch->quantity_remaining = Quantity::round((float) $batch->quantity_remaining - $take);
                $batch->save();
                InventoryMovementBatch::query()->create([
                    'tenant_id' => $original->tenant_id,
                    'inventory_movement_id' => $reversal->id,
                    'inventory_batch_id' => $batch->id,
                    'quantity' => Quantity::round($take),
                ]);
                $remaining = Quantity::round($remaining - $take);
            }

            return;
        }

        foreach ($original->batchAllocations as $allocation) {
            if (! $allocation->batch) {
                continue;
            }

            $allocation->batch->quantity_remaining = Quantity::round(
                (float) $allocation->batch->quantity_remaining + (float) $allocation->quantity,
            );
            $allocation->batch->save();
            InventoryMovementBatch::query()->create([
                'tenant_id' => $original->tenant_id,
                'inventory_movement_id' => $reversal->id,
                'inventory_batch_id' => $allocation->batch->id,
                'quantity' => $allocation->quantity,
            ]);
        }

        foreach ($sourceBatches as $batch) {
            $batch->quantity_remaining = 0;
            $batch->save();
        }
    }

    private function restoreAverageCostAfterIncomingReversal(
        InventoryMovement $original,
        float $beforeQuantity,
        int $beforeAverageMinor,
    ): void {
        $stock = InventoryStockLevel::query()
            ->where('tenant_id', $original->tenant_id)
            ->where('inventory_location_id', $original->inventory_location_id)
            ->where('product_variant_id', $original->product_variant_id)
            ->lockForUpdate()
            ->first();

        if (! $stock) {
            return;
        }

        $remainingQuantity = max(0.0, (float) $stock->quantity_on_hand);
        $remainingValue = max(0, ($beforeQuantity * $beforeAverageMinor) - (int) $original->movement_value_minor);
        $stock->average_cost_minor = $remainingQuantity > 0
            ? (int) round($remainingValue / $remainingQuantity)
            : 0;
        $stock->save();
    }

    private function reverseJournals(InventoryMovement $original, InventoryMovement $reversal): void
    {
        FinanceJournalEntry::query()
            ->with('lines.account')
            ->where('tenant_id', $original->tenant_id)
            ->where('source_type', 'inventory_movement')
            ->where('source_id', $original->id)
            ->get()
            ->each(function (FinanceJournalEntry $entry) use ($original, $reversal): void {
                $this->postJournalEntry->execute(
                    (string) $original->tenant_id,
                    now()->toDateString(),
                    'Reversal of '.$entry->entry_number,
                    $entry->lines->map(fn ($line): array => [
                        'account_code' => $line->account->code,
                        'branch_id' => $line->branch_id,
                        'party_type' => $line->party_type,
                        'party_id' => $line->party_id,
                        'debit_minor' => (int) $line->credit_minor,
                        'credit_minor' => (int) $line->debit_minor,
                        'memo' => 'Reversal of inventory movement #'.$original->id,
                    ])->all(),
                    'inventory_movement',
                    $reversal->id,
                    'reversal-'.$entry->id,
                );
            });
    }
}
