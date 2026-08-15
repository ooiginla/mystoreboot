<?php

declare(strict_types=1);

namespace Modules\Inventory\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Actions\PostJournalEntryAction;
use Modules\Inventory\Enums\InventoryMovementType;
use Modules\Inventory\Enums\StockCondition;
use Modules\Inventory\Models\InventoryBatch;
use Modules\Inventory\Models\InventoryMovement;
use Modules\Inventory\Models\InventoryStockLevel;

final class PostInventoryMovementAction
{
    private const SYSTEM_SOURCE_TYPES = ['goods_receipt', 'sales_order', 'sales_return'];

    public function __construct(private readonly PostJournalEntryAction $postJournalEntry) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): void
    {
        $this->executeMovement($data, false);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function executeFromSource(array $data, string $sourceType, int $sourceId): void
    {
        if (! in_array($sourceType, self::SYSTEM_SOURCE_TYPES, true)) {
            throw new \InvalidArgumentException("Unsupported inventory movement source [{$sourceType}].");
        }

        $this->executeMovement([
            ...$data,
            'reference_type' => $sourceType,
            'reference_id' => $sourceId,
        ], true);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function executeMovement(array $data, bool $accountingHandledBySource): void
    {
        DB::transaction(function () use ($data, $accountingHandledBySource): void {
            $type = InventoryMovementType::from($data['movement_type']);

            if ($type === InventoryMovementType::TransferOut) {
                $this->postTransfer($data);

                return;
            }

            $this->postSingleMovement($data, $type, $accountingHandledBySource);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function postTransfer(array $data): void
    {
        $source = $this->stockLevel($data['tenant_id'], (int) $data['inventory_location_id'], (int) $data['product_variant_id']);
        $destination = $this->stockLevel($data['tenant_id'], (int) $data['destination_inventory_location_id'], (int) $data['product_variant_id']);
        $quantity = (int) $data['quantity'];

        $this->assertEnoughStock($source, $quantity);

        $unitCostMinor = $this->moneyToMinor($data['unit_cost'] ?? 0) ?: (int) $source->average_cost_minor;

        $this->applyDelta($source, -$quantity, $unitCostMinor);
        $this->applyDelta($destination, $quantity, $unitCostMinor);

        $transferOut = $this->recordMovement($data, InventoryMovementType::TransferOut, $source, -$quantity, $unitCostMinor);
        $this->recordMovement([
            ...$data,
            'inventory_location_id' => $data['destination_inventory_location_id'],
            'destination_inventory_location_id' => $data['inventory_location_id'],
        ], InventoryMovementType::TransferIn, $destination, $quantity, $unitCostMinor);

        $valueMinor = $quantity * $unitCostMinor;

        if ($valueMinor > 0) {
            $sourceBranchId = $source->loadMissing('location')->location?->branch_id;
            $destinationBranchId = $destination->loadMissing('location')->location?->branch_id;

            $this->postJournalEntry->execute(
                $data['tenant_id'],
                (string) ($data['occurred_at'] ?? now()->toDateString()),
                'Inventory transfer',
                [
                    ['account_code' => '1200', 'branch_id' => $destinationBranchId, 'debit_minor' => $valueMinor],
                    ['account_code' => '1200', 'branch_id' => $sourceBranchId, 'credit_minor' => $valueMinor],
                ],
                'inventory_movement',
                $transferOut->id,
                'transferred',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function postSingleMovement(array $data, InventoryMovementType $type, bool $accountingHandledBySource): void
    {
        $stockLevel = $this->stockLevel($data['tenant_id'], (int) $data['inventory_location_id'], (int) $data['product_variant_id']);
        $quantity = (int) $data['quantity'];
        $delta = $quantity * $type->stockDeltaSign();
        $providedUnitCostMinor = array_key_exists('unit_cost_minor', $data)
            ? (int) $data['unit_cost_minor']
            : $this->moneyToMinor($data['unit_cost'] ?? 0);

        if ($type === InventoryMovementType::OpeningStock && $providedUnitCostMinor <= 0) {
            throw ValidationException::withMessages([
                'unit_cost' => 'Enter a unit cost greater than zero for opening stock.',
            ]);
        }

        $unitCostMinor = $providedUnitCostMinor ?: (int) $stockLevel->average_cost_minor;
        $movementValueMinor = array_key_exists('movement_value_minor', $data)
            ? (int) $data['movement_value_minor']
            : abs($delta) * $unitCostMinor;

        if ($delta < 0) {
            $this->assertEnoughStock($stockLevel, abs($delta));
        }

        $this->applyDelta($stockLevel, $delta, $unitCostMinor, $movementValueMinor);
        $movement = $this->recordMovement($data, $type, $stockLevel, $delta, $unitCostMinor, $movementValueMinor);
        $this->recordBatchIfApplicable($data, $type, $delta, $unitCostMinor);
        $this->postAccountingEntryIfApplicable(
            $data,
            $type,
            $delta,
            $unitCostMinor,
            $stockLevel,
            $movement,
            $accountingHandledBySource,
        );
    }

    private function stockLevel(string $tenantId, int $locationId, int $variantId): InventoryStockLevel
    {
        return InventoryStockLevel::query()
            ->where('tenant_id', $tenantId)
            ->where('inventory_location_id', $locationId)
            ->where('product_variant_id', $variantId)
            ->lockForUpdate()
            ->firstOrCreate([
                'tenant_id' => $tenantId,
                'inventory_location_id' => $locationId,
                'product_variant_id' => $variantId,
            ]);
    }

    private function applyDelta(
        InventoryStockLevel $stockLevel,
        int $delta,
        int $unitCostMinor,
        ?int $incomingValueMinor = null,
    ): void
    {
        $currentQuantity = $stockLevel->quantity_on_hand;

        if ($delta > 0 && $unitCostMinor > 0) {
            $currentValue = max(0, $currentQuantity) * $stockLevel->average_cost_minor;
            $incomingValue = $incomingValueMinor ?? ($delta * $unitCostMinor);
            $newQuantity = max(0, $currentQuantity) + $delta;
            $stockLevel->average_cost_minor = (int) round(($currentValue + $incomingValue) / max(1, $newQuantity));
        }

        $stockLevel->quantity_on_hand = $currentQuantity + $delta;
        $stockLevel->last_movement_at = now();
        $stockLevel->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function recordMovement(
        array $data,
        InventoryMovementType $type,
        InventoryStockLevel $stockLevel,
        int $delta,
        int $unitCostMinor,
        ?int $movementValueMinor = null,
    ): InventoryMovement
    {
        return InventoryMovement::query()->create([
            'tenant_id' => $data['tenant_id'],
            'inventory_location_id' => $data['inventory_location_id'],
            'destination_inventory_location_id' => $data['destination_inventory_location_id'] ?? null,
            'product_variant_id' => $data['product_variant_id'],
            'vendor_id' => $data['vendor_id'] ?? null,
            'movement_type' => $type->value,
            'stock_condition' => $data['stock_condition'] ?? StockCondition::Sellable->value,
            'quantity' => $delta,
            'stock_after' => $stockLevel->quantity_on_hand,
            'unit_cost_minor' => $unitCostMinor,
            'movement_value_minor' => $movementValueMinor ?? (abs($delta) * $unitCostMinor),
            'batch_number' => $data['batch_number'] ?? null,
            'expiry_date' => $data['expiry_date'] ?? null,
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'reference_number' => $data['reference_number'] ?? null,
            'notes' => $data['notes'] ?? null,
            'occurred_at' => $data['occurred_at'] ?? now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function recordBatchIfApplicable(array $data, InventoryMovementType $type, int $delta, int $unitCostMinor): void
    {
        $condition = StockCondition::from($data['stock_condition'] ?? StockCondition::Sellable->value);

        if ($delta <= 0 && ! in_array($type, [InventoryMovementType::Damaged], true)) {
            return;
        }

        $batchNumber = $data['batch_number'] ?? null;
        $expiryDate = $data['expiry_date'] ?? null;

        if (! $batchNumber && ! $expiryDate && $condition === StockCondition::Sellable) {
            return;
        }

        InventoryBatch::query()->create([
            'tenant_id' => $data['tenant_id'],
            'inventory_location_id' => $data['inventory_location_id'],
            'product_variant_id' => $data['product_variant_id'],
            'batch_number' => $batchNumber,
            'expiry_date' => $expiryDate,
            'stock_condition' => $condition->value,
            'quantity_remaining' => abs($delta),
            'unit_cost_minor' => $unitCostMinor,
        ]);
    }

    private function assertEnoughStock(InventoryStockLevel $stockLevel, int $quantity): void
    {
        if ($stockLevel->quantity_available >= $quantity) {
            return;
        }

        throw ValidationException::withMessages([
            'quantity' => 'There is not enough available stock at the selected location.',
        ]);
    }

    private function moneyToMinor(mixed $value): int
    {
        return (int) round(((float) (is_string($value) ? str_replace(',', '', $value) : ($value ?: 0))) * 100);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function postAccountingEntryIfApplicable(
        array $data,
        InventoryMovementType $type,
        int $delta,
        int $unitCostMinor,
        InventoryStockLevel $stockLevel,
        InventoryMovement $movement,
        bool $accountingHandledBySource,
    ): void
    {
        if ($accountingHandledBySource) {
            return;
        }

        $valueMinor = (int) $movement->movement_value_minor;

        if ($valueMinor <= 0 || in_array($type, [InventoryMovementType::TransferIn, InventoryMovementType::TransferOut], true)) {
            return;
        }

        $lines = match (true) {
            $type === InventoryMovementType::OpeningStock => [
                ['account_code' => '1200', 'branch_id' => $stockLevel->loadMissing('location')->location?->branch_id, 'debit_minor' => $valueMinor],
                ['account_code' => '3400', 'branch_id' => $stockLevel->location?->branch_id, 'credit_minor' => $valueMinor],
            ],
            $delta > 0 => [
                ['account_code' => '1200', 'branch_id' => $stockLevel->loadMissing('location')->location?->branch_id, 'debit_minor' => $valueMinor],
                ['account_code' => '4120', 'branch_id' => $stockLevel->location?->branch_id, 'credit_minor' => $valueMinor],
            ],
            default => [
                ['account_code' => 'EXP-6050', 'branch_id' => $stockLevel->loadMissing('location')->location?->branch_id, 'debit_minor' => $valueMinor],
                ['account_code' => '1200', 'branch_id' => $stockLevel->location?->branch_id, 'credit_minor' => $valueMinor],
            ],
        };

        $this->postJournalEntry->execute(
            $data['tenant_id'],
            (string) ($data['occurred_at'] ?? now()->toDateString()),
            $type === InventoryMovementType::OpeningStock
                ? 'Inventory opening stock'
                : 'Inventory '.$type->label().' adjustment',
            $lines,
            'inventory_movement',
            $movement->id,
            $type->value,
        );
    }
}
