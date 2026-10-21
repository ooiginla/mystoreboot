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
        Schema::table('reseller_settings', function (Blueprint $table): void {
            $table->boolean('hide_out_of_stock_products')->default(true)->after('show_source_store');
            $table->boolean('show_source_store')->default(false)->change();
        });

        DB::table('reseller_settings')
            ->where('show_source_store', true)
            ->whereColumn('created_at', 'updated_at')
            ->update(['show_source_store' => false]);
    }

    public function down(): void
    {
        Schema::table('reseller_settings', function (Blueprint $table): void {
            $table->boolean('show_source_store')->default(true)->change();
            $table->dropColumn('hide_out_of_stock_products');
        });
    }
};
