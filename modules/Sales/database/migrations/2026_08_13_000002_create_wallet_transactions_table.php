<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The wallet ledger. Every movement is an immutable row; the wallet's cached balances are
 * derived from these. A credit from an online sale starts in the "pending" state and flips
 * to "available" once the gateway settles the funds to Storeboot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->string('direction', 12);              // credit | debit
            $table->string('state', 16);                  // pending | available | withdrawn | reversed
            $table->string('category', 40);               // online_sale | settlement | withdrawal | transfer_fee | platform_fee | reversal | adjustment
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency_code', 3);
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reference')->nullable()->index();
            $table->string('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamps();

            // Idempotency: at most one ledger row per (tenant, source, category). MySQL treats
            // multiple NULL source rows as distinct, so manual/adjustment rows never collide.
            $table->unique(['tenant_id', 'source_type', 'source_id', 'category'], 'wallet_txn_source_unique');
            $table->index(['wallet_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
