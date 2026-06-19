<?php

declare(strict_types=1);

namespace Modules\Sales\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

            if ($amountMinor > $order->balance_minor) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment cannot be more than the outstanding balance.',
                ]);
            }

            $payment = $order->payments()->create([
                'tenant_id' => $order->tenant_id,
                'sales_till_session_id' => $tillSession->id,
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
                    ['account_code' => $this->cashAccountFor($data['payment_method'], $tillSession), 'debit_minor' => $amountMinor, 'party_type' => 'customer', 'party_id' => $order->customer_id],
                    ['account_code' => '1100', 'credit_minor' => $amountMinor, 'party_type' => 'customer', 'party_id' => $order->customer_id],
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

    private function cashAccountFor(?string $paymentMethod, SalesTillSession $tillSession): string
    {
        return $this->isCashMethod($paymentMethod)
            ? $this->ensureTillCashLocation($tillSession)->financeAccount->code
            : '1000';
    }

    private function isCashMethod(?string $paymentMethod): bool
    {
        return str_contains(strtolower((string) $paymentMethod), 'cash');
    }

    private function ensureTillCashLocation(SalesTillSession $tillSession): SalesCashLocation
    {
        if ($tillSession->cashLocation?->financeAccount) {
            return $tillSession->cashLocation;
        }

        $account = FinanceAccount::query()->firstOrCreate([
            'tenant_id' => $tillSession->tenant_id,
            'code' => 'CT-'.$tillSession->id,
        ], [
            'name' => 'Cashier Till '.$tillSession->session_number,
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_system' => true,
            'is_active' => true,
        ]);

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

        if (! $tillSession->cash_location_id) {
            $tillSession->update(['cash_location_id' => $location->id]);
        }

        return $location->load('financeAccount');
    }
}
