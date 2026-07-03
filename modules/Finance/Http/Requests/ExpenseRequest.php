<?php

declare(strict_types=1);

namespace Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Finance\Models\FinanceAccount;

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
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'expense_category' => ['required', 'string', 'max:140'],
            'expense_account_code' => ['required', 'string', Rule::exists('finance_accounts', 'code')->where('tenant_id', $tenantId)],
            'payment_account_code' => ['nullable', 'string', Rule::exists('finance_accounts', 'code')->where('tenant_id', $tenantId)],
            'expense_date' => ['required', 'date'],
            'payee_name' => ['nullable', 'string', 'max:180'],
            'payment_status' => ['required', Rule::in(['paid', 'partially_paid', 'unpaid'])],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'paid_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'reference_number' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $tenantId = $this->string('tenant_id')->toString();
            $expenseAccount = FinanceAccount::query()
                ->where('tenant_id', $tenantId)
                ->where('code', $this->string('expense_account_code')->toString())
                ->first();

            if (! $expenseAccount || ! $expenseAccount->is_active || $expenseAccount->type !== 'expense') {
                $validator->errors()->add('expense_account_code', 'Select an active expense line.');
            } elseif ($expenseAccount->category !== $this->string('expense_category')->toString()) {
                $validator->errors()->add('expense_account_code', 'Select an expense line within the selected category.');
            }

            $amount = (float) $this->input('amount', 0);
            $paidAmount = match ($this->string('payment_status')->toString()) {
                'paid' => $amount,
                'unpaid' => 0.0,
                default => min($amount, (float) $this->input('paid_amount', 0)),
            };

            if ($paidAmount <= 0) {
                return;
            }

            $paymentAccount = FinanceAccount::query()
                ->where('tenant_id', $tenantId)
                ->where('code', $this->string('payment_account_code')->toString())
                ->first();

            if (! $paymentAccount || ! $paymentAccount->is_active || $paymentAccount->type !== 'asset') {
                $validator->errors()->add('payment_account_code', 'Select an active asset account to pay from.');
            }
        });
    }

    private function cleanMoney(mixed $value): mixed
    {
        return is_string($value) ? str_replace(',', '', $value) : $value;
    }
}
