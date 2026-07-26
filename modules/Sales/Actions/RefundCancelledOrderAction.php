<?php

declare(strict_types=1);

namespace Modules\Sales\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Actions\PostJournalEntryAction;
use Modules\Sales\Enums\SalesOrderStatus;
use Modules\Sales\Enums\SalesPaymentStatus;
use Modules\Sales\Models\SalesOrder;

/**
 * Refunds a cancelled order: books the refund journal, updates the order and
 * decrements the originating cash location. Shared by the controller (direct) and
 * the approval executor (deferred), so the accounting is identical either way.
 */
final class RefundCancelledOrderAction
{
    public function __construct(private readonly PostJournalEntryAction $postJournalEntry) {}

    /**
     * @return int the refunded amount in minor units
     */
    public function execute(SalesOrder $order): int
    {
        return DB::transaction(function () use ($order): int {
            $lockedOrder = SalesOrder::query()
                ->with(['payments.tillSession.cashLocation.financeAccount'])
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($lockedOrder->order_status !== SalesOrderStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'order' => 'Only cancelled orders can be marked as refunded.',
                ]);
            }

            $refundMinor = max(0, (int) $lockedOrder->paid_minor - (int) $lockedOrder->refunded_minor);

            if ($refundMinor <= 0) {
                throw ValidationException::withMessages([
                    'order' => 'There is no customer credit left to refund for this order.',
                ]);
            }

            $refundAccountCode = $this->refundAccountCodeFor($lockedOrder);
            $lockedOrder->update([
                'refunded_minor' => (int) $lockedOrder->paid_minor,
                'payment_status' => SalesPaymentStatus::Refunded->value,
            ]);

            $this->postJournalEntry->execute(
                $lockedOrder->tenant_id,
                now()->toDateString(),
                'Refund for cancelled order '.$lockedOrder->order_number,
                [
                    ['account_code' => '2300', 'branch_id' => $lockedOrder->branch_id, 'debit_minor' => $refundMinor, 'party_type' => 'customer', 'party_id' => $lockedOrder->customer_id],
                    ['account_code' => $refundAccountCode, 'branch_id' => $lockedOrder->branch_id, 'credit_minor' => $refundMinor, 'party_type' => 'customer', 'party_id' => $lockedOrder->customer_id],
                ],
                'sales_order',
                $lockedOrder->id,
                'refunded_cancelled_order',
            );

            $cashPayment = $lockedOrder->payments->first(fn ($payment): bool => $this->isCashMethod($payment->payment_method));

            if ($cashPayment?->tillSession?->cashLocation) {
                $cashPayment->tillSession->cashLocation->decrement('balance_minor', $refundMinor);
            }

            return $refundMinor;
        });
    }

    /**
     * The outstanding customer credit available to refund (before executing).
     */
    public function refundableMinor(SalesOrder $order): int
    {
        return max(0, (int) $order->paid_minor - (int) $order->refunded_minor);
    }

    private function refundAccountCodeFor(SalesOrder $order): string
    {
        $payment = $order->payments->first();

        if ($payment && $this->isCashMethod($payment->payment_method) && $payment->tillSession?->cashLocation?->financeAccount) {
            return $payment->tillSession->cashLocation->financeAccount->code;
        }

        return $this->nonCashAccountFor($payment?->payment_method);
    }

    private function isCashMethod(?string $paymentMethod): bool
    {
        return str_contains(strtolower((string) $paymentMethod), 'cash');
    }

    private function nonCashAccountFor(?string $paymentMethod): string
    {
        $method = strtolower((string) $paymentMethod);

        return match (true) {
            str_contains($method, 'pos'), str_contains($method, 'card') => '1050',
            str_contains($method, 'online'), str_contains($method, 'paystack'), str_contains($method, 'gateway') => '1060',
            default => '1040',
        };
    }
}
