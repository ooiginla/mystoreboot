<?php

declare(strict_types=1);

namespace Modules\HrPayroll\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Finance\Actions\EnsureDefaultChartOfAccountsAction;
use Modules\Finance\Models\FinanceAccount;

final class PayrollRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $tenantId = $this->string('tenant_id')->toString();

        if ($tenantId !== '') {
            app(EnsureDefaultChartOfAccountsAction::class)->execute($tenantId);
        }
    }

    public function rules(): array
    {
        $tenantId = $this->string('tenant_id')->toString();

        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'payroll_month' => ['required', 'date_format:Y-m'],
            'funding_account_code' => ['required', 'string', Rule::exists('finance_accounts', 'code')->where('tenant_id', $tenantId)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $account = FinanceAccount::query()
                ->where('tenant_id', $this->string('tenant_id')->toString())
                ->where('code', $this->string('funding_account_code')->toString())
                ->first();

            if (! $account || ! $account->is_active || $account->type !== 'asset') {
                $validator->errors()->add('funding_account_code', 'Select an active asset account to pay wages from.');
            }
        });
    }
}
