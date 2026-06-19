<?php

declare(strict_types=1);

namespace Modules\HrPayroll\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'monthly_salary' => is_string($this->input('monthly_salary')) ? str_replace(',', '', $this->input('monthly_salary')) : $this->input('monthly_salary'),
        ]);
    }

    public function rules(): array
    {
        $tenantId = $this->string('tenant_id')->toString();
        $staffId = $this->route('staff')?->id;

        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'staff_number' => ['nullable', 'string', 'max:80', Rule::unique('hr_staff', 'staff_number')->where('tenant_id', $tenantId)->ignore($staffId)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:60'],
            'job_title' => ['nullable', 'string', 'max:140'],
            'hire_date' => ['nullable', 'date'],
            'monthly_salary' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'status' => ['required', Rule::in(['active', 'inactive', 'terminated'])],
            'address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
