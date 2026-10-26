<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CustomerPriceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim($this->string('code')->toString())),
            'is_active' => $this->boolean('is_active'),
            'allow_additional_discounts' => $this->boolean('allow_additional_discounts'),
        ]);
    }

    public function rules(): array
    {
        $tenantId = $this->string('tenant_id')->toString();
        $priceListId = $this->route('priceList')?->id;

        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'name' => ['required', 'string', 'max:140', Rule::unique('customer_price_lists', 'name')->where('tenant_id', $tenantId)->ignore($priceListId)],
            'code' => ['required', 'string', 'max:80', Rule::unique('customer_price_lists', 'code')->where('tenant_id', $tenantId)->ignore($priceListId)],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'allow_additional_discounts' => ['boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
