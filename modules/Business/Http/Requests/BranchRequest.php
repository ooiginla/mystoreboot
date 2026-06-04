<?php

declare(strict_types=1);

namespace Modules\Business\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper((string) $this->input('code')),
            'currency_code' => $this->filled('currency_code') ? strtoupper((string) $this->input('currency_code')) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'name' => ['required', 'string', 'max:140'],
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('branches', 'code')->where('tenant_id', $this->string('tenant_id')->toString()),
            ],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email:rfc', 'max:160'],
            'address' => ['nullable', 'string', 'max:1000'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'default_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_primary' => ['nullable', 'boolean'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
