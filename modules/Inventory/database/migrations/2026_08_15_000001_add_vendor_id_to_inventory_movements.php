<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional vendor on a stock movement — lets a merchant record where added stock came from,
 * straight from the product's Inventory tab, without a full purchase order. Traceability
 * only (no payable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventory_movements', 'vendor_id')) {
                $table->foreignId('vendor_id')->nullable()->after('product_variant_id')->constrained('vendors')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            if (Schema::hasColumn('inventory_movements', 'vendor_id')) {
                $table->dropConstrainedForeignId('vendor_id');
            }
        });
    }
};
