<?php

declare(strict_types=1);

namespace Modules\Sales\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\BusinessPaymentAccount;
use Modules\Finance\Actions\PostJournalEntryAction;
use Modules\Finance\Models\FinanceAccount;
use Modules\Sales\Enums\SalesPaymentStatus;
use Modules\Sales\Models\SalesCashLocation;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderPayment;
use Modules\Sales\Models\SalesTillSession;

final class RecordSalesPaymentAction
{
    public function __construct(private readonly PostJournalEntryAction $postJournalEntry) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(SalesOrder $order, array $data, int $userId): SalesOrderPayment
    {
        return DB::transaction(function () use ($order, $data, $userId): SalesOrderPayment {
            $tillSession = SalesTillSession::query()
                ->where('tenant_id', $order->tenant_id)
                ->where('branch_id', $order->branch_id)
                ->where('user_id', $userId)
                ->where('status', 'open')
                ->find($data['sales_till_session_id']);

            if (! $tillSession) {
                throw ValidationException::withMessages([
                    'sales_till_session_id' => 'Open a till for this order branch before collecting payment.',
                ]);
            }

            $amountMinor = $this->moneyToMinor($data['amount']);
            $paymentAccount = $this->paymentAccountFor($order->tenant_id, $order->branch_id, $data['payment_method'], $data['business_payment_account_id'] ?? null, 'business_payment_account_id');

            if ($amountMinor > $order->balance_minor) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment cannot be more than the outstanding balance.',
                ]);
            }

            $payment = $order->payments()->create([
                'tenant_id' => $order->tenant_id,
                'sales_till_session_id' => $tillSession->id,
                'business_payment_account_id' => $paymentAccount?->id,
                'payment_date' => $data['payment_date'],
                'payment_method' => $data['payment_method'],
                'amount_minor' => $amountMinor,
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $paidMinor = $order->payments()->sum('amount_minor');
            $order->update([
                'paid_minor' => $paidMinor,
                'payment_status' => $paidMinor >= $order->total_minor
                    ? SalesPaymentStatus::Paid->value
                    : ($paidMinor > 0 ? SalesPaymentStatus::PartiallyPaid->value : SalesPaymentStatus::Unpaid->value),
            ]);

            if ($order->customer && $order->is_credit_sale) {
                $order->customer->update([
                    'account_balance_minor' => max(0, $order->customer->account_balance_minor - $amountMinor),
                ]);
            }

            $this->postJournalEntry->execute(
                $order->tenant_id,
                (string) $data['payment_date'],
                'Customer payment for '.$order->order_number,
                [
                    ['account_code' => $this->cashAccountFor($data['payment_method'], $tillSession, $paymentAccount), 'branch_id' => $order->branch_id, 'debit_minor' => $amountMinor, 'party_type' => 'customer', 'party_id' => $order->customer_id],
                    ['account_code' => '1100', 'branch_id' => $order->branch_id, 'credit_minor' => $amountMinor, 'party_type' => 'customer', 'party_id' => $order->customer_id],
                ],
                'sales_order_payment',
                $payment->id,
                'received',
            );

            if ($this->isCashMethod($data['payment_method'])) {
                $this->ensureTillCashLocation($tillSession)->increment('balance_minor', $amountMinor);
            }

            return $payment->refresh();
        });
    }

    private function moneyToMinor(mixed $value): int
    {
        return (int) round(((float) (is_string($value) ? str_replace(',', '', $value) : ($value ?: 0))) * 100);
    }

    private function cashAccountFor(?string $paymentMethod, SalesTillSession $tillSession, ?BusinessPaymentAccount $paymentAccount = null): string
    {
        if ($this->isCashMethod($paymentMethod)) {
            return $this->ensureTillCashLocation($tillSession)->financeAccount->code;
        }

        if ($paymentAccount?->financeAccount) {
            return $paymentAccount->financeAccount->code;
        }

        return $this->nonCashAccountFor($paymentMethod);
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

    private function paymentAccountFor(string $tenantId, int|string $branchId, ?string $paymentMethod, mixed $paymentAccountId, string $field): ?BusinessPaymentAccount
    {
        $branchId = (int) $branchId;

        if ($this->isCashMethod($paymentMethod)) {
            return null;
        }

        $accounts = BusinessPaymentAccount::query()
            ->with('financeAccount')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $branchId))
            ->get()
            ->filter(fn (BusinessPaymentAccount $account): bool => $account->supports((string) $paymentMethod));

        if (! $paymentAccountId) {
            if ($accounts->isNotEmpty()) {
                throw ValidationException::withMessages([
                    $field => 'Select a receiving account for this payment method.',
                ]);
            }

            return null;
        }

        $account = $accounts->first(fn (BusinessPaymentAccount $account): bool => (string) $account->getKey() === (string) $paymentAccountId);

        if (! $account) {
            throw ValidationException::withMessages([
                $field => 'Select an active receiving account that supports this payment method for the branch.',
            ]);
        }

        return $account;
    }

    private function ensureTillCashLocation(SalesTillSession $tillSession): SalesCashLocation
    {
        if ($tillSession->cashLocation?->financeAccount?->code === '1020') {
            $tillSession->cashLocation->financeAccount->fill([
                'name' => 'Cash in Tills',
                'type' => 'asset',
                'category' => 'Current Assets',
                'description' => 'Cash currently held by cashier tills and registers.',
                'normal_balance' => 'debit',
            ])->save();

            return $tillSession->cashLocation;
        }

        $account = FinanceAccount::query()->firstOrCreate([
            'tenant_id' => $tillSession->tenant_id,
            'code' => '1020',
        ], [
            'name' => 'Cash in Tills',
            'type' => 'asset',
            'category' => 'Current Assets',
            'description' => 'Cash currently held by cashier tills and registers.',
            'normal_balance' => 'debit',
            'is_system' => true,
            'is_active' => true,
        ]);
        $account->fill([
            'name' => 'Cash in Tills',
            'type' => 'asset',
            'category' => 'Current Assets',
            'description' => 'Cash currently held by cashier tills and registers.',
            'normal_balance' => 'debit',
            'is_system' => true,
            'is_active' => true,
        ])->save();

        $location = SalesCashLocation::query()->firstOrCreate([
            'tenant_id' => $tillSession->tenant_id,
            'code' => 'CT-'.$tillSession->id,
        ], [
            'branch_id' => $tillSession->branch_id,
            'sales_till_session_id' => $tillSession->id,
            'user_id' => $tillSession->user_id,
            'finance_account_id' => $account->id,
            'name' => 'Cashier Till '.$tillSession->session_number,
            'location_type' => 'till',
            'is_active' => true,
        ]);
        $location->fill(['finance_account_id' => $account->id])->save();

        if (! $tillSession->cash_location_id) {
            $tillSession->update(['cash_location_id' => $location->id]);
        }

        return $location->load('financeAccount');
    }
}
