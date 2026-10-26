<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CustomerBalanceAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['amount' => is_string($this->input('amount')) ? str_replace(',', '', $this->input('amount')) : $this->input('amount')]);
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'adjustment_date' => ['required', 'date'],
            'direction' => ['required', Rule::in(['increase', 'decrease'])],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'reason' => ['required', 'string', 'max:240'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
