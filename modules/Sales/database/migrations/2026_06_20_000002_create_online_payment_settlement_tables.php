<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
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

        Schema::create('online_collected_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('online_payment_settlement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 40)->default('paystack')->index();
            $table->string('payment_method', 80)->nullable();
            $table->string('provider_reference', 160);
            $table->string('gateway_reference', 160)->nullable();
            $table->string('customer_email', 160)->nullable();
            $table->string('currency', 3)->default('NGN');
            $table->unsignedBigInteger('product_amount_minor')->default(0);
            $table->unsignedBigInteger('shipping_amount_minor')->default(0);
            $table->unsignedBigInteger('gateway_charge_minor')->default(0);
            $table->unsignedBigInteger('amount_minor')->default(0);
            $table->unsignedBigInteger('fees_minor')->default(0);
            $table->unsignedBigInteger('net_amount_minor')->default(0);
            $table->string('status', 40)->default('successful')->index();
            $table->boolean('is_settled')->default(false)->index();
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'provider', 'provider_reference'], 'online_payment_provider_reference_unique');
            $table->index(['tenant_id', 'branch_id', 'is_settled'], 'online_payment_tenant_branch_settled_idx');
            $table->index(['tenant_id', 'online_payment_settlement_id'], 'online_payment_settlement_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_collected_payments');
        Schema::dropIfExists('online_payment_settlements');
    }
};
