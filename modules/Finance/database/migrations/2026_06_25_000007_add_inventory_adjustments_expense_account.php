<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $tenants = DB::table('tenants')->pluck('id');

        foreach ($tenants as $tenantId) {
            $exists = DB::table('finance_accounts')
                ->where('tenant_id', $tenantId)
                ->where('code', 'EXP-6050')
                ->exists();

            if (! $exists) {
                DB::table('finance_accounts')->insert([
                    'tenant_id' => $tenantId,
                    'code' => 'EXP-6050',
                    'name' => 'Inventory Adjustments and Write-Offs',
                    'type' => 'expense',
                    'category' => 'Direct Costs',
                    'description' => 'Inventory gains, losses, damage, shrinkage, and manual return adjustments.',
                    'normal_balance' => 'debit',
                    'is_system' => true,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $accountId = DB::table('finance_accounts')
                ->where('tenant_id', $tenantId)
                ->where('code', 'EXP-6050')
                ->value('id');

            if ($accountId && ! DB::table('finance_expense_categories')->where('tenant_id', $tenantId)->where('code', 'inventory-adjustments')->exists()) {
                DB::table('finance_expense_categories')->insert([
                    'tenant_id' => $tenantId,
                    'finance_account_id' => $accountId,
                    'name' => 'Inventory Adjustments',
                    'code' => 'inventory-adjustments',
                    'description' => 'Default inventory adjustment and write-off category.',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('finance_expense_categories')
            ->where('code', 'inventory-adjustments')
            ->delete();

        $accountIds = DB::table('finance_accounts')
            ->where('code', 'EXP-6050')
            ->where('is_system', true)
            ->pluck('id');

        $usedAccountIds = DB::table('finance_journal_lines')
            ->whereIn('finance_account_id', $accountIds)
            ->pluck('finance_account_id')
            ->merge(DB::table('finance_expenses')->whereIn('finance_account_id', $accountIds)->pluck('finance_account_id'))
            ->unique();

        DB::table('finance_accounts')
            ->whereIn('id', $accountIds->diff($usedAccountIds)->values())
            ->delete();
    }
};
