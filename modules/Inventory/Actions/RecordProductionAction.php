<?php

declare(strict_types=1);

namespace Modules\Inventory\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Enums\InventoryMovementType;
use Modules\Inventory\Enums\StockCondition;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Inventory\Models\ProductionOrder;
use Modules\Inventory\Models\ProductionOrderItem;
use Modules\Inventory\Models\Recipe;
use Modules\Inventory\Models\UnitOfMeasure;
use Modules\Inventory\Support\Quantity;
use Modules\Inventory\Support\UnitConverter;

/**
 * Records production: raw materials are consumed from the source store and the finished
 * good is created in the output store at the batch's real unit cost (total input cost ÷
 * actual yield). This is a value-preserving transformation within inventory — no COGS is
 * booked here; COGS lands when the finished good sells.
 *
 * Two ways to record it:
 *
 *  - execute(): made and recorded in one go — for a batch logged after the fact.
 *  - start() then complete(): the kitchen starts a batch, the ingredients are *held* at the
 *    source store so nobody else can promise them, and only when it is finished are the
 *    amounts actually used deducted and the real yield put into stock. cancel() hands the
 *    held ingredients back.
 *
 * The recipe supplies the plan; the cook supplies what was actually used and made, which
 * is where real yield, wastage and variance come from.
 */
final class RecordProductionAction
{
    public function __construct(
        private readonly PostInventoryMovementAction $postInventoryMovement,
        private readonly AdjustInventoryReservationAction $reservations,
        private readonly UnitConverter $converter,
    ) {}

    /**
     * @param  array{
     *     tenant_id: string,
     *     recipe_id: int,
     *     source_location_id: int,
     *     output_location_id?: int|null,
     *     planned_quantity?: float|int|string|null,
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
            $recipe = $this->recipeFor($data);
            $yield = $this->yieldFrom($data);
            $planned = $this->plannedFrom($data, $recipe);
            $sourceLocationId = (int) $data['source_location_id'];

            $order = ProductionOrder::query()->create([
                'tenant_id' => $data['tenant_id'],
                'recipe_id' => $recipe->id,
                'recipe_version' => $recipe->version,
                'output_product_variant_id' => $recipe->output_product_variant_id,
                'source_location_id' => $sourceLocationId,
                'output_location_id' => (int) ($data['output_location_id'] ?? $sourceLocationId),
                'reference_number' => $data['reference_number'] ?? null,
                'planned_quantity' => $planned,
                'actual_yield_quantity' => $yield,
                'status' => ProductionOrder::STATUS_COMPLETED,
                'produced_at' => $data['produced_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            return $this->consumeAndProduce($order, $recipe, $yield, (array) $data['items']);
        });
    }

    /**
     * Start a batch: record the plan and hold each ingredient, scaled to it, at the source
     * store. Nothing is deducted. Ingredients the store cannot fully cover are held as far
     * as possible and named back, so the kitchen knows before it is halfway through.
     *
     * @param  array{
     *     tenant_id: string,
     *     recipe_id: int,
     *     source_location_id: int,
     *     output_location_id?: int|null,
     *     planned_quantity: float|int|string,
     *     reference_number?: string|null,
     *     notes?: string|null,
     * }  $data
     * @return array{order: ProductionOrder, short: list<string>}
     */
    public function start(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $recipe = $this->recipeFor($data, ['items.unit', 'items.componentVariant.product']);
            $planned = $this->plannedFrom($data, $recipe);
            $sourceLocationId = (int) $data['source_location_id'];
            $scale = $planned / max(0.0001, (float) $recipe->yield_quantity);

            $order = ProductionOrder::query()->create([
                'tenant_id' => $data['tenant_id'],
                'recipe_id' => $recipe->id,
                'recipe_version' => $recipe->version,
                'output_product_variant_id' => $recipe->output_product_variant_id,
                'source_location_id' => $sourceLocationId,
                'output_location_id' => (int) ($data['output_location_id'] ?? $sourceLocationId),
                'reference_number' => $data['reference_number'] ?? null,
                'planned_quantity' => $planned,
                'actual_yield_quantity' => 0,
                'status' => ProductionOrder::STATUS_IN_PROGRESS,
                'started_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $short = [];

            foreach ($recipe->items as $item) {
                // Same amount the dialog shows: the recipe line, its waste, scaled to the plan.
                $plannedQuantity = Quantity::round((float) $item->quantity * (1 + (float) $item->wastage_percent / 100) * $scale);

                if ($plannedQuantity <= 0) {
                    continue;
                }

                $baseQuantity = $item->unit && $item->unit->isConvertible()
                    ? $this->converter->toBase($plannedQuantity, $item->unit)
                    : $plannedQuantity;

                $available = (float) (InventoryStockLevel::query()
                    ->where('tenant_id', $data['tenant_id'])
                    ->where('inventory_location_id', $sourceLocationId)
                    ->where('product_variant_id', $item->component_product_variant_id)
                    ->first()?->quantity_available ?? 0);

                $held = Quantity::round(max(0.0, min($baseQuantity, $available)));

                if ($held > 0) {
                    $this->reservations->reserve($data['tenant_id'], $sourceLocationId, (int) $item->component_product_variant_id, $held);
                }

                if ($held < $baseQuantity - 0.00001) {
                    $short[] = $item->componentVariant?->product?->name ?? 'An ingredient';
                }

                $order->items()->create([
                    'tenant_id' => $data['tenant_id'],
                    'component_product_variant_id' => $item->component_product_variant_id,
                    'unit_id' => $item->unit_id,
                    'planned_quantity' => $plannedQuantity,
                    'reserved_base_quantity' => $held,
                    'actual_quantity' => 0,
                ]);
            }

            return ['order' => $order->refresh()->load('items'), 'short' => $short];
        });
    }

