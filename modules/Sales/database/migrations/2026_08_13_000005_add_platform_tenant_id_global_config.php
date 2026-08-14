<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Storeboot platform's own tenant/books. When set, platform-level income (e.g. the
 * transfer fee earned on merchant withdrawals) is posted here instead of the merchant's
 * ledger. Left empty by default — designate a dedicated platform tenant, not a real merchant.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('global_configs')->updateOrInsert(
            [
                'tenant_id' => null,
                'key' => 'PLATFORM_TENANT_ID',
            ],
            [
                'value' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('global_configs')
            ->whereNull('tenant_id')
            ->where('key', 'PLATFORM_TENANT_ID')
            ->delete();
    }
};
