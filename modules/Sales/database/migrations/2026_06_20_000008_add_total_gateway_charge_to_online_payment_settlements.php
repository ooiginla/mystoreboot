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
            if (! Schema::hasColumn('online_payment_settlements', 'total_gateway_charge_minor')) {
                $table->unsignedBigInteger('total_gateway_charge_minor')->default(0)->after('total_shipping_amount_minor');
            }
        });

        DB::table('online_payment_settlements')
            ->orderBy('id')
            ->each(function (object $settlement): void {
                $totalGatewayChargeMinor = DB::table('online_collected_payments')
                    ->where('online_payment_settlement_id', $settlement->id)
                    ->sum('gateway_charge_minor');

                DB::table('online_payment_settlements')
                    ->where('id', $settlement->id)
                    ->update([
                        'total_gateway_charge_minor' => (int) $totalGatewayChargeMinor,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('online_payment_settlements', function (Blueprint $table): void {
            if (Schema::hasColumn('online_payment_settlements', 'total_gateway_charge_minor')) {
                $table->dropColumn('total_gateway_charge_minor');
            }
        });
    }
};
