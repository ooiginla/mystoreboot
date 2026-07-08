<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('tenants')
            ->select('id')
            ->orderBy('id')
            ->chunk(100, function ($tenants) use ($now): void {
                foreach ($tenants as $tenant) {
                    foreach ($this->accounts() as $code => $account) {
                        DB::table('finance_accounts')->updateOrInsert([
                            'tenant_id' => $tenant->id,
                            'code' => $code,
                        ], [
                            'name' => $account['name'],
                            'type' => $account['type'],
                            'category' => $account['category'],
                            'description' => $account['description'],
                            'normal_balance' => $account['normal_balance'],
                            'is_system' => true,
                            'is_active' => true,
                            'updated_at' => $now,
                            'created_at' => $now,
                        ]);
                    }

                    $replacementCodes = [
                        'EXP-6010' => 'EXP-6100',
                        'EXP-6020' => 'EXP-6130',
                        'EXP-6180' => 'EXP-6030',
                    ];

                    foreach ($replacementCodes as $oldCode => $newCode) {
                        $oldAccountId = DB::table('finance_accounts')
                            ->where('tenant_id', $tenant->id)
                            ->where('code', $oldCode)
                            ->value('id');
                        $newAccountId = DB::table('finance_accounts')
                            ->where('tenant_id', $tenant->id)
                            ->where('code', $newCode)
                            ->value('id');

                        if ($oldAccountId && $newAccountId) {
                            $this->repointAccountReferences((int) $oldAccountId, (int) $newAccountId);
                        }
                    }

                    $this->repointExpenseCategory($tenant->id, 'rent', 'EXP-6100');
                    $this->repointExpenseCategory($tenant->id, 'utilities', 'EXP-6130');

                    DB::table('finance_accounts')
                        ->where('tenant_id', $tenant->id)
                        ->whereIn('code', array_keys($replacementCodes))
                        ->delete();
                }
            });
    }

    public function down(): void
    {
        // The removed duplicate default accounts are intentionally not recreated.
    }

    /**
     * @return array<string, array{name: string, type: string, category: string, description: string, normal_balance: string}>
     */
    private function accounts(): array
    {
        return [
            '1000' => $this->account('Cash on Hand', 'asset', 'Current Assets', 'Loose physical cash that is not held in tills, vaults, or petty cash.', 'debit'),
            '1040' => $this->account('Bank Transfer Clearing', 'asset', 'Current Assets', 'Customer bank transfer receipts awaiting reconciliation to a bank account.', 'debit'),
            '1050' => $this->account('POS/Card Clearing', 'asset', 'Current Assets', 'POS and card receipts awaiting settlement into a bank account.', 'debit'),
            '1060' => $this->account('Online Payment Clearing', 'asset', 'Current Assets', 'Online gateway receipts awaiting settlement into a bank account.', 'debit'),
            '1210' => $this->account('Inventory Freight / Landed Cost Clearing', 'asset', 'Current Assets', 'Inbound freight and landing costs to be allocated into inventory cost.', 'debit'),
            '1320' => $this->account('Input VAT / Tax Recoverable', 'asset', 'Current Assets', 'Recoverable input VAT or purchase tax paid to vendors.', 'debit'),
            '2100' => $this->account('Sales Tax / VAT Payable', 'liability', 'Current Liabilities', 'Sales tax or VAT collected and payable to government.', 'credit'),
            '2400' => $this->account('Accrued Expenses', 'liability', 'Current Liabilities', 'Expenses incurred but not yet invoiced or paid.', 'credit'),
            '2500' => $this->account('Loans Payable', 'liability', 'Liabilities', 'Principal owed on business loans and financing.', 'credit'),
            '3100' => $this->account('Retained Earnings', 'equity', 'Equity', 'Accumulated profits retained in the business.', 'credit'),
            '3200' => $this->account('Owner Drawings', 'equity', 'Equity', 'Withdrawals made by owners from the business.', 'debit'),
            '3300' => $this->account('Owner Contributions', 'equity', 'Equity', 'Additional capital contributed by owners.', 'credit'),
            '4120' => $this->account('Inventory Adjustment Gains', 'income', 'Other Income', 'Gains recognized when inventory is increased through manual adjustments.', 'credit'),
            'EXP-5100' => $this->account('Outbound Delivery Expense', 'expense', 'Admin & Ops', 'Customer delivery and outbound shipping expenses.', 'debit'),
            'EXP-5200' => $this->account('Taxes and Licenses', 'expense', 'Non-Operating Expenses', 'Business taxes, levies, permits, and licenses that are not recoverable VAT.', 'debit'),
            'EXP-6050' => $this->account('Inventory Shrinkage and Write-Offs', 'expense', 'Direct Costs', 'Inventory losses, damage, shrinkage, and write-offs.', 'debit'),
            'EXP-6340' => $this->account('Income Tax Expense', 'expense', 'Non-Operating Expenses', 'Income tax expense on business profit.', 'debit'),
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

    private function repointAccountReferences(int $oldAccountId, int $newAccountId): void
    {
        DB::table('finance_journal_lines')
            ->where('finance_account_id', $oldAccountId)
            ->update(['finance_account_id' => $newAccountId]);

        DB::table('finance_expense_categories')
            ->where('finance_account_id', $oldAccountId)
            ->update(['finance_account_id' => $newAccountId]);

        DB::table('finance_expenses')
            ->where('finance_account_id', $oldAccountId)
            ->update(['finance_account_id' => $newAccountId]);

        DB::table('finance_expenses')
            ->where('payment_finance_account_id', $oldAccountId)
            ->update(['payment_finance_account_id' => $newAccountId]);
    }

    private function repointExpenseCategory(string $tenantId, string $categoryCode, string $accountCode): void
    {
        $accountId = DB::table('finance_accounts')
            ->where('tenant_id', $tenantId)
            ->where('code', $accountCode)
            ->value('id');

        if (! $accountId) {
            return;
        }

        DB::table('finance_expense_categories')
            ->where('tenant_id', $tenantId)
            ->where('code', $categoryCode)
            ->update([
                'finance_account_id' => $accountId,
                'updated_at' => now(),
            ]);
    }
};
