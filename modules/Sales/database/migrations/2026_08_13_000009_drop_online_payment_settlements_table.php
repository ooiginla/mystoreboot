<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the legacy manual-settlement table and its link on online_collected_payments.
 * Storeboot now settles directly (subaccount split) or via the wallet, so batch settlement
 * records are no longer produced. Destructive by design — there is no data to preserve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('online_collected_payments', function (Blueprint $table): void {
            $table->dropIndex('online_payment_settlement_idx');
            $table->dropConstrainedForeignId('online_payment_settlement_id');
        });

        Schema::dropIfExists('online_payment_settlements');
    }

    public function down(): void
    {
        Schema::create('online_payment_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40)->default('paystack')->index();
            $table->string('reference', 120);
            $table->string('status', 40)->default('settled')->index();
            $table->string('currency', 3)->default('NGN');
            $table->unsignedBigInteger('total_product_amount_minor')->default(0);
            $table->unsignedBigInteger('total_shipping_amount_minor')->default(0);
            $table->unsignedBigInteger('total_gateway_charge_minor')->default(0);
            $table->unsignedBigInteger('total_fees_minor')->default(0);
            $table->unsignedBigInteger('total_net_amount_minor')->default(0);
            $table->unsignedBigInteger('storeboot_charges_minor')->default(0);
            $table->unsignedBigInteger('total_settled_minor')->default(0);
            $table->unsignedInteger('payment_count')->default(0);
            $table->date('settlement_date')->nullable()->index();
            $table->timestamp('settled_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'reference']);
            $table->index(['tenant_id', 'settlement_date'], 'online_settlement_tenant_date_idx');
        });

        Schema::table('online_collected_payments', function (Blueprint $table): void {
            $table->foreignId('online_payment_settlement_id')->nullable()->after('sales_order_payment_id')->constrained()->nullOnDelete();
            $table->index(['tenant_id', 'online_payment_settlement_id'], 'online_payment_settlement_idx');
        });
    }
};
