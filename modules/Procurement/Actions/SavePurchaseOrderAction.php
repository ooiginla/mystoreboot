<?php

declare(strict_types=1);

namespace Modules\Procurement\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Models\ProductVariant;
use Modules\Inventory\Models\UnitOfMeasure;
use Modules\Inventory\Support\Quantity;
use Modules\Inventory\Support\ReorderLevels;
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
                ->filter(fn (array $item): bool => (float) ($item['quantity_ordered'] ?? 0) > 0)
                ->values()
                ->map(fn (array $item, int $index): array => $this->prepareItem($data['tenant_id'], $item, $index));
            $subtotalMinor = (int) $items->sum('line_total_minor');
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
                $purchaseOrder->items()->create([
                    'tenant_id' => $data['tenant_id'],
                    'product_variant_id' => $item['product_variant_id'],
                    'inventory_location_id' => $item['inventory_location_id'],
                    'quantity_ordered' => $item['quantity_ordered'],
                    'entered_quantity' => $item['entered_quantity'],
                    'entered_unit_id' => $item['entered_unit_id'],
                    'entered_unit_code' => $item['entered_unit_code'],
                    'unit_cost_minor' => $item['unit_cost_minor'],
                    'line_total_minor' => $item['line_total_minor'],
                    'vendor_sku' => $item['vendor_sku'] ?? null,
                ]);
            }

            return $purchaseOrder->refresh()->load(['vendor', 'items.variant.product', 'items.location']);
        });
    }

    /**
     * Convert the entered measurement to the base quantity used by inventory while
     * retaining the supplier-facing quantity and exact total paid for the PO line.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function prepareItem(string $tenantId, array $item, int $index): array
    {
        $variant = ProductVariant::query()
            ->with(['product.unitCategory.units', 'baseUnit'])
            ->where('tenant_id', $tenantId)
            ->findOrFail((int) $item['product_variant_id']);
        $enteredQuantity = (float) $item['quantity_ordered'];
        $enteredUnit = null;
        $factor = 1.0;

        if (! empty($item['unit_id'])) {
            $enteredUnit = UnitOfMeasure::query()
                ->where('tenant_id', $tenantId)
                ->find((int) $item['unit_id']);
            $allowedUnitIds = collect(ReorderLevels::unitsOf($variant))->pluck('id')->filter()->map(fn ($id): int => (int) $id);

            if (! $enteredUnit || ! $enteredUnit->isConvertible() || ! $allowedUnitIds->contains($enteredUnit->id)) {
                throw ValidationException::withMessages([
                    "items.{$index}.unit_id" => 'Choose a measurement unit configured for this item.',
                ]);
            }

            $factor = (float) $enteredUnit->to_base_factor;
        }

        $baseQuantity = Quantity::round($enteredQuantity * $factor);
        $lineTotalMinor = $this->moneyToMinor($item['line_total'] ?? 0);

        return [
            ...$item,
            'quantity_ordered' => $baseQuantity,
            'entered_quantity' => $enteredQuantity,
            'entered_unit_id' => $enteredUnit?->id,
            'entered_unit_code' => $enteredUnit?->code ?? $variant->baseUnit?->code ?? 'pc',
            'unit_cost_minor' => (int) round($lineTotalMinor / $baseQuantity),
            'line_total_minor' => $lineTotalMinor,
        ];
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
