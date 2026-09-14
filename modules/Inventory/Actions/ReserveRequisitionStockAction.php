<?php

declare(strict_types=1);

namespace Modules\Inventory\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Inventory\Models\StockRequisition;
use Modules\Inventory\Models\StockRequisitionItem;
use Modules\Inventory\Support\Quantity;
use Modules\Inventory\Support\UnitConverter;

/**
 * Holds stock at the source store from the moment a requisition is approved until it is
 * shipped, rejected, or cancelled.
 *
 * Without this, approval promises nothing: two requisitions could both be approved
 * against the same 12kg of rice and the second would fail only at fulfilment. Reserving
 * makes an approval mean "this stock is spoken for".
 *
 * The per-line mechanics live in AdjustInventoryReservationAction, which already owns
 * `quantity_reserved` for sales orders — this action only adds what is specific to a
 * requisition: iterating its lines, converting to base units, and failing as a whole.
 */
final class ReserveRequisitionStockAction
{
    public function __construct(
        private readonly AdjustInventoryReservationAction $reservations,
        private readonly UnitConverter $converter,
    ) {}

    /**
     * Reserve every line at the source. Fails as a whole if any line cannot be covered:
     * a partial hold would lock stock away with nothing left to release it.
     */
    public function reserve(StockRequisition $requisition): void
    {
        DB::transaction(function () use ($requisition): void {
            $requisition->loadMissing('items.unit', 'items.componentVariant.product');

            foreach ($requisition->items as $item) {
                $quantity = $this->baseQuantity($item);

                if ($quantity <= 0) {
                    continue;
                }

                $name = $item->componentVariant?->product?->name ?? 'An item';

                try {
                    $this->reservations->reserve(
                        $requisition->tenant_id,
                        (int) $requisition->source_location_id,
                        (int) $item->product_variant_id,
                        $quantity,
                        $name,
                    );
                } catch (ValidationException) {
                    // The shared action speaks to shoppers ("remove it from your cart");
                    // a storekeeper approving a requisition needs different words.
                    throw ValidationException::withMessages([
                        'items' => sprintf(
                            '%s: only %s available at the source store, but %s was requested.',
                            $name,
                            Quantity::format($this->availableAt($requisition, $item)),
                            Quantity::format($quantity),
                        ),
                    ]);
                }
            }
        });
    }

    /**
     * Give the held stock back. Must run *before* the transfer is posted on fulfilment:
     * the requisition's own reservation would otherwise make its own stock look
     * unavailable to `assertEnoughStock()`.
     */
    public function release(StockRequisition $requisition): void
    {
        DB::transaction(function () use ($requisition): void {
            $requisition->loadMissing('items.unit');

            foreach ($requisition->items as $item) {
                $quantity = $this->baseQuantity($item);

                if ($quantity <= 0) {
                    continue;
                }

                $this->reservations->release(
                    $requisition->tenant_id,
                    (int) $requisition->source_location_id,
                    (int) $item->product_variant_id,
                    $quantity,
                );
            }
        });
    }

    private function availableAt(StockRequisition $requisition, StockRequisitionItem $item): float
    {
        $level = InventoryStockLevel::query()
            ->where('tenant_id', $requisition->tenant_id)
            ->where('inventory_location_id', $requisition->source_location_id)
            ->where('product_variant_id', $item->product_variant_id)
            ->first();

        return max(0, (float) ($level?->quantity_available ?? 0));
    }

    /**
     * Reservations are held in the item's base unit, matching how fulfilment posts.
     */
    private function baseQuantity(StockRequisitionItem $item): float
    {
        $quantity = (float) $item->requested_quantity;

        return $item->unit && $item->unit->isConvertible()
            ? $this->converter->toBase($quantity, $item->unit)
            : Quantity::round($quantity);
    }
}
