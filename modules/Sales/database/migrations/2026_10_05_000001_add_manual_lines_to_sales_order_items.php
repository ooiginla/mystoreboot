<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table): void {
            $table->dropForeign(['product_variant_id']);
        });

        Schema::table('sales_order_items', function (Blueprint $table): void {
            $table->foreignId('product_variant_id')->nullable()->change();
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->nullOnDelete();
            $table->foreignId('product_category_id')->nullable()->after('product_variant_id')
                ->constrained('product_categories')->nullOnDelete();
            $table->string('category_name', 140)->nullable()->after('product_category_id');
            $table->string('line_type', 24)->default('catalog')->after('category_name')->index();
            $table->string('cost_basis', 24)->default('inventory')->after('unit_cost_minor')->index();
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table): void {
            $table->dropForeign(['product_variant_id']);
            $table->dropForeign(['product_category_id']);
            $table->dropIndex(['line_type']);
            $table->dropIndex(['cost_basis']);
            $table->dropColumn(['product_category_id', 'category_name', 'line_type', 'cost_basis']);
        });

        Schema::table('sales_order_items', function (Blueprint $table): void {
            $table->foreignId('product_variant_id')->nullable(false)->change();
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
        });
    }
};
