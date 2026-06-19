<?php

declare(strict_types=1);

namespace Modules\Procurement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class VendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'lead_time_days' => $this->filled('lead_time_days') ? $this->integer('lead_time_days') : 0,
        ]);
    }

    public function rules(): array
    {
        $tenantId = $this->string('tenant_id')->toString();
        $vendorId = $this->route('vendor')?->id;

        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'name' => ['required', 'string', 'max:180'],
            'code' => ['nullable', 'string', 'max:60', Rule::unique('vendors', 'code')->where('tenant_id', $tenantId)->ignore($vendorId)],
            'contact_name' => ['nullable', 'string', 'max:140'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:1000'],
            'tax_number' => ['nullable', 'string', 'max:80'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'bank_accounts' => ['nullable', 'array', 'max:10'],
            'bank_accounts.*.bank_name' => ['nullable', 'string', 'max:140'],
            'bank_accounts.*.account_name' => ['nullable', 'string', 'max:160'],
            'bank_accounts.*.account_number' => ['nullable', 'string', 'max:80'],
            'bank_accounts.*.currency_code' => ['nullable', 'string', 'size:3'],
            'bank_accounts.*.is_primary' => ['nullable', 'boolean'],
        ];
    }
}
