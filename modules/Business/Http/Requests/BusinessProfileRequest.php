<?php

declare(strict_types=1);

namespace Modules\Business\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Business\Enums\BusinessType;

final class BusinessProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'country_code' => strtoupper((string) $this->input('country_code', 'NG')),
            'currency_code' => strtoupper((string) $this->input('currency_code', 'NGN')),
            'maintenance_mode' => $this->boolean('maintenance_mode'),
        ]);

        if ($this->has('use_estimated_cost_for_cogs')) {
            $this->merge([
                'use_estimated_cost_for_cogs' => $this->boolean('use_estimated_cost_for_cogs'),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['nullable', 'uuid', 'exists:tenants,id'],
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:120', 'alpha_dash:ascii'],
            'business_type' => ['required', Rule::in(array_keys(BusinessType::options()))],
            'registration_number' => ['nullable', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email:rfc', 'max:160'],
            'website' => ['nullable', 'url', 'max:180'],
            'address' => ['nullable', 'string', 'max:1000'],
            'country_code' => ['required', 'string', 'size:2'],
            'timezone' => ['required', 'string', 'max:64'],
            'currency_code' => ['required', 'string', 'size:3'],
            'tax_identifier' => ['nullable', 'string', 'max:80'],
            'default_tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'plan_id' => ['nullable', 'integer', 'exists:plans,id'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'opening_hours' => ['array'],
            'opening_hours.*.is_open' => ['nullable', 'boolean'],
            'opening_hours.*.opens_at' => ['nullable', 'date_format:H:i'],
            'opening_hours.*.closes_at' => ['nullable', 'date_format:H:i'],
            'payment_methods' => ['nullable', 'string', 'max:1000'],
            'bank_details' => ['nullable', 'array', 'max:10'],
            'bank_details.*.bank_name' => ['nullable', 'string', 'max:140'],
            'bank_details.*.account_name' => ['nullable', 'string', 'max:160'],
            'bank_details.*.account_number' => ['nullable', 'string', 'max:80'],
            'bank_details.*.status' => ['nullable', Rule::in(['active', 'inactive'])],
            'bank_details.*.asset_account_code' => ['nullable', 'string', 'max:40'],
            'maintenance_mode' => ['boolean'],
            'use_estimated_cost_for_cogs' => ['sometimes', 'boolean'],
        ];
    }
}
