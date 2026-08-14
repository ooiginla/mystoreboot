<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ensure every existing business has a zero-balance wallet (new signups get one at
 * registration). A wallet is provisioned regardless of payout mode so any held balance is
 * always visible and withdrawable.
 */
return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('wallets')->pluck('tenant_id')->all();

        $rows = DB::table('tenants')
            ->whereNotIn('id', $existing ?: ['00000000-0000-0000-0000-000000000000'])
            ->when(Schema::hasColumn('tenants', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
            ->get(['id', 'currency_code'])
            ->map(fn (object $tenant): array => [
                'tenant_id' => $tenant->id,
                'currency_code' => $tenant->currency_code ?: 'NGN',
                'available_balance_minor' => 0,
                'pending_balance_minor' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        if ($rows !== []) {
            DB::table('wallets')->insert($rows);
        }
    }

    public function down(): void
    {
        // Wallets are foundational; leave them in place on rollback.
    }
};
