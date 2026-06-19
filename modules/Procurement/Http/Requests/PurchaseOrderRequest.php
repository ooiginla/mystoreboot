<?php

declare(strict_types=1);

namespace Modules\Procurement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'tax' => $this->cleanMoney($this->input('tax')),
            'shipping' => $this->cleanMoney($this->input('shipping')),
            'items' => collect((array) $this->input('items', []))->map(function (array $item): array {
                $item['unit_cost'] = $this->cleanMoney($item['unit_cost'] ?? null);

                return $item;
            })->all(),
        ]);
    }

    public function rules(): array
    {
        $tenantId = $this->string('tenant_id')->toString();
        $purchaseOrderId = $this->route('purchaseOrder')?->id;

        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'vendor_id' => ['required', 'integer', Rule::exists('vendors', 'id')->where('tenant_id', $tenantId)],
            'po_number' => ['nullable', 'string', 'max:80', Rule::unique('purchase_orders', 'po_number')->where('tenant_id', $tenantId)->ignore($purchaseOrderId)],
            'order_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'shipping' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')->where('tenant_id', $tenantId)],
            'items.*.inventory_location_id' => ['required', 'integer', Rule::exists('inventory_locations', 'id')->where('tenant_id', $tenantId)],
            'items.*.quantity_ordered' => ['required', 'integer', 'min:1', 'max:999999999'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.vendor_sku' => ['nullable', 'string', 'max:120'],
        ];
    }

    private function cleanMoney(mixed $value): mixed
    {
        return is_string($value) ? str_replace(',', '', $value) : $value;
    }
}
