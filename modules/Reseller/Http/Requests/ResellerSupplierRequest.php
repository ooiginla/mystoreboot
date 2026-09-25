<?php

declare(strict_types=1);

namespace Modules\Reseller\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Reseller\Models\ResellerSupplier;

final class ResellerSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $url = rtrim(trim((string) $this->input('website_url')), '/');

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'website_url' => $url,
            'is_active' => $this->boolean('is_active', true),
            'auto_publish_products' => $this->has('auto_publish_products')
                ? $this->boolean('auto_publish_products')
                : null,
        ]);
    }

    public function rules(): array
    {
        $supplier = $this->route('supplier');
        $supplierId = $supplier instanceof ResellerSupplier ? $supplier->id : null;
        $tenantId = $this->string('tenant_id')->toString();

        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'name' => ['required', 'string', 'max:160'],
            'website_url' => [
                'required',
                'url:http,https',
                'max:2048',
                Rule::unique('reseller_suppliers', 'website_url')->where('tenant_id', $tenantId)->ignore($supplierId),
            ],
            'logo_url' => ['nullable', 'url:http,https', 'max:2048'],
            'contact_email' => ['nullable', 'email:rfc', 'max:160'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'whatsapp' => ['nullable', 'string', 'max:40'],
            'scan_frequency' => ['nullable', Rule::in(['daily', 'twice_daily'])],
            'auto_publish_products' => ['nullable', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
