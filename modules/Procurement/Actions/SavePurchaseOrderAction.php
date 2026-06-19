<?php

declare(strict_types=1);

namespace Modules\Procurement\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Procurement\Enums\PaymentStatus;
use Modules\Procurement\Enums\PurchaseOrderStatus;
use Modules\Procurement\Models\PurchaseOrder;

final class SavePurchaseOrderAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?PurchaseOrder $purchaseOrder = null): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $purchaseOrder): PurchaseOrder {
            $items = collect((array) $data['items'])
                ->filter(fn (array $item): bool => (int) ($item['quantity_ordered'] ?? 0) > 0)
                ->values();
            $subtotalMinor = $items->sum(fn (array $item): int => (int) $item['quantity_ordered'] * $this->moneyToMinor($item['unit_cost'] ?? 0));
            $taxMinor = $this->moneyToMinor($data['tax'] ?? 0);
            $shippingMinor = $this->moneyToMinor($data['shipping'] ?? 0);

            $values = [
                'tenant_id' => $data['tenant_id'],
                'vendor_id' => $data['vendor_id'],
                'po_number' => $data['po_number'] ?: ($purchaseOrder?->po_number ?? $this->generatePoNumber($data['tenant_id'])),
                'order_date' => $data['order_date'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'subtotal_minor' => $subtotalMinor,
                'tax_minor' => $taxMinor,
                'shipping_minor' => $shippingMinor,
                'total_minor' => $subtotalMinor + $taxMinor + $shippingMinor,
                'notes' => $data['notes'] ?? null,
            ];

            if ($purchaseOrder) {
                $purchaseOrder->update($values);
                $purchaseOrder->items()->delete();
            } else {
                $purchaseOrder = PurchaseOrder::query()->create($values + [
                    'status' => PurchaseOrderStatus::PendingApproval->value,
                    'payment_status' => PaymentStatus::Unpaid->value,
                ]);
            }

            foreach ($items as $item) {
                $unitCostMinor = $this->moneyToMinor($item['unit_cost'] ?? 0);

                $purchaseOrder->items()->create([
                    'tenant_id' => $data['tenant_id'],
                    'product_variant_id' => $item['product_variant_id'],
                    'inventory_location_id' => $item['inventory_location_id'],
                    'quantity_ordered' => $item['quantity_ordered'],
                    'unit_cost_minor' => $unitCostMinor,
                    'line_total_minor' => (int) $item['quantity_ordered'] * $unitCostMinor,
                    'vendor_sku' => $item['vendor_sku'] ?? null,
                ]);
            }

            return $purchaseOrder->refresh()->load(['vendor', 'items.variant.product', 'items.location']);
        });
    }

    private function generatePoNumber(string $tenantId): string
    {
        return 'PO-'.now()->format('Ymd').'-'.str_pad((string) (PurchaseOrder::query()->where('tenant_id', $tenantId)->count() + 1), 4, '0', STR_PAD_LEFT);
    }

    private function moneyToMinor(mixed $value): int
    {
        return (int) round(((float) (is_string($value) ? str_replace(',', '', $value) : ($value ?: 0))) * 100);
    }
}
