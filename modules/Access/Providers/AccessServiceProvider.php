<?php

declare(strict_types=1);

namespace Modules\Access\Providers;

use App\Support\Modules\ModuleServiceProvider;
use Modules\Access\Support\ApprovalService;

final class AccessServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Access';
    }

    public function register(): void
    {
        // Singleton so executor registrations persist for the whole request.
        $this->app->singleton(ApprovalService::class);
    }

    public function boot(): void
    {
        parent::boot();

        $approvals = $this->app->make(ApprovalService::class);
        $approvals->registerExecutor('inventory_adjustment', \Modules\Access\Approvals\InventoryAdjustmentExecutor::class);
        $approvals->registerExecutor('purchase_order', \Modules\Access\Approvals\PurchaseOrderExecutor::class);
        $approvals->registerExecutor('refund', \Modules\Access\Approvals\RefundExecutor::class);
        $approvals->registerExecutor('expense', \Modules\Access\Approvals\ExpenseExecutor::class);
        $approvals->registerExecutor('journal', \Modules\Access\Approvals\JournalExecutor::class);
        $approvals->registerExecutor('payroll', \Modules\Access\Approvals\PayrollExecutor::class);
    }
}
