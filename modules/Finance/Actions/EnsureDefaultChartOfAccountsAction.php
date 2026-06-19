<?php

declare(strict_types=1);

namespace Modules\Finance\Actions;

use Illuminate\Support\Str;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceExpenseCategory;

final class EnsureDefaultChartOfAccountsAction
{
    /**
     * @return array<string, FinanceAccount>
     */
    public function execute(string $tenantId): array
    {
        $accounts = [];

        foreach ($this->defaults() as $code => $definition) {
            $accounts[$code] = FinanceAccount::query()->firstOrCreate([
                'tenant_id' => $tenantId,
                'code' => $code,
            ], $definition + [
                'is_system' => true,
                'is_active' => true,
            ]);
        }

        foreach ($this->defaultExpenseCategories() as $name => $accountCode) {
            FinanceExpenseCategory::query()->firstOrCreate([
                'tenant_id' => $tenantId,
                'code' => Str::slug($name),
            ], [
                'finance_account_id' => $accounts[$accountCode]->id,
                'name' => $name,
                'description' => 'Default operating expense category.',
                'is_active' => true,
            ]);
        }

        return $accounts;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function defaults(): array
    {
        return [
            '1000' => ['name' => 'Cash / Bank', 'type' => 'asset', 'normal_balance' => 'debit'],
            '1010' => ['name' => 'Petty Cash', 'type' => 'asset', 'normal_balance' => 'debit'],
            '1100' => ['name' => 'Accounts Receivable', 'type' => 'asset', 'normal_balance' => 'debit'],
            '1200' => ['name' => 'Inventory', 'type' => 'asset', 'normal_balance' => 'debit'],
            '1300' => ['name' => 'Staff Salary Advances', 'type' => 'asset', 'normal_balance' => 'debit'],
            '1310' => ['name' => 'Staff Deductions Receivable', 'type' => 'asset', 'normal_balance' => 'debit'],
            '2000' => ['name' => 'Accounts Payable', 'type' => 'liability', 'normal_balance' => 'credit'],
            '2100' => ['name' => 'Sales Tax Payable', 'type' => 'liability', 'normal_balance' => 'credit'],
            '2200' => ['name' => 'Payroll Payable', 'type' => 'liability', 'normal_balance' => 'credit'],
            '3000' => ['name' => 'Owner Equity', 'type' => 'equity', 'normal_balance' => 'credit'],
            '4000' => ['name' => 'Sales Revenue', 'type' => 'income', 'normal_balance' => 'credit'],
            '4010' => ['name' => 'Shipping Income', 'type' => 'income', 'normal_balance' => 'credit'],
            '4020' => ['name' => 'Sales Discounts', 'type' => 'income', 'normal_balance' => 'debit'],
            '4030' => ['name' => 'Sales Returns and Allowances', 'type' => 'income', 'normal_balance' => 'debit'],
            '4100' => ['name' => 'Staff Fine Income', 'type' => 'income', 'normal_balance' => 'credit'],
            '4110' => ['name' => 'Other Payroll Deduction Income', 'type' => 'income', 'normal_balance' => 'credit'],
            '5000' => ['name' => 'Cost of Goods Sold', 'type' => 'expense', 'normal_balance' => 'debit'],
            '5100' => ['name' => 'Freight and Delivery Expense', 'type' => 'expense', 'normal_balance' => 'debit'],
            '5200' => ['name' => 'Tax Expense', 'type' => 'expense', 'normal_balance' => 'debit'],
            '6000' => ['name' => 'General Operating Expense', 'type' => 'expense', 'normal_balance' => 'debit'],
            '6010' => ['name' => 'Rent Expense', 'type' => 'expense', 'normal_balance' => 'debit'],
            '6020' => ['name' => 'Utilities Expense', 'type' => 'expense', 'normal_balance' => 'debit'],
            '6030' => ['name' => 'Salaries and Wages Expense', 'type' => 'expense', 'normal_balance' => 'debit'],
            '6040' => ['name' => 'Marketing Expense', 'type' => 'expense', 'normal_balance' => 'debit'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function defaultExpenseCategories(): array
    {
        return [
            'General Operations' => '6000',
            'Rent' => '6010',
            'Utilities' => '6020',
            'Salaries and Wages' => '6030',
            'Marketing' => '6040',
            'Freight and Delivery' => '5100',
            'Taxes and Licenses' => '5200',
        ];
    }
}
