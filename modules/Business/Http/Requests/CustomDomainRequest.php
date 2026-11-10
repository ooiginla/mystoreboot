<?php

declare(strict_types=1);

namespace Modules\Business\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Tenancy\Rules\ValidCustomDomain;
use Modules\Tenancy\Support\TenantDomainService;

final class CustomDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'domain' => app(TenantDomainService::class)->normalize((string) $this->input('domain')),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'domain' => [
                'required',
                'string',
                'max:255',
                new ValidCustomDomain,
                Rule::unique('tenant_domains', 'domain'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'domain.unique' => 'This domain is already connected to another Storeboot business.',
        ];
    }
}
