<?php

declare(strict_types=1);

namespace Modules\Procurement\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Actions\PostInventoryMovementAction;
use Modules\Inventory\Enums\InventoryMovementType;
use Modules\Inventory\Enums\StockCondition;
use Modules\Procurement\Enums\PurchaseOrderStatus;
use Modules\Procurement\Models\GoodsReceipt;
use Modules\Procurement\Models\PurchaseOrder;

final class ReceivePurchaseOrderAction
{
    public function __construct(private readonly PostInventoryMovementAction $postInventoryMovement) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(PurchaseOrder $purchaseOrder, array $data): GoodsReceipt
    {
        return DB::transaction(function () use ($purchaseOrder, $data): GoodsReceipt {
            $validItems = collect((array) $data['items'])
                ->map(function (array $item) use ($purchaseOrder): array {
                    $quantity = (int) ($item['quantity_received'] ?? 0);
                    $poItem = $purchaseOrder->items()->whereKey($item['purchase_order_item_id'])->firstOrFail();

                    if ($quantity > $poItem->quantity_pending) {
                        throw ValidationException::withMessages([
                            'items' => 'Received quantity cannot be more than the pending quantity.',
                        ]);
                    }

                    return [$item, $poItem, $quantity];
                })
                ->filter(fn (array $row): bool => $row[2] > 0)
                ->values();

            if ($validItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Enter at least one quantity to receive.',
                ]);
            }

            $receipt = GoodsReceipt::query()->create([
                'tenant_id' => $purchaseOrder->tenant_id,
                'purchase_order_id' => $purchaseOrder->id,
                'receipt_number' => $data['receipt_number'] ?: $this->generateReceiptNumber($purchaseOrder),
                'received_at' => $data['received_at'],
                'delivery_status' => 'received',
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($validItems as [$item, $poItem, $quantity]) {
                $receipt->items()->create([
                    'tenant_id' => $purchaseOrder->tenant_id,
                    'purchase_order_item_id' => $poItem->id,
                    'quantity_received' => $quantity,
                    'batch_number' => $item['batch_number'] ?? null,
                    'expiry_date' => $item['expiry_date'] ?? null,
                ]);

                $poItem->increment('quantity_received', $quantity);

                $this->postInventoryMovement->execute([
                    'tenant_id' => $purchaseOrder->tenant_id,
                    'inventory_location_id' => $poItem->inventory_location_id,
                    'product_variant_id' => $poItem->product_variant_id,
                    'movement_type' => InventoryMovementType::StockIn->value,
                    'stock_condition' => StockCondition::Sellable->value,
                    'quantity' => $quantity,
                    'unit_cost' => $poItem->unit_cost_minor / 100,
                    'batch_number' => $item['batch_number'] ?? null,
                    'expiry_date' => $item['expiry_date'] ?? null,
                    'reference_type' => 'purchase_order',
                    'reference_number' => $purchaseOrder->po_number,
                    'notes' => 'Goods received from supplier.',
                    'occurred_at' => $data['received_at'],
                ]);
            }

            $purchaseOrder->refresh()->load('items');
            $allReceived = $purchaseOrder->items->every(fn ($item): bool => $item->quantity_received >= $item->quantity_ordered);
            $anyReceived = $purchaseOrder->items->contains(fn ($item): bool => $item->quantity_received > 0);

            $purchaseOrder->update([
                'status' => $allReceived
                    ? PurchaseOrderStatus::Received->value
                    : ($anyReceived ? PurchaseOrderStatus::PartiallyReceived->value : $purchaseOrder->status->value),
            ]);

            return $receipt->refresh()->load(['items.purchaseOrderItem.variant.product']);
        });
    }

    private function generateReceiptNumber(PurchaseOrder $purchaseOrder): string
    {
        return 'GRN-'.now()->format('Ymd').'-'.$purchaseOrder->id.'-'.($purchaseOrder->receipts()->count() + 1);
    }
}
