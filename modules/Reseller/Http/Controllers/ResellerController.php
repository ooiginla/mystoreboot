<?php

declare(strict_types=1);

namespace Modules\Reseller\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ActiveBranchManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Business\Models\OnlineStore;
use Modules\Reseller\Actions\RepriceResellerProducts;
use Modules\Reseller\Enums\PaymentStatus;
use Modules\Reseller\Enums\PricingMode;
use Modules\Reseller\Enums\ScanStatus;
use Modules\Reseller\Http\Requests\ResellerSettingsRequest;
use Modules\Reseller\Http\Requests\ResellerSupplierRequest;
use Modules\Reseller\Jobs\RecoverSupplierProductsJob;
use Modules\Reseller\Models\ResellerOrder;
use Modules\Reseller\Models\ResellerOrderItem;
use Modules\Reseller\Models\ResellerPayment;
use Modules\Reseller\Models\ResellerProduct;
use Modules\Reseller\Models\ResellerSetting;
use Modules\Reseller\Models\ResellerSupplier;
use Modules\Reseller\Services\RecoverSupplierProducts;
use Modules\Reseller\Support\SupplierScanSchedule;
use Modules\Sales\Enums\SalesOrderStatus;
use Modules\Sales\Models\SalesOrder;
use Modules\Tenancy\Enums\CommerceMode;
use Modules\Tenancy\Models\Tenant;
use Throwable;

final class ResellerController extends Controller
{
    public function __construct(
        private readonly ActiveBranchManager $branches,
        private readonly SupplierScanSchedule $scanSchedule,
    ) {}

    public function index(Request $request): View
    {
        return $this->page($request, 'overview');
    }

    public function suppliers(Request $request): View
    {
        return $this->page($request, 'suppliers');
    }

    public function products(Request $request): View
    {
        return $this->page($request, 'products');
    }

    public function orders(Request $request): View
    {
        return $this->page($request, 'orders');
    }

    public function payments(Request $request): View
    {
        return $this->page($request, 'payments');
    }

    public function settings(Request $request): View
    {
        return $this->page($request, 'settings');
    }

    private function page(Request $request, string $section): View
    {
        $tenant = $this->tenant($request);
        $settings = ResellerSetting::query()->firstOrCreate(['tenant_id' => $tenant->id]);
        $productSearch = trim($request->string('product_search')->toString());
        $suppliers = ResellerSupplier::query()
            ->withCount('products')
            ->where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get();

        return view('reseller::admin.index', [
            'tenant' => $tenant,
            'section' => $section,
            'resellerStoreName' => OnlineStore::query()
                ->where('tenant_id', $tenant->id)
                ->value('store_name'),
            'settings' => $settings,
            'pricingModes' => PricingMode::cases(),
            'productSearch' => $productSearch,
            'suppliers' => $suppliers,
            'supplierScanSchedules' => $suppliers->mapWithKeys(fn (ResellerSupplier $supplier): array => [
                $supplier->id => [
                    'frequency' => $this->scanSchedule->frequency($supplier, $settings),
                    'next_run_at' => $this->scanSchedule->nextDispatchAt($supplier, $settings),
                ],
            ]),
            'products' => ResellerProduct::query()
                ->with('supplier')
                ->where('tenant_id', $tenant->id)
                ->when($productSearch !== '', fn ($query) => $query->where(function ($query) use ($productSearch): void {
                    $query
                        ->whereLike('name', '%'.$productSearch.'%')
                        ->orWhereHas('supplier', fn ($supplierQuery) => $supplierQuery->whereLike('name', '%'.$productSearch.'%'));
                }))
                ->latest('last_checked_at')
                ->paginate(25, ['*'], 'products_page')
                ->withQueryString(),
            'orders' => ResellerOrder::query()
                ->withCount('items')
                ->where('tenant_id', $tenant->id)
                ->latest()
                ->limit(50)
                ->get(),
            'payments' => ResellerPayment::query()
                ->with('order')
                ->where('tenant_id', $tenant->id)
                ->latest()
                ->limit(50)
                ->get(),
        ]);
    }

