<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rename the transfer-fee config keys for clarity on databases that already ran the earlier
 * seeds. Fresh installs seed the new names directly, so this is a no-op there.
 *   TRANSFER_FEE          -> STOREBOOT_TRANSFER_FEE  (Storeboot's own withdrawal markup)
 *   GATEWAY_TRANSFER_FEES -> GATEWAY_TRANSFER_FEE    (the gateway's payout fee tiers)
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('global_configs')->where('key', 'TRANSFER_FEE')
            ->update(['key' => 'STOREBOOT_TRANSFER_FEE', 'updated_at' => now()]);

        DB::table('global_configs')->where('key', 'GATEWAY_TRANSFER_FEES')
            ->update(['key' => 'GATEWAY_TRANSFER_FEE', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('global_configs')->where('key', 'STOREBOOT_TRANSFER_FEE')
            ->update(['key' => 'TRANSFER_FEE', 'updated_at' => now()]);

        DB::table('global_configs')->where('key', 'GATEWAY_TRANSFER_FEE')
            ->update(['key' => 'GATEWAY_TRANSFER_FEES', 'updated_at' => now()]);
    }
};
