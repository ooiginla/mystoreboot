<?php

declare(strict_types=1);

namespace Modules\Sales\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Enums\ProductType;
use Modules\Inventory\Actions\AdjustInventoryReservationAction;
use Modules\Sales\Enums\SalesOrderStatus;
use Modules\Sales\Enums\SalesPaymentStatus;
use Modules\Sales\Models\SalesOrder;

/**
 * Cancels unpaid online orders whose stock reservation has expired and releases
 * the held stock. Runs both from the scheduled command and lazily at checkout so
 * abandoned holds are freed even if the scheduler is not running. Paid or
 * partially paid orders are never auto-cancelled; offline orders are excluded.
 */
final class ExpireOnlineOrderReservationsAction
{
    public function __construct(private readonly AdjustInventoryReservationAction $reservations) {}

    /**
     * @param  string|null  $tenantId  Optionally scope to a single tenant (used by the lazy checkout sweep).
     * @return int  Number of orders cancelled.
     */
    public function execute(?string $tenantId = null): int
    {
        $expiredIds = SalesOrder::query()
            ->where('source', 'online')
            ->where('order_status', SalesOrderStatus::Pending->value)
            ->where('stock_reserved', true)
            ->where('paid_minor', 0)
            ->whereNotNull('reserved_until')
            ->where('reserved_until', '<=', now())
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->pluck('id');

        $cancelled = 0;

        foreach ($expiredIds as $orderId) {
            DB::transaction(function () use ($orderId, &$cancelled): void {
                $order = SalesOrder::query()
                    ->with('items.variant.product')
                    ->lockForUpdate()
                    ->find($orderId);

                // Re-check under the lock: the order may have been paid or handled
                // between the initial scan and acquiring the row lock.
                if (! $order
                    || $order->order_status !== SalesOrderStatus::Pending
                    || (int) $order->paid_minor > 0
                    || ! $order->stock_reserved
                    || $order->reserved_until === null
                    || $order->reserved_until->isFuture()) {
                    return;
                }

                if ($order->inventory_location_id) {
                    foreach ($order->items as $item) {
                        if ($item->variant?->product?->product_type !== ProductType::Product) {
                            continue;
                        }

                        $this->reservations->release(
                            $order->tenant_id,
                            (int) $order->inventory_location_id,
                            (int) $item->product_variant_id,
                            (int) $item->quantity,
                        );
                    }
                }

                $order->update([
                    'order_status' => SalesOrderStatus::Cancelled->value,
                    'payment_status' => SalesPaymentStatus::Unpaid->value,
                    'stock_reserved' => false,
                    'reserved_until' => null,
                ]);

                $cancelled++;
            });
        }

        return $cancelled;
    }
}
