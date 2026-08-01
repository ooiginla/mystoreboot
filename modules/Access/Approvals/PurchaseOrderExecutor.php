<?php

declare(strict_types=1);

namespace Modules\Access\Approvals;

use App\Models\User;
use Modules\Access\Contracts\ApprovalExecutor;
use Modules\Access\Models\ApprovalRequest;
use Modules\Procurement\Actions\ApprovePurchaseOrderAction;
use Modules\Procurement\Models\PurchaseOrder;

final class PurchaseOrderExecutor implements ApprovalExecutor
{
    public function __construct(private readonly ApprovePurchaseOrderAction $action) {}

    public function execute(ApprovalRequest $request, User $approver): void
    {
        $purchaseOrderId = (int) ($request->payload['purchase_order_id'] ?? 0);

        if ($purchaseOrderId <= 0) {
            return;
        }

        $purchaseOrder = PurchaseOrder::query()
            ->where('tenant_id', $request->tenant_id)
            ->findOrFail($purchaseOrderId);

        $this->action->execute($purchaseOrder, $approver);
    }
}
