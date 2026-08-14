<?php

declare(strict_types=1);

namespace Modules\Sales\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Business\Models\BusinessPaymentAccount;
use Modules\Finance\Actions\PostJournalEntryAction;
use Modules\Sales\Models\WalletWithdrawal;
use Modules\Sales\Support\PlatformFees;
use Modules\Sales\Support\Wallet\WalletService;

/**
 * Applies the final outcome of a payout transfer (from the transfer webhook, or inline when a
 * transfer resolves immediately). On success it books the withdrawal to the ledger; on
 * failure/reversal it refunds the reserved funds to the wallet. Idempotent on terminal states.
 */
final class ProcessTransferOutcomeAction
{
    public function __construct(private readonly WalletService $wallet) {}

    /**
     * @param  self::* $outcome  One of WalletWithdrawal::STATUS_COMPLETED|FAILED|REVERSED
     * @param  array<string, mixed>  $raw
     */
    public function execute(WalletWithdrawal $withdrawal, string $outcome, array $raw = []): void
    {
        if ($this->isTerminal($withdrawal->status)) {
            return;
        }

        if ($outcome === WalletWithdrawal::STATUS_COMPLETED) {
            DB::transaction(function () use ($withdrawal, $raw): void {
                $locked = WalletWithdrawal::query()->lockForUpdate()->find($withdrawal->id);

                if (! $locked || $this->isTerminal($locked->status)) {
                    return;
                }

                $locked->update([
                    'status' => WalletWithdrawal::STATUS_COMPLETED,
                    'processed_at' => now(),
                    'meta' => array_merge($locked->meta ?? [], ['outcome' => $raw]),
                ]);

                $this->postJournal($locked);
                $this->postPlatformFeeIncome($locked);
            });

            return;
        }

        // failed | reversed → return the money to the wallet.
        $this->wallet->refundWithdrawal($withdrawal);
        $withdrawal->update([
            'status' => $outcome,
            'failure_reason' => (string) (data_get($raw, 'message') ?? 'Transfer '.$outcome),
            'meta' => array_merge($withdrawal->meta ?? [], ['outcome' => $raw]),
        ]);
    }

    /**
     * Book the payout in the tenant's ledger: the funds leave Online Payment Clearing (1060)
     * — the merchant's share lands in their settlement bank account, and the fees (gateway +
     * Storeboot) are expensed to Bank, POS and Gateway Charges (EXP-6350).
     */
    private function postJournal(WalletWithdrawal $withdrawal): void
    {
        $bankAccountCode = $this->settlementBankAccountCode($withdrawal) ?? '1040';
        $feesMinor = (int) $withdrawal->gateway_fee_minor + (int) $withdrawal->platform_fee_minor;

        app(PostJournalEntryAction::class)->execute(
            $withdrawal->tenant_id,
            now()->toDateString(),
            'Wallet withdrawal '.$withdrawal->reference,
            [
                ['account_code' => '1060', 'credit_minor' => (int) $withdrawal->total_debit_minor],
                ['account_code' => $bankAccountCode, 'debit_minor' => (int) $withdrawal->amount_minor],
                ['account_code' => 'EXP-6350', 'debit_minor' => $feesMinor],
            ],
            'wallet_withdrawal',
            $withdrawal->id,
            'withdrawal_paid',
        );
    }

    /**
     * Recognise Storeboot's transfer fee as income in the platform tenant's own books (if a
     * platform tenant is designated and it isn't the merchant itself). The fee is retained in
     * the platform's clearing balance, so debit 1060 and credit Platform Fee Income (4140).
     */
    private function postPlatformFeeIncome(WalletWithdrawal $withdrawal): void
    {
        $fee = (int) $withdrawal->platform_fee_minor;

        if ($fee <= 0) {
            return;
        }

        $platformTenantId = app(PlatformFees::class)->platformTenantId();

        if (! $platformTenantId || $platformTenantId === $withdrawal->tenant_id) {
            return;
        }

        app(PostJournalEntryAction::class)->execute(
            $platformTenantId,
            now()->toDateString(),
            'Transfer fee income — '.$withdrawal->reference,
            [
                ['account_code' => '1060', 'debit_minor' => $fee],
                ['account_code' => '4140', 'credit_minor' => $fee],
            ],
            'wallet_withdrawal',
            $withdrawal->id,
            'platform_fee_income',
        );
    }

    private function settlementBankAccountCode(WalletWithdrawal $withdrawal): ?string
    {
        $account = BusinessPaymentAccount::query()
            ->where('tenant_id', $withdrawal->tenant_id)
            ->where('bank_code', $withdrawal->bank_code)
            ->where('account_number', $withdrawal->account_number)
            ->with('financeAccount')
            ->first();

        return $account?->financeAccount?->code;
    }

    private function isTerminal(string $status): bool
    {
        return in_array($status, [
            WalletWithdrawal::STATUS_COMPLETED,
            WalletWithdrawal::STATUS_FAILED,
            WalletWithdrawal::STATUS_REVERSED,
        ], true);
    }
}
