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
            ], [
                'name' => $definition['name'],
                'type' => $definition['type'],
                'category' => $definition['category'],
                'description' => $definition['description'],
                'normal_balance' => $definition['normal_balance'],
                'is_system' => true,
                'is_active' => true,
            ]);

            $accounts[$code]->fill([
                'name' => $definition['name'],
                'type' => $definition['type'],
                'category' => $definition['category'],
                'description' => $definition['description'],
                'normal_balance' => $definition['normal_balance'],
                'is_system' => true,
                'is_active' => true,
            ])->save();
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
     * @return array<string, array{name: string, type: string, category: string, description: string, normal_balance: string}>
     */
    private function defaults(): array
    {
        return [
            '1000' => $this->account('Cash / Bank', 'asset', 'Current Assets', 'Cash and bank balances available for operations.', 'debit'),
            '1010' => $this->account('Petty Cash', 'asset', 'Current Assets', 'Cash kept on hand for small operating expenses.', 'debit'),
            '1100' => $this->account('Accounts Receivable', 'asset', 'Current Assets', 'Amounts owed by customers.', 'debit'),
            '1200' => $this->account('Inventory', 'asset', 'Current Assets', 'Inventory held for resale or production.', 'debit'),
            '1300' => $this->account('Staff Salary Advances', 'asset', 'Employee Receivables', 'Salary advances recoverable from staff.', 'debit'),
            '1310' => $this->account('Staff Deductions Receivable', 'asset', 'Employee Receivables', 'Other staff deductions recoverable from payroll.', 'debit'),
            '2000' => $this->account('Accounts Payable', 'liability', 'Current Liabilities', 'Amounts owed to vendors and suppliers.', 'credit'),
            '2100' => $this->account('Sales Tax Payable', 'liability', 'Current Liabilities', 'Sales tax collected and payable to government.', 'credit'),
            '2200' => $this->account('Payroll Payable', 'liability', 'Current Liabilities', 'Net payroll owed to employees.', 'credit'),
            '2300' => $this->account('Customer Credits', 'liability', 'Current Liabilities', 'Payments received from customers and held for future use or refund.', 'credit'),
            '3000' => $this->account('Owner Equity', 'equity', 'Equity', 'Owner capital and retained interest in the business.', 'credit'),
            '4000' => $this->account('Sales Revenue', 'income', 'Operating Income', 'Income from product and service sales.', 'credit'),
            '4010' => $this->account('Shipping Income', 'income', 'Operating Income', 'Delivery and shipping income charged to customers.', 'credit'),
            '4020' => $this->account('Sales Discounts', 'income', 'Contra Income', 'Discounts granted to customers.', 'debit'),
            '4030' => $this->account('Sales Returns and Allowances', 'income', 'Contra Income', 'Returns, refunds, and sales allowances.', 'debit'),
            '4100' => $this->account('Staff Fine Income', 'income', 'Other Income', 'Income recognized from staff fines.', 'credit'),
            '4110' => $this->account('Other Payroll Deduction Income', 'income', 'Other Income', 'Income recognized from other payroll deductions.', 'credit'),
            'EXP-5000' => $this->account('Cost of Goods Sold', 'expense', 'Direct Costs', 'Cost of inventory sold.', 'debit'),
            'EXP-5100' => $this->account('Freight and Delivery Expense', 'expense', 'Admin & Ops', 'Delivery, shipping, and freight expenses.', 'debit'),
            'EXP-5200' => $this->account('Tax Expense', 'expense', 'Non-Operating Expenses', 'Taxes expensed by the business.', 'debit'),
            'EXP-6000' => $this->account('General Operating Expense', 'expense', 'Admin & Ops', 'General uncategorized operating expenses.', 'debit'),
            'EXP-6010' => $this->account('Rent Expense', 'expense', 'Facilities & Utility', 'Rent paid for office, store, or operating space.', 'debit'),
            'EXP-6020' => $this->account('Utilities Expense', 'expense', 'Facilities & Utility', 'General utilities used in business operations.', 'debit'),
            'EXP-6030' => $this->account('Salaries and Wages Expense', 'expense', 'Compensation & Labour', 'Payroll expense for employees.', 'debit'),
            'EXP-6040' => $this->account('Marketing Expense', 'expense', 'Sales & Marketing', 'Marketing and promotional expenses.', 'debit'),
            'EXP-6050' => $this->account('Inventory Adjustments and Write-Offs', 'expense', 'Direct Costs', 'Inventory gains, losses, damage, shrinkage, and manual return adjustments.', 'debit'),
            'EXP-6100' => $this->account('Rent & Lease', 'expense', 'Facilities & Utility', 'Monthly payments for office space.', 'debit'),
            'EXP-6110' => $this->account('Property Taxes', 'expense', 'Facilities & Utility', 'Annual taxes paid on owned buildings.', 'debit'),
            'EXP-6120' => $this->account('Internet & Phone', 'expense', 'Facilities & Utility', 'Connectivity costs for business operations.', 'debit'),
            'EXP-6130' => $this->account('Utilities: Electricity', 'expense', 'Facilities & Utility', 'Electricity.', 'debit'),
            'EXP-6140' => $this->account('Utilities: Water', 'expense', 'Facilities & Utility', 'Water.', 'debit'),
            'EXP-6150' => $this->account('Utilities: Fuel/ Gas', 'expense', 'Facilities & Utility', 'Gas/Petrol.', 'debit'),
            'EXP-6160' => $this->account('Utilities: Waste Disposal', 'expense', 'Facilities & Utility', 'Waste Disposal.', 'debit'),
            'EXP-6170' => $this->account('Utilities: Cleaning', 'expense', 'Facilities & Utility', 'General Cleaning Materials, tissue paper, soap.', 'debit'),
            'EXP-6180' => $this->account('Wages & Salaries', 'expense', 'Compensation & Labour', 'Regular pay for non-production staff.', 'debit'),
            'EXP-6190' => $this->account('Compensation Taxes', 'expense', 'Compensation & Labour', 'Employer-paid government taxes on worker wages.', 'debit'),
            'EXP-6200' => $this->account('Employee Benefits', 'expense', 'Compensation & Labour', 'Health insurance, retirement matches, and perks.', 'debit'),
            'EXP-6210' => $this->account('Contractor Fees', 'expense', 'Compensation & Labour', 'Payments made to freelance workers.', 'debit'),
            'EXP-6220' => $this->account('Repairs and Maintenance', 'expense', 'Admin & Ops', 'Upkeep for buildings, furniture, and computers.', 'debit'),
            'EXP-6230' => $this->account('IT and Software Subscription', 'expense', 'Admin & Ops', 'Cloud tools, CRM, and accounting platforms.', 'debit'),
            'EXP-6240' => $this->account('Office Supplies', 'expense', 'Admin & Ops', 'Paper, pens, ink, and stationery.', 'debit'),
            'EXP-6250' => $this->account('Insurance', 'expense', 'Admin & Ops', 'General liability, professional, and property policies.', 'debit'),
            'EXP-6260' => $this->account('Office Expense / General Admin', 'expense', 'Admin & Ops', 'General uncategorized items.', 'debit'),
            'EXP-6270' => $this->account('Advertising', 'expense', 'Sales & Marketing', 'Paid digital ads, print, and billboards.', 'debit'),
            'EXP-6280' => $this->account('Marketing campaigns', 'expense', 'Sales & Marketing', 'Event sponsorships and promotional materials.', 'debit'),
            'EXP-6290' => $this->account('Sales commissions', 'expense', 'Sales & Marketing', 'Performance bonuses paid to sales staff.', 'debit'),
            'EXP-6300' => $this->account('Travel and entertainment', 'expense', 'Sales & Marketing', 'Client dinners, flights, and lodging.', 'debit'),
            'EXP-6310' => $this->account('Loan Interest', 'expense', 'Non-Operating Expenses', 'Fees paid on business loans.', 'debit'),
            'EXP-6320' => $this->account('Amortization', 'expense', 'Non-Operating Expenses', 'Expensing intangible assets over time.', 'debit'),
            'EXP-6330' => $this->account('Depreciation', 'expense', 'Non-Operating Expenses', 'Spreading asset costs over useful lifespans.', 'debit'),
            'EXP-6340' => $this->account('Income Taxes', 'expense', 'Non-Operating Expenses', 'Federal, state, and local business taxes.', 'debit'),
        ];
    }

    /**
     * @return array{name: string, type: string, category: string, description: string, normal_balance: string}
     */
    private function account(string $name, string $type, string $category, string $description, string $normalBalance): array
    {
        return [
            'name' => $name,
            'type' => $type,
            'category' => $category,
            'description' => $description,
            'normal_balance' => $normalBalance,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function defaultExpenseCategories(): array
    {
        return [
            'General Operations' => 'EXP-6000',
            'Rent' => 'EXP-6010',
            'Utilities' => 'EXP-6020',
            'Salaries and Wages' => 'EXP-6030',
            'Marketing' => 'EXP-6040',
            'Inventory Adjustments' => 'EXP-6050',
            'Freight and Delivery' => 'EXP-5100',
            'Taxes and Licenses' => 'EXP-5200',
        ];
    }
}
