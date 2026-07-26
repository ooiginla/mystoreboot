<?php

declare(strict_types=1);

namespace Modules\Procurement\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Procurement\Enums\PurchaseOrderStatus;
use Modules\Procurement\Models\PurchaseOrder;

final class ApprovePurchaseOrderAction
{
    public function execute(PurchaseOrder $purchaseOrder, User $user): PurchaseOrder
    {
        DB::transaction(function () use ($purchaseOrder, $user): void {
            $purchaseOrder->update([
                'status' => PurchaseOrderStatus::Approved->value,
                'approved_at' => now(),
                'approved_by' => $user->id,
            ]);
        });

        return $purchaseOrder->refresh();
    }
}
