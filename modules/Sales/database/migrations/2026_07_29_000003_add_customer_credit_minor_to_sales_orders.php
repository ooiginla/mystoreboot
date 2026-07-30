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
        Schema::table('sales_orders', function (Blueprint $table): void {
            // Outstanding refundable credit owed to the customer, created by
            // cancellations and returns and cleared when the refund is paid out.
            $table->unsignedBigInteger('customer_credit_minor')->default(0)->after('refunded_minor');
        });

        // Backfill orders already sitting on customer credit so their refund still
        // works. Done in PHP to stay portable across MySQL and SQLite.
        DB::table('sales_orders')
            ->where('payment_status', 'customer_credit')
            ->select('id', 'paid_minor', 'refunded_minor')
            ->orderBy('id')
            ->chunk(500, function ($orders): void {
                foreach ($orders as $order) {
                    DB::table('sales_orders')
                        ->where('id', $order->id)
                        ->update(['customer_credit_minor' => max(0, (int) $order->paid_minor - (int) $order->refunded_minor)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->dropColumn('customer_credit_minor');
        });
    }
};
