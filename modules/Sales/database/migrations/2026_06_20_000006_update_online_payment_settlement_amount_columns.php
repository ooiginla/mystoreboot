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
        Schema::table('online_payment_settlements', function (Blueprint $table): void {
            if (! Schema::hasColumn('online_payment_settlements', 'total_product_amount_minor')) {
                $table->unsignedBigInteger('total_product_amount_minor')->default(0)->after('currency');
            }

            if (! Schema::hasColumn('online_payment_settlements', 'total_shipping_amount_minor')) {
                $table->unsignedBigInteger('total_shipping_amount_minor')->default(0)->after('total_product_amount_minor');
            }

            if (! Schema::hasColumn('online_payment_settlements', 'total_fees_minor')) {
                $table->unsignedBigInteger('total_fees_minor')->default(0)->after('total_shipping_amount_minor');
            }

            if (! Schema::hasColumn('online_payment_settlements', 'total_net_amount_minor')) {
                $table->unsignedBigInteger('total_net_amount_minor')->default(0)->after('total_fees_minor');
            }

            if (! Schema::hasColumn('online_payment_settlements', 'storeboot_charges_minor')) {
                $table->unsignedBigInteger('storeboot_charges_minor')->default(0)->after('total_net_amount_minor');
            }
        });

        DB::table('online_payment_settlements')
            ->orderBy('id')
            ->each(function (object $settlement): void {
                $totals = DB::table('online_collected_payments')
                    ->where('online_payment_settlement_id', $settlement->id)
                    ->selectRaw('COALESCE(SUM(product_amount_minor), 0) as product_total')
                    ->selectRaw('COALESCE(SUM(shipping_amount_minor), 0) as shipping_total')
                    ->selectRaw('COALESCE(SUM(fees_minor), 0) as fees_total')
                    ->selectRaw('COALESCE(SUM(net_amount_minor), 0) as net_total')
                    ->first();

                DB::table('online_payment_settlements')
                    ->where('id', $settlement->id)
                    ->update([
                        'total_product_amount_minor' => (int) ($totals->product_total ?? 0),
                        'total_shipping_amount_minor' => (int) ($totals->shipping_total ?? 0),
                        'total_fees_minor' => (int) ($totals->fees_total ?? 0),
                        'total_net_amount_minor' => (int) ($totals->net_total ?? ($settlement->total_collected_minor ?? 0)),
                        'storeboot_charges_minor' => (int) ($settlement->charges_minor ?? 0),
                    ]);
            });

        Schema::table('online_payment_settlements', function (Blueprint $table): void {
            if (Schema::hasColumn('online_payment_settlements', 'total_collected_minor')) {
                $table->dropColumn('total_collected_minor');
            }

            if (Schema::hasColumn('online_payment_settlements', 'charges_minor')) {
                $table->dropColumn('charges_minor');
            }

            if (Schema::hasColumn('online_payment_settlements', 'balance_minor')) {
                $table->dropColumn('balance_minor');
            }
        });
    }

    public function down(): void
    {
        Schema::table('online_payment_settlements', function (Blueprint $table): void {
            if (! Schema::hasColumn('online_payment_settlements', 'total_collected_minor')) {
                $table->unsignedBigInteger('total_collected_minor')->default(0)->after('currency');
            }

            if (! Schema::hasColumn('online_payment_settlements', 'charges_minor')) {
                $table->unsignedBigInteger('charges_minor')->default(0)->after('total_collected_minor');
            }

            if (! Schema::hasColumn('online_payment_settlements', 'balance_minor')) {
                $table->bigInteger('balance_minor')->default(0)->after('total_settled_minor');
            }
        });

        DB::table('online_payment_settlements')
            ->orderBy('id')
            ->each(function (object $settlement): void {
                DB::table('online_payment_settlements')
                    ->where('id', $settlement->id)
                    ->update([
                        'total_collected_minor' => (int) ($settlement->total_net_amount_minor ?? 0),
                        'charges_minor' => (int) ($settlement->storeboot_charges_minor ?? 0),
                        'balance_minor' => 0,
                    ]);
            });

        Schema::table('online_payment_settlements', function (Blueprint $table): void {
            foreach (['storeboot_charges_minor', 'total_net_amount_minor', 'total_fees_minor', 'total_shipping_amount_minor', 'total_product_amount_minor'] as $column) {
                if (Schema::hasColumn('online_payment_settlements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
