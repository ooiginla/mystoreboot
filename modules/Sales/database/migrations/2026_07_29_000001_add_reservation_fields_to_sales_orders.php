<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->boolean('stock_reserved')->default(false)->after('inventory_location_id');
            $table->timestamp('reserved_until')->nullable()->after('stock_reserved');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->dropColumn(['stock_reserved', 'reserved_until']);
        });
    }
};
