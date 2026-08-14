<?php

declare(strict_types=1);

namespace Modules\Sales\Support\Payments;

/**
 * A provider-neutral webhook event. Each gateway maps its own event names
 * (Paystack "charge.success", another provider's equivalent) onto these constants.
 */
final readonly class GatewayWebhookEvent
{
    public const PAYMENT_SUCCEEDED = 'payment.succeeded';

    public const SETTLEMENT_SUCCEEDED = 'settlement.succeeded';

    public const TRANSFER_SUCCEEDED = 'transfer.succeeded';

    public const TRANSFER_FAILED = 'transfer.failed';

    public const TRANSFER_REVERSED = 'transfer.reversed';

    public const IGNORED = 'ignored';

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $type,
        public string $reference = '',
        public ?int $orderId = null,
        public ?string $settlementId = null,
        public ?string $transferCode = null,
        public array $metadata = [],
    ) {}

    public function isPaymentSucceeded(): bool
    {
        return $this->type === self::PAYMENT_SUCCEEDED;
    }

    public function isSettlementSucceeded(): bool
    {
        return $this->type === self::SETTLEMENT_SUCCEEDED;
    }

    public function isTransferEvent(): bool
    {
        return in_array($this->type, [
            self::TRANSFER_SUCCEEDED,
            self::TRANSFER_FAILED,
            self::TRANSFER_REVERSED,
        ], true);
    }
}
