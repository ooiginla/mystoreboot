<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-product fulfilment policy. track_inventory = true (default) keeps today's behaviour:
 * stock is counted, reserved and deducted. false = made-to-order: always sellable, never
 * reserved or deducted. lead_time is an optional "Ready in 3–5 days" note for the storefront.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'track_inventory')) {
                $table->boolean('track_inventory')->default(true)->after('has_variants');
            }
            if (! Schema::hasColumn('products', 'lead_time')) {
                $table->string('lead_time', 120)->nullable()->after('track_inventory');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            foreach (['track_inventory', 'lead_time'] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
