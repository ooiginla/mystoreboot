<?php

declare(strict_types=1);

namespace Modules\Sales\Support\Wallet;

use Illuminate\Support\Facades\DB;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\Wallet;
use Modules\Sales\Models\WalletTransaction;
use Modules\Sales\Models\WalletWithdrawal;
use Modules\Tenancy\Models\Tenant;
use RuntimeException;

/**
 * Operational ledger for custodial payout modes. Every balance change is an idempotent,
 * row-locked movement that keeps the cached wallet balances in step with the ledger.
 *
 * Provider-neutral by design — it deals only in orders and amounts, never gateway payloads.
 */
final class WalletService
{
    public function walletFor(Tenant $tenant): Wallet
    {
        return Wallet::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'currency_code' => $tenant->currency_code ?: 'NGN'],
            ['available_balance_minor' => 0, 'pending_balance_minor' => 0],
        );
    }

    /**
     * Credit the merchant's share of a paid online order as a PENDING balance (the funds are
     * with the gateway, not yet settled to Storeboot). Idempotent per order.
     */
    public function creditPendingFromSale(SalesOrder $order, int $amountMinor): ?WalletTransaction
    {
        if ($amountMinor <= 0) {
            return null;
        }

        $tenant = $order->tenant;

        if (! $tenant instanceof Tenant) {
            return null;
        }

        $wallet = $this->walletFor($tenant);

        return DB::transaction(function () use ($wallet, $order, $amountMinor): ?WalletTransaction {
            $locked = Wallet::query()->lockForUpdate()->findOrFail($wallet->id);

            $created = false;
            $transaction = WalletTransaction::query()->firstOrCreate(
                [
                    'tenant_id' => $locked->tenant_id,
                    'source_type' => 'sales_order',
                    'source_id' => $order->id,
                    'category' => 'online_sale',
                ],
                [
                    'wallet_id' => $locked->id,
                    'direction' => WalletTransaction::DIRECTION_CREDIT,
                    'state' => WalletTransaction::STATE_PENDING,
                    'amount_minor' => $amountMinor,
                    'currency_code' => $locked->currency_code,
                    'reference' => $order->order_number,
                    'description' => 'Online sale '.$order->order_number,
                ],
            );

            // firstOrCreate returns the existing row on a repeat call; only move the balance
            // the first time so double-firing (callback + webhook) never double-credits.
            if ($transaction->wasRecentlyCreated) {
                $locked->increment('pending_balance_minor', $amountMinor);
                $created = true;
            }

            return $created ? $transaction : null;
        });
    }

    /**
     * Flip an order's PENDING online-sale credit to AVAILABLE once the gateway has settled the
     * funds to Storeboot. Idempotent — a credit already available (or absent) is left untouched.
     */
    public function markSaleAvailable(int $orderId, ?string $settlementReference = null): ?WalletTransaction
    {
        return DB::transaction(function () use ($orderId, $settlementReference): ?WalletTransaction {
            $transaction = WalletTransaction::query()
                ->where('source_type', 'sales_order')
                ->where('source_id', $orderId)
                ->where('category', 'online_sale')
                ->lockForUpdate()
                ->first();

            if (! $transaction || $transaction->state !== WalletTransaction::STATE_PENDING) {
                return null;
            }

            $wallet = Wallet::query()->lockForUpdate()->findOrFail($transaction->wallet_id);

            $wallet->decrement('pending_balance_minor', $transaction->amount_minor);
            $wallet->increment('available_balance_minor', $transaction->amount_minor);

            $transaction->forceFill([
                'state' => WalletTransaction::STATE_AVAILABLE,
                'available_at' => now(),
                'meta' => array_merge($transaction->meta ?? [], array_filter([
                    'settlement_reference' => $settlementReference,
                ])),
            ])->save();

            return $transaction;
        });
    }

    /**
     * Reserve a withdrawal's full cost against the AVAILABLE balance, atomically. Throws when
     * the balance can't cover it. Records the debit in the ledger.
     */
    public function reserveForWithdrawal(WalletWithdrawal $withdrawal): WalletTransaction
    {
        return DB::transaction(function () use ($withdrawal): WalletTransaction {
            $wallet = Wallet::query()->lockForUpdate()->findOrFail($withdrawal->wallet_id);

            if ($wallet->available_balance_minor < $withdrawal->total_debit_minor) {
                throw new RuntimeException('Insufficient available wallet balance for this withdrawal.');
            }

            $wallet->decrement('available_balance_minor', $withdrawal->total_debit_minor);

            return WalletTransaction::query()->create([
                'tenant_id' => $wallet->tenant_id,
                'wallet_id' => $wallet->id,
                'direction' => WalletTransaction::DIRECTION_DEBIT,
                'state' => WalletTransaction::STATE_WITHDRAWN,
                'category' => 'withdrawal',
                'amount_minor' => $withdrawal->total_debit_minor,
                'currency_code' => $wallet->currency_code,
                'source_type' => 'wallet_withdrawal',
                'source_id' => $withdrawal->id,
                'reference' => $withdrawal->reference,
                'description' => 'Withdrawal '.$withdrawal->reference,
            ]);
        });
    }

    /**
     * Return a reserved withdrawal's funds to AVAILABLE (failed or reversed transfer).
     * Idempotent — a withdrawal already reversed leaves balances untouched.
     */
    public function refundWithdrawal(WalletWithdrawal $withdrawal): void
    {
        DB::transaction(function () use ($withdrawal): void {
            $debit = WalletTransaction::query()
                ->where('source_type', 'wallet_withdrawal')
                ->where('source_id', $withdrawal->id)
                ->where('category', 'withdrawal')
                ->lockForUpdate()
                ->first();

            // Only refund a live debit once.
            if (! $debit || $debit->state === WalletTransaction::STATE_REVERSED) {
                return;
            }

            $wallet = Wallet::query()->lockForUpdate()->findOrFail($debit->wallet_id);
            $wallet->increment('available_balance_minor', $debit->amount_minor);

            $debit->forceFill(['state' => WalletTransaction::STATE_REVERSED])->save();

            WalletTransaction::query()->create([
                'tenant_id' => $wallet->tenant_id,
                'wallet_id' => $wallet->id,
                'direction' => WalletTransaction::DIRECTION_CREDIT,
                'state' => WalletTransaction::STATE_AVAILABLE,
                'category' => 'reversal',
                'amount_minor' => $debit->amount_minor,
                'currency_code' => $wallet->currency_code,
                'source_type' => 'wallet_withdrawal',
                'source_id' => $withdrawal->id,
                'reference' => $withdrawal->reference,
                'description' => 'Reversed withdrawal '.$withdrawal->reference,
            ]);
        });
    }
}
