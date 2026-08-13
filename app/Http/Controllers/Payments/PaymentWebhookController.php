<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Modules\Business\Models\OnlineStore;
use Modules\Sales\Actions\RecordGatewayPaymentAction;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Support\Payments\PaymentGatewayManager;

/**
 * Server-to-server payment webhook — a single fixed URL configured in the provider's
 * dashboard. Unlike the browser callback, it fires even if the customer never returns,
 * so it is the reliable source of payment confirmation.
 *
 * Provider-neutral by design: the URL carries the provider name and everything
 * provider-specific lives behind {@see PaymentGatewayManager}.
 */
final class PaymentWebhookController extends Controller
{
    public function handle(
        Request $request,
        PaymentGatewayManager $gateways,
        RecordGatewayPaymentAction $record,
        string $provider = 'paystack',
    ): Response {
        try {
            $gateway = $gateways->for($provider);
        } catch (InvalidArgumentException) {
            return response('Unsupported payment provider.', 404);
        }

        $payload = json_decode((string) $request->getContent(), true);

        if (! is_array($payload)) {
            return response('Invalid payload.', 400);
        }

        $event = $gateway->parseWebhookEvent($payload);

        // Nothing actionable in this payload — acknowledge so the provider stops retrying.
        if (! $event) {
            return response('ok', 200);
        }

        $order = $event->orderId
            ? SalesOrder::query()->with('customer')->find($event->orderId)
            : null;

        // Unknown / non-online order: acknowledge and do nothing (never act on it).
        if (! $order || $order->source !== 'online') {
            return response('ok', 200);
        }

        $store = OnlineStore::query()->where('tenant_id', $order->tenant_id)->first();

        if (! $store) {
            return response('ok', 200);
        }

        // The secret is chosen from the order's OWN (DB-trusted) payment method, so a forged
        // webhook can never get itself verified against the wrong account's key.
        $secret = $this->secretFor($store, (string) $order->payment_method);

        if ($secret === '' || ! $gateway->verifyWebhookSignature($request, $secret)) {
            Log::warning('Rejected payment webhook: signature verification failed.', [
                'provider' => $provider,
                'order_id' => $order->id,
                'reference' => $event->reference,
            ]);

            return response('Invalid signature.', 401);
        }

        if (! $event->isPaymentSucceeded()) {
            return response('ok', 200);
        }

        // Re-verify against the provider's API — never trust the webhook body for amounts.
        $payment = $gateway->fetchTransaction($event->reference, $secret);
        $currency = strtoupper($store->tenant?->currency_code ?? 'NGN');

        if (! $payment || ! $payment->coversOrder($order, $currency)) {
            Log::warning('Payment webhook could not be verified against the order.', [
                'provider' => $provider,
                'order_id' => $order->id,
                'reference' => $event->reference,
            ]);

            return response('ok', 200);
        }

        $record->execute($order, $payment);

        return response('ok', 200);
    }

    /**
     * The secret key for verifying this order's gateway signature: the store's own key for
     * self-hosted Paystack, otherwise the Storeboot platform key.
     */
    private function secretFor(OnlineStore $store, string $paymentMethod): string
    {
        if ($paymentMethod === 'self_hosted_paystack') {
            return (string) data_get($store->payment_settings, 'paystack.private_key', '');
        }

        return (string) config('services.paystack.secret_key', '');
    }
}
