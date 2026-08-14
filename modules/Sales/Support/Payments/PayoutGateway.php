<?php

declare(strict_types=1);

namespace Modules\Sales\Support\Payments;

/**
 * The "send money out" capability — creating transfer recipients and initiating payouts to a
 * merchant's bank. Kept separate from {@see PaymentGateway} (taking money in) so a provider
 * can implement either or both. Swapping payout providers stays a one-class change.
 */
interface PayoutGateway
{
    public function provider(): string;

    /** The provider's own transfer fee for this amount, in minor units (the merchant bears it). */
    public function transferFeeMinor(int $amountMinor, string $currency): int;

    /**
     * Create (or look up) a transfer recipient for a bank account and return its code, or null
     * on failure.
     */
    public function createTransferRecipient(
        string $bankCode,
        string $accountNumber,
        string $accountName,
        string $currency,
        string $secretKey,
    ): ?string;

    /**
     * Initiate a payout to a recipient. The result's status is typically "pending" (final
     * outcome arrives by webhook); some providers/modes resolve to "success" immediately.
     */
    public function initiateTransfer(
        string $recipientCode,
        int $amountMinor,
        string $reference,
        string $reason,
        string $secretKey,
    ): TransferResult;
}
