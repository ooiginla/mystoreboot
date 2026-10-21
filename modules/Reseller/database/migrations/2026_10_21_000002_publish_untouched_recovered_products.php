<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Reseller\Enums\ProductAvailability;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('reseller_settings')
            ->where('auto_publish_products', true)
            ->whereColumn('created_at', 'updated_at')
            ->orderBy('tenant_id')
            ->each(function (object $settings): void {
                $currency = DB::table('tenants')->where('id', $settings->tenant_id)->value('currency_code');

                if (! is_string($currency) || $currency === '') {
                    return;
                }

                DB::table('reseller_products')
                    ->where('tenant_id', $settings->tenant_id)
                    ->where('currency_code', strtoupper($currency))
                    ->where('availability', '!=', ProductAvailability::OutOfStock->value)
                    ->where('is_visible', false)
                    ->where('is_excluded', false)
                    ->whereColumn('created_at', 'updated_at')
                    ->update([
                        'is_visible' => true,
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        // Visibility changes cannot be safely reversed without hiding products
        // that a reseller may have published after this migration ran.
    }
};
