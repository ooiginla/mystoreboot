<?php

declare(strict_types=1);

namespace Modules\Sales\Support\Payments;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Paystack implementation of the {@see PaymentGateway} contract. This is the ONLY place
 * that knows Paystack's signature scheme, event names, and payload shape.
 */
final class PaystackGateway implements PaymentGateway
{
    public function provider(): string
    {
        return 'paystack';
    }

    public function verifyWebhookSignature(Request $request, string $secretKey): bool
    {
        $signature = (string) $request->header('x-paystack-signature', '');

        if ($signature === '' || $secretKey === '') {
            return false;
        }

        // Paystack signs the raw request body with HMAC-SHA512 using the secret key.
        $expected = hash_hmac('sha512', (string) $request->getContent(), $secretKey);

        return hash_equals($expected, $signature);
    }

    public function parseWebhookEvent(array $payload): ?GatewayWebhookEvent
    {
        $data = (array) ($payload['data'] ?? []);
        $reference = (string) ($data['reference'] ?? '');

        if ($reference === '') {
            return null;
        }

        $type = ($payload['event'] ?? '') === 'charge.success'
            ? GatewayWebhookEvent::PAYMENT_SUCCEEDED
            : GatewayWebhookEvent::IGNORED;

        return new GatewayWebhookEvent(
            type: $type,
            reference: $reference,
            orderId: $this->orderIdFrom($data, $reference),
            metadata: (array) ($data['metadata'] ?? []),
        );
    }

    public function fetchTransaction(string $reference, string $secretKey): ?GatewayPayment
    {
        $response = Http::withToken($secretKey)
            ->acceptJson()
            ->get(rtrim((string) config('services.paystack.base_url'), '/').'/transaction/verify/'.rawurlencode($reference));

        $payload = $response->json();

        if (! $response->successful() || ! (bool) data_get($payload, 'status')) {
            return null;
        }

        $data = (array) data_get($payload, 'data', []);

        return new GatewayPayment(
            provider: $this->provider(),
            reference: (string) ($data['reference'] ?? $reference),
            successful: ($data['status'] ?? null) === 'success',
            amountMinor: (int) ($data['amount'] ?? 0),
            currency: (string) ($data['currency'] ?? ''),
            feesMinor: (int) ($data['fees'] ?? 0),
            gatewayReference: isset($data['id']) ? (string) $data['id'] : null,
            customerEmail: data_get($data, 'customer.email'),
            settledDirectly: filled($data['subaccount'] ?? null),
            paidAt: isset($data['paid_at']) ? (string) $data['paid_at'] : null,
            raw: is_array($payload) ? $payload : [],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function orderIdFrom(array $data, string $reference): ?int
    {
        $metaId = data_get($data, 'metadata.sales_order_id');

        if (is_numeric($metaId)) {
            return (int) $metaId;
        }

        // Our references are minted as "PSK-{orderId}-{random}" — fall back to that.
        if (preg_match('/^PSK-(\d+)-/i', $reference, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
