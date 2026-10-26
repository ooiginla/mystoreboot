<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CustomerSpecificPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'unit_price' => is_string($this->input('unit_price')) ? str_replace(',', '', $this->input('unit_price')) : $this->input('unit_price'),
            'is_active' => $this->boolean('is_active'),
            'allow_additional_discounts' => $this->boolean('allow_additional_discounts'),
        ]);
    }

    public function rules(): array
    {
        $tenantId = $this->string('tenant_id')->toString();
        $priceId = $this->route('price')?->id;

        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'product_variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')->where('tenant_id', $tenantId)],
            'minimum_quantity' => [
                'required',
                'numeric',
                'gt:0',
                'max:999999999',
                Rule::unique('customer_specific_prices', 'minimum_quantity')
                    ->where('customer_id', $this->integer('customer_id'))
                    ->where('product_variant_id', $this->integer('product_variant_id'))
                    ->ignore($priceId),
            ],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['boolean'],
            'allow_additional_discounts' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
