<?php

declare(strict_types=1);

namespace Modules\Sales\Support\Payments;

use Modules\Sales\Models\SalesOrder;

/**
 * A provider-neutral, server-verified payment. Every gateway (Paystack today, another
 * tomorrow) normalises its "verify transaction" response into this shape, so the rest of
 * the settlement code never touches provider-specific payloads.
 */
final readonly class GatewayPayment
{
    /**
     * @param  array<string, mixed>  $raw  The untouched provider payload (stored for audit).
     */
    public function __construct(
        public string $provider,
        public string $reference,
        public bool $successful,
        public int $amountMinor,
        public string $currency,
        public int $feesMinor = 0,
        public ?string $gatewayReference = null,
        public ?string $customerEmail = null,
        public bool $settledDirectly = false,
        public ?string $paidAt = null,
        public array $raw = [],
    ) {}

    /**
     * Is this a genuine, successful payment that covers the order in the expected currency?
     */
    public function coversOrder(SalesOrder $order, string $expectedCurrency): bool
    {
        return $this->successful
            && $this->amountMinor >= (int) $order->total_minor
            && hash_equals(strtoupper($expectedCurrency), strtoupper($this->currency));
    }
}
