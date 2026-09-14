<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0, Workstream B: sales-order and sales-return quantities become decimals so
 * items can be sold by weight/volume (0.5 kg, 1.5 L) alongside whole units.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table): void {
            $table->decimal('quantity', 15, 4)->change();
            $table->decimal('quantity_returned', 15, 4)->default(0)->change();
        });

        Schema::table('sales_return_items', function (Blueprint $table): void {
            $table->decimal('quantity', 15, 4)->change();
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table): void {
            $table->integer('quantity')->change();
            $table->integer('quantity_returned')->default(0)->change();
        });

        Schema::table('sales_return_items', function (Blueprint $table): void {
            $table->integer('quantity')->change();
        });
    }
};
