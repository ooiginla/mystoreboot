<?php

declare(strict_types=1);

namespace Modules\Access\Approvals;

use App\Models\User;
use Modules\Access\Contracts\ApprovalExecutor;
use Modules\Access\Models\ApprovalRequest;
use Modules\Sales\Actions\VoidCheckAction;
use Modules\Sales\Actions\VoidCheckItemAction;
use Modules\Sales\Models\SalesOrder;

/**
 * Performs a restaurant void once a manager approves it: one sent line (or part of it),
 * or a whole check. The approver is recorded as the person who voided it.
 */
final class SalesVoidExecutor implements ApprovalExecutor
{
    public function __construct(
        private readonly VoidCheckItemAction $voidItem,
        private readonly VoidCheckAction $voidCheck,
    ) {}

    public function execute(ApprovalRequest $request, User $approver): void
    {
        $payload = (array) $request->payload;

        $check = SalesOrder::query()
            ->where('tenant_id', $request->tenant_id)
            ->find((int) ($payload['check_id'] ?? 0));

        if (! $check || ! $check->check_status?->isOpen()) {
            return;
        }

        $reason = (string) ($payload['reason'] ?? 'Approved void');

        if (($payload['kind'] ?? 'item') === 'check') {
            $this->voidCheck->execute($check, $approver, $reason);

            return;
        }

        $item = $check->items()->find((int) ($payload['item_id'] ?? 0));

        if (! $item || $item->voided_at !== null) {
            return;
        }

        $this->voidItem->execute($item, $approver, $reason, (float) ($payload['quantity'] ?? $item->quantity));
    }
}
