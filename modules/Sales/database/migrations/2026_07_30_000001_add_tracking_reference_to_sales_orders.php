<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Sales\Models\SalesOrder;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            // Globally unique across all tenants so a buyer's tracking link is
            // unambiguous even when many stores are selling at the same time.
            $table->string('tracking_reference', 40)->nullable()->after('receipt_number');
        });

        // Backfill existing orders with a unique reference before enforcing uniqueness.
        DB::table('sales_orders')
            ->whereNull('tracking_reference')
            ->orderBy('id')
            ->select('id')
            ->chunkById(500, function ($orders): void {
                foreach ($orders as $order) {
                    DB::table('sales_orders')
                        ->where('id', $order->id)
                        ->update(['tracking_reference' => SalesOrder::freshTrackingReference()]);
                }
            });

        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->unique('tracking_reference');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->dropUnique(['tracking_reference']);
            $table->dropColumn('tracking_reference');
        });
    }
};
