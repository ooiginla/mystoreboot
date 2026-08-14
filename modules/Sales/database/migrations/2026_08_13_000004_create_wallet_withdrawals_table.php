<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A merchant payout from their wallet to their settlement bank. amount_minor is what the
 * merchant receives; the merchant also bears the gateway transfer fee and Storeboot's
 * transfer fee, so the wallet is debited amount + gateway_fee + platform_fee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_withdrawals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->unsignedBigInteger('amount_minor');           // received by merchant
            $table->unsignedBigInteger('gateway_fee_minor')->default(0);
            $table->unsignedBigInteger('platform_fee_minor')->default(0);
            $table->unsignedBigInteger('total_debit_minor');      // amount + both fees
            $table->char('currency_code', 3);
            $table->string('status', 16)->default('pending');     // pending|processing|completed|failed|reversed
            $table->string('bank_code', 20)->nullable();
            $table->string('account_number', 20)->nullable();
            $table->string('account_name')->nullable();
            $table->string('recipient_code')->nullable();
            $table->string('transfer_code')->nullable()->index();
            $table->string('reference')->unique();
            $table->string('failure_reason')->nullable();
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_withdrawals');
    }
};
