<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0, Workstream B: purchase and goods-receipt quantities become decimals so
 * stock can be ordered/received in kg, litres, packs, etc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table): void {
            $table->decimal('quantity_ordered', 15, 4)->change();
            $table->decimal('quantity_received', 15, 4)->default(0)->change();
        });

        Schema::table('goods_receipt_items', function (Blueprint $table): void {
            $table->decimal('quantity_received', 15, 4)->change();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table): void {
            $table->integer('quantity_ordered')->change();
            $table->integer('quantity_received')->default(0)->change();
        });

        Schema::table('goods_receipt_items', function (Blueprint $table): void {
            $table->integer('quantity_received')->change();
        });
    }
};
