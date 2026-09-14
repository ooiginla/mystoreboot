<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0, Workstream B: physical quantities become decimals so stock can be kept in
 * kg, litres, cups, etc. decimal(15,4) holds ~1e11 with 4 dp — ample. Existing
 * integers become n.0000, so nothing changes for whole-unit stock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_stock_levels', function (Blueprint $table): void {
            $table->decimal('quantity_on_hand', 15, 4)->default(0)->change();
            $table->decimal('quantity_reserved', 15, 4)->default(0)->change();
            $table->decimal('reorder_level', 15, 4)->default(0)->change();
            $table->decimal('reorder_quantity', 15, 4)->default(0)->change();
        });

        Schema::table('inventory_batches', function (Blueprint $table): void {
            $table->decimal('quantity_remaining', 15, 4)->default(0)->change();
        });

        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->decimal('quantity', 15, 4)->change();
            $table->decimal('stock_after', 15, 4)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_stock_levels', function (Blueprint $table): void {
            $table->integer('quantity_on_hand')->default(0)->change();
            $table->integer('quantity_reserved')->default(0)->change();
            $table->integer('reorder_level')->default(0)->change();
            $table->integer('reorder_quantity')->default(0)->change();
        });

        Schema::table('inventory_batches', function (Blueprint $table): void {
            $table->integer('quantity_remaining')->default(0)->change();
        });

        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->integer('quantity')->change();
            $table->integer('stock_after')->default(0)->change();
        });
    }
};
