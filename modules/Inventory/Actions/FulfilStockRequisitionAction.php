<?php

declare(strict_types=1);

namespace Modules\Inventory\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Enums\InventoryMovementType;
use Modules\Inventory\Enums\RequisitionStatus;
use Modules\Inventory\Models\StockRequisition;
use Modules\Inventory\Support\Quantity;
use Modules\Inventory\Support\UnitConverter;

/**
 * Fulfils an approved requisition by transferring each line's fulfilled quantity from
 * the source store to the destination store (existing atomic transfer, correct
 * inter-location valuation). The controller sets each line's fulfilled_quantity
 * (defaulting to the requested amount, allowing shortfalls) before calling this.
 */
final class FulfilStockRequisitionAction
{
    public function __construct(
        private readonly PostInventoryMovementAction $postInventoryMovement,
        private readonly UnitConverter $converter,
        private readonly ReserveRequisitionStockAction $reservation,
    ) {}

    public function execute(StockRequisition $requisition): StockRequisition
    {
        if ($requisition->status !== RequisitionStatus::Approved) {
            throw ValidationException::withMessages([
                'status' => 'Only an approved requisition can be fulfilled.',
            ]);
        }

        return DB::transaction(function () use ($requisition): StockRequisition {
            $requisition->loadMissing('items.unit');

            // Hand the held stock back first: the requisition's own reservation would
            // otherwise make its own stock look unavailable to assertEnoughStock().
            $this->reservation->release($requisition);

            foreach ($requisition->items as $item) {
                // decimal casts return strings ("0.0000" is truthy), so compare numerically.
                $fulfilled = (float) $item->fulfilled_quantity;
                $quantity = $fulfilled > 0 ? $fulfilled : (float) $item->requested_quantity;

                if ($quantity <= 0) {
                    continue;
                }

                $baseQuantity = $item->unit && $item->unit->isConvertible()
                    ? $this->converter->toBase($quantity, $item->unit)
                    : Quantity::round($quantity);

                $this->postInventoryMovement->execute([
                    'tenant_id' => $requisition->tenant_id,
                    'inventory_location_id' => $requisition->source_location_id,
                    'destination_inventory_location_id' => $requisition->destination_location_id,
                    'product_variant_id' => $item->product_variant_id,
                    'movement_type' => InventoryMovementType::TransferOut->value,
                    'quantity' => $baseQuantity,
                    'reference_number' => $requisition->requisition_number,
                    'notes' => 'Requisition fulfilment.',
                    'occurred_at' => now(),
                ]);

                if ((float) $item->fulfilled_quantity <= 0) {
                    $item->update(['fulfilled_quantity' => Quantity::round($quantity)]);
                }
            }

            $requisition->update([
                'status' => RequisitionStatus::Fulfilled->value,
                'fulfilled_at' => now(),
            ]);

            return $requisition->refresh()->load('items');
        });
    }
}
