<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a product as a "finished product" — something produced via a recipe. The
 * Production area lists these and each has its own detail page (recipe, margins,
 * production history).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('is_finished_product')->default(false)->index()->after('unit_category_id');
        });

        // Any product that already has an active recipe is a finished product.
        $withRecipes = DB::table('recipes')
            ->join('product_variants', 'recipes.output_product_variant_id', '=', 'product_variants.id')
            ->where('recipes.is_active', true)
            ->pluck('product_variants.product_id');

        if ($withRecipes->isNotEmpty()) {
            DB::table('products')->whereIn('id', $withRecipes)->update(['is_finished_product' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('is_finished_product');
        });
    }
};
