<?php

declare(strict_types=1);

namespace Modules\Sales\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\BusinessPaymentAccount;
use Modules\Finance\Actions\PostJournalEntryAction;
use Modules\Sales\Enums\SalesOrderStatus;
use Modules\Sales\Enums\SalesPaymentStatus;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesTillSession;

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
    public function execute(SalesOrder $order, array $data, ?int $userId = null): int
    {
        // Keep approvals created before refund-account selection was introduced
        // executable, while every new direct/approval request supplies these.
        $data = array_merge([
            'refund_date' => now()->toDateString(),
            'payment_method' => $order->payment_method ?: 'Bank transfer',
        ], $data);

        return DB::transaction(function () use ($order, $data, $userId): int {
            $lockedOrder = SalesOrder::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            if (! in_array($lockedOrder->order_status, [SalesOrderStatus::Cancelled, SalesOrderStatus::Returned, SalesOrderStatus::PartiallyReturned], true)) {
                throw ValidationException::withMessages([
                    'order' => 'Only cancelled or returned orders can be marked as refunded.',
                ]);
            }

            $refundMinor = max(0, (int) $lockedOrder->customer_credit_minor);

            if ($refundMinor <= 0) {
                throw ValidationException::withMessages([
                    'order' => 'There is no customer credit left to refund for this order.',
                ]);
            }

            [$refundAccountCode, $till, $paymentAccount] = $this->resolveRefundAccount($lockedOrder, $data);
            $lockedOrder->update([
                'refunded_minor' => (int) $lockedOrder->refunded_minor + $refundMinor,
                'customer_credit_minor' => 0,
                'payment_status' => SalesPaymentStatus::Refunded->value,
            ]);

            $this->postJournalEntry->execute(
                $lockedOrder->tenant_id,
                (string) $data['refund_date'],
                'Refund for cancelled order '.$lockedOrder->order_number,
                [
                    ['account_code' => '2300', 'branch_id' => $lockedOrder->branch_id, 'debit_minor' => $refundMinor, 'party_type' => 'customer', 'party_id' => $lockedOrder->customer_id],
                    ['account_code' => $refundAccountCode, 'branch_id' => $lockedOrder->branch_id, 'credit_minor' => $refundMinor, 'party_type' => 'customer', 'party_id' => $lockedOrder->customer_id],
                ],
                'sales_order',
                $lockedOrder->id,
                'refunded_cancelled_order',
            );

            $lockedOrder->refunds()->create([
                'tenant_id' => $lockedOrder->tenant_id,
                'user_id' => $userId,
                'sales_till_session_id' => $till?->id,
                'business_payment_account_id' => $paymentAccount?->id,
                'refund_date' => $data['refund_date'],
                'payment_method' => $data['payment_method'],
                'amount_minor' => $refundMinor,
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($till?->cashLocation) {
                $till->cashLocation->decrement('balance_minor', $refundMinor);
            }

            return $refundMinor;
        });
    }

    /**
     * The outstanding customer credit available to refund (before executing).
     */
    public function refundableMinor(SalesOrder $order): int
    {
        return max(0, (int) $order->customer_credit_minor);
    }

    /**
     * @return array{0: string, 1: ?SalesTillSession, 2: ?BusinessPaymentAccount}
     */
    private function resolveRefundAccount(SalesOrder $order, array $data): array
    {
        $method = (string) $data['payment_method'];

        if ($this->isCashMethod($method)) {
            $till = filled($data['sales_till_session_id'] ?? null)
                ? SalesTillSession::query()
                    ->with('cashLocation.financeAccount')
                    ->where('tenant_id', $order->tenant_id)
                    ->where('branch_id', $order->branch_id)
                    ->find($data['sales_till_session_id'])
                : null;

            if (filled($data['sales_till_session_id'] ?? null) && ! $till) {
                throw ValidationException::withMessages([
                    'sales_till_session_id' => 'Select a valid till for this order branch.',
                ]);
            }

            return [$till?->cashLocation?->financeAccount?->code ?? '1000', $till, null];
        }

        $accounts = BusinessPaymentAccount::query()
            ->with('financeAccount')
            ->where('tenant_id', $order->tenant_id)
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $order->branch_id))
            ->get()
            ->filter(fn (BusinessPaymentAccount $account): bool => $account->supports($method));
        $paymentAccount = filled($data['business_payment_account_id'] ?? null)
            ? $accounts->firstWhere('id', (int) $data['business_payment_account_id'])
            : null;

        if ($accounts->isNotEmpty() && ! $paymentAccount) {
            throw ValidationException::withMessages([
                'business_payment_account_id' => 'Select the account the refund was paid from.',
            ]);
        }

        if ($paymentAccount && ! $paymentAccount->financeAccount) {
            throw ValidationException::withMessages([
                'business_payment_account_id' => 'The selected payment account is not linked to an accounting account.',
            ]);
        }

        return [$paymentAccount?->financeAccount?->code ?? $this->nonCashAccountFor($method), null, $paymentAccount];
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
