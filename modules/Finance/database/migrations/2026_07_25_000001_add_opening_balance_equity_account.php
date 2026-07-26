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
                        'code' => '3400',
                    ], [
                        'name' => 'Opening Balance Equity',
                        'type' => 'equity',
                        'category' => 'Equity',
                        'description' => 'Temporary offset for opening balances imported when an existing business starts using Storeboot.',
                        'normal_balance' => 'credit',
                        'is_system' => true,
                        'is_active' => true,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Keep the account and any opening-balance postings intact on rollback.
    }
};
