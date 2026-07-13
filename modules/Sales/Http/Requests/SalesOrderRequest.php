<?php

declare(strict_types=1);

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SalesOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_credit_sale' => $this->boolean('is_credit_sale'),
            'shipping' => $this->cleanMoney($this->input('shipping')),
            'admin_discount_value' => $this->cleanMoney($this->input('admin_discount_value')),
            'amount_paid' => $this->cleanMoney($this->input('amount_paid')),
            'items' => collect((array) $this->input('items', []))->map(function (array $item): array {
                $item['unit_price'] = $this->cleanMoney($item['unit_price'] ?? null);

                return $item;
            })->all(),
        ]);
    }

    public function rules(): array
    {
        $tenantId = $this->string('tenant_id')->toString();

        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'source' => ['nullable', Rule::in(['in_store', 'retail_pos', 'offline', 'online'])],
            'sales_till_session_id' => ['required', 'integer', Rule::exists('sales_till_sessions', 'id')->where('tenant_id', $tenantId)->where('status', 'open')],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'inventory_location_id' => ['required', 'integer', Rule::exists('inventory_locations', 'id')->where('tenant_id', $tenantId)],
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'order_date' => ['required', 'date'],
            'is_credit_sale' => ['boolean'],
            'payment_method' => ['nullable', 'string', 'max:80'],
            'business_payment_account_id' => ['nullable', 'integer', Rule::exists('business_payment_accounts', 'id')->where('tenant_id', $tenantId)->where('status', 'active')],
            'amount_paid' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'coupon_code' => ['nullable', 'string', 'max:80'],
            'admin_discount_type' => ['nullable', Rule::in(['amount', 'percentage'])],
            'admin_discount_value' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'shipping' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'delivery_method' => ['nullable', 'string', 'max:120'],
            'delivery_status' => ['required', Rule::in(['pending', 'processing', 'out_for_delivery', 'delivered', 'failed', 'returned'])],
            'delivery_address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')->where('tenant_id', $tenantId)],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999999999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ];
    }

    private function cleanMoney(mixed $value): mixed
    {
        return is_string($value) ? str_replace(',', '', $value) : $value;
    }
}
