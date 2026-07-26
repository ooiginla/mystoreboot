<?php

declare(strict_types=1);

namespace Modules\Access\Approvals;

use Modules\Access\Contracts\ApprovalExecutor;
use Modules\Access\Models\ApprovalRequest;
use Modules\Sales\Actions\ProcessSalesReturnAction;
use Modules\Sales\Actions\RefundCancelledOrderAction;
use Modules\Sales\Models\SalesOrder;

/**
 * Performs an approved refund using the same action the controller would, so the
 * accounting is identical. Handles two payload shapes:
 *   - kind = "return": a product return with line items (ProcessSalesReturnAction)
 *   - otherwise: refunding a cancelled order to credit (RefundCancelledOrderAction)
 */
final class RefundExecutor implements ApprovalExecutor
{
    public function __construct(
        private readonly RefundCancelledOrderAction $refundCancelled,
        private readonly ProcessSalesReturnAction $processReturn,
    ) {}

    public function execute(ApprovalRequest $request): void
    {
        $payload = (array) $request->payload;
        $orderId = (int) ($payload['order_id'] ?? 0);

        if ($orderId <= 0) {
            return;
        }

        $order = SalesOrder::query()->where('tenant_id', $request->tenant_id)->find($orderId);

        if (! $order) {
            return;
        }

        if (($payload['kind'] ?? null) === 'return') {
            $this->processReturn->execute(
                $order->load('items.variant', 'customer', 'branch'),
                (array) ($payload['data'] ?? []),
            );

            return;
        }

        $this->refundCancelled->execute($order);
    }
}
