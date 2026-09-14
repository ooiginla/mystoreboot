<?php

declare(strict_types=1);

namespace Modules\Sales\Support;

use Illuminate\Support\Collection;
use Modules\Access\Enums\ApprovalStatus;
use Modules\Access\Models\ApprovalRequest;
use Modules\Sales\Models\SalesOrder;

/**
 * Voids waiting on a manager. Derived from the approval queue rather than a flag on the
 * line, so a rejected request simply stops being pending — nothing to clean up.
 */
final class PendingVoids
{
    /**
     * @return Collection<int, ApprovalRequest>
     */
    public static function forCheck(SalesOrder $check): Collection
    {
        return ApprovalRequest::query()
            ->where('tenant_id', $check->tenant_id)
            ->where('type', 'sales_void')
            ->where('status', ApprovalStatus::Pending->value)
            ->where('payload->check_id', $check->id)
            ->get();
    }

    /**
     * Item id => quantity waiting to be voided.
     *
     * @param  Collection<int, ApprovalRequest>  $requests
     * @return array<int, float>
     */
    public static function itemQuantities(Collection $requests): array
    {
        $pending = [];

        foreach ($requests as $request) {
            $payload = (array) $request->payload;

            if (($payload['kind'] ?? 'item') === 'item' && ! empty($payload['item_id'])) {
                $pending[(int) $payload['item_id']] = (float) ($payload['quantity'] ?? 0);
            }
        }

        return $pending;
    }

    /**
     * @param  Collection<int, ApprovalRequest>  $requests
     */
    public static function wholeCheck(Collection $requests): bool
    {
        return $requests->contains(fn (ApprovalRequest $r): bool => (((array) $r->payload)['kind'] ?? null) === 'check');
    }
}
