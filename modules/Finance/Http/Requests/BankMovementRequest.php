<?php

declare(strict_types=1);

namespace Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Business\Models\BusinessPaymentAccount;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceJournalLine;

final class BankMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'gross_amount' => $this->cleanMoney($this->input('gross_amount')),
            'fee_amount' => $this->cleanMoney($this->input('fee_amount')),
        ]);
    }

    public function rules(): array
    {
        $tenantId = $this->string('tenant_id')->toString();

        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'movement_type' => ['required', Rule::in(['bank_cash', 'reconcile_transfer', 'settle_pos', 'settle_online'])],
            'source_account_code' => ['required', 'string', Rule::in(['1030', '1040', '1050', '1060'])],
            'destination_account_code' => [
                'required',
                'string',
                Rule::exists('finance_accounts', 'code')->where('tenant_id', $tenantId)->where('type', 'asset')->where('is_active', true),
            ],
            'movement_date' => ['required', 'date'],
            'gross_amount' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'fee_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'reference_number' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $sourceByType = [
                'bank_cash' => '1030',
                'reconcile_transfer' => '1040',
                'settle_pos' => '1050',
                'settle_online' => '1060',
            ];
            $movementType = (string) $this->input('movement_type');
            $sourceCode = (string) $this->input('source_account_code');

            if (isset($sourceByType[$movementType]) && $sourceByType[$movementType] !== $sourceCode) {
                $validator->errors()->add('source_account_code', 'The selected source account does not match the banking action.');
            }

            $destinationCode = (string) $this->input('destination_account_code');
            $isConfiguredPaymentAccount = BusinessPaymentAccount::query()
                ->where('tenant_id', $this->string('tenant_id')->toString())
                ->where('status', 'active')
                ->whereHas('financeAccount', fn ($query) => $query->where('code', $destinationCode))
                ->exists();

            if (! str_starts_with($destinationCode, 'BANK-') && ! $isConfiguredPaymentAccount) {
                $validator->errors()->add('destination_account_code', 'Select an active business payment or bank account.');
            }

            $grossMinor = (int) round(((float) $this->input('gross_amount', 0)) * 100);
            $feeMinor = (int) round(((float) $this->input('fee_amount', 0)) * 100);

            if ($feeMinor > $grossMinor) {
                $validator->errors()->add('fee_amount', 'Settlement charges cannot be more than the gross amount.');
            }

            if ($movementType === 'bank_cash' && $feeMinor > 0) {
                $validator->errors()->add('fee_amount', 'Cash banking from vault should not include POS or gateway charges.');
            }

            $tenantId = $this->string('tenant_id')->toString();
            $sourceAccount = FinanceAccount::query()
                ->where('tenant_id', $tenantId)
                ->where('code', $sourceCode)
                ->first();

            if ($sourceAccount && $grossMinor > $this->accountBalance($tenantId, $sourceAccount)) {
                $validator->errors()->add('gross_amount', 'The amount is more than the available balance in the selected source account.');
            }
        });
    }

    private function accountBalance(string $tenantId, FinanceAccount $account): int
    {
        $debitMinor = (int) FinanceJournalLine::query()
            ->where('tenant_id', $tenantId)
            ->where('finance_account_id', $account->id)
            ->sum('debit_minor');
        $creditMinor = (int) FinanceJournalLine::query()
            ->where('tenant_id', $tenantId)
            ->where('finance_account_id', $account->id)
            ->sum('credit_minor');

        return $account->normal_balance === 'credit' ? $creditMinor - $debitMinor : $debitMinor - $creditMinor;
    }

    private function cleanMoney(mixed $value): mixed
    {
        return is_string($value) ? str_replace(',', '', $value) : $value;
    }
}
