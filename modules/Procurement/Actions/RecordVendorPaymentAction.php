<?php

declare(strict_types=1);

namespace Modules\Procurement\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Finance\Actions\PostJournalEntryAction;
use Modules\Finance\Models\FinanceJournalEntry;
use Modules\Finance\Models\FinanceJournalLine;
use Modules\Procurement\Enums\PaymentStatus;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\VendorPayment;

final class RecordVendorPaymentAction
{
    public function __construct(private readonly PostJournalEntryAction $postJournalEntry) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): VendorPayment
    {
        return DB::transaction(function () use ($data): VendorPayment {
            $amountMinor = $this->moneyToMinor($data['amount']);
            $purchaseOrder = ! empty($data['purchase_order_id'])
                ? PurchaseOrder::query()->findOrFail($data['purchase_order_id'])
                : null;
            $payableMinor = $purchaseOrder
                ? min($amountMinor, $this->outstandingPayableMinor($purchaseOrder))
                : $amountMinor;
            $advanceMinor = $purchaseOrder ? $amountMinor - $payableMinor : 0;

            if ($purchaseOrder && $this->hasLegacyApprovalJournal($purchaseOrder)) {
                $payableMinor = $amountMinor;
                $advanceMinor = 0;
            }

            $payment = VendorPayment::query()->create([
                'tenant_id' => $data['tenant_id'],
                'vendor_id' => $data['vendor_id'],
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'payment_date' => $data['payment_date'],
                'amount_minor' => $amountMinor,
                'payment_method' => $data['payment_method'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($purchaseOrder) {
                $paidMinor = $purchaseOrder->payments()->sum('amount_minor');

                $purchaseOrder->update([
                    'paid_minor' => $paidMinor,
                    'payment_status' => $paidMinor >= $purchaseOrder->total_minor
                        ? PaymentStatus::Paid->value
                        : ($paidMinor > 0 ? PaymentStatus::PartiallyPaid->value : PaymentStatus::Unpaid->value),
                ]);
            }

            $this->postJournalEntry->execute(
                $payment->tenant_id,
                (string) $data['payment_date'],
                'Vendor payment',
                [
                    ['account_code' => '2000', 'debit_minor' => $payableMinor, 'party_type' => 'vendor', 'party_id' => $payment->vendor_id],
                    ['account_code' => '1220', 'debit_minor' => $advanceMinor, 'party_type' => 'vendor', 'party_id' => $payment->vendor_id],
                    ['account_code' => $data['payment_account_code'], 'credit_minor' => $amountMinor, 'party_type' => 'vendor', 'party_id' => $payment->vendor_id],
                ],
                'vendor_payment',
                $payment->id,
                'paid',
            );

            return $payment->refresh()->load(['vendor', 'purchaseOrder']);
        });
    }

    private function moneyToMinor(mixed $value): int
    {
        return (int) round(((float) (is_string($value) ? str_replace(',', '', $value) : ($value ?: 0))) * 100);
    }

    private function hasLegacyApprovalJournal(PurchaseOrder $purchaseOrder): bool
    {
        return FinanceJournalEntry::query()
            ->where('tenant_id', $purchaseOrder->tenant_id)
            ->where('source_type', 'purchase_order')
            ->where('source_id', $purchaseOrder->id)
            ->where('source_event', 'approved')
            ->exists();
    }

    private function outstandingPayableMinor(PurchaseOrder $purchaseOrder): int
    {
        $receiptIds = $purchaseOrder->receipts()->pluck('id');
        $paymentIds = $purchaseOrder->payments()->pluck('id');

        $lines = FinanceJournalLine::query()
            ->where('tenant_id', $purchaseOrder->tenant_id)
            ->whereHas('account', fn ($query) => $query->where('code', '2000'))
            ->whereHas('entry', fn ($query) => $query->where(function ($sourceQuery) use ($receiptIds, $paymentIds): void {
                $sourceQuery
                    ->where(fn ($receiptQuery) => $receiptQuery
                        ->where('source_type', 'goods_receipt')
                        ->whereIn('source_id', $receiptIds))
                    ->orWhere(fn ($paymentQuery) => $paymentQuery
                        ->where('source_type', 'vendor_payment')
                        ->whereIn('source_id', $paymentIds));
            }))
            ->get();

        return max(0, (int) $lines->sum('credit_minor') - (int) $lines->sum('debit_minor'));
    }

}
