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

        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'name' => ['required', 'string', 'max:140', Rule::unique('customer_groups', 'name')->where('tenant_id', $tenantId)],
            'code' => ['nullable', 'string', 'max:80', Rule::unique('customer_groups', 'code')->where('tenant_id', $tenantId)],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
