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
            $table->string('delivery_city', 120)->nullable()->after('delivery_address');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', fn (Blueprint $table) => $table->dropColumn('delivery_city'));
    }
};
