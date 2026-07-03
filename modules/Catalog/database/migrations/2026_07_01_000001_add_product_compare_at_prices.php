<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'compare_at_price_minor')) {
                $table->unsignedBigInteger('compare_at_price_minor')->nullable()->after('base_cost_price_minor');
            }
        });

        DB::table('products')
            ->whereNull('compare_at_price_minor')
            ->whereNotNull('discount_price_minor')
            ->update(['compare_at_price_minor' => DB::raw('discount_price_minor')]);

        DB::table('product_variants')
            ->whereNull('compare_at_price_minor')
            ->whereNotNull('discount_price_minor')
            ->update(['compare_at_price_minor' => DB::raw('discount_price_minor')]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'compare_at_price_minor')) {
                $table->dropColumn('compare_at_price_minor');
            }
        });
    }
};
