<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The gateway's own transfer fee (what the provider charges to send a payout), keyed by
 * provider so it survives a gateway swap. The merchant bears this fee. Paystack (NGN):
 *   ≤ ₦5,000 → ₦10 · ₦5,001–₦50,000 → ₦25 · > ₦50,000 → ₦50
 *   + ₦50 stamp duty on transfers of ₦10,000 and above.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('global_configs')->updateOrInsert(
            [
                'tenant_id' => null,
                'key' => 'GATEWAY_TRANSFER_FEE',
            ],
            [
                'value' => json_encode([
                    'paystack' => [
                        'tiers' => [
                            ['max_minor' => 500000, 'fee_minor' => 1000],   // ≤ ₦5,000 → ₦10
                            ['max_minor' => 5000000, 'fee_minor' => 2500],  // ≤ ₦50,000 → ₦25
                            ['max_minor' => null, 'fee_minor' => 5000],     // > ₦50,000 → ₦50
                        ],
                        'stamp_duty' => ['threshold_minor' => 1000000, 'fee_minor' => 5000], // ≥ ₦10,000 → +₦50
                    ],
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
            ->where('key', 'GATEWAY_TRANSFER_FEE')
            ->delete();
    }
};
