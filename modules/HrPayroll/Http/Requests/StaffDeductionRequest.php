<?php

declare(strict_types=1);

namespace Modules\HrPayroll\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StaffDeductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => is_string($this->input('amount')) ? str_replace(',', '', $this->input('amount')) : $this->input('amount'),
        ]);
    }

    public function rules(): array
    {
        $tenantId = $this->string('tenant_id')->toString();

        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'hr_staff_id' => ['required', 'integer', Rule::exists('hr_staff', 'id')->where('tenant_id', $tenantId)],
            'deduction_type' => ['required', Rule::in(['fine', 'salary_advance', 'other'])],
            'deduction_month' => ['required', 'date_format:Y-m'],
            'deduction_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
