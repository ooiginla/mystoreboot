<?php

declare(strict_types=1);

namespace Modules\Sales\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Actions\PostJournalEntryAction;
use Modules\Inventory\Actions\PostInventoryMovementAction;
use Modules\Inventory\Enums\InventoryMovementType;
use Modules\Inventory\Enums\StockCondition;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Sales\Enums\ReturnStatus;
use Modules\Sales\Enums\SalesOrderStatus;
use Modules\Sales\Enums\SalesPaymentStatus;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesReturn;

final class ProcessSalesReturnAction
{
    public function __construct(
        private readonly PostInventoryMovementAction $postInventoryMovement,
        private readonly PostJournalEntryAction $postJournalEntry,
    ) {}

    /**
     * Compute the refund total for a proposed return without persisting anything.
     * Used to check the acting user's refund limit before deciding to process or
     * divert the return into the approval workflow.
     *
     * @param  array<string, mixed>  $data
     */
    public function previewRefundMinor(SalesOrder $order, array $data): int
    {
        $refundMinor = 0;

        foreach ((array) ($data['items'] ?? []) as $item) {
            $quantity = (int) ($item['quantity'] ?? 0);

            if ($quantity <= 0) {
                continue;
            }

            $orderItem = $order->items()->whereKey($item['sales_order_item_id'] ?? null)->first();

            if ($orderItem) {
                $refundMinor += (int) round(($orderItem->line_total_minor / max(1, $orderItem->quantity)) * $quantity);
            }
        }

        return $refundMinor;
    }

