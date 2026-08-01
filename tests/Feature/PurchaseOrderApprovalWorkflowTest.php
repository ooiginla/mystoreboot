<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Access\Enums\ApprovalStatus;
use Modules\Access\Enums\MembershipStatus;
use Modules\Access\Models\Permission;
use Modules\Access\Models\Role;
use Modules\Access\Models\TenantMembership;
use Modules\Access\Support\ApprovalService;
use Modules\Access\Support\PermissionCatalogue;
use Modules\Procurement\Enums\PaymentStatus;
use Modules\Procurement\Enums\PurchaseOrderStatus;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\Vendor;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class PurchaseOrderApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_orders_use_the_central_approval_authority_and_executor(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Approval Shop',
            'slug' => 'approval-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
            'settings' => [
                'approvals' => [
                    'enabled' => true,
                    'actions' => ['purchase_order' => true],
                ],
            ],
        ]);
        $requester = User::factory()->create();
        $approver = User::factory()->create();
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'procurement.approve'],
            ['module' => 'procurement', 'name' => 'Approve purchase orders', 'description' => 'Approve purchase orders.'],
        );
        $role = Role::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Purchase Order Approver',
            'slug' => 'purchase-order-approver',
        ]);
        $role->permissions()->attach($permission);
        TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $approver->id,
            'role_id' => $role->id,
            'status' => MembershipStatus::Active->value,
            'joined_at' => now(),
        ]);
        $vendor = Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Gift Supplier',
            'status' => 'active',
        ]);
        $purchaseOrder = PurchaseOrder::query()->create([
            'tenant_id' => $tenant->id,
            'vendor_id' => $vendor->id,
            'po_number' => 'PO-APPROVAL-001',
            'status' => PurchaseOrderStatus::PendingApproval->value,
            'payment_status' => PaymentStatus::Unpaid->value,
            'order_date' => now()->toDateString(),
            'total_minor' => 250000,
        ]);

        $approvals = app(ApprovalService::class);
        $approvalRequest = $approvals->create($tenant, $requester, 'purchase_order', 'Purchase order · PO-APPROVAL-001', [
            'amount_minor' => $purchaseOrder->total_minor,
            'payload' => ['purchase_order_id' => $purchaseOrder->id],
        ]);

        $this->assertTrue($approvals->requiresApproval($tenant, 'purchase_order'));
        $this->assertSame('procurement.approve', PermissionCatalogue::approvable()['purchase_order']['approve']);
        $this->assertTrue($approvals->pendingForApprover($tenant, $approver)->contains($approvalRequest));
        $this->assertFalse($approvals->pendingForApprover($tenant, $requester)->contains($approvalRequest));

        $approvals->approve($approvalRequest, $approver, 'Approved for purchasing.');

        $this->assertSame(ApprovalStatus::Approved, $approvalRequest->refresh()->status);
        $this->assertSame($approver->id, $approvalRequest->decided_by);
        $this->assertSame(PurchaseOrderStatus::Approved, $purchaseOrder->refresh()->status);
        $this->assertSame($approver->id, $purchaseOrder->approved_by);
    }
}
