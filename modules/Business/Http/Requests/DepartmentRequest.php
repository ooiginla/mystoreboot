<?php

declare(strict_types=1);

namespace Modules\Business\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper((string) $this->input('code')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('tenant_id', $this->string('tenant_id')->toString()),
            ],
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('departments', 'code')->where('tenant_id', $this->string('tenant_id')->toString()),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
