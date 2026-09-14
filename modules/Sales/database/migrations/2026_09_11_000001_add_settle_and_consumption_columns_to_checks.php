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
            $table->timestamp('bill_printed_at')->nullable()->after('check_status');
            $table->timestamp('settled_at')->nullable()->after('bill_printed_at');
        });

        Schema::table('sales_order_items', function (Blueprint $table): void {
            // Sorted option ids. Two taps of "Suya, extra pepper" merge into one line;
            // "Suya, extra pepper" and "Suya, no onions" must not.
            $table->string('modifier_key', 191)->nullable()->after('seat_number');
            // Set when the line's ingredients left the store (at fire, for tenants that
            // deplete then). Settling reads it so nothing is taken out twice.
            $table->timestamp('ingredients_depleted_at')->nullable()->after('fired_at');
            // What those ingredients cost, so COGS at settle — or waste at void — books
            // the cost actually consumed rather than re-pricing it later.
            $table->unsignedBigInteger('consumed_cost_minor')->default(0)->after('ingredients_depleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table): void {
            $table->dropColumn(['modifier_key', 'ingredients_depleted_at', 'consumed_cost_minor']);
        });

        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->dropColumn(['bill_printed_at', 'settled_at']);
        });
    }
};
