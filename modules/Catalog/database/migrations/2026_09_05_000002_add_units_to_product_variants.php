<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0, Workstream A: give each variant a stocking base unit and an optional
 * purchase unit with a product-specific pack factor. Nullable so existing rows are
 * untouched here; a follow-up migration backfills the base unit to "each".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->foreignId('base_unit_id')->nullable()->after('barcode')
                ->constrained('units_of_measure')->nullOnDelete();
            $table->foreignId('purchase_unit_id')->nullable()->after('base_unit_id')
                ->constrained('units_of_measure')->nullOnDelete();
            // How many base units one purchase unit equals (e.g. 1 carton = 24 eaches).
            $table->decimal('purchase_to_base_factor', 18, 6)->nullable()->after('purchase_unit_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('purchase_unit_id');
            $table->dropColumn('purchase_to_base_factor');
            $table->dropConstrainedForeignId('base_unit_id');
        });
    }
};
