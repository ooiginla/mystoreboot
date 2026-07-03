<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('global_configs')->updateOrInsert(
            [
                'tenant_id' => null,
                'key' => 'PAYMENT_GATEWAY_CHARGE',
            ],
            [
                'value' => json_encode([
                    'percentage_rate' => 1.5,
                    'fixed_amount_minor' => 10000,
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
            ->where('key', 'PAYMENT_GATEWAY_CHARGE')
            ->delete();
    }
};
