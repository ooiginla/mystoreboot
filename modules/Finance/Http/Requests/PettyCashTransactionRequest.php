<?php

declare(strict_types=1);

namespace Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PettyCashTransactionRequest extends FormRequest
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
            'finance_expense_category_id' => ['nullable', 'integer', Rule::exists('finance_expense_categories', 'id')->where('tenant_id', $tenantId)],
            'transaction_date' => ['required', 'date'],
            'transaction_type' => ['required', Rule::in(['top_up', 'expense', 'return_to_bank'])],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'payee_name' => ['nullable', 'string', 'max:180'],
            'reference_number' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
