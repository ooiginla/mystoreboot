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
                    DB::table('finance_accounts')->updateOrInsert([
                        'tenant_id' => $tenant->id,
                        'code' => '1020',
                    ], [
                        'name' => 'Cash in Tills',
                        'type' => 'asset',
                        'category' => 'Current Assets',
                        'description' => 'Cash currently held by cashier tills and registers.',
                        'normal_balance' => 'debit',
                        'is_system' => true,
                        'is_active' => true,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]);

                    DB::table('finance_accounts')->updateOrInsert([
                        'tenant_id' => $tenant->id,
                        'code' => '1030',
                    ], [
                        'name' => 'Branch Safe / Vault',
                        'type' => 'asset',
                        'category' => 'Current Assets',
                        'description' => 'Cash held in branch safes or vaults before banking.',
                        'normal_balance' => 'debit',
                        'is_system' => true,
                        'is_active' => true,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]);

                    $tillAccountId = DB::table('finance_accounts')
                        ->where('tenant_id', $tenant->id)
                        ->where('code', '1020')
                        ->value('id');
                    $vaultAccountId = DB::table('finance_accounts')
                        ->where('tenant_id', $tenant->id)
                        ->where('code', '1030')
                        ->value('id');

                    $oldTillAccountIds = DB::table('finance_accounts')
                        ->where('tenant_id', $tenant->id)
                        ->where('code', 'like', 'CT-%')
                        ->pluck('id');
                    $oldVaultAccountIds = DB::table('finance_accounts')
                        ->where('tenant_id', $tenant->id)
                        ->where('code', 'like', 'BV-%')
                        ->pluck('id');

                    if ($oldTillAccountIds->isNotEmpty()) {
                        $this->repointAccountReferences($oldTillAccountIds->all(), (int) $tillAccountId);
                    }

                    if ($oldVaultAccountIds->isNotEmpty()) {
                        $this->repointAccountReferences($oldVaultAccountIds->all(), (int) $vaultAccountId);
                    }

                    DB::table('finance_accounts')
                        ->where('tenant_id', $tenant->id)
                        ->where(function ($query): void {
                            $query->where('code', 'like', 'CT-%')
                                ->orWhere('code', 'like', 'BV-%');
                        })
                        ->delete();
                }
            });
    }

    public function down(): void
    {
        // The generated per-session/per-branch account clutter is intentionally not recreated.
    }

    /**
     * @param  array<int, int>  $oldAccountIds
     */
    private function repointAccountReferences(array $oldAccountIds, int $newAccountId): void
    {
        DB::table('finance_journal_lines')
            ->whereIn('finance_account_id', $oldAccountIds)
            ->update(['finance_account_id' => $newAccountId]);

        DB::table('sales_cash_locations')
            ->whereIn('finance_account_id', $oldAccountIds)
            ->update(['finance_account_id' => $newAccountId]);

        DB::table('finance_expense_categories')
            ->whereIn('finance_account_id', $oldAccountIds)
            ->update(['finance_account_id' => $newAccountId]);

        DB::table('finance_expenses')
            ->whereIn('finance_account_id', $oldAccountIds)
            ->update(['finance_account_id' => $newAccountId]);

        DB::table('finance_expenses')
            ->whereIn('payment_finance_account_id', $oldAccountIds)
            ->update(['payment_finance_account_id' => $newAccountId]);
    }
};
