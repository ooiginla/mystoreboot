<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Storeboot's own fee on a wallet withdrawal (on top of the gateway's transfer fee, which
 * the merchant bears). Same shape as PAYMENT_GATEWAY_CHARGE — a percentage and/or a fixed
 * amount, tenant-overridable. Seeded to zero; turn it on later when desired.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('global_configs')->updateOrInsert(
            [
                'tenant_id' => null,
                'key' => 'STOREBOOT_TRANSFER_FEE',
            ],
            [
                'value' => json_encode([
                    'percentage_rate' => 0,
                    'fixed_amount_minor' => 0,
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('global_configs')
            ->whereNull('tenant_id')
            ->where('key', 'TRANSFER_FEE')
            ->delete();
    }
};