    public function updateMode(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'commerce_mode' => ['required', Rule::enum(CommerceMode::class)],
        ]);
        $tenant = $this->tenant($request, $data['tenant_id']);
        $mode = CommerceMode::from($data['commerce_mode']);

        if ($mode === $tenant->commerce_mode) {
            return back()->with('status', 'Commerce mode is already active.');
        }

        if ($this->hasActiveOrders($tenant)) {
            return back()->withErrors(['commerce_mode' => 'Resolve active orders before changing commerce mode.']);
        }

        $tenant->update(['commerce_mode' => $mode]);

        if ($mode === CommerceMode::Reseller) {
            ResellerSetting::query()->firstOrCreate(['tenant_id' => $tenant->id]);
        }

        return redirect()
            ->route('admin.reseller.index', ['tenant' => $tenant->id])
            ->with('status', $mode === CommerceMode::Reseller
                ? 'Reseller store activated.'
                : 'Standard product store activated.');
    }

    public function updateSettings(
        ResellerSettingsRequest $request,
        RepriceResellerProducts $repricer,
    ): RedirectResponse {
        $data = $request->validated();
        $tenant = $this->resellerTenant($request, $data['tenant_id']);
        $settings = ResellerSetting::query()->firstOrCreate(['tenant_id' => $tenant->id]);
        $settings->update(collect($data)->except('tenant_id')->all());
        $count = $repricer->execute($settings->refresh());

        return back()->with('status', "Reseller settings saved. {$count} products repriced.");
    }

    public function storeSupplier(ResellerSupplierRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->resellerTenant($request, $data['tenant_id']);
        $supplier = ResellerSupplier::query()->create($data);

        return back()->with('status', "Supplier {$supplier->name} added.");
    }

    public function updateSupplier(
        ResellerSupplierRequest $request,
        ResellerSupplier $supplier,
    ): RedirectResponse {
        $data = $request->validated();
        $this->resellerTenant($request, $data['tenant_id']);
        abort_unless($supplier->tenant_id === $data['tenant_id'], 404);
        $supplier->update(collect($data)->except('tenant_id')->all());

        return back()->with('status', "Supplier {$supplier->name} updated.");
    }

    public function destroySupplier(Request $request, ResellerSupplier $supplier): RedirectResponse
    {
        $tenant = $this->resellerTenant($request);
        abort_unless($supplier->tenant_id === $tenant->id, 404);
        $name = $supplier->name;
        $supplier->delete();

        return back()->with('status', "Supplier {$name} deleted.");
    }

    public function scheduleSupplierScan(Request $request, ResellerSupplier $supplier): RedirectResponse
    {
        $tenant = $this->resellerTenant($request);
        abort_unless($supplier->tenant_id === $tenant->id, 404);

        if (! $supplier->is_active) {
            return back()->withErrors(['scan' => 'Activate this supplier before scheduling a scan.']);
        }

        if ($supplier->last_scan_status === ScanStatus::Running) {
            return back()->withErrors(['scan' => "A scan for {$supplier->name} is already running."]);
        }

        RecoverSupplierProductsJob::dispatch($supplier->id);

        return back()->with('status', "A product recovery scan for {$supplier->name} was scheduled and queued.");
    }

    public function scanSupplier(
        Request $request,
        ResellerSupplier $supplier,
        RecoverSupplierProducts $recovery,
    ): RedirectResponse {
        $tenant = $this->resellerTenant($request);
        abort_unless($supplier->tenant_id === $tenant->id, 404);

        if (! $supplier->is_active) {
            return back()->withErrors(['scan' => 'Activate this supplier before running a scan.']);
        }

        if ($supplier->last_scan_status === ScanStatus::Running) {
            return back()->withErrors(['scan' => "A scan for {$supplier->name} is already running."]);
        }

        try {
            $result = $recovery->execute($supplier);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['scan' => "The scan for {$supplier->name} failed: {$exception->getMessage()}"]);
        }

        return back()->with('status', "Scan complete for {$supplier->name}: {$result['created']} created, {$result['updated']} updated, {$result['pages']} pages checked, {$result['errors']} errors.");
    }

    public function updateProduct(Request $request, ResellerProduct $product): RedirectResponse
    {
        $tenant = $this->resellerTenant($request);
        abort_unless($product->tenant_id === $tenant->id, 404);
        $data = $request->validate([
            'is_visible' => ['required', 'boolean'],
            'is_excluded' => ['required', 'boolean'],
        ]);
        $excluded = (bool) $data['is_excluded'];
        $product->update([
            'is_excluded' => $excluded,
            'is_visible' => $excluded ? false : (bool) $data['is_visible'],
        ]);

        return back()->with('status', "Product {$product->name} updated.");
    }

    public function showOrder(Request $request, ResellerOrder $order): View
    {
        $tenant = $this->resellerTenant($request);
        abort_unless($order->tenant_id === $tenant->id, 404);
        $order->load(['items.supplier', 'payments']);

        return view('reseller::admin.order', [
            'tenant' => $tenant,
            'order' => $order,
            'paymentStatuses' => PaymentStatus::cases(),
            'supplierGroups' => $order->items->groupBy(fn (ResellerOrderItem $item): string => (string) ($item->supplier_id ?? 'removed')),
        ]);
    }

    public function updateOrderPaymentStatus(Request $request, ResellerOrder $order): RedirectResponse
    {
        $tenant = $this->resellerTenant($request);
        abort_unless($order->tenant_id === $tenant->id, 404);
        $data = $request->validate([
            'payment_status' => ['required', Rule::enum(PaymentStatus::class)],
        ]);
        $status = PaymentStatus::from($data['payment_status']);

        DB::transaction(function () use ($request, $order, $status): void {
            $previousStatus = (string) $order->payment_status;
            $order->update(['payment_status' => $status->value]);
            $payment = $order->payments()->latest('id')->first();

            if (! $payment) {
                return;
            }

            $metadata = (array) $payment->metadata;
            $history = (array) ($metadata['status_history'] ?? []);
            $history[] = [
                'from' => $previousStatus,
                'to' => $status->value,
                'changed_by_user_id' => $request->user()?->id,
                'changed_at' => now()->toIso8601String(),
            ];
            $metadata['status_history'] = $history;

            $payment->update([
                'status' => $status->value,
                'processed_at' => $status === PaymentStatus::Pending ? null : now(),
                'metadata' => $metadata,
            ]);
        });

        return back()->with('status', "Payment status for {$order->order_reference} updated to {$status->label()}.");
    }

    public function updateOrderItem(Request $request, ResellerOrder $order, ResellerOrderItem $item): RedirectResponse
    {
        $tenant = $this->resellerTenant($request);
        abort_unless($order->tenant_id === $tenant->id && $item->tenant_id === $tenant->id && $item->reseller_order_id === $order->id, 404);
        $data = $request->validate([
            'fulfilment_status' => ['required', Rule::in(['unfulfilled', 'supplier_notified', 'dispatched', 'delivered', 'cancelled'])],
            'tracking_reference' => ['nullable', 'string', 'max:160'],
            'tracking_url' => ['nullable', 'url:http,https', 'max:2048'],
        ]);
        $item->update($data);
        $statuses = $order->items()->pluck('fulfilment_status');
        $allCancelled = $statuses->every(fn (string $status): bool => $status === 'cancelled');
        $allFinished = $statuses->every(fn (string $status): bool => in_array($status, ['delivered', 'cancelled'], true));
        $anyStarted = $statuses->contains(fn (string $status): bool => $status !== 'unfulfilled');
        $order->update([
            'fulfilment_status' => $allFinished ? 'fulfilled' : ($anyStarted ? 'partially_fulfilled' : 'unfulfilled'),
            'order_status' => $allCancelled ? 'cancelled' : ($allFinished ? 'completed' : 'processing'),
            'completed_at' => $allFinished && ! $allCancelled ? now() : null,
            'cancelled_at' => $allCancelled ? now() : null,
        ]);

        return back()->with('status', "Fulfilment for {$item->product_name} updated.");
    }

    private function tenant(Request $request, ?string $expectedTenantId = null): Tenant
    {
        $tenant = $this->branches->stateForRequest($request, $request->user())['tenant'] ?? null;
        abort_unless($tenant instanceof Tenant, 403);
        abort_if($expectedTenantId !== null && $tenant->id !== $expectedTenantId, 403);

        return $tenant;
    }

    private function resellerTenant(Request $request, ?string $expectedTenantId = null): Tenant
    {
        $tenant = $this->tenant($request, $expectedTenantId);
        abort_unless($tenant->isReseller(), 403, 'Activate reseller mode first.');

        return $tenant;
    }

    private function hasActiveOrders(Tenant $tenant): bool
    {
        if ($tenant->isReseller()) {
            return ResellerOrder::query()
                ->where('tenant_id', $tenant->id)
                ->whereNotIn('order_status', ['completed', 'cancelled'])
                ->exists();
        }

        return SalesOrder::query()
            ->where('tenant_id', $tenant->id)
            ->whereNotIn('order_status', [
                SalesOrderStatus::Completed->value,
                SalesOrderStatus::Cancelled->value,
                SalesOrderStatus::Returned->value,
                SalesOrderStatus::PartiallyReturned->value,
            ])
            ->exists();
    }
}
