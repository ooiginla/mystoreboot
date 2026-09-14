<?php

declare(strict_types=1);

namespace Modules\Inventory\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Enums\InventoryMovementType;
use Modules\Inventory\Enums\StockCondition;
use Modules\Inventory\Models\ProductionOrder;
use Modules\Inventory\Models\Recipe;
use Modules\Inventory\Models\UnitOfMeasure;
use Modules\Inventory\Support\Quantity;
use Modules\Inventory\Support\UnitConverter;

/**
 * Records a production batch: raw materials are consumed from the source store and
 * the finished good is created in the output store at the batch's real unit cost
 * (total input cost ÷ actual yield). This is a value-preserving transformation
 * within inventory — no COGS is booked here; COGS lands when the finished good sells.
 *
 * The recipe supplies the plan; the caller supplies the actual quantities used and
 * the actual yield, which is where real yield/wastage/variance comes from.
 */
final class RecordProductionAction
{
    public function __construct(
        private readonly PostInventoryMovementAction $postInventoryMovement,
        private readonly UnitConverter $converter,
    ) {}

    /**
     * @param  array{
     *     tenant_id: string,
     *     recipe_id: int,
     *     source_location_id: int,
     *     output_location_id?: int|null,
     *     actual_yield_quantity: float|int|string,
     *     reference_number?: string|null,
     *     notes?: string|null,
     *     produced_at?: string|null,
     *     items: array<int, array{component_product_variant_id: int, unit_id?: int|null, actual_quantity: float|int|string, planned_quantity?: float|int|string}>,
     * }  $data
     */
    public function execute(array $data): ProductionOrder
    {
        return DB::transaction(function () use ($data): ProductionOrder {
            $recipe = Recipe::query()
                ->where('tenant_id', $data['tenant_id'])
                ->with('items')
                ->findOrFail($data['recipe_id']);

            $yield = Quantity::round((float) $data['actual_yield_quantity']);

            if ($yield <= 0) {
                throw ValidationException::withMessages([
                    'actual_yield_quantity' => 'Enter the quantity actually produced.',
                ]);
            }

            $sourceLocationId = (int) $data['source_location_id'];
            $outputLocationId = (int) ($data['output_location_id'] ?? $sourceLocationId);

            $order = ProductionOrder::query()->create([
                'tenant_id' => $data['tenant_id'],
                'recipe_id' => $recipe->id,
                'recipe_version' => $recipe->version,
                'output_product_variant_id' => $recipe->output_product_variant_id,
                'source_location_id' => $sourceLocationId,
                'output_location_id' => $outputLocationId,
                'reference_number' => $data['reference_number'] ?? null,
                'planned_quantity' => Quantity::round((float) $recipe->yield_quantity),
                'actual_yield_quantity' => $yield,
                'status' => 'completed',
                'produced_at' => $data['produced_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $totalCostMinor = 0;

            foreach ((array) $data['items'] as $line) {
                $actualQuantity = Quantity::round((float) ($line['actual_quantity'] ?? 0));

                if ($actualQuantity <= 0) {
                    continue;
                }

                $variantId = (int) $line['component_product_variant_id'];
                $unit = $this->resolveUnit($line['unit_id'] ?? null);

                // Convert the entered quantity to the component's base unit for depletion.
                $baseQuantity = $unit && $unit->isConvertible()
                    ? $this->converter->toBase($actualQuantity, $unit)
                    : $actualQuantity;

                $stockLevel = \Modules\Inventory\Models\InventoryStockLevel::query()
                    ->where('tenant_id', $data['tenant_id'])
                    ->where('inventory_location_id', $sourceLocationId)
                    ->where('product_variant_id', $variantId)
                    ->first();

                $unitCostMinor = (int) ($stockLevel->average_cost_minor ?? 0);
                $lineCostMinor = (int) round($baseQuantity * $unitCostMinor);
                $totalCostMinor += $lineCostMinor;

                // Consume the raw material; accounting is handled here (net-zero), so the
                // movement itself posts no journal entry.
                $this->postInventoryMovement->executeFromSource([
                    'tenant_id' => $data['tenant_id'],
                    'inventory_location_id' => $sourceLocationId,
                    'product_variant_id' => $variantId,
                    'movement_type' => InventoryMovementType::ProductionConsume->value,
                    'stock_condition' => StockCondition::Sellable->value,
                    'quantity' => $baseQuantity,
                    'unit_cost_minor' => $unitCostMinor,
                    'reference_number' => $order->reference_number,
                    'notes' => 'Consumed by production.',
                    'occurred_at' => $order->produced_at,
                ], 'production', $order->id);

                $order->items()->create([
                    'tenant_id' => $data['tenant_id'],
                    'component_product_variant_id' => $variantId,
                    'unit_id' => $unit?->id,
                    'planned_quantity' => Quantity::round((float) ($line['planned_quantity'] ?? $actualQuantity)),
                    'actual_quantity' => $actualQuantity,
                    'unit_cost_minor' => $unitCostMinor,
                    'line_cost_minor' => $lineCostMinor,
                ]);
            }

            $finishedUnitCostMinor = (int) round($totalCostMinor / max(0.0001, $yield));

            // Create the finished good at the batch's real unit cost. Every production run
            // gets a lot so a cooked batch can be traced and expired like any other stock.
            $this->postInventoryMovement->executeFromSource([
                'tenant_id' => $data['tenant_id'],
                'inventory_location_id' => $outputLocationId,
                'product_variant_id' => $recipe->output_product_variant_id,
                'movement_type' => InventoryMovementType::ProductionOutput->value,
                'stock_condition' => StockCondition::Sellable->value,
                'quantity' => $yield,
                'unit_cost_minor' => $finishedUnitCostMinor,
                'movement_value_minor' => $totalCostMinor,
                'reference_number' => $order->reference_number,
                'batch_number' => $this->batchNumberFor($order),
                'expiry_date' => $this->expiryFor($recipe, $order),
                'notes' => 'Produced from batch.',
                'occurred_at' => $order->produced_at,
            ], 'production', $order->id);

            $order->update([
                'total_cost_minor' => $totalCostMinor,
                'unit_cost_minor' => $finishedUnitCostMinor,
            ]);

            return $order->refresh()->load('items');
        });
    }

    private function resolveUnit(mixed $unitId): ?UnitOfMeasure
    {
        return $unitId ? UnitOfMeasure::query()->find((int) $unitId) : null;
    }

    /**
     * The cook's own reference wins when they gave one; otherwise the batch is named
     * after the production order so the lot always points back at the run that made it.
     */
    private function batchNumberFor(ProductionOrder $order): string
    {
        $reference = trim((string) ($order->reference_number ?? ''));

        return $reference !== ''
            ? $reference
            : 'PRD-'.$order->produced_at->format('Ymd').'-'.$order->id;
    }

    /**
     * Null shelf life means the finished good does not expire — the lot is still created
     * for traceability, it just carries no expiry date.
     */
    private function expiryFor(Recipe $recipe, ProductionOrder $order): ?string
    {
        $days = $recipe->shelf_life_days;

        return $days === null
            ? null
            : $order->produced_at->copy()->addDays((int) $days)->toDateString();
    }
}