    /**
     * Finish a started batch with what actually happened: the held ingredients are released,
     * the amounts actually used are deducted, and the real yield goes into stock.
     *
     * @param  array{
     *     actual_yield_quantity: float|int|string,
     *     output_location_id?: int|null,
     *     notes?: string|null,
     *     items?: array<int|string, array{actual_quantity?: float|int|string|null}>,
     * }  $data  items keyed by production item id; a line left out uses its planned amount
     */
    public function complete(ProductionOrder $order, array $data): ProductionOrder
    {
        return DB::transaction(function () use ($order, $data): ProductionOrder {
            $order = ProductionOrder::query()->with('items')->lockForUpdate()->findOrFail($order->id);

            if (! $order->isInProgress()) {
                throw ValidationException::withMessages([
                    'production' => 'This production is not in progress — it has already been completed or cancelled.',
                ]);
            }

            $yield = $this->yieldFrom($data);

            // Hand the held ingredients back first, so the real consumption below is checked
            // against what is truly on the shelf rather than blocked by its own hold.
            $this->releaseHeld($order);

            $note = trim((string) ($data['notes'] ?? ''));

            $order->update([
                'output_location_id' => ! empty($data['output_location_id']) ? (int) $data['output_location_id'] : $order->output_location_id,
                'actual_yield_quantity' => $yield,
                'status' => ProductionOrder::STATUS_COMPLETED,
                'produced_at' => now(),
                'notes' => $note === '' ? $order->notes : trim(($order->notes ? $order->notes."\n" : '').$note),
            ]);

            $actuals = (array) ($data['items'] ?? []);

            $lines = $order->items->map(fn (ProductionOrderItem $item): array => [
                'item_id' => $item->id,
                'component_product_variant_id' => $item->component_product_variant_id,
                'unit_id' => $item->unit_id,
                'planned_quantity' => (float) $item->planned_quantity,
                'actual_quantity' => $actuals[$item->id]['actual_quantity'] ?? $item->planned_quantity,
            ])->all();

            $recipe = $order->recipe_id ? Recipe::query()->find($order->recipe_id) : null;

            return $this->consumeAndProduce($order->refresh(), $recipe, $yield, $lines);
        });
    }

    /** Abandon a started batch. The held ingredients become available again; nothing was used. */
    public function cancel(ProductionOrder $order, ?string $reason = null): ProductionOrder
    {
        return DB::transaction(function () use ($order, $reason): ProductionOrder {
            $order = ProductionOrder::query()->with('items')->lockForUpdate()->findOrFail($order->id);

            if (! $order->isInProgress()) {
                throw ValidationException::withMessages([
                    'production' => 'Only a production that is still in progress can be cancelled.',
                ]);
            }

            $this->releaseHeld($order);

            $reason = trim((string) $reason);

            $order->update([
                'status' => ProductionOrder::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'notes' => $reason === '' ? $order->notes : trim(($order->notes ? $order->notes."\n" : '').'Cancelled: '.$reason),
            ]);

            return $order->refresh()->load('items');
        });
    }

