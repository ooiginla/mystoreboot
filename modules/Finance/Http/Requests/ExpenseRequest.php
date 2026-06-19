<?php

declare(strict_types=1);

namespace Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => $this->cleanMoney($this->input('amount')),
            'paid_amount' => $this->cleanMoney($this->input('paid_amount')),
        ]);
    }

    public function rules(): array
    {
        $tenantId = $this->string('tenant_id')->toString();

        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'finance_expense_category_id' => ['required', 'integer', Rule::exists('finance_expense_categories', 'id')->where('tenant_id', $tenantId)],
            'expense_date' => ['required', 'date'],
            'payee_name' => ['nullable', 'string', 'max:180'],
            'payment_method' => ['required', 'string', 'max:80'],
            'payment_status' => ['required', Rule::in(['paid', 'partially_paid', 'unpaid'])],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'paid_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'reference_number' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function cleanMoney(mixed $value): mixed
    {
        return is_string($value) ? str_replace(',', '', $value) : $value;
    }
}
