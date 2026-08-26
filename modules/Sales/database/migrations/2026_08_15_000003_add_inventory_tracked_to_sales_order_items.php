<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot the product's inventory policy onto each order line at order time, so completing,
 * cancelling or returning an old order never behaves differently just because the product's
 * policy was changed afterwards. Existing lines default to tracked (unchanged behaviour).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('sales_order_items', 'inventory_tracked')) {
                $table->boolean('inventory_tracked')->default(true)->after('product_variant_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table): void {
            if (Schema::hasColumn('sales_order_items', 'inventory_tracked')) {
                $table->dropColumn('inventory_tracked');
            }
        });
    }
};