    /**
     * Deduct each line's actual amount and put the yield into stock at the batch's real cost.
     * A line with an item_id updates the planned line created at start; otherwise a new
     * line is written.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function consumeAndProduce(ProductionOrder $order, ?Recipe $recipe, float $yield, array $lines): ProductionOrder
    {
        $totalCostMinor = 0;
        $existing = $order->items()->get()->keyBy('id');

        foreach ($lines as $line) {
            $actualQuantity = Quantity::round((float) ($line['actual_quantity'] ?? 0));
            $item = ! empty($line['item_id']) ? $existing->get((int) $line['item_id']) : null;

            if ($actualQuantity <= 0) {
                // Planned but not used at all: keep the record honest rather than dropping it.
                $item?->update(['actual_quantity' => 0, 'unit_cost_minor' => 0, 'line_cost_minor' => 0]);

                continue;
            }

            $variantId = (int) $line['component_product_variant_id'];
            $unit = $this->resolveUnit($line['unit_id'] ?? null);

            // Convert the entered quantity to the component's base unit for depletion.
            $baseQuantity = $unit && $unit->isConvertible()
                ? $this->converter->toBase($actualQuantity, $unit)
                : $actualQuantity;

            $stockLevel = InventoryStockLevel::query()
                ->where('tenant_id', $order->tenant_id)
                ->where('inventory_location_id', $order->source_location_id)
                ->where('product_variant_id', $variantId)
                ->first();

            $unitCostMinor = (int) ($stockLevel->average_cost_minor ?? 0);
            $lineCostMinor = (int) round($baseQuantity * $unitCostMinor);
            $totalCostMinor += $lineCostMinor;

            // Consume the raw material; accounting is handled here (net-zero), so the
            // movement itself posts no journal entry.
            $this->postInventoryMovement->executeFromSource([
                'tenant_id' => $order->tenant_id,
                'inventory_location_id' => $order->source_location_id,
                'product_variant_id' => $variantId,
                'movement_type' => InventoryMovementType::ProductionConsume->value,
                'stock_condition' => StockCondition::Sellable->value,
                'quantity' => $baseQuantity,
                'unit_cost_minor' => $unitCostMinor,
                'reference_number' => $order->reference_number,
                'notes' => 'Consumed by production.',
                'occurred_at' => $order->produced_at,
            ], 'production', $order->id);

            $values = [
                'actual_quantity' => $actualQuantity,
                'unit_cost_minor' => $unitCostMinor,
                'line_cost_minor' => $lineCostMinor,
            ];

            if ($item) {
                $item->update($values);
            } else {
                $order->items()->create([
                    'tenant_id' => $order->tenant_id,
                    'component_product_variant_id' => $variantId,
                    'unit_id' => $unit?->id,
                    'planned_quantity' => Quantity::round((float) ($line['planned_quantity'] ?? $actualQuantity)),
                    ...$values,
                ]);
            }
        }

        $finishedUnitCostMinor = (int) round($totalCostMinor / max(0.0001, $yield));

        // Create the finished good at the batch's real unit cost. Every production run
        // gets a lot so a cooked batch can be traced and expired like any other stock.
        $this->postInventoryMovement->executeFromSource([
            'tenant_id' => $order->tenant_id,
            'inventory_location_id' => $order->output_location_id,
            'product_variant_id' => $order->output_product_variant_id,
            'movement_type' => InventoryMovementType::ProductionOutput->value,
            'stock_condition' => StockCondition::Sellable->value,
            'quantity' => $yield,
            'unit_cost_minor' => $finishedUnitCostMinor,
            'movement_value_minor' => $totalCostMinor,
            'reference_number' => $order->reference_number,
            'batch_number' => $this->batchNumberFor($order),
            'expiry_date' => $recipe ? $this->expiryFor($recipe, $order) : null,
            'notes' => 'Produced from batch.',
            'occurred_at' => $order->produced_at,
        ], 'production', $order->id);

        $order->update([
            'total_cost_minor' => $totalCostMinor,
            'unit_cost_minor' => $finishedUnitCostMinor,
        ]);

        return $order->refresh()->load('items');
    }

    /** Release every hold a started batch placed at its source store. */
    private function releaseHeld(ProductionOrder $order): void
    {
        foreach ($order->items as $item) {
            $held = (float) $item->reserved_base_quantity;

            if ($held <= 0) {
                continue;
            }

            $this->reservations->release($order->tenant_id, (int) $order->source_location_id, (int) $item->component_product_variant_id, $held);
            $item->update(['reserved_base_quantity' => 0]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $with
     */
    private function recipeFor(array $data, array $with = ['items']): Recipe
    {
        return Recipe::query()
            ->where('tenant_id', $data['tenant_id'])
            ->with($with)
            ->findOrFail($data['recipe_id']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function yieldFrom(array $data): float
    {
        $yield = Quantity::round((float) ($data['actual_yield_quantity'] ?? 0));

        if ($yield <= 0) {
            throw ValidationException::withMessages([
                'actual_yield_quantity' => 'Enter the quantity actually produced.',
            ]);
        }

        return $yield;
    }

    /**
     * The plan is how many the cook set out to make — 50 pies from a one-pie recipe, or 2.5
     * trays of a 10-portion one. Without it, one batch is the plan. Yield variance is
     * measured against this, so it must be the real intention, not the recipe's size.
     *
     * @param  array<string, mixed>  $data
     */
    private function plannedFrom(array $data, Recipe $recipe): float
    {
        $planned = Quantity::round((float) ($data['planned_quantity'] ?? $recipe->yield_quantity));

        if ($planned <= 0) {
            throw ValidationException::withMessages([
                'planned_quantity' => 'Enter how many you are making.',
            ]);
        }

        return $planned;
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
