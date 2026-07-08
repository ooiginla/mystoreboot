<?php

declare(strict_types=1);

namespace Modules\Procurement\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Actions\PostJournalEntryAction;
use Modules\Procurement\Enums\PurchaseOrderStatus;
use Modules\Procurement\Models\PurchaseOrder;

final class ApprovePurchaseOrderAction
{
    public function __construct(private readonly PostJournalEntryAction $postJournalEntry) {}

    public function execute(PurchaseOrder $purchaseOrder, User $user): PurchaseOrder
    {
        DB::transaction(function () use ($purchaseOrder, $user): void {
            $purchaseOrder->update([
                'status' => PurchaseOrderStatus::Approved->value,
                'approved_at' => now(),
                'approved_by' => $user->id,
            ]);

            $this->postJournalEntry->execute(
                $purchaseOrder->tenant_id,
                $purchaseOrder->approved_at?->toDateString() ?? now()->toDateString(),
                'Purchase order '.$purchaseOrder->po_number,
                [
                    ['account_code' => '1200', 'debit_minor' => $purchaseOrder->subtotal_minor],
                    ['account_code' => '1320', 'debit_minor' => $purchaseOrder->tax_minor],
                    ['account_code' => '1210', 'debit_minor' => $purchaseOrder->shipping_minor],
                    ['account_code' => '2000', 'credit_minor' => $purchaseOrder->total_minor, 'party_type' => 'vendor', 'party_id' => $purchaseOrder->vendor_id],
                ],
                'purchase_order',
                $purchaseOrder->id,
                'approved',
            );
        });

        return $purchaseOrder->refresh();
    }
}
