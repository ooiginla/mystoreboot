<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Production can now be started and completed later. While a run is in progress its
 * ingredients are held (reserved) at the source store, so nobody else can promise them,
 * and only deducted when the run is completed with the amounts actually used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_orders', function (Blueprint $table): void {
            $table->timestamp('started_at')->nullable()->after('status');
            $table->timestamp('cancelled_at')->nullable()->after('produced_at');
        });

        Schema::table('production_order_items', function (Blueprint $table): void {
            // What is being held at the source store for this ingredient, in its base unit —
            // exactly what has to be released again, whatever unit the recipe line uses.
            $table->decimal('reserved_base_quantity', 15, 4)->default(0)->after('planned_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('production_order_items', function (Blueprint $table): void {
            $table->dropColumn('reserved_base_quantity');
        });

        Schema::table('production_orders', function (Blueprint $table): void {
            $table->dropColumn(['started_at', 'cancelled_at']);
        });
    }
};
