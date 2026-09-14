<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase "Model A": how a product depletes stock at sale. Backfilled from the existing
 * track_inventory flag (off → no tracking, on → track own stock). "recipe" is opt-in
 * from the product editor for à la carte items that deduct ingredients on sale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('stock_policy', 20)->default('tracked')->after('track_inventory')->index();
        });

        DB::table('products')->where('track_inventory', false)->update(['stock_policy' => 'none']);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('stock_policy');
        });
    }
};
