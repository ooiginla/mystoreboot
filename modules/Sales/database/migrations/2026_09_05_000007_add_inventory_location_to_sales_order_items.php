<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0, Workstream C: per-line depletion location. One order can now deplete from
 * several stores (a grill line from the grill store, a kitchen line from the kitchen
 * store). Nullable and unused in Phase 0 — populated and consumed from Phase 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table): void {
            $table->foreignId('inventory_location_id')->nullable()->after('product_variant_id')
                ->constrained('inventory_locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('inventory_location_id');
        });
    }
};
