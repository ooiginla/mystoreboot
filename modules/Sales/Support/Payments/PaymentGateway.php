<?php

declare(strict_types=1);

namespace Modules\Sales\Support\Payments;

use Illuminate\Http\Request;

/**
 * The contract every payment provider implements. Keep provider-specific details
 * (signature schemes, event names, verify endpoints, payload shapes) behind this
 * interface so the gateway can be swapped without touching checkout or settlement.
 */
interface PaymentGateway
{
    /** Machine name of the provider, e.g. "paystack". Stored on records for auditing. */
    public function provider(): string;

    /**
     * Verify that a webhook request genuinely came from the provider, using the
     * account's secret. Reads the raw request body and the provider's own signature header.
     */
    public function verifyWebhookSignature(Request $request, string $secretKey): bool;

    /**
     * Normalise a decoded webhook payload into a provider-neutral event, or null when the
     * payload is unusable (no reference to act on).
     *
     * @param  array<string, mixed>  $payload
     */
    public function parseWebhookEvent(array $payload): ?GatewayWebhookEvent;

    /**
     * Server-to-server re-verification of a transaction by reference — the source of truth
     * for amount/status, never the (spoofable) webhook body. Null when it cannot be verified.
     */
    public function fetchTransaction(string $reference, string $secretKey): ?GatewayPayment;
}
