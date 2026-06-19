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
    public function __construct(private readonly PostJournalEntryAction $postJournalEntry) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): void
    {
        DB::transaction(function () use ($data): void {
            $type = InventoryMovementType::from($data['movement_type']);

            if ($type === InventoryMovementType::TransferOut) {
                $this->postTransfer($data);

                return;
            }

            $this->postSingleMovement($data, $type);
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

        $unitCostMinor = $this->moneyToMinor($data['unit_cost'] ?? 0) ?: $source->average_cost_minor;

        $this->applyDelta($source, -$quantity, $unitCostMinor);
        $this->applyDelta($destination, $quantity, $unitCostMinor);

        $this->recordMovement($data, InventoryMovementType::TransferOut, $source, -$quantity, $unitCostMinor);
        $this->recordMovement([
            ...$data,
            'inventory_location_id' => $data['destination_inventory_location_id'],
            'destination_inventory_location_id' => $data['inventory_location_id'],
        ], InventoryMovementType::TransferIn, $destination, $quantity, $unitCostMinor);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function postSingleMovement(array $data, InventoryMovementType $type): void
    {
        $stockLevel = $this->stockLevel($data['tenant_id'], (int) $data['inventory_location_id'], (int) $data['product_variant_id']);
        $quantity = (int) $data['quantity'];
        $delta = $quantity * $type->stockDeltaSign();
        $unitCostMinor = $this->moneyToMinor($data['unit_cost'] ?? 0) ?: $stockLevel->average_cost_minor;

        if ($delta < 0) {
            $this->assertEnoughStock($stockLevel, abs($delta));
        }

        $this->applyDelta($stockLevel, $delta, $unitCostMinor);
        $this->recordMovement($data, $type, $stockLevel, $delta, $unitCostMinor);
        $this->recordBatchIfApplicable($data, $type, $delta, $unitCostMinor);
        $this->postAccountingEntryIfApplicable($data, $type, $delta, $unitCostMinor);
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

    private function applyDelta(InventoryStockLevel $stockLevel, int $delta, int $unitCostMinor): void
    {
        $currentQuantity = $stockLevel->quantity_on_hand;

        if ($delta > 0 && $unitCostMinor > 0) {
            $currentValue = max(0, $currentQuantity) * $stockLevel->average_cost_minor;
            $incomingValue = $delta * $unitCostMinor;
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
    private function recordMovement(array $data, InventoryMovementType $type, InventoryStockLevel $stockLevel, int $delta, int $unitCostMinor): void
    {
        InventoryMovement::query()->create([
            'tenant_id' => $data['tenant_id'],
            'inventory_location_id' => $data['inventory_location_id'],
            'destination_inventory_location_id' => $data['destination_inventory_location_id'] ?? null,
            'product_variant_id' => $data['product_variant_id'],
            'movement_type' => $type->value,
            'stock_condition' => $data['stock_condition'] ?? StockCondition::Sellable->value,
            'quantity' => $delta,
            'stock_after' => $stockLevel->quantity_on_hand,
            'unit_cost_minor' => $unitCostMinor,
            'movement_value_minor' => abs($delta) * $unitCostMinor,
            'batch_number' => $data['batch_number'] ?? null,
            'expiry_date' => $data['expiry_date'] ?? null,
            'reference_type' => $data['reference_type'] ?? null,
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
    private function postAccountingEntryIfApplicable(array $data, InventoryMovementType $type, int $delta, int $unitCostMinor): void
    {
        if (in_array($data['reference_type'] ?? null, ['sales_order', 'sales_return', 'purchase_order'], true)) {
            return;
        }

        $valueMinor = abs($delta) * $unitCostMinor;

        if ($valueMinor <= 0 || in_array($type, [InventoryMovementType::TransferIn, InventoryMovementType::TransferOut], true)) {
            return;
        }

        $lines = $delta > 0
            ? [
                ['account_code' => '1200', 'debit_minor' => $valueMinor],
                ['account_code' => '3000', 'credit_minor' => $valueMinor],
            ]
            : [
                ['account_code' => '5000', 'debit_minor' => $valueMinor],
                ['account_code' => '1200', 'credit_minor' => $valueMinor],
            ];

        $this->postJournalEntry->execute(
            $data['tenant_id'],
            (string) ($data['occurred_at'] ?? now()->toDateString()),
            'Inventory '.$type->label().' adjustment',
            $lines,
            'inventory_movement',
            null,
            null,
        );
    }
}
