<?php

declare(strict_types=1);

namespace Modules\Access\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TenantUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'role_id' => [
                'nullable',
                'integer',
                Rule::exists('roles', 'id')->where('tenant_id', $this->string('tenant_id')->toString()),
            ],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('tenant_id', $this->string('tenant_id')->toString()),
            ],
        ];
    }
}
