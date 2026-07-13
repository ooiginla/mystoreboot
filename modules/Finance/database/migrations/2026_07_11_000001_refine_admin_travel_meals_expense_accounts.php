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

                    $this->repointAccount($tenant->id, 'EXP-6260', 'EXP-6000');
                    $this->ensureExpenseCategory($tenant->id, 'General Operations', 'EXP-6000');
                    $this->ensureExpenseCategory($tenant->id, 'Travelling & Transportation', 'EXP-6300');
                    $this->ensureExpenseCategory($tenant->id, 'Meals & Entertainment', 'EXP-6360');

                    DB::table('finance_accounts')
                        ->where('tenant_id', $tenant->id)
                        ->where('code', 'EXP-6260')
                        ->delete();
                }
            });
    }

    public function down(): void
    {
        // The removed duplicate admin expense account is intentionally not recreated.
    }

    /**
     * @return array<string, array{name: string, type: string, category: string, description: string, normal_balance: string}>
     */
    private function accounts(): array
    {
        return [
            'EXP-6000' => $this->account('General Office & Administrative Expense', 'expense', 'Admin & Ops', 'General office, administrative, and uncategorized operating expenses.', 'debit'),
            'EXP-6300' => $this->account('Travelling & Transportation', 'expense', 'Travel & Logistics', 'Business transport, local travel, flights, lodging, and related travel costs.', 'debit'),
            'EXP-6360' => $this->account('Meals & Entertainment', 'expense', 'Meals & Entertainment', 'Business meals, client entertainment, refreshments, and hospitality costs.', 'debit'),
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

    private function repointAccount(string $tenantId, string $oldCode, string $newCode): void
    {
        $oldAccountId = DB::table('finance_accounts')
            ->where('tenant_id', $tenantId)
            ->where('code', $oldCode)
            ->value('id');
        $newAccountId = DB::table('finance_accounts')
            ->where('tenant_id', $tenantId)
            ->where('code', $newCode)
            ->value('id');

        if (! $oldAccountId || ! $newAccountId) {
            return;
        }

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

        DB::table('finance_bank_movements')
            ->where('fee_finance_account_id', $oldAccountId)
            ->update(['fee_finance_account_id' => $newAccountId]);
    }

    private function ensureExpenseCategory(string $tenantId, string $name, string $accountCode): void
    {
        $accountId = DB::table('finance_accounts')
            ->where('tenant_id', $tenantId)
            ->where('code', $accountCode)
            ->value('id');

        if (! $accountId) {
            return;
        }

        DB::table('finance_expense_categories')->updateOrInsert([
            'tenant_id' => $tenantId,
            'code' => str($name)->slug()->toString(),
        ], [
            'finance_account_id' => $accountId,
            'name' => $name,
            'description' => 'Default operating expense category.',
            'is_active' => true,
            'updated_at' => now(),
            'created_at' => now(),
        ]);
    }
};
