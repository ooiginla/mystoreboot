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
        Schema::table('online_collected_payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('customer_total_minor')->default(0)->after('gateway_charge_minor');
            $table->bigInteger('storeboot_profit_minor')->default(0)->after('net_amount_minor');
        });

        DB::table('online_collected_payments')
            ->join('sales_orders', 'sales_orders.id', '=', 'online_collected_payments.sales_order_id')
            ->select([
                'online_collected_payments.id',
                'online_collected_payments.net_amount_minor',
                'sales_orders.subtotal_minor',
                'sales_orders.tax_minor',
                'sales_orders.shipping_minor',
                'sales_orders.coupon_discount_minor',
                'sales_orders.admin_discount_minor',
            ])
            ->orderBy('online_collected_payments.id')
            ->each(function (object $payment): void {
                $customerTotalMinor = max(0,
                    (int) $payment->subtotal_minor
                    + (int) $payment->tax_minor
                    + (int) $payment->shipping_minor
                    - (int) $payment->coupon_discount_minor
                    - (int) $payment->admin_discount_minor
                );

                DB::table('online_collected_payments')
                    ->where('id', $payment->id)
                    ->update([
                        'customer_total_minor' => $customerTotalMinor,
                        'storeboot_profit_minor' => (int) $payment->net_amount_minor - $customerTotalMinor,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('online_collected_payments', function (Blueprint $table): void {
            $table->dropColumn(['customer_total_minor', 'storeboot_profit_minor']);
        });
    }
};
