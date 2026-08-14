<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Remove the obsolete ONLINE_STOREBOOT_CHARGE config. Storeboot's cut is now taken as the
 * per-sale gateway-charge markup (recognised in the platform income GL), so no separate
 * charge is deducted at settlement.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('global_configs')->where('key', 'ONLINE_STOREBOOT_CHARGE')->delete();
    }

    public function down(): void
    {
        DB::table('global_configs')->updateOrInsert(
            ['tenant_id' => null, 'key' => 'ONLINE_STOREBOOT_CHARGE'],
            [
                'value' => json_encode(['percentage_rate' => 1.5, 'fixed_amount_minor' => 100000], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
};
