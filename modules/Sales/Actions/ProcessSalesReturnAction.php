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
     * @param  array<string, mixed>  $data
     */
    public function execute(SalesOrder $order, array $data): SalesReturn
    {
        return DB::transaction(function () use ($order, $data): SalesReturn {
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
                'return_number' => 'RET-'.now()->format('Ymd').'-'.str_pad((string) ($order->returns()->count() + 1), 4, '0', STR_PAD_LEFT),
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

                $this->postInventoryMovement->execute([
                    'tenant_id' => $order->tenant_id,
                    'inventory_location_id' => $inventoryLocationId,
                    'product_variant_id' => $orderItem->product_variant_id,
                    'movement_type' => InventoryMovementType::Returned->value,
                    'stock_condition' => StockCondition::Returned->value,
                    'quantity' => $quantity,
                    'unit_cost' => $orderItem->unit_cost_minor / 100,
                    'reference_type' => 'sales_return',
                    'reference_number' => $salesReturn->return_number,
                    'notes' => 'Sales return.',
                    'occurred_at' => $data['return_date'],
                ]);
            }

            $order->refresh()->load('items');
            $allReturned = $order->items->every(fn ($item): bool => $item->quantity_returned >= $item->quantity);

            $salesReturn->update(['refund_minor' => $refundMinor]);
            $order->update([
                'refunded_minor' => $order->refunded_minor + $refundMinor,
                'order_status' => $allReturned ? SalesOrderStatus::Returned->value : SalesOrderStatus::PartiallyReturned->value,
                'payment_status' => $allReturned ? SalesPaymentStatus::Refunded->value : SalesPaymentStatus::PartiallyRefunded->value,
            ]);

            if ($order->customer && $order->is_credit_sale) {
                $order->customer->update([
                    'account_balance_minor' => max(0, $order->customer->account_balance_minor - $refundMinor),
                ]);
            }

            $this->postJournalEntry->execute(
                $order->tenant_id,
                (string) $data['return_date'],
                'Sales return '.$salesReturn->return_number,
                [
                    ['account_code' => '4030', 'branch_id' => $order->branch_id, 'debit_minor' => $refundMinor, 'party_type' => 'customer', 'party_id' => $order->customer_id],
                    ['account_code' => '1100', 'branch_id' => $order->branch_id, 'credit_minor' => $refundMinor, 'party_type' => 'customer', 'party_id' => $order->customer_id],
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
