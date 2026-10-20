<?php

declare(strict_types=1);

namespace Modules\Inventory\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\Product;
use Modules\Finance\Actions\PostJournalEntryAction;
use Modules\Inventory\Enums\InventoryMovementType;
use Modules\Inventory\Models\InventoryMovement;
use Modules\Inventory\Models\InventoryStockLevel;

final class RevalueProductInventoryAction
{
    public function __construct(private readonly PostJournalEntryAction $postJournalEntry) {}

    /**
     * Revalue every positive stock balance using its variant's newly saved cost.
     * Quantity never changes; each location gets its own audit movement and journal.
     *
     * @param  list<int>|null  $variantIds
     */
    public function execute(Product $product, ?int $actorId = null, ?array $variantIds = null): int
    {
        return DB::transaction(function () use ($product, $actorId, $variantIds): int {
            $variantCosts = $product->variants()->pluck('cost_price_minor', 'id');
            if ($variantIds !== null) {
                $variantCosts = $variantCosts->only($variantIds);
            }
            $levels = InventoryStockLevel::query()
                ->where('tenant_id', $product->tenant_id)
                ->whereIn('product_variant_id', $variantCosts->keys())
                ->where('quantity_on_hand', '>', 0)
                ->with('location:id,branch_id')
                ->lockForUpdate()
                ->get();
            $count = 0;

            foreach ($levels as $level) {
                $newCostMinor = (int) ($variantCosts[$level->product_variant_id] ?? 0);
                $oldCostMinor = (int) $level->average_cost_minor;

                if ($newCostMinor === $oldCostMinor) {
                    continue;
                }

                $quantity = (float) $level->quantity_on_hand;
                $oldValueMinor = (int) round($quantity * $oldCostMinor);
                $newValueMinor = (int) round($quantity * $newCostMinor);
                $differenceMinor = $newValueMinor - $oldValueMinor;
                $isImportedOpeningCorrection = $oldCostMinor === 0 && InventoryMovement::query()
                    ->where('tenant_id', $product->tenant_id)
                    ->where('inventory_location_id', $level->inventory_location_id)
                    ->where('product_variant_id', $level->product_variant_id)
                    ->where('reference_type', 'catalog_import')
                    ->where('reference_id', $product->id)
                    ->exists();

                $level->average_cost_minor = $newCostMinor;
                $level->last_movement_at = now();
                $level->save();

                $movement = InventoryMovement::query()->create([
                    'tenant_id' => $product->tenant_id,
                    'inventory_location_id' => $level->inventory_location_id,
                    'product_variant_id' => $level->product_variant_id,
                    'movement_type' => InventoryMovementType::Revaluation->value,
                    'stock_condition' => 'sellable',
                    'quantity' => 0,
                    'stock_after' => $level->quantity_on_hand,
                    'unit_cost_minor' => $newCostMinor,
                    'movement_value_minor' => abs($differenceMinor),
                    'reference_type' => 'product_cost_revaluation',
                    'reference_id' => $product->id,
                    'notes' => sprintf(
                        'Inventory cost revalued from %d to %d minor units for %s units%s.',
                        $oldCostMinor,
                        $newCostMinor,
                        rtrim(rtrim(number_format($quantity, 4, '.', ''), '0'), '.'),
                        $actorId ? ' by user '.$actorId : '',
                    ),
                    'occurred_at' => now(),
                ]);

                if ($differenceMinor !== 0) {
                    $branchId = $level->location?->branch_id;
                    $offsetAccount = $isImportedOpeningCorrection ? '3400' : ($differenceMinor > 0 ? '4120' : 'EXP-6050');
                    $amountMinor = abs($differenceMinor);
                    $lines = $differenceMinor > 0
                        ? [
                            ['account_code' => '1200', 'branch_id' => $branchId, 'debit_minor' => $amountMinor],
                            ['account_code' => $offsetAccount, 'branch_id' => $branchId, 'credit_minor' => $amountMinor],
                        ]
                        : [
                            ['account_code' => $offsetAccount, 'branch_id' => $branchId, 'debit_minor' => $amountMinor],
                            ['account_code' => '1200', 'branch_id' => $branchId, 'credit_minor' => $amountMinor],
                        ];

                    $this->postJournalEntry->execute(
                        $product->tenant_id,
                        now()->toDateString(),
                        'Inventory cost revaluation · '.$product->name,
                        $lines,
                        'inventory_movement',
                        $movement->id,
                        'revalued',
                    );
                }

                $count++;
            }

            return $count;
        });
    }
}
