<?php

declare(strict_types=1);

namespace Modules\Procurement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Access\Enums\MembershipStatus;
use Modules\Access\Models\TenantMembership;
use Modules\Business\Models\Branch;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Models\ProductVariant;
use Modules\Inventory\Enums\InventoryLocationType;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Procurement\Actions\ApprovePurchaseOrderAction;
use Modules\Procurement\Actions\ReceivePurchaseOrderAction;
use Modules\Procurement\Actions\RecordVendorPaymentAction;
use Modules\Procurement\Actions\SavePurchaseOrderAction;
use Modules\Procurement\Enums\PurchaseOrderStatus;
use Modules\Procurement\Http\Requests\GoodsReceiptRequest;
use Modules\Procurement\Http\Requests\PurchaseOrderRequest;
use Modules\Procurement\Http\Requests\VendorPaymentRequest;
use Modules\Procurement\Http\Requests\VendorRequest;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\PurchaseOrderItem;
use Modules\Procurement\Models\Vendor;
use Modules\Procurement\Models\VendorPayment;
use Modules\Tenancy\Models\Tenant;

final class ProcurementController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $tenants = $this->visibleTenantsFor($user);
        $tenant = $this->resolveTenant($request, $tenants);

        abort_if(! $tenant, 403);
        $this->ensureBranchLocations($tenant);

        $vendorSearch = trim($request->string('vendor_search')->toString());
        $poFilters = [
            'vendor_id' => $request->string('vendor_id')->toString(),
            'status' => $request->string('status')->toString(),
            'payment_status' => $request->string('payment_status')->toString(),
            'date_from' => $request->string('date_from')->toString(),
            'date_to' => $request->string('date_to')->toString(),
        ];

        $vendors = Vendor::query()
            ->with('bankAccounts')
            ->where('tenant_id', $tenant->id)
            ->when($vendorSearch !== '', fn ($query) => $query->where(function ($query) use ($vendorSearch): void {
                $query->where('name', 'like', "%{$vendorSearch}%")
                    ->orWhere('contact_name', 'like', "%{$vendorSearch}%")
                    ->orWhere('email', 'like', "%{$vendorSearch}%")
                    ->orWhere('phone', 'like', "%{$vendorSearch}%")
                    ->orWhere('code', 'like', "%{$vendorSearch}%");
            }))
            ->orderBy('name')
            ->get();
        $allVendors = Vendor::query()->with('bankAccounts')->where('tenant_id', $tenant->id)->orderBy('name')->get();
        $locations = InventoryLocation::query()->where('tenant_id', $tenant->id)->orderBy('name')->get();
        $variants = ProductVariant::query()
            ->with('product')
            ->where('tenant_id', $tenant->id)
            ->whereHas('product', fn ($query) => $query->where('product_type', ProductType::Product->value))
            ->orderBy('sku')
            ->get();
        $purchaseOrdersQuery = PurchaseOrder::query()
            ->with(['vendor', 'items.variant.product', 'items.location', 'receipts', 'payments'])
            ->where('tenant_id', $tenant->id);
        $allPurchaseOrders = (clone $purchaseOrdersQuery)->latest()->get();
        $purchaseOrders = $purchaseOrdersQuery
            ->when($poFilters['vendor_id'] !== '', fn ($query) => $query->where('vendor_id', $poFilters['vendor_id']))
            ->when($poFilters['status'] !== '', fn ($query) => $query->where('status', $poFilters['status']))
            ->when($poFilters['payment_status'] !== '', fn ($query) => $query->where('payment_status', $poFilters['payment_status']))
            ->when($poFilters['date_from'] !== '', fn ($query) => $query->whereDate('order_date', '>=', $poFilters['date_from']))
            ->when($poFilters['date_to'] !== '', fn ($query) => $query->whereDate('order_date', '<=', $poFilters['date_to']))
            ->latest()
            ->get();
        $payments = VendorPayment::query()->with(['vendor', 'purchaseOrder'])->where('tenant_id', $tenant->id)->latest('payment_date')->get();

        return view('procurement::admin.index', [
            'tenant' => $tenant,
            'tenants' => $tenants,
            'isPlatformAdmin' => $user->is_platform_admin,
            'vendors' => $vendors,
            'allVendors' => $allVendors,
            'locations' => $locations,
            'variants' => $variants,
            'purchaseOrders' => $purchaseOrders,
            'allPurchaseOrders' => $allPurchaseOrders,
            'payments' => $payments,
            'vendorSearch' => $vendorSearch,
            'poFilters' => $poFilters,
            'paymentMethods' => $tenant->settings['payment_methods'] ?? ['Cash', 'Bank transfer', 'POS/Card', 'Cheque'],
            'pendingOrders' => $allPurchaseOrders->whereIn('status.value', ['approved', 'partially_received']),
            'pricingRows' => PurchaseOrderItem::query()->with(['purchaseOrder.vendor', 'variant.product'])->where('tenant_id', $tenant->id)->latest()->limit(80)->get(),
            'vendorPerformance' => $allVendors->map(fn (Vendor $vendor): array => [
                'vendor' => $vendor,
                'orders' => $allPurchaseOrders->where('vendor_id', $vendor->id)->count(),
                'received' => $allPurchaseOrders->where('vendor_id', $vendor->id)->where('status.value', 'received')->count(),
                'spend_minor' => $allPurchaseOrders->where('vendor_id', $vendor->id)->sum('total_minor'),
                'paid_minor' => $allPurchaseOrders->where('vendor_id', $vendor->id)->sum('paid_minor'),
                'balance_minor' => $allPurchaseOrders->where('vendor_id', $vendor->id)->sum(fn (PurchaseOrder $order): int => $order->balance_minor),
            ]),
            'stats' => [
                'vendors' => $allVendors->count(),
                'pending_pos' => $allPurchaseOrders->whereIn('status.value', ['pending_approval', 'approved', 'partially_received'])->count(),
                'outstanding_minor' => $allPurchaseOrders->sum(fn (PurchaseOrder $order): int => $order->balance_minor),
                'spend_minor' => $allPurchaseOrders->sum('total_minor'),
            ],
        ]);
    }

    public function storeVendor(VendorRequest $request): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());
        $data = $request->validated();
        $vendor = Vendor::query()->create(collect($data)->except('bank_accounts')->all());
        $this->syncBankAccounts($vendor, $data['bank_accounts'] ?? []);

        return redirect()->to(route('admin.procurement.index', ['tenant' => $vendor->tenant_id]).'#vendors')->with('status', "Vendor {$vendor->name} created.");
    }

    public function updateVendor(VendorRequest $request, Vendor $vendor): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $vendor->tenant_id);
        $data = $request->validated();
        abort_unless($data['tenant_id'] === $vendor->tenant_id, 403);
        $vendor->update(collect($data)->except('bank_accounts')->all());
        $this->syncBankAccounts($vendor, $data['bank_accounts'] ?? []);

        return redirect()->to(route('admin.procurement.index', ['tenant' => $vendor->tenant_id]).'#vendors')->with('status', "Vendor {$vendor->name} updated.");
    }

    public function storePurchaseOrder(PurchaseOrderRequest $request, SavePurchaseOrderAction $action): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());
        $purchaseOrder = $action->execute($request->validated());

        return redirect()->to(route('admin.procurement.index', ['tenant' => $purchaseOrder->tenant_id]).'#purchase-orders')->with('status', "Purchase order {$purchaseOrder->po_number} created.");
    }

    public function updatePurchaseOrder(PurchaseOrderRequest $request, PurchaseOrder $purchaseOrder, SavePurchaseOrderAction $action): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $purchaseOrder->tenant_id);
        abort_unless($purchaseOrder->status === PurchaseOrderStatus::PendingApproval, 422, 'Only purchase orders awaiting approval can be edited.');
        $data = $request->validated();
        abort_unless($data['tenant_id'] === $purchaseOrder->tenant_id, 403);
        $updatedPurchaseOrder = $action->execute($data, $purchaseOrder);

        return redirect()->to(route('admin.procurement.index', ['tenant' => $updatedPurchaseOrder->tenant_id]).'#purchase-orders')->with('status', "Purchase order {$updatedPurchaseOrder->po_number} updated.");
    }

    public function cancelPurchaseOrder(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $purchaseOrder->tenant_id);
        abort_unless($purchaseOrder->status === PurchaseOrderStatus::PendingApproval, 422, 'Only purchase orders awaiting approval can be cancelled.');
        $purchaseOrder->update(['status' => PurchaseOrderStatus::Cancelled->value]);

        return redirect()->to(route('admin.procurement.index', ['tenant' => $purchaseOrder->tenant_id]).'#purchase-orders')->with('status', "Purchase order {$purchaseOrder->po_number} cancelled.");
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder, ApprovePurchaseOrderAction $action): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $purchaseOrder->tenant_id);
        $action->execute($purchaseOrder, $request->user());

        return redirect()->to(route('admin.procurement.index', ['tenant' => $purchaseOrder->tenant_id]).'#purchase-orders')->with('status', "{$purchaseOrder->po_number} approved.");
    }

    public function receive(GoodsReceiptRequest $request, PurchaseOrder $purchaseOrder, ReceivePurchaseOrderAction $action): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $purchaseOrder->tenant_id);
        abort_unless(in_array($purchaseOrder->status, [PurchaseOrderStatus::Approved, PurchaseOrderStatus::PartiallyReceived], true), 422, 'Only approved purchase orders with pending quantities can be received.');
        $receipt = $action->execute($purchaseOrder->load('items'), $request->validated());

        return redirect()->to(route('admin.procurement.index', ['tenant' => $receipt->tenant_id]).'#receipts')->with('status', "Goods receipt {$receipt->receipt_number} posted.");
    }

    public function storePayment(VendorPaymentRequest $request, RecordVendorPaymentAction $action): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());
        $data = $request->validated();

        if (! empty($data['purchase_order_id'])) {
            $purchaseOrder = PurchaseOrder::query()->where('tenant_id', $data['tenant_id'])->findOrFail($data['purchase_order_id']);
            abort_unless(in_array($purchaseOrder->status, [PurchaseOrderStatus::Approved, PurchaseOrderStatus::PartiallyReceived, PurchaseOrderStatus::Received], true), 422, 'Only approved purchase orders can be paid.');
        }

        $payment = $action->execute($data);

        return redirect()->to(route('admin.procurement.index', ['tenant' => $payment->tenant_id]).'#payments')->with('status', 'Vendor payment recorded.');
    }

    private function ensureBranchLocations(Tenant $tenant): void
    {
        Branch::query()->where('tenant_id', $tenant->id)->where('status', 'active')->each(function (Branch $branch) use ($tenant): void {
            InventoryLocation::query()->firstOrCreate([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
            ], [
                'name' => $branch->name,
                'code' => $branch->code,
                'location_type' => InventoryLocationType::Branch->value,
                'status' => 'active',
            ]);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $accounts
     */
    private function syncBankAccounts(Vendor $vendor, array $accounts): void
    {
        $vendor->bankAccounts()->delete();

        collect($accounts)
            ->filter(fn (array $account): bool => trim((string) ($account['bank_name'] ?? '')) !== '' && trim((string) ($account['account_number'] ?? '')) !== '')
            ->values()
            ->each(function (array $account, int $index) use ($vendor): void {
                $vendor->bankAccounts()->create([
                    'tenant_id' => $vendor->tenant_id,
                    'bank_name' => $account['bank_name'],
                    'account_name' => $account['account_name'] ?? $vendor->name,
                    'account_number' => $account['account_number'],
                    'currency_code' => isset($account['currency_code']) ? strtoupper((string) $account['currency_code']) : null,
                    'is_primary' => (bool) ($account['is_primary'] ?? $index === 0),
                ]);
            });
    }

    private function visibleTenantsFor(User $user): EloquentCollection
    {
        if ($user->is_platform_admin) {
            return Tenant::query()->orderBy('name')->get();
        }

        return Tenant::query()->whereHas('memberships', fn ($query) => $query->where('user_id', $user->id)->where('status', MembershipStatus::Active->value))->orderBy('name')->get();
    }

    private function resolveTenant(Request $request, EloquentCollection $visibleTenants): ?Tenant
    {
        $tenantId = $request->string('tenant')->toString();
        if ($tenantId !== '') {
            abort_unless($visibleTenants->contains('id', $tenantId), 403);

            return Tenant::query()->find($tenantId);
        }

        return $visibleTenants->first();
    }

    private function authorizeTenantIdAccess(?User $user, string $tenantId): void
    {
        abort_unless($user instanceof User, 403);

        if ($user->is_platform_admin) {
            return;
        }

        abort_unless(TenantMembership::query()->where('tenant_id', $tenantId)->where('user_id', $user->id)->where('status', MembershipStatus::Active->value)->exists(), 403);
    }
}
