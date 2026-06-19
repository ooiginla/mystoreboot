<?php

declare(strict_types=1);

namespace Modules\HrPayroll\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PayrollRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'payroll_month' => ['required', 'date_format:Y-m'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