    /**
     * A tenant-unique return number. The unique key is (tenant_id, return_number),
     * so the daily sequence must be scoped to the tenant — not the individual order —
     * and is probed to guarantee no collision.
     */
    private function nextReturnNumber(string $tenantId): string
    {
        $prefix = 'RET-'.now()->format('Ymd').'-';
        $seq = SalesReturn::query()
            ->where('tenant_id', $tenantId)
            ->where('return_number', 'like', $prefix.'%')
            ->count() + 1;

        do {
            $candidate = $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            $seq++;
        } while (SalesReturn::query()->where('tenant_id', $tenantId)->where('return_number', $candidate)->exists());

        return $candidate;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(SalesOrder $order, array $data): SalesReturn
    {
        return DB::transaction(function () use ($order, $data): SalesReturn {
            if (! in_array($order->order_status, [SalesOrderStatus::Completed, SalesOrderStatus::PartiallyReturned], true)) {
                throw ValidationException::withMessages([
                    'order' => 'Only completed orders can be returned.',
                ]);
            }

            $validItems = collect((array) $data['items'])
                ->map(function (array $item) use ($order): array {
                    $quantity = (int) ($item['quantity'] ?? 0);
                    $orderItem = $order->items()->whereKey($item['sales_order_item_id'])->firstOrFail();

                    if ($quantity > $orderItem->quantity_returnable) {
                        throw ValidationException::withMessages([
                            'items' => 'Return quantity cannot exceed the quantity available to return.',
                        ]);
                    }

                    return [$orderItem, $quantity];
                })
                ->filter(fn (array $row): bool => $row[1] > 0)
                ->values();

            if ($validItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Enter at least one quantity to return.',
                ]);
            }

            $salesReturn = $order->returns()->create([
                'tenant_id' => $order->tenant_id,
                'return_number' => $this->nextReturnNumber($order->tenant_id),
                'return_date' => $data['return_date'],
                'status' => ReturnStatus::Approved->value,
                'reason' => $data['reason'] ?? null,
            ]);

            $refundMinor = 0;
            $returnedCostMinor = 0;
            $inventoryLocationId = InventoryLocation::query()
                ->where('tenant_id', $order->tenant_id)
                ->where('branch_id', $order->branch_id)
                ->value('id');

            if (! $inventoryLocationId) {
                throw ValidationException::withMessages([
                    'items' => 'No inventory location is linked to this order branch.',
                ]);
            }

            foreach ($validItems as [$orderItem, $quantity]) {
                $lineRefundMinor = (int) round(($orderItem->line_total_minor / max(1, $orderItem->quantity)) * $quantity);
                $refundMinor += $lineRefundMinor;
                $returnedCostMinor += $quantity * (int) $orderItem->unit_cost_minor;

                $salesReturn->items()->create([
                    'tenant_id' => $order->tenant_id,
                    'sales_order_item_id' => $orderItem->id,
                    'quantity' => $quantity,
                    'refund_minor' => $lineRefundMinor,
                ]);

                $orderItem->increment('quantity_returned', $quantity);

                $this->postInventoryMovement->executeFromSource([
                    'tenant_id' => $order->tenant_id,
                    'inventory_location_id' => $inventoryLocationId,
                    'product_variant_id' => $orderItem->product_variant_id,
                    'movement_type' => InventoryMovementType::Returned->value,
                    'stock_condition' => StockCondition::Returned->value,
                    'quantity' => $quantity,
                    'unit_cost' => $orderItem->unit_cost_minor / 100,
                    'reference_number' => $salesReturn->return_number,
                    'notes' => 'Sales return.',
                    'occurred_at' => $data['return_date'],
                ], 'sales_return', $salesReturn->id);
            }

            // Split the refund: the portion the customer still owed cancels their
            // receivable (1100); the portion they already paid becomes refundable
            // Customer Credit (2300) — the same treatment cancellation uses, so a
            // paid order never posts a phantom negative receivable.
            $owedBeforeReturn = max(0, (int) $order->total_minor - (int) $order->paid_minor - (int) $order->refunded_minor);
            $receivableReversalMinor = min($refundMinor, $owedBeforeReturn);
            $customerCreditMinor = $refundMinor - $receivableReversalMinor;

            $order->refresh()->load('items');
            $allReturned = $order->items->every(fn ($item): bool => $item->quantity_returned >= $item->quantity);
            $creditHeldMinor = (int) $order->customer_credit_minor + $customerCreditMinor;

            $salesReturn->update(['refund_minor' => $refundMinor]);
            $order->update([
                'customer_credit_minor' => $creditHeldMinor,
                'order_status' => $allReturned ? SalesOrderStatus::Returned->value : SalesOrderStatus::PartiallyReturned->value,
                'payment_status' => $creditHeldMinor > 0
                    ? SalesPaymentStatus::CustomerCredit->value
                    : ($allReturned ? SalesPaymentStatus::Refunded->value : SalesPaymentStatus::PartiallyRefunded->value),
            ]);

            // Only the receivable portion reduces what the customer owes.
            if ($order->customer && $order->is_credit_sale && $receivableReversalMinor > 0) {
                $order->customer->update([
                    'account_balance_minor' => max(0, $order->customer->account_balance_minor - $receivableReversalMinor),
                ]);
            }

            $this->postJournalEntry->execute(
                $order->tenant_id,
                (string) $data['return_date'],
                'Sales return '.$salesReturn->return_number,
                [
                    ['account_code' => '4030', 'branch_id' => $order->branch_id, 'debit_minor' => $refundMinor, 'party_type' => 'customer', 'party_id' => $order->customer_id],
                    ['account_code' => '1100', 'branch_id' => $order->branch_id, 'credit_minor' => $receivableReversalMinor, 'party_type' => 'customer', 'party_id' => $order->customer_id],
                    ['account_code' => '2300', 'branch_id' => $order->branch_id, 'credit_minor' => $customerCreditMinor, 'party_type' => 'customer', 'party_id' => $order->customer_id],
                    ['account_code' => '1200', 'branch_id' => $order->branch_id, 'debit_minor' => $returnedCostMinor],
                    ['account_code' => 'EXP-5000', 'branch_id' => $order->branch_id, 'credit_minor' => $returnedCostMinor],
                ],
                'sales_return',
                $salesReturn->id,
                'approved',
            );

            return $salesReturn->refresh()->load('items.orderItem');
        });
    }
}
