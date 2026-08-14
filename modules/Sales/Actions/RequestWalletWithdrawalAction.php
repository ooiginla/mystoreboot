<?php

declare(strict_types=1);

namespace Modules\Sales\Actions;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\OnlineStore;
use Modules\Sales\Models\WalletWithdrawal;
use Modules\Sales\Support\Payments\PaymentGatewayManager;
use Modules\Sales\Support\Payments\TransferResult;
use Modules\Sales\Support\PlatformFees;
use Modules\Sales\Support\Wallet\WalletService;
use Modules\Tenancy\Models\Tenant;
use RuntimeException;
use Throwable;

/**
 * Requests a wallet payout to the merchant's settlement bank. Auto-processed (no approval):
 * reserves the full cost (payout + gateway fee + Storeboot fee) against the AVAILABLE
 * balance, then initiates the gateway transfer. On any failure the reservation is refunded.
 */
final class RequestWalletWithdrawalAction
{
    public function __construct(
        private readonly WalletService $wallet,
        private readonly PlatformFees $platformFees,
        private readonly PaymentGatewayManager $gateways,
    ) {}

    public function execute(Tenant $tenant, int $receiveMinor, ?int $requestedByUserId = null): WalletWithdrawal
    {
        if ($receiveMinor <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount to withdraw.']);
        }

        $store = OnlineStore::query()->where('tenant_id', $tenant->id)->first();
        $settlement = (array) data_get($store?->payment_settings, 'settlement_bank_account', []);
        $bankCode = (string) ($settlement['bank_code'] ?? '');
        $accountNumber = (string) ($settlement['account_number'] ?? '');
        $accountName = (string) ($settlement['account_name'] ?? '') ?: ($store?->store_name ?: $tenant->name);

        if ($bankCode === '' || $accountNumber === '') {
            throw ValidationException::withMessages(['withdrawal' => 'Set up your settlement bank account before withdrawing.']);
        }

        $currency = $tenant->currency_code ?: 'NGN';
        $payout = $this->gateways->payout((string) config('services.payments.default', 'paystack'));
        $gatewayFee = $payout->transferFeeMinor($receiveMinor, $currency);
        $platformFee = $this->platformFees->transferFeeMinor($tenant->id, $receiveMinor);
        $totalDebit = $receiveMinor + $gatewayFee + $platformFee;

        $wallet = $this->wallet->walletFor($tenant);

        $withdrawal = WalletWithdrawal::query()->create([
            'tenant_id' => $tenant->id,
            'wallet_id' => $wallet->id,
            'amount_minor' => $receiveMinor,
            'gateway_fee_minor' => $gatewayFee,
            'platform_fee_minor' => $platformFee,
            'total_debit_minor' => $totalDebit,
            'currency_code' => $currency,
            'status' => WalletWithdrawal::STATUS_PENDING,
            'bank_code' => $bankCode,
            'account_number' => $accountNumber,
            'account_name' => $accountName,
            'reference' => 'WD-'.Str::upper(Str::random(12)),
            'requested_by_user_id' => $requestedByUserId,
        ]);

        // Reserve funds atomically (the cumulative cost must be available).
        try {
            $this->wallet->reserveForWithdrawal($withdrawal);
        } catch (RuntimeException) {
            $withdrawal->delete();

            throw ValidationException::withMessages([
                'amount' => 'Insufficient available balance. You need '.$this->money($totalDebit, $currency)
                    .' available (amount + fees) to withdraw '.$this->money($receiveMinor, $currency).'.',
            ]);
        }

        $secret = (string) config('services.paystack.secret_key', '');

        try {
            $recipientCode = (string) ($settlement['recipient_code'] ?? '');

            if ($recipientCode === '') {
                $recipientCode = (string) $payout->createTransferRecipient($bankCode, $accountNumber, $accountName, $currency, $secret);

                if ($recipientCode === '') {
                    throw new RuntimeException('The bank account could not be registered for transfers.');
                }

                // Cache the recipient on the settlement account for reuse.
                $paymentSettings = $store->payment_settings ?? [];
                $paymentSettings['settlement_bank_account']['recipient_code'] = $recipientCode;
                $store->forceFill(['payment_settings' => $paymentSettings])->save();
            }

            $transfer = $payout->initiateTransfer(
                $recipientCode,
                $receiveMinor,
                $withdrawal->reference,
                'Wallet withdrawal '.$withdrawal->reference,
                $secret,
            );

            if (! $transfer->ok) {
                throw new RuntimeException($transfer->message ?: 'The transfer could not be initiated.');
            }

            $withdrawal->update([
                'status' => WalletWithdrawal::STATUS_PROCESSING,
                'recipient_code' => $recipientCode,
                'transfer_code' => $transfer->transferCode,
                'meta' => array_merge($withdrawal->meta ?? [], ['initiate' => $transfer->raw]),
            ]);

            // Some transfers resolve immediately (e.g. Paystack test mode) — finalise now
            // rather than waiting for the webhook.
            if ($transfer->status === TransferResult::STATUS_SUCCESS) {
                app(ProcessTransferOutcomeAction::class)->execute(
                    $withdrawal->refresh(),
                    WalletWithdrawal::STATUS_COMPLETED,
                    $transfer->raw,
                );
            }
        } catch (Throwable $exception) {
            $this->wallet->refundWithdrawal($withdrawal);
            $withdrawal->update([
                'status' => WalletWithdrawal::STATUS_FAILED,
                'failure_reason' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'withdrawal' => 'Could not start the transfer: '.$exception->getMessage(),
            ]);
        }

        return $withdrawal->refresh();
    }

    private function money(int $minor, string $currency): string
    {
        return $currency.' '.number_format($minor / 100, 2);
    }
}
