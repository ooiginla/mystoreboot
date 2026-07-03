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
            if (! Schema::hasColumn('online_collected_payments', 'product_amount_minor')) {
                $table->unsignedBigInteger('product_amount_minor')->default(0)->after('currency');
            }

            if (! Schema::hasColumn('online_collected_payments', 'shipping_amount_minor')) {
                $table->unsignedBigInteger('shipping_amount_minor')->default(0)->after('product_amount_minor');
            }

            if (! Schema::hasColumn('online_collected_payments', 'gateway_charge_minor')) {
                $table->unsignedBigInteger('gateway_charge_minor')->default(0)->after('shipping_amount_minor');
            }
        });

        DB::table('online_collected_payments')
            ->join('sales_orders', 'sales_orders.id', '=', 'online_collected_payments.sales_order_id')
            ->where('online_collected_payments.product_amount_minor', 0)
            ->where('online_collected_payments.shipping_amount_minor', 0)
            ->where('online_collected_payments.gateway_charge_minor', 0)
            ->select([
                'online_collected_payments.id',
                'online_collected_payments.amount_minor',
                'sales_orders.shipping_minor',
                'sales_orders.gateway_charge_minor',
            ])
            ->orderBy('online_collected_payments.id')
            ->each(function (object $payment): void {
                $shippingAmountMinor = (int) $payment->shipping_minor;
                $gatewayChargeMinor = (int) $payment->gateway_charge_minor;

                DB::table('online_collected_payments')
                    ->where('id', $payment->id)
                    ->update([
                        'shipping_amount_minor' => $shippingAmountMinor,
                        'gateway_charge_minor' => $gatewayChargeMinor,
                        'product_amount_minor' => max(0, (int) $payment->amount_minor - $shippingAmountMinor - $gatewayChargeMinor),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('online_collected_payments', function (Blueprint $table): void {
            if (Schema::hasColumn('online_collected_payments', 'gateway_charge_minor')) {
                $table->dropColumn('gateway_charge_minor');
            }

            if (Schema::hasColumn('online_collected_payments', 'shipping_amount_minor')) {
                $table->dropColumn('shipping_amount_minor');
            }

            if (Schema::hasColumn('online_collected_payments', 'product_amount_minor')) {
                $table->dropColumn('product_amount_minor');
            }
        });
    }
};
