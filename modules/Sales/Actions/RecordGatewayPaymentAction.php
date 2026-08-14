<?php

declare(strict_types=1);

namespace Modules\Sales\Actions;

use App\Mail\OnlineOrderConfirmationMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Business\Models\OnlineStore;
use Modules\Finance\Actions\PostJournalEntryAction;
use Modules\Sales\Enums\SalesPaymentStatus;
use Modules\Sales\Models\OnlineCollectedPayment;
use Modules\Sales\Enums\PayoutMode;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderPayment;
use Modules\Sales\Support\Payments\GatewayPayment;
use Modules\Sales\Support\PlatformFees;
use Modules\Sales\Support\Wallet\WalletService;
use Throwable;

/**
 * Records a verified online gateway payment against an order and books the accounting.
 * Provider-neutral: it consumes a normalised {@see GatewayPayment}, so it serves every
 * settlement path — the browser redirect verify AND the server-to-server webhook.
 *
 * Fully idempotent: the payment row is keyed by reference, the collected-payment record by
 * (tenant, provider, reference), and the journal entry by (source, event) — so callback +
 * webhook + client verify all firing is safe. The confirmation email is sent only on the
 * transition into Paid, so the customer is emailed exactly once.
 */
final class RecordGatewayPaymentAction
{
    /**
     * @return bool  Whether this call transitioned the order into a fully-paid state.
     */
    public function execute(SalesOrder $order, GatewayPayment $payment): bool
    {
        $wasPaid = $order->payment_status === SalesPaymentStatus::Paid;

        DB::transaction(function () use ($order, $payment): void {
            $lockedOrder = SalesOrder::query()->lockForUpdate()->findOrFail($order->id);
            $reference = $payment->reference;
            $amountMinor = $payment->amountMinor;

            $orderPayment = SalesOrderPayment::query()
                ->where('sales_order_id', $lockedOrder->id)
                ->where('reference_number', $reference)
                ->first();

            if (! $orderPayment) {
                $orderPayment = $lockedOrder->payments()->create([
                    'tenant_id' => $lockedOrder->tenant_id,
                    'payment_date' => now()->toDateString(),
                    'payment_method' => $lockedOrder->payment_method ?? $payment->provider,
                    'amount_minor' => min($amountMinor, $lockedOrder->total_minor),
                    'reference_number' => $reference,
                    'notes' => 'Verified '.ucfirst($payment->provider).' payment.',
                ]);
            }

            $paidMinor = (int) $lockedOrder->payments()->sum('amount_minor');
            $feesMinor = $payment->feesMinor;
            $paidAmountMinor = min($amountMinor, $lockedOrder->total_minor);
            $shippingAmountMinor = (int) $lockedOrder->shipping_minor;
            $gatewayChargeMinor = (int) ($lockedOrder->gateway_charge_minor ?? 0);
            $productAmountMinor = max(0, $paidAmountMinor - $shippingAmountMinor - $gatewayChargeMinor);
            $customerTotalMinor = max(0,
                (int) $lockedOrder->subtotal_minor
                + (int) $lockedOrder->tax_minor
                + $shippingAmountMinor
                - (int) $lockedOrder->coupon_discount_minor
                - (int) $lockedOrder->admin_discount_minor
            );
            $netAmountMinor = max(0, $paidAmountMinor - $feesMinor);
            // Split transactions are settled by the gateway straight to the merchant's bank,
            // so the merchant portion needs no Storeboot settlement run.
            $settledDirectly = $payment->settledDirectly;

            OnlineCollectedPayment::query()->updateOrCreate([
                'tenant_id' => $lockedOrder->tenant_id,
                'provider' => $payment->provider,
                'provider_reference' => $reference,
            ], [
                'branch_id' => $lockedOrder->branch_id,
                'sales_order_id' => $lockedOrder->id,
                'sales_order_payment_id' => $orderPayment?->id,
                'payment_method' => $lockedOrder->payment_method,
                'gateway_reference' => $payment->gatewayReference,
                'customer_email' => $payment->customerEmail ?: $lockedOrder->customer?->email,
                'currency' => $payment->currency ?: 'NGN',
                'product_amount_minor' => $productAmountMinor,
                'shipping_amount_minor' => $shippingAmountMinor,
                'gateway_charge_minor' => $gatewayChargeMinor,
                'customer_total_minor' => $customerTotalMinor,
                'amount_minor' => $paidAmountMinor,
                'fees_minor' => $feesMinor,
                'net_amount_minor' => $netAmountMinor,
                'storeboot_profit_minor' => $netAmountMinor - $customerTotalMinor,
                'status' => 'successful',
                'is_settled' => $settledDirectly,
                'collected_at' => $payment->paidAt ?: now(),
                'verified_at' => now(),
                'raw_payload' => $payment->raw,
            ]);

            $lockedOrder->update([
                'paid_minor' => min($paidMinor, $lockedOrder->total_minor),
                'payment_status' => $paidMinor >= $lockedOrder->total_minor
                    ? SalesPaymentStatus::Paid->value
                    : SalesPaymentStatus::PartiallyPaid->value,
                // Paid orders no longer auto-cancel; the reservation is held until
                // the order is completed (stock deducted) or later cancelled.
                'reserved_until' => null,
            ]);

            // Recognise the gateway receipt now: debit Online Payment Clearing and
            // credit Customer Deposits (unearned revenue) until the order completes.
            app(PostJournalEntryAction::class)->execute(
                $lockedOrder->tenant_id,
                now()->toDateString(),
                'Online deposit received for '.$lockedOrder->order_number,
                [
                    ['account_code' => '1060', 'branch_id' => $lockedOrder->branch_id, 'debit_minor' => (int) $orderPayment->amount_minor, 'party_type' => 'customer', 'party_id' => $lockedOrder->customer_id],
                    ['account_code' => '2310', 'branch_id' => $lockedOrder->branch_id, 'credit_minor' => (int) $orderPayment->amount_minor, 'party_type' => 'customer', 'party_id' => $lockedOrder->customer_id],
                ],
                'sales_order_payment',
                $orderPayment->id,
                'deposit_received',
            );
        });

        $newlyPaid = ! $wasPaid && $order->refresh()->payment_status === SalesPaymentStatus::Paid;

        if ($newlyPaid) {
            $this->sendConfirmation($order);
            $this->creditWalletIfCustodial($order);
            $this->recognisePlatformSaleIncome($order, $payment);
        }

        return $newlyPaid;
    }

