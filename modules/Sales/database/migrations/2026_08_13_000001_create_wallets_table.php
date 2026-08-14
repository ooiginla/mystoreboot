<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A per-tenant wallet for custodial payout modes (WalletOnSettlement / WalletInstant):
 * online earnings collect here and are withdrawn to the merchant's bank. Balances are
 * cached here and kept in step with the wallet_transactions ledger under row locks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->char('currency_code', 3);
            $table->unsignedBigInteger('available_balance_minor')->default(0);
            $table->unsignedBigInteger('pending_balance_minor')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'currency_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
