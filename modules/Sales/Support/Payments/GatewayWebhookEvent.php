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

    public const IGNORED = 'ignored';

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $type,
        public string $reference,
        public ?int $orderId = null,
        public array $metadata = [],
    ) {}

    public function isPaymentSucceeded(): bool
    {
        return $this->type === self::PAYMENT_SUCCEEDED;
    }
}
