<?php

declare(strict_types=1);

namespace Modules\Access\Approvals;

use Modules\Access\Contracts\ApprovalExecutor;
use Modules\Access\Models\ApprovalRequest;
use Modules\Inventory\Actions\PostInventoryMovementAction;

/**
 * Replays a deferred inventory adjustment once its approval request is approved.
 * The full validated movement payload was stored on the request.
 */
final class InventoryAdjustmentExecutor implements ApprovalExecutor
{
    public function __construct(private readonly PostInventoryMovementAction $action) {}

    public function execute(ApprovalRequest $request): void
    {
        $payload = (array) $request->payload;

        if ($payload === []) {
            return;
        }

        $this->action->execute($payload);
    }
}
