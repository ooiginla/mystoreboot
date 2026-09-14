<?php

declare(strict_types=1);

namespace Modules\Inventory\Actions;

use Illuminate\Support\Collection;
use Modules\Inventory\Enums\StockCondition;
use Modules\Inventory\Models\InventoryBatch;
use Modules\Inventory\Models\InventoryMovement;
use Modules\Inventory\Models\InventoryMovementBatch;
use Modules\Inventory\Support\Quantity;

/**
 * Draws an outbound quantity down against the lots actually sitting at a location.
 *
 * Strategy is FEFO — first-expired, first-out — which is what perishable stock needs:
 * the tray of chicken that expires on Tuesday leaves before the one that expires on
 * Friday. Lots with no expiry date sort last and are then taken oldest-first, so a
 * business that never records expiry dates gets plain FIFO for free.
 *
 * Batch records are a traceability overlay, not the stock ledger: inventory_stock_levels
 * remains the source of truth. If a location holds less batch-tracked stock than the
 * movement takes (common when batch numbers were only captured for some receipts), we
 * allocate what exists and leave the remainder untraced rather than blocking the sale.
 */
final class DepleteBatchesAction
{
    /**
     * @return Collection<int, InventoryMovementBatch> the allocations that were written
     */
    public function execute(InventoryMovement $movement, float $quantity): Collection
    {
        $remaining = Quantity::round(abs($quantity));
        $allocations = collect();

        if ($remaining <= 0) {
            return $allocations;
        }

        foreach ($this->availableBatches($movement) as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, (float) $batch->quantity_remaining);

            if ($take <= 0) {
                continue;
            }

            $batch->quantity_remaining = Quantity::round((float) $batch->quantity_remaining - $take);
            $batch->save();

            $allocations->push(InventoryMovementBatch::query()->create([
                'tenant_id' => $movement->tenant_id,
                'inventory_movement_id' => $movement->id,
                'inventory_batch_id' => $batch->id,
                'quantity' => Quantity::round($take),
            ]));

            $remaining = Quantity::round($remaining - $take);
        }

        return $allocations;
    }

    /**
     * Mirror the lots consumed at a source location into the destination, so a transfer
     * does not erase lot identity. Returns the batches created at the destination.
     *
     * @param  Collection<int, InventoryMovementBatch>  $allocations
     * @return Collection<int, InventoryBatch>
     */
    public function mirrorToDestination(Collection $allocations, int $destinationLocationId, InventoryMovement $inboundMovement): Collection
    {
        return $allocations->map(function (InventoryMovementBatch $allocation) use ($destinationLocationId, $inboundMovement): InventoryBatch {
            $source = $allocation->batch;

            $destination = InventoryBatch::query()->create([
                'tenant_id' => $source->tenant_id,
                'inventory_location_id' => $destinationLocationId,
                'product_variant_id' => $source->product_variant_id,
                // Keeps the recall chain intact when a lot crosses a store boundary.
                'source_inventory_batch_id' => $source->id,
                'batch_number' => $source->batch_number,
                'expiry_date' => $source->expiry_date,
                'stock_condition' => $source->stock_condition->value,
                'quantity_remaining' => $allocation->quantity,
                'unit_cost_minor' => $source->unit_cost_minor,
            ]);

            InventoryMovementBatch::query()->create([
                'tenant_id' => $source->tenant_id,
                'inventory_movement_id' => $inboundMovement->id,
                'inventory_batch_id' => $destination->id,
                'quantity' => $allocation->quantity,
            ]);

            return $destination;
        });
    }

    /**
     * @return Collection<int, InventoryBatch>
     */
    private function availableBatches(InventoryMovement $movement): Collection
    {
        return InventoryBatch::query()
            ->where('tenant_id', $movement->tenant_id)
            ->where('inventory_location_id', $movement->inventory_location_id)
            ->where('product_variant_id', $movement->product_variant_id)
            ->where('quantity_remaining', '>', 0)
            // Damaged, expired and quarantined lots are not on the shelf to be taken.
            ->where('stock_condition', StockCondition::Sellable->value)
            // NULL expiry sorts last so dated lots always go first; id breaks ties FIFO.
            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expiry_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }
}
