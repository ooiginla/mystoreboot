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
                        'code' => 'EXP-6370',
                    ], [
                        'name' => 'Cash Short & Over (Till Variance)',
                        'type' => 'expense',
                        'category' => 'Admin & Ops',
                        'description' => 'Cash drawer shortages and overages recognised at till close.',
                        'normal_balance' => 'debit',
                        'is_system' => true,
                        'is_active' => true,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]);

                    $oldAccountId = DB::table('finance_accounts')
                        ->where('tenant_id', $tenant->id)
                        ->where('code', 'EXP-6360')
                        ->value('id');
                    $newAccountId = DB::table('finance_accounts')
                        ->where('tenant_id', $tenant->id)
                        ->where('code', 'EXP-6370')
                        ->value('id');

                    if (! $oldAccountId || ! $newAccountId) {
                        continue;
                    }

                    $varianceEntryIds = DB::table('finance_journal_entries')
                        ->where('tenant_id', $tenant->id)
                        ->whereIn('source_event', ['variance_shortage', 'variance_overage'])
                        ->pluck('id');

                    if ($varianceEntryIds->isEmpty()) {
                        continue;
                    }

                    DB::table('finance_journal_lines')
                        ->where('tenant_id', $tenant->id)
                        ->where('finance_account_id', $oldAccountId)
                        ->whereIn('finance_journal_entry_id', $varianceEntryIds)
                        ->update(['finance_account_id' => $newAccountId]);
                }
            });
    }

    public function down(): void
    {
        // Keep the account and historical postings intact on rollback.
    }
};
