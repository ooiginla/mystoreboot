<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CustomerGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->string('tenant_id')->toString();
        $groupId = $this->route('group')?->id;

        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'customer_price_list_id' => ['nullable', 'integer', Rule::exists('customer_price_lists', 'id')->where('tenant_id', $tenantId)],
            'name' => ['required', 'string', 'max:140', Rule::unique('customer_groups', 'name')->where('tenant_id', $tenantId)->ignore($groupId)],
            'code' => ['nullable', 'string', 'max:80', Rule::unique('customer_groups', 'code')->where('tenant_id', $tenantId)->ignore($groupId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'credit_enabled' => ['boolean'],
            'credit_limit' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'credit_terms_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'credit_enabled' => $this->boolean('credit_enabled'),
            'credit_limit' => is_string($this->input('credit_limit')) ? str_replace(',', '', $this->input('credit_limit')) : $this->input('credit_limit'),
        ]);
    }
}
