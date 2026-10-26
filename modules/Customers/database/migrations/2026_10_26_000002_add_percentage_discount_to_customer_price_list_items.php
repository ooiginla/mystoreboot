<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_price_list_items', function (Blueprint $table): void {
            $table->string('price_type', 20)->default('fixed')->after('minimum_quantity');
            $table->decimal('discount_percent', 5, 2)->nullable()->after('unit_price_minor');
        });
    }

    public function down(): void
    {
        Schema::table('customer_price_list_items', function (Blueprint $table): void {
            $table->dropColumn(['price_type', 'discount_percent']);
        });
    }
};
