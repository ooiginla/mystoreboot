<?php

declare(strict_types=1);

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Sales\Enums\DiscountType;

final class SalesCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'discount_value' => is_string($this->input('discount_value')) ? str_replace(',', '', $this->input('discount_value')) : $this->input('discount_value'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        $tenantId = $this->string('tenant_id')->toString();

        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'code' => ['required', 'string', 'max:80', Rule::unique('sales_coupons', 'code')->where('tenant_id', $tenantId)],
            'discount_type' => ['required', Rule::in(DiscountType::values())],
            'discount_value' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['boolean'],
        ];
    }
}
