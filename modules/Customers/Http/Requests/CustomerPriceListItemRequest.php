<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CustomerPriceListItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'price_type' => $this->input('price_type', 'fixed'),
            'unit_price' => is_string($this->input('unit_price')) ? str_replace(',', '', $this->input('unit_price')) : $this->input('unit_price'),
        ]);
    }

    public function rules(): array
    {
        $tenantId = $this->string('tenant_id')->toString();
        $itemId = $this->route('item')?->id;

        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'customer_price_list_id' => ['required', 'integer', Rule::exists('customer_price_lists', 'id')->where('tenant_id', $tenantId)],
            'product_variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')->where('tenant_id', $tenantId)],
            'minimum_quantity' => [
                'required',
                'numeric',
                'gt:0',
                'max:999999999',
                Rule::unique('customer_price_list_items', 'minimum_quantity')
                    ->where('customer_price_list_id', $this->integer('customer_price_list_id'))
                    ->where('product_variant_id', $this->integer('product_variant_id'))
                    ->ignore($itemId),
            ],
            'price_type' => ['required', Rule::in(['fixed', 'percentage'])],
            'unit_price' => ['nullable', 'required_if:price_type,fixed', 'numeric', 'min:0', 'max:999999999'],
            'discount_percent' => ['nullable', 'required_if:price_type,percentage', 'numeric', 'gt:0', 'max:100'],
        ];
    }
}