    /**
     * Recognise Storeboot's cut of the sale (the gateway-charge markup, net of the gateway's
     * real processing fee) as income in the platform tenant's own books — in every payout
     * mode, so the platform GL captures what Storeboot actually earns. Posted at payment time
     * on an accrual basis; idempotent per order.
     */
    private function recognisePlatformSaleIncome(SalesOrder $order, GatewayPayment $payment): void
    {
        $platformTenantId = app(PlatformFees::class)->platformTenantId();

        if (! $platformTenantId || $platformTenantId === $order->tenant_id) {
            return;
        }

        $collected = OnlineCollectedPayment::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('provider', $payment->provider)
            ->where('provider_reference', $payment->reference)
            ->first();

        if (! $collected) {
            return;
        }

        $grossMinor = (int) $collected->gateway_charge_minor;   // what Storeboot charged (e.g. 3.5% + ₦100)
        $feesMinor = (int) $collected->fees_minor;              // what the gateway actually took (e.g. 1.5% + ₦100)

        if ($grossMinor <= 0 && $feesMinor <= 0) {
            return;
        }

        // Revenue = gross gateway charge; cost = the gateway's processing fee; the remainder
        // (storeboot_profit) is retained cash.
        $netMinor = $grossMinor - $feesMinor;
        $lines = [
            ['account_code' => '4130', 'credit_minor' => $grossMinor],
            ['account_code' => 'EXP-6350', 'debit_minor' => $feesMinor],
        ];
        $lines[] = $netMinor >= 0
            ? ['account_code' => '1060', 'debit_minor' => $netMinor]
            : ['account_code' => '1060', 'credit_minor' => -$netMinor];

        app(PostJournalEntryAction::class)->execute(
            $platformTenantId,
            now()->toDateString(),
            'Gateway charge income — '.$order->order_number,
            $lines,
            'sales_order',
            $order->id,
            'platform_sale_income',
        );
    }

    /**
     * In a custodial payout mode (WalletOnSettlement / WalletInstant), Storeboot collects the
     * full payment and owes the merchant their share. Credit that share to the wallet as a
     * PENDING balance; it becomes withdrawable once the gateway settles the funds to Storeboot.
     */
    private function creditWalletIfCustodial(SalesOrder $order): void
    {
        $tenant = $order->tenant;

        if (! $tenant || ! PayoutMode::fromTenant($tenant)->isCustodial()) {
            return;
        }

        // The merchant's net = the customer total (Storeboot keeps the gateway charge).
        $merchantShareMinor = max(0,
            (int) $order->subtotal_minor
            + (int) $order->tax_minor
            + (int) $order->shipping_minor
            - (int) $order->coupon_discount_minor
            - (int) $order->admin_discount_minor
        );

        app(WalletService::class)->creditPendingFromSale($order, $merchantShareMinor);
    }

    /**
     * Send the order confirmation once payment is verified. A missing customer email or a
     * transport failure is swallowed so it never blocks the settlement.
     */
    private function sendConfirmation(SalesOrder $order): void
    {
        $order->loadMissing(['customer', 'items', 'branch']);

        if (! filled($order->customer?->email)) {
            return;
        }

        $store = OnlineStore::query()->where('tenant_id', $order->tenant_id)->first();

        if (! $store) {
            return;
        }

        try {
            Mail::to($order->customer->email)->send(new OnlineOrderConfirmationMail($store, $order));
        } catch (Throwable $exception) {
            Log::warning('Online order confirmation email could not be sent.', [
                'sales_order_id' => $order->id,
                'order_number' => $order->order_number,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
