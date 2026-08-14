<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Modules\Business\Models\OnlineStore;
use Modules\Sales\Actions\ProcessTransferOutcomeAction;
use Modules\Sales\Actions\ReconcileWalletSettlementAction;
use Modules\Sales\Actions\RecordGatewayPaymentAction;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\WalletWithdrawal;
use Modules\Sales\Support\Payments\GatewayWebhookEvent;
use Modules\Sales\Support\Payments\PaymentGateway;
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
        ReconcileWalletSettlementAction $reconcile,
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

        // Settlement events are account-level (no single order) — reconcile the wallet.
        if ($event->isSettlementSucceeded()) {
            return $this->handleSettlement($request, $gateway, $provider, $event, $reconcile);
        }

        // Transfer (payout) events resolve a withdrawal, not an order.
        if ($event->isTransferEvent()) {
            return $this->handleTransfer($request, $gateway, $provider, $event, $payload);
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
     * Handle a settlement webhook: the gateway has paid a batch of transactions to the
     * Storeboot platform account, so the matching wallet credits become Available. Settlements
     * are account-level, so the signature is verified with the platform key.
     */
    private function handleSettlement(
        Request $request,
        PaymentGateway $gateway,
        string $provider,
        GatewayWebhookEvent $event,
        ReconcileWalletSettlementAction $reconcile,
    ): Response {
        $secret = (string) config('services.paystack.secret_key', '');

        if ($secret === '' || ! $gateway->verifyWebhookSignature($request, $secret)) {
            Log::warning('Rejected settlement webhook: signature verification failed.', [
                'provider' => $provider,
                'settlement_id' => $event->settlementId,
            ]);

            return response('Invalid signature.', 401);
        }

        $references = $gateway->fetchSettlementReferences((string) $event->settlementId, $secret);
        $flipped = $reconcile->execute($references, (string) $event->settlementId);

        Log::info('Wallet settlement reconciled.', [
            'provider' => $provider,
            'settlement_id' => $event->settlementId,
            'transactions' => count($references),
            'made_available' => $flipped,
        ]);

        return response('ok', 200);
    }

    /**
     * Handle a payout transfer webhook: mark the withdrawal completed (and book it) or, on
     * failure/reversal, refund the reserved funds. Transfers are on the platform account, so
     * the signature is verified with the platform key.
     *
     * @param  array<string, mixed>  $payload
     */
    private function handleTransfer(
        Request $request,
        PaymentGateway $gateway,
        string $provider,
        GatewayWebhookEvent $event,
        array $payload,
    ): Response {
        $secret = (string) config('services.paystack.secret_key', '');

        if ($secret === '' || ! $gateway->verifyWebhookSignature($request, $secret)) {
            Log::warning('Rejected transfer webhook: signature verification failed.', [
                'provider' => $provider,
                'transfer_code' => $event->transferCode,
                'reference' => $event->reference,
            ]);

            return response('Invalid signature.', 401);
        }

        $withdrawal = WalletWithdrawal::query()
            ->when($event->transferCode, fn ($query) => $query->where('transfer_code', $event->transferCode))
            ->when(! $event->transferCode && $event->reference !== '', fn ($query) => $query->where('reference', $event->reference))
            ->first();

        if (! $withdrawal) {
            return response('ok', 200);
        }

        $outcome = match ($event->type) {
            GatewayWebhookEvent::TRANSFER_SUCCEEDED => WalletWithdrawal::STATUS_COMPLETED,
            GatewayWebhookEvent::TRANSFER_FAILED => WalletWithdrawal::STATUS_FAILED,
            GatewayWebhookEvent::TRANSFER_REVERSED => WalletWithdrawal::STATUS_REVERSED,
            default => null,
        };

        if ($outcome === null) {
            return response('ok', 200);
        }

        app(ProcessTransferOutcomeAction::class)->execute($withdrawal, $outcome, (array) data_get($payload, 'data', []));

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
