<?php

declare(strict_types=1);

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ActiveBranchManager;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Access\Enums\MembershipStatus;
use Modules\Access\Models\TenantMembership;
use Modules\Access\Support\ApprovalService;
use Modules\Business\Models\Branch;
use Modules\Business\Models\BusinessPaymentAccount;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Models\ProductVariant;
use Modules\Customers\Models\Customer;
use Modules\Finance\Actions\PostJournalEntryAction;
use Modules\Finance\Models\FinanceAccount;
use Modules\Inventory\Actions\AdjustInventoryReservationAction;
use Modules\Inventory\Actions\EnsureInventoryLocationsAction;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Sales\Actions\CompleteSalesOrderAction;
use Modules\Sales\Actions\CreateSalesOrderAction;
use Modules\Sales\Actions\ProcessSalesReturnAction;
use Modules\Sales\Actions\RecordSalesPaymentAction;
use Modules\Sales\Actions\RefundCancelledOrderAction;
use Modules\Sales\Enums\DiscountType;
use Modules\Sales\Enums\SalesOrderStatus;
use Modules\Sales\Enums\SalesPaymentStatus;
use Modules\Sales\Http\Requests\SalesCouponRequest;
use Modules\Sales\Http\Requests\SalesOrderRefundRequest;
use Modules\Sales\Http\Requests\SalesOrderRequest;
use Modules\Sales\Http\Requests\SalesPaymentRequest;
use Modules\Sales\Http\Requests\SalesReturnRequest;
use Modules\Sales\Http\Requests\TillCloseRequest;
use Modules\Sales\Http\Requests\TillMovementRequest;
use Modules\Sales\Http\Requests\TillOpenRequest;
use Modules\Sales\Models\OnlineCollectedPayment;
use Modules\Sales\Models\SalesCashLocation;
use Modules\Sales\Models\SalesCoupon;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesTillSession;
use Modules\Subscriptions\Support\TenantModuleAccess;
use Modules\Tenancy\Models\Tenant;

final class SalesController extends Controller
{
    public function index(
        Request $request,
        ActiveBranchManager $branchManager,
        TenantModuleAccess $moduleAccess,
        EnsureInventoryLocationsAction $inventoryLocations,
    ): View {
        /** @var User $user */
        $user = $request->user();
        $tenants = $this->visibleTenantsFor($user);
        $tenant = $this->resolveTenant($request, $tenants);

        abort_if(! $tenant, 403);
        $inventoryEnabled = $moduleAccess->allows($tenant, 'inventory');
        if ($inventoryEnabled) {
            $inventoryLocations->forTenant($tenant);
        }

        $walkInCustomer = $this->walkInCustomer($tenant);
        $orderSearch = trim($request->string('order_search')->toString());
        $branches = Branch::query()->where('tenant_id', $tenant->id)->orderByDesc('is_primary')->orderBy('name')->get();
        $requestedOrderBranchId = $request->integer('order_branch');
        $orderBranchId = $requestedOrderBranchId > 0 && $branches->contains('id', $requestedOrderBranchId)
            ? $requestedOrderBranchId
            : null;
        $requestedOrderSource = $request->string('order_source')->toString();
        $orderSource = in_array($requestedOrderSource, ['retail_pos', 'online', 'offline'], true) ? $requestedOrderSource : '';
        $requestedOrderStatus = $request->string('order_status')->toString();
        $orderStatus = in_array($requestedOrderStatus, SalesOrderStatus::values(), true) ? $requestedOrderStatus : '';
        $requestedOrderPaymentStatus = $request->string('order_payment_status')->toString();
        $orderPaymentStatus = in_array($requestedOrderPaymentStatus, SalesPaymentStatus::values(), true) ? $requestedOrderPaymentStatus : '';
        $recordSaleBranch = $branchManager->stateForRequest($request, $user)['activeBranch'] ?? $branches->first();
        $locations = InventoryLocation::query()->where('tenant_id', $tenant->id)->where('status', 'active')->orderBy('name')->get();
        $activeTill = SalesTillSession::query()
            ->with(['branch', 'user', 'cashLocation.financeAccount', 'vaultCashLocation.financeAccount', 'movements.user', 'payments.order.customer'])
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();
        $activeTillRows = $activeTill ? $this->tillBreakdown($activeTill, $tenant) : collect();
        $recentTillSessions = SalesTillSession::query()
            ->with(['branch', 'user', 'cashLocation', 'vaultCashLocation'])
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->latest('opened_at')
            ->limit(10)
            ->get();
        $customers = Customer::query()->where('tenant_id', $tenant->id)->orderBy('first_name')->get();
        $variants = ProductVariant::query()
            ->with(['product', 'product.category', 'product.taxes'])
            ->where('tenant_id', $tenant->id)
            ->whereHas('product', fn ($query) => $query->where('product_type', ProductType::Product->value))
            ->orderBy('sku')
            ->get();
        $ordersQuery = SalesOrder::query()->with(['customer', 'branch', 'cashier', 'tillSession', 'items.variant.product', 'payments', 'returns.items.orderItem'])->where('tenant_id', $tenant->id);
        $orders = $ordersQuery
            ->when($orderBranchId !== null, fn ($query) => $query->where('branch_id', $orderBranchId))
            ->when($orderSource !== '', fn ($query) => $orderSource === 'retail_pos'
                ? $query->whereIn('source', ['retail_pos', 'in_store'])
                : $query->where('source', $orderSource))
            ->when($orderStatus !== '', fn ($query) => $query->where('order_status', $orderStatus))
            ->when($orderPaymentStatus !== '', fn ($query) => $query->where('payment_status', $orderPaymentStatus))
            ->when($orderSearch !== '', fn ($query) => $query->where(function ($query) use ($orderSearch): void {
                $query->where('order_number', 'like', "%{$orderSearch}%")
                    ->orWhere('invoice_number', 'like', "%{$orderSearch}%")
                    ->orWhere('receipt_number', 'like', "%{$orderSearch}%")
                    ->orWhereHas('customer', fn ($query) => $query->where('first_name', 'like', "%{$orderSearch}%")->orWhere('last_name', 'like', "%{$orderSearch}%")->orWhere('phone', 'like', "%{$orderSearch}%"));
            }))
            ->latest()
            ->get();
        $allOrders = SalesOrder::query()->with(['customer', 'branch', 'cashier', 'tillSession', 'items.variant.product', 'payments', 'returns.items.orderItem'])->where('tenant_id', $tenant->id)->latest()->get();
        $coupons = SalesCoupon::query()->where('tenant_id', $tenant->id)->latest()->get();

        return view('sales::admin.index', [
            'tenant' => $tenant,
            'tenants' => $tenants,
            'isPlatformAdmin' => $user->is_platform_admin,
            'walkInCustomer' => $walkInCustomer,
            'branches' => $branches,
            'recordSaleBranch' => $recordSaleBranch,
            'locations' => $locations,
            'inventoryEnabled' => $inventoryEnabled,
            'activeTill' => $activeTill,
            'activeTillRows' => $activeTillRows,
            'recentTillSessions' => $recentTillSessions,
            'customers' => $customers,
            'variants' => $variants,
            'orders' => $orders,
            'allOrders' => $allOrders,
            'coupons' => $coupons,
            'orderSearch' => $orderSearch,
            'orderBranchId' => $orderBranchId,
            'orderSource' => $orderSource,
            'orderStatus' => $orderStatus,
            'orderPaymentStatus' => $orderPaymentStatus,
            'paymentMethods' => $tenant->settings['payment_methods'] ?? ['Cash', 'Bank transfer', 'POS/Card', 'Cheque'],
            'paymentAccounts' => $this->paymentAccountsFor($tenant->id, $recordSaleBranch?->id),
            'refundPaymentAccounts' => $this->paymentAccountsFor($tenant->id),
            'deliveryMethods' => $branches->flatMap(fn (Branch $branch) => collect($branch->settings['delivery_methods'] ?? []))->where('status', 'active')->values(),
            'orderStatuses' => SalesOrderStatus::cases(),
            'paymentStatuses' => SalesPaymentStatus::cases(),
        ]);
    }

    public function storeOrder(SalesOrderRequest $request, CreateSalesOrderAction $action): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());
        $order = $action->execute($request->validated(), $request->user()->id);

        $target = $order->source === 'retail_pos'
            ? route('admin.sales.retail-pos', ['tenant' => $order->tenant_id])
            : route('admin.sales.orders.index', ['tenant' => $order->tenant_id]).'#orders';
        $completionDialogKey = match (true) {
            $order->source === 'retail_pos' => 'receipt_order_id',
            $order->order_status === SalesOrderStatus::Pending => 'view_order_id',
            default => 'invoice_order_id',
        };

        return redirect()
            ->to($target)
            ->with('status', "Sales order {$order->order_number} created.")
            ->with($completionDialogKey, $order->id);
    }

    public function retailPos(
        Request $request,
        TenantModuleAccess $moduleAccess,
        EnsureInventoryLocationsAction $inventoryLocations,
    ): View {
        /** @var User $user */
        $user = $request->user();
        $tenants = $this->visibleTenantsFor($user);
        $tenant = $this->resolveTenant($request, $tenants);

        abort_if(! $tenant, 403);

        if ($moduleAccess->allows($tenant, 'inventory')) {
            $inventoryLocations->forTenant($tenant);
        }

        $walkInCustomer = $this->walkInCustomer($tenant);
        $branches = Branch::query()->where('tenant_id', $tenant->id)->orderByDesc('is_primary')->orderBy('name')->get();
        $locations = InventoryLocation::query()->where('tenant_id', $tenant->id)->where('status', 'active')->orderBy('name')->get();
        $activeTill = SalesTillSession::query()
            ->with(['branch', 'user', 'cashLocation.financeAccount', 'vaultCashLocation.financeAccount', 'movements.user', 'payments.order.customer'])
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();
        $activeTillRows = $activeTill ? $this->tillBreakdown($activeTill, $tenant) : collect();
        $recentTillSessions = SalesTillSession::query()
            ->with(['branch', 'user', 'cashLocation', 'vaultCashLocation'])
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->latest('opened_at')
            ->limit(10)
            ->get();
        $customers = Customer::query()->where('tenant_id', $tenant->id)->orderBy('first_name')->get();
        $variants = ProductVariant::query()
            ->with(['product', 'product.category', 'product.taxes'])
            ->where('tenant_id', $tenant->id)
            ->whereHas('product', fn ($query) => $query->where('product_type', ProductType::Product->value))
            ->orderBy('sku')
            ->get();
        $categories = $variants
            ->map(fn (ProductVariant $variant) => $variant->product?->category)
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();
        $coupons = SalesCoupon::query()->where('tenant_id', $tenant->id)->latest()->get();
        $sessionOrders = $activeTill
            ? SalesOrder::query()
                ->with(['customer', 'branch', 'cashier', 'tillSession', 'items.variant.product', 'payments'])
                ->where('tenant_id', $tenant->id)
                ->where('sales_till_session_id', $activeTill->id)
                ->latest()
                ->get()
            : collect();
        // Receipts are needed for both the session-orders list and the just-completed sale.
        $recentOrders = SalesOrder::query()
            ->with(['customer', 'branch', 'cashier', 'tillSession', 'items.variant.product', 'payments'])
            ->where('tenant_id', $tenant->id)
            ->latest()
            ->limit(40)
            ->get();
        $recentOrders = $recentOrders->concat($sessionOrders)->unique('id')->values();

        return view('sales::admin.retail-pos', [
            'tenant' => $tenant,
            'tenants' => $tenants,
            'isPlatformAdmin' => $user->is_platform_admin,
            'cashier' => $user,
            'walkInCustomer' => $walkInCustomer,
            'branches' => $branches,
            'locations' => $locations,
            'activeTill' => $activeTill,
            'activeTillRows' => $activeTillRows,
            'recentTillSessions' => $recentTillSessions,
            'customers' => $customers,
            'variants' => $variants,
            'categories' => $categories,
            'coupons' => $coupons,
            'recentOrders' => $recentOrders,
            'sessionOrders' => $sessionOrders,
            'paymentMethods' => $tenant->settings['payment_methods'] ?? ['Cash', 'Bank transfer', 'POS/Card', 'Cheque'],
            'paymentAccounts' => $this->paymentAccountsFor($tenant->id, $activeTill?->branch_id),
            'deliveryMethods' => $branches->flatMap(fn (Branch $branch) => collect($branch->settings['delivery_methods'] ?? []))->where('status', 'active')->values(),
        ]);
    }

    public function storeQuickCustomer(Request $request): JsonResponse
    {
        $tenantId = $request->string('tenant_id')->toString();
        $this->authorizeTenantIdAccess($request->user(), $tenantId);
        $request->merge([
            'phone' => preg_replace('/\s+/', '', $request->string('phone')->toString()),
        ]);

        $validator = Validator::make($request->all(), [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:40', Rule::unique('customers', 'phone')->where('tenant_id', $tenantId)],
            'email' => ['nullable', 'email', 'max:180'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Check the customer details and try again.',
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $data = $validator->validated();

        $customer = Customer::query()->create([
            'tenant_id' => $data['tenant_id'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'status' => 'active',
        ]);

        return response()->json([
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
        ]);
    }

    public function storeCoupon(SalesCouponRequest $request): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());
        $data = $request->validated();
        $coupon = SalesCoupon::query()->create(collect($data)->except('discount_value')->all() + [
            'discount_value_minor' => $data['discount_type'] === DiscountType::Amount->value ? $this->moneyToMinor($data['discount_value']) : 0,
            'discount_percent' => $data['discount_type'] === DiscountType::Percentage->value ? $data['discount_value'] : null,
        ]);

        return redirect()
            ->to(route('admin.catalog.index', ['tenant' => $coupon->tenant_id]).'#taxes-coupons')
            ->with('catalog_accordion', 'coupons')
            ->with('status', "Coupon {$coupon->code} created.");
    }

    public function storePayment(SalesPaymentRequest $request, SalesOrder $order, RecordSalesPaymentAction $action): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $order->tenant_id);
        $action->execute($order, $request->validated(), $request->user()->id);

        return redirect()->to(route('admin.sales.orders.index', ['tenant' => $order->tenant_id]).'#orders')->with('status', "Payment recorded for {$order->order_number}.");
    }

    public function cancelOrder(Request $request, SalesOrder $order, PostJournalEntryAction $postJournalEntry, AdjustInventoryReservationAction $reservations): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $order->tenant_id);

        DB::transaction(function () use ($order, $postJournalEntry, $reservations): void {
            $lockedOrder = SalesOrder::query()->with('items.variant.product')->lockForUpdate()->findOrFail($order->id);

            if (! in_array($lockedOrder->order_status, [SalesOrderStatus::Pending, SalesOrderStatus::Processing], true)) {
                throw ValidationException::withMessages([
                    'order' => 'Only pending or processing orders can be cancelled from here.',
                ]);
            }

            // Free any reserved stock so it becomes available to other shoppers.
            if ($lockedOrder->stock_reserved && $lockedOrder->inventory_location_id) {
                foreach ($lockedOrder->items as $item) {
                    if ($item->variant?->product?->product_type !== ProductType::Product) {
                        continue;
                    }

                    $reservations->release($lockedOrder->tenant_id, (int) $lockedOrder->inventory_location_id, (int) $item->product_variant_id, (int) $item->quantity);
                }
            }

            $creditMinor = max(0, (int) $lockedOrder->paid_minor - (int) $lockedOrder->refunded_minor);
            $lockedOrder->update([
                'order_status' => SalesOrderStatus::Cancelled->value,
                'payment_status' => $creditMinor > 0
                    ? SalesPaymentStatus::CustomerCredit->value
                    : SalesPaymentStatus::Unpaid->value,
                'customer_credit_minor' => $creditMinor,
                'stock_reserved' => false,
                'reserved_until' => null,
            ]);

            if ($creditMinor > 0) {
                // Deposits taken while pending are held in Customer Deposits (2310).
                // Cancellation converts that unrefunded amount to Customer Credit (2300).
                $postJournalEntry->execute(
                    $lockedOrder->tenant_id,
                    now()->toDateString(),
                    'Customer credit from cancelled order '.$lockedOrder->order_number,
                    [
                        ['account_code' => '2310', 'branch_id' => $lockedOrder->branch_id, 'debit_minor' => $creditMinor, 'party_type' => 'customer', 'party_id' => $lockedOrder->customer_id],
                        ['account_code' => '2300', 'branch_id' => $lockedOrder->branch_id, 'credit_minor' => $creditMinor, 'party_type' => 'customer', 'party_id' => $lockedOrder->customer_id],
                    ],
                    'sales_order',
                    $lockedOrder->id,
                    'cancelled_to_customer_credit',
                );
            }
        });

        return redirect()->to(route('admin.sales.orders.index', ['tenant' => $order->tenant_id]).'#orders')->with('status', "Order {$order->order_number} cancelled.");
    }

    public function markOrderRefunded(
        SalesOrderRefundRequest $request,
        SalesOrder $order,
        RefundCancelledOrderAction $refundAction,
        ApprovalService $approvals,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeTenantIdAccess($user, $order->tenant_id);
        $data = $request->validated();

        $redirect = route('admin.sales.orders.index', ['tenant' => $order->tenant_id]).'#orders';
        $refundMinor = $refundAction->refundableMinor($order->loadMissing('payments'));

        // Role refund cap: over the limit either routes to approval (if enabled) or is blocked.
        if ($refundMinor > 0 && ! $user->is_platform_admin) {
            $tenant = Tenant::query()->findOrFail($order->tenant_id);
            $limit = $user->permissionLimit($tenant, 'sales.refund.max_minor');

            if ($limit !== null && $refundMinor > (int) $limit) {
                if ($approvals->requiresApproval($tenant, 'refund')) {
                    $approvals->create($tenant, $user, 'refund', 'Refund · order '.$order->order_number, [
                        'branch_id' => $order->branch_id,
                        'amount_minor' => $refundMinor,
                        'payload' => ['order_id' => $order->id, 'data' => $data],
                        'description' => 'Refund of a cancelled order to customer credit.',
                    ]);

                    return redirect()->to($redirect)->with('status', "Refund for {$order->order_number} exceeds your limit and has been sent for approval.");
                }

                throw ValidationException::withMessages([
                    'order' => sprintf('This refund (%s) is above your limit of %s.', $tenant->currency_code.' '.number_format($refundMinor / 100, 2), $tenant->currency_code.' '.number_format((int) $limit / 100, 2)),
                ]);
            }
        }

        $refundAction->execute($order, $data, $user->id);

        return redirect()->to($redirect)->with('status', "Order {$order->order_number} marked as refunded.");
    }

    public function settlements(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $tenants = $this->visibleTenantsFor($user);
        $tenant = $this->resolveTenant($request, $tenants);

        abort_if(! $tenant, 403);

        $filters = $this->settlementReportFilters($request);
        $payments = $this->settlementReportQuery($tenant, $filters)->limit(300)->get();

        // Stats reflect ALL successful collections (unfiltered) for an accurate overview.
        $all = OnlineCollectedPayment::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'successful')
            ->get(['customer_total_minor', 'is_settled']);

        $payoutMode = \Modules\Sales\Enums\PayoutMode::fromTenant($tenant);
        $onlineStore = \Modules\Business\Models\OnlineStore::query()->where('tenant_id', $tenant->id)->first();
        $settlementBank = (array) ($onlineStore?->payment_settings['settlement_bank_account'] ?? []);

        return view('sales::admin.settlements.index', [
            'tenant' => $tenant,
            'tenants' => $tenants,
            'isPlatformAdmin' => $user->is_platform_admin,
            'payoutMode' => $payoutMode,
            'settlementBank' => $settlementBank,
            'payments' => $payments,
            'filters' => $filters,
            'payoutModes' => \Modules\Sales\Enums\PayoutMode::cases(),
            'stats' => [
                'earnings_minor' => (int) $all->sum('customer_total_minor'),
                'earnings_settled_minor' => (int) $all->where('is_settled', true)->sum('customer_total_minor'),
                'earnings_pending_minor' => (int) $all->where('is_settled', false)->sum('customer_total_minor'),
                'count' => $all->count(),
            ],
        ]);
    }

    public function settlementsStatement(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $tenant = $this->resolveTenant($request, $this->visibleTenantsFor($user));

        abort_if(! $tenant, 403);

        $filters = $this->settlementReportFilters($request);
        $payments = $this->settlementReportQuery($tenant, $filters)->get();
        $currency = $tenant->currency_code ?: 'NGN';
        $filename = 'settlements-'.$tenant->slug.'-'.now()->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($payments, $currency): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Order', 'Date', 'Customer', 'Gateway Reference', 'Mode', "Customer Total ({$currency})", "Gateway Charge ({$currency})", "Fees ({$currency})", 'Status', 'Settled At']);

            foreach ($payments as $payment) {
                fputcsv($handle, [
                    $payment->order?->order_number,
                    optional($payment->collected_at)->toDateTimeString(),
                    $payment->order?->customer?->name ?? $payment->customer_email,
                    $payment->provider_reference,
                    $payment->payout_mode,
                    number_format($payment->customer_total_minor / 100, 2, '.', ''),
                    number_format($payment->gateway_charge_minor / 100, 2, '.', ''),
                    number_format($payment->fees_minor / 100, 2, '.', ''),
                    $payment->is_settled ? 'Settled' : 'Pending',
                    optional($payment->settled_at)->toDateTimeString(),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Platform admin can flip an online collection's settlement status straight from the
     * report (e.g. mark a lingering Pending as Settled, or revert).
     */
    public function updateCollectedPaymentStatus(Request $request, OnlineCollectedPayment $payment): RedirectResponse
    {
        abort_unless($request->user()?->is_platform_admin, 403);

        $settled = $request->boolean('settled');
        $payment->forceFill([
            'is_settled' => $settled,
            'settled_at' => $settled ? ($payment->settled_at ?: now()) : null,
        ])->save();

        return back()->with('status', 'Payment '.$payment->provider_reference.' marked '.($settled ? 'settled' : 'pending').'.');
    }

    /**
     * @return array<string, string>
     */
    private function settlementReportFilters(Request $request): array
    {
        return [
            'mode' => $request->string('mode')->toString(),
            'status' => $request->string('status')->toString(),
            'from' => $request->string('from')->toString(),
            'to' => $request->string('to')->toString(),
            'search' => trim($request->string('search')->toString()),
        ];
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function settlementReportQuery(Tenant $tenant, array $filters)
    {
        return OnlineCollectedPayment::query()
            ->with('order.customer')
            ->where('tenant_id', $tenant->id)
            ->where('status', 'successful')
            ->when($filters['mode'] !== '', fn ($query) => $query->where('payout_mode', $filters['mode']))
            ->when($filters['status'] === 'settled', fn ($query) => $query->where('is_settled', true))
            ->when($filters['status'] === 'pending', fn ($query) => $query->where('is_settled', false))
            ->when($filters['from'] !== '', fn ($query) => $query->whereDate('collected_at', '>=', $filters['from']))
            ->when($filters['to'] !== '', fn ($query) => $query->whereDate('collected_at', '<=', $filters['to']))
            ->when($filters['search'] !== '', fn ($query) => $query->where(function ($query) use ($filters): void {
                $query->where('provider_reference', 'like', '%'.$filters['search'].'%')
                    ->orWhere('customer_email', 'like', '%'.$filters['search'].'%')
                    ->orWhereHas('order', fn ($orderQuery) => $orderQuery->where('order_number', 'like', '%'.$filters['search'].'%'));
            }))
            ->latest('collected_at');
    }

    public function wallet(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $tenants = $this->visibleTenantsFor($user);
        $tenant = $this->resolveTenant($request, $tenants);

        abort_if(! $tenant, 403);

        $payoutMode = \Modules\Sales\Enums\PayoutMode::fromTenant($tenant);
        $wallet = app(\Modules\Sales\Support\Wallet\WalletService::class)->walletFor($tenant);
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();
        $transactions = \Modules\Sales\Models\WalletTransaction::query()
            ->where('tenant_id', $tenant->id)
            ->when($from !== '', fn ($query) => $query->whereDate('created_at', '>=', $from))
            ->when($to !== '', fn ($query) => $query->whereDate('created_at', '<=', $to))
            ->latest('id')
            ->limit(200)
            ->get();
        $withdrawals = \Modules\Sales\Models\WalletWithdrawal::query()
            ->where('tenant_id', $tenant->id)
            ->latest('id')
            ->limit(50)
            ->get();
        $onlineStore = \Modules\Business\Models\OnlineStore::query()->where('tenant_id', $tenant->id)->first();
        $settlementBank = (array) ($onlineStore?->payment_settings['settlement_bank_account'] ?? []);
        // Withdrawal depends on having a settlement bank and a positive balance — not on the
        // payout mode. A business can always move any balance held for them.
        $hasSettlementBank = filled($settlementBank['bank_code'] ?? null) && filled($settlementBank['account_number'] ?? null);
        $canWithdraw = $hasSettlementBank && (int) $wallet->available_balance_minor > 0;

        return view('sales::admin.wallet.index', [
            'tenant' => $tenant,
            'tenants' => $tenants,
            'isPlatformAdmin' => $user->is_platform_admin,
            'payoutMode' => $payoutMode,
            'wallet' => $wallet,
            'transactions' => $transactions,
            'withdrawals' => $withdrawals,
            'settlementBank' => $settlementBank,
            'canWithdraw' => $canWithdraw,
            'hasSettlementBank' => $hasSettlementBank,
            'currency' => $tenant->currency_code ?: 'NGN',
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function walletStatement(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $tenant = $this->resolveTenant($request, $this->visibleTenantsFor($user));

        abort_if(! $tenant, 403);

        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();
        $currency = $tenant->currency_code ?: 'NGN';

        $transactions = \Modules\Sales\Models\WalletTransaction::query()
            ->where('tenant_id', $tenant->id)
            ->when($from !== '', fn ($query) => $query->whereDate('created_at', '>=', $from))
            ->when($to !== '', fn ($query) => $query->whereDate('created_at', '<=', $to))
            ->oldest('id')
            ->get();

        $filename = 'wallet-statement-'.$tenant->slug.'-'.now()->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($transactions, $currency): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Reference', 'Description', 'Category', 'Direction', 'State', "Amount ({$currency})"]);

            foreach ($transactions as $txn) {
                $signed = ($txn->direction === 'debit' ? -1 : 1) * ($txn->amount_minor / 100);
                fputcsv($handle, [
                    optional($txn->created_at)->toDateTimeString(),
                    $txn->reference,
                    $txn->description,
                    $txn->category,
                    $txn->direction,
                    $txn->state,
                    number_format($signed, 2, '.', ''),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function walletWithdrawPreview(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tenant = $this->resolveTenant($request, $this->visibleTenantsFor($user));

        abort_if(! $tenant, 403);

        $receiveMinor = $this->moneyToMinor($request->input('amount'));
        $currency = $tenant->currency_code ?: 'NGN';
        $gatewayFee = app(\Modules\Sales\Support\Payments\PaymentGatewayManager::class)
            ->payout((string) config('services.payments.default', 'paystack'))
            ->transferFeeMinor($receiveMinor, $currency);
        $platformFee = app(\Modules\Sales\Support\PlatformFees::class)->transferFeeMinor($tenant->id, $receiveMinor);
        $available = (int) app(\Modules\Sales\Support\Wallet\WalletService::class)->walletFor($tenant)->available_balance_minor;
        $total = $receiveMinor + $gatewayFee + $platformFee;

        return response()->json([
            'amount_minor' => $receiveMinor,
            'gateway_fee_minor' => $gatewayFee,
            'platform_fee_minor' => $platformFee,
            'total_minor' => $total,
            'available_minor' => $available,
            'affordable' => $receiveMinor > 0 && $total <= $available,
        ]);
    }

    public function walletWithdraw(Request $request, \Modules\Sales\Actions\RequestWalletWithdrawalAction $withdraw): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tenant = $this->resolveTenant($request, $this->visibleTenantsFor($user));

        abort_if(! $tenant, 403);

        $data = $request->validate(['amount' => ['required']]);
        $receiveMinor = $this->moneyToMinor($data['amount']);

        try {
            $withdrawal = $withdraw->execute($tenant, $receiveMinor, $user->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        $currency = $tenant->currency_code ?: 'NGN';

        return back()->with('status', 'Withdrawal of '.$currency.' '.number_format($withdrawal->amount_minor / 100, 2).' started — status: '.$withdrawal->status.'.');
    }

    public function updatePayoutMode(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->is_platform_admin, 403);

        $tenant = $this->resolveTenant($request, $this->visibleTenantsFor($request->user()));
        abort_if(! $tenant, 403);

        $data = $request->validate([
            'payout_mode' => ['required', Rule::in(array_map(static fn (\Modules\Sales\Enums\PayoutMode $mode): string => $mode->value, \Modules\Sales\Enums\PayoutMode::cases()))],
        ]);

        $tenant->settings = array_merge($tenant->settings ?? [], ['payout_mode' => $data['payout_mode']]);
        $tenant->save();

        return back()->with('status', 'Payout mode set to '.\Modules\Sales\Enums\PayoutMode::from($data['payout_mode'])->label().'.');
    }

    public function openTill(TillOpenRequest $request, PostJournalEntryAction $postJournalEntry): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());
        $user = $request->user();
        $data = $request->validated();

        abort_if(
            SalesTillSession::query()->where('tenant_id', $data['tenant_id'])->where('user_id', $user->id)->where('status', 'open')->exists(),
            422,
            'Close your current till before opening another branch session.',
        );

        $session = SalesTillSession::query()->create([
            'tenant_id' => $data['tenant_id'],
            'branch_id' => $data['branch_id'],
            'user_id' => $user->id,
            'session_number' => $this->tillNumber($data['tenant_id']),
            'status' => 'open',
            'opening_float_minor' => $this->moneyToMinor($data['opening_float'] ?? 0),
            'opened_at' => now(),
            'opening_note' => $data['opening_note'] ?? null,
        ]);
        $vault = $this->ensureBranchVault($session);
        $till = $this->ensureTillCashLocation($session);
        $openingFloatMinor = (int) $session->opening_float_minor;

        if ($openingFloatMinor > 0) {
            $postJournalEntry->execute(
                $session->tenant_id,
                now()->toDateString(),
                'Opening float for '.$session->session_number,
                [
                    ['account_code' => $till->financeAccount->code, 'branch_id' => $session->branch_id, 'debit_minor' => $openingFloatMinor, 'memo' => 'Cash issued to cashier till.'],
                    ['account_code' => $vault->financeAccount->code, 'branch_id' => $session->branch_id, 'credit_minor' => $openingFloatMinor, 'memo' => 'Cash issued from branch safe vault.'],
                ],
                'sales_till_session',
                $session->id,
                'opened',
            );

            $till->increment('balance_minor', $openingFloatMinor);
            $vault->decrement('balance_minor', $openingFloatMinor);
        }

        return redirect()->to(route('admin.sales.index', ['tenant' => $session->tenant_id]).'#till')->with('status', "Till {$session->session_number} opened.");
    }

    public function storeTillMovement(TillMovementRequest $request, SalesTillSession $tillSession, PostJournalEntryAction $postJournalEntry): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $tillSession->tenant_id);
        abort_unless($tillSession->user_id === $request->user()->id && $tillSession->status === 'open', 403);
        $data = $request->validated();

        $movement = $tillSession->movements()->create([
            'tenant_id' => $tillSession->tenant_id,
            'user_id' => $request->user()->id,
            'movement_type' => $data['movement_type'],
            'payment_method' => 'Cash',
            'amount_minor' => $this->moneyToMinor($data['amount']),
            'reference_number' => $data['reference_number'] ?? null,
            'notes' => $data['notes'] ?? null,
            'occurred_at' => now(),
        ]);
        $amountMinor = (int) $movement->amount_minor;
        $till = $this->ensureTillCashLocation($tillSession);

        $vault = $this->ensureBranchVault($tillSession);

        if ($movement->movement_type === 'cash_in') {
            $postJournalEntry->execute(
                $tillSession->tenant_id,
                now()->toDateString(),
                'Cash issued to '.$tillSession->session_number,
                [
                    ['account_code' => $till->financeAccount->code, 'branch_id' => $tillSession->branch_id, 'debit_minor' => $amountMinor, 'memo' => 'Cash received into cashier till.'],
                    ['account_code' => $vault->financeAccount->code, 'branch_id' => $tillSession->branch_id, 'credit_minor' => $amountMinor, 'memo' => 'Cash issued from branch safe vault.'],
                ],
                'sales_till_movement',
                $movement->id,
                'cash_in',
            );
            $till->increment('balance_minor', $amountMinor);
            $vault->decrement('balance_minor', $amountMinor);
        } elseif (in_array($movement->movement_type, ['cash_deposit', 'cash_out'], true)) {
            $postJournalEntry->execute(
                $tillSession->tenant_id,
                now()->toDateString(),
                ($movement->movement_type === 'cash_deposit' ? 'Move to vault from ' : 'Cash out from ').$tillSession->session_number,
                [
                    ['account_code' => $vault->financeAccount->code, 'branch_id' => $tillSession->branch_id, 'debit_minor' => $amountMinor, 'memo' => 'Cash received into branch safe vault.'],
                    ['account_code' => $till->financeAccount->code, 'branch_id' => $tillSession->branch_id, 'credit_minor' => $amountMinor, 'memo' => 'Cash remitted from cashier till.'],
                ],
                'sales_till_movement',
                $movement->id,
                $movement->movement_type,
            );
            $till->decrement('balance_minor', $amountMinor);
            $vault->increment('balance_minor', $amountMinor);
        } elseif ($movement->movement_type === 'petty_cash_withdrawal') {
            $postJournalEntry->execute(
                $tillSession->tenant_id,
                now()->toDateString(),
                'Petty cash withdrawal from '.$tillSession->session_number,
                [
                    ['account_code' => '1010', 'branch_id' => $tillSession->branch_id, 'debit_minor' => $amountMinor, 'memo' => 'Petty cash funded from cashier till.'],
                    ['account_code' => $till->financeAccount->code, 'branch_id' => $tillSession->branch_id, 'credit_minor' => $amountMinor, 'memo' => 'Cash withdrawn from cashier till.'],
                ],
                'sales_till_movement',
                $movement->id,
                'petty_cash_withdrawal',
            );
            $till->decrement('balance_minor', $amountMinor);
        }

        return redirect()->to(route('admin.sales.index', ['tenant' => $tillSession->tenant_id]).'#till')->with('status', 'Till movement recorded.');
    }

    public function closeTill(TillCloseRequest $request, SalesTillSession $tillSession, PostJournalEntryAction $postJournalEntry): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $tillSession->tenant_id);
        abort_unless($tillSession->user_id === $request->user()->id && $tillSession->status === 'open', 403);
        $data = $request->validated();
        $rows = $this->tillBreakdown($tillSession->fresh(['payments', 'movements']), $tillSession->tenant);
        $actuals = collect($data['actuals'] ?? []);
        $varianceTotalMinor = 0;
        $actualTotalMinor = 0;

        foreach ($rows as $row) {
            $actualMinor = $this->moneyToMinor($actuals->get($row['method'], 0));
            $varianceMinor = $actualMinor - $row['expected_minor'];
            $varianceTotalMinor += $varianceMinor;
            $actualTotalMinor += $actualMinor;

            $tillSession->closingCounts()->updateOrCreate([
                'payment_method' => $row['method'],
            ], [
                'tenant_id' => $tillSession->tenant_id,
                'expected_minor' => $row['expected_minor'],
                'actual_minor' => $actualMinor,
                'variance_minor' => $varianceMinor,
            ]);
        }

        $hasVariance = $varianceTotalMinor !== 0;
        $bookVariance = (bool) ($data['book_variance'] ?? false);

        if ($hasVariance && ! $bookVariance) {
            return redirect()
                ->to(route('admin.sales.index', ['tenant' => $tillSession->tenant_id]).'#till')
                ->withErrors(['actuals' => 'Till variances must be 0 to close, or tick "book the variance as loss/gain" to write it off.'])
                ->withInput();
        }

        $cashExpectedMinor = (int) ($rows->firstWhere('method', 'Cash')['expected_minor'] ?? 0);
        $cashActualMinor = $this->moneyToMinor($actuals->get('Cash', 0));

        $tillSession->update([
            'status' => 'closed',
            'expected_cash_minor' => $cashExpectedMinor,
            'expected_total_minor' => (int) $rows->sum('expected_minor'),
            'actual_total_minor' => $actualTotalMinor,
            'variance_total_minor' => $varianceTotalMinor,
            'closed_at' => now(),
            'closing_note' => $data['closing_note'] ?? null,
        ]);

        $till = $this->ensureTillCashLocation($tillSession);
        $vault = $this->ensureBranchVault($tillSession);

        // Hand the physically counted cash over to the branch safe vault.
        if ($cashActualMinor > 0) {
            $postJournalEntry->execute(
                $tillSession->tenant_id,
                now()->toDateString(),
                'Till close cash handover for '.$tillSession->session_number,
                [
                    ['account_code' => $vault->financeAccount->code, 'branch_id' => $tillSession->branch_id, 'debit_minor' => $cashActualMinor, 'memo' => 'Counted cash received into branch safe vault.'],
                    ['account_code' => $till->financeAccount->code, 'branch_id' => $tillSession->branch_id, 'credit_minor' => $cashActualMinor, 'memo' => 'Counted cash handed over from cashier till.'],
                ],
                'sales_till_session',
                $tillSession->id,
                'closed_remitted',
            );

            $till->decrement('balance_minor', $cashActualMinor);
            $vault->increment('balance_minor', $cashActualMinor);
        }

        // Write off the drawer variance to Cash Short & Over so the till zeroes out.
        if ($hasVariance && $bookVariance) {
            $shortOver = $this->ensureCashShortOverAccount($tillSession->tenant_id);
            $amount = abs($varianceTotalMinor);
            $note = $data['variance_note'] ?? null;

            if ($varianceTotalMinor < 0) {
                // Shortage — recognise a loss.
                $postJournalEntry->execute(
                    $tillSession->tenant_id,
                    now()->toDateString(),
                    'Till shortage written off for '.$tillSession->session_number.($note ? ' — '.$note : ''),
                    [
                        ['account_code' => $shortOver->code, 'branch_id' => $tillSession->branch_id, 'debit_minor' => $amount, 'memo' => 'Cash drawer shortage recognised as loss.'],
                        ['account_code' => $till->financeAccount->code, 'branch_id' => $tillSession->branch_id, 'credit_minor' => $amount, 'memo' => 'Shortage removed from cashier till.'],
                    ],
                    'sales_till_session',
                    $tillSession->id,
                    'variance_shortage',
                );
                $till->decrement('balance_minor', $amount);
            } else {
                // Overage — recognise a gain (contra to the short/over account).
                $postJournalEntry->execute(
                    $tillSession->tenant_id,
                    now()->toDateString(),
                    'Till overage recognised for '.$tillSession->session_number.($note ? ' — '.$note : ''),
                    [
                        ['account_code' => $till->financeAccount->code, 'branch_id' => $tillSession->branch_id, 'debit_minor' => $amount, 'memo' => 'Cash drawer overage added to cashier till.'],
                        ['account_code' => $shortOver->code, 'branch_id' => $tillSession->branch_id, 'credit_minor' => $amount, 'memo' => 'Cash drawer overage recognised as gain.'],
                    ],
                    'sales_till_session',
                    $tillSession->id,
                    'variance_overage',
                );
                $till->increment('balance_minor', $amount);
            }
        }

        return redirect()->to(route('admin.sales.index', ['tenant' => $tillSession->tenant_id]).'#till')->with('status', $hasVariance ? "Till {$tillSession->session_number} closed and variance booked." : "Till {$tillSession->session_number} closed.");
    }

    public function updateDeliveryStatus(Request $request, SalesOrder $order): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $order->tenant_id);
        $data = $request->validate([
            'delivery_status' => ['required', 'in:pending,processing,out_for_delivery,delivered,failed,returned'],
        ]);

        $order->update(['delivery_status' => $data['delivery_status']]);

        return redirect()->to(route('admin.sales.orders.index', ['tenant' => $order->tenant_id]).'#orders')->with('status', "Delivery status updated for {$order->order_number}.");
    }

    public function updateOrderStatus(
        Request $request,
        SalesOrder $order,
        CompleteSalesOrderAction $completeSalesOrder,
    ): RedirectResponse {
        $this->authorizeTenantIdAccess($request->user(), $order->tenant_id);
        $data = $request->validate([
            'order_status' => ['required', Rule::in([
                SalesOrderStatus::Pending->value,
                SalesOrderStatus::Processing->value,
                SalesOrderStatus::Completed->value,
            ])],
        ]);

        $requestedStatus = SalesOrderStatus::from($data['order_status']);
        $currentStatus = $order->order_status;

        if ($currentStatus === SalesOrderStatus::Completed && $requestedStatus !== SalesOrderStatus::Completed) {
            throw ValidationException::withMessages([
                'order_status' => 'A completed order cannot be moved back to pending or processing.',
            ]);
        }

        if ($requestedStatus === SalesOrderStatus::Completed) {
            $completeSalesOrder->execute($order);
        } elseif ($currentStatus !== $requestedStatus) {
            $order->update(['order_status' => $requestedStatus->value]);
        }

        return redirect()->to(route('admin.sales.orders.index', ['tenant' => $order->tenant_id]).'#orders')->with('status', "Order status updated for {$order->order_number}.");
    }

    public function storeReturn(
        SalesReturnRequest $request,
        SalesOrder $order,
        ProcessSalesReturnAction $action,
        ApprovalService $approvals,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeTenantIdAccess($user, $order->tenant_id);

        $order->load('items.variant', 'customer', 'branch');

        if (! in_array($order->order_status, [SalesOrderStatus::Completed, SalesOrderStatus::PartiallyReturned], true)) {
            throw ValidationException::withMessages([
                'order' => 'Only completed orders can be returned.',
            ]);
        }

        $data = $request->validated();
        $ordersUrl = route('admin.sales.orders.index', ['tenant' => $order->tenant_id]).'#orders';

        if (! $user->is_platform_admin) {
            $tenant = Tenant::query()->findOrFail($order->tenant_id);
            $refundMinor = $action->previewRefundMinor($order, $data);
            $approvalsOn = $approvals->requiresApproval($tenant, 'refund');
            $mustApprove = false;

            if ($user->hasPermission($tenant, 'sales.refunds.issue')) {
                // Can issue directly — but a refund over the role cap needs approval (if enabled) or is blocked.
                $limit = $user->permissionLimit($tenant, 'sales.refund.max_minor');

                if ($limit !== null && $refundMinor > (int) $limit) {
                    if ($approvalsOn) {
                        $mustApprove = true;
                    } else {
                        throw ValidationException::withMessages([
                            'items' => sprintf('This return (%s) is above your refund limit of %s.', $tenant->currency_code.' '.number_format($refundMinor / 100, 2), $tenant->currency_code.' '.number_format((int) $limit / 100, 2)),
                        ]);
                    }
                }
            } elseif ($user->hasPermission($tenant, 'sales.refunds.request')) {
                // Requester: can only submit for approval, and only when approvals are enabled.
                if (! $approvalsOn) {
                    throw ValidationException::withMessages([
                        'items' => 'You can request returns, but a manager must issue them. Ask an owner to enable refund approvals.',
                    ]);
                }
                $mustApprove = true;
            } else {
                abort(403, 'You do not have permission to process returns.');
            }

            if ($mustApprove) {
                $approvals->create($tenant, $user, 'refund', 'Return · order '.$order->order_number, [
                    'branch_id' => $order->branch_id,
                    'amount_minor' => $refundMinor,
                    'payload' => ['kind' => 'return', 'order_id' => $order->id, 'data' => $data],
                    'description' => 'Product return to customer credit.',
                    'request_note' => $data['reason'] ?? null,
                ]);

                return redirect()->to($ordersUrl)->with('status', 'Return submitted for approval.');
            }
        }

        $salesReturn = $action->execute($order, $data);

        return redirect()->to(route('admin.sales.orders.index', ['tenant' => $salesReturn->tenant_id]).'#returns')->with('status', "Return {$salesReturn->return_number} processed.");
    }

    private function ensureCashShortOverAccount(string $tenantId): FinanceAccount
    {
        $account = FinanceAccount::query()->firstOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'EXP-6370'],
            [
                'name' => 'Cash Short & Over (Till Variance)',
                'type' => 'expense',
                'category' => 'Admin & Ops',
                'description' => 'Cash drawer shortages and overages recognised at till close.',
                'normal_balance' => 'debit',
                'is_system' => true,
                'is_active' => true,
            ],
        );

        return $account;
    }

    private function walkInCustomer(Tenant $tenant): Customer
    {
        return Customer::query()->firstOrCreate([
            'tenant_id' => $tenant->id,
            'phone' => 'WALK-IN',
        ], [
            'first_name' => 'Walk-In',
            'last_name' => 'Customer',
            'status' => 'active',
        ]);
    }

    private function tillNumber(string $tenantId): string
    {
        return 'TILL-'.now()->format('Ymd').'-'.str_pad((string) (SalesTillSession::query()->where('tenant_id', $tenantId)->whereDate('opened_at', now()->toDateString())->count() + 1), 4, '0', STR_PAD_LEFT);
    }

    private function ensureBranchVault(SalesTillSession $tillSession): SalesCashLocation
    {
        if ($tillSession->vaultCashLocation?->financeAccount?->code === '1030') {
            $tillSession->vaultCashLocation->financeAccount->fill([
                'name' => 'Branch Safe / Vault',
                'type' => 'asset',
                'category' => 'Current Assets',
                'description' => 'Cash held in branch safes or vaults before banking.',
                'normal_balance' => 'debit',
            ])->save();

            return $tillSession->vaultCashLocation;
        }

        $account = FinanceAccount::query()->firstOrCreate([
            'tenant_id' => $tillSession->tenant_id,
            'code' => '1030',
        ], [
            'name' => 'Branch Safe / Vault',
            'type' => 'asset',
            'category' => 'Current Assets',
            'description' => 'Cash held in branch safes or vaults before banking.',
            'normal_balance' => 'debit',
            'is_system' => true,
            'is_active' => true,
        ]);
        $account->fill([
            'name' => 'Branch Safe / Vault',
            'type' => 'asset',
            'category' => 'Current Assets',
            'description' => 'Cash held in branch safes or vaults before banking.',
            'normal_balance' => 'debit',
            'is_system' => true,
            'is_active' => true,
        ])->save();

        $code = 'BV-'.$tillSession->branch_id;
        $location = SalesCashLocation::query()->firstOrCreate([
            'tenant_id' => $tillSession->tenant_id,
            'code' => $code,
        ], [
            'branch_id' => $tillSession->branch_id,
            'finance_account_id' => $account->id,
            'name' => 'Branch Safe Vault - '.($tillSession->branch?->name ?? 'Branch '.$tillSession->branch_id),
            'location_type' => 'vault',
            'is_active' => true,
        ]);
        $location->fill(['finance_account_id' => $account->id])->save();

        if (! $tillSession->vault_cash_location_id) {
            $tillSession->update(['vault_cash_location_id' => $location->id]);
        }

        return $location->load('financeAccount');
    }

    private function ensureTillCashLocation(SalesTillSession $tillSession): SalesCashLocation
    {
        if ($tillSession->cashLocation?->financeAccount?->code === '1020') {
            $tillSession->cashLocation->financeAccount->fill([
                'name' => 'Cash in Tills',
                'type' => 'asset',
                'category' => 'Current Assets',
                'description' => 'Cash currently held by cashier tills and registers.',
                'normal_balance' => 'debit',
            ])->save();

            return $tillSession->cashLocation;
        }

        $account = FinanceAccount::query()->firstOrCreate([
            'tenant_id' => $tillSession->tenant_id,
            'code' => '1020',
        ], [
            'name' => 'Cash in Tills',
            'type' => 'asset',
            'category' => 'Current Assets',
            'description' => 'Cash currently held by cashier tills and registers.',
            'normal_balance' => 'debit',
            'is_system' => true,
            'is_active' => true,
        ]);
        $account->fill([
            'name' => 'Cash in Tills',
            'type' => 'asset',
            'category' => 'Current Assets',
            'description' => 'Cash currently held by cashier tills and registers.',
            'normal_balance' => 'debit',
            'is_system' => true,
            'is_active' => true,
        ])->save();

        $code = 'CT-'.$tillSession->id;
        $location = SalesCashLocation::query()->firstOrCreate([
            'tenant_id' => $tillSession->tenant_id,
            'code' => $code,
        ], [
            'branch_id' => $tillSession->branch_id,
            'sales_till_session_id' => $tillSession->id,
            'user_id' => $tillSession->user_id,
            'finance_account_id' => $account->id,
            'name' => 'Cashier Till '.$tillSession->session_number,
            'location_type' => 'till',
            'is_active' => true,
        ]);
        $location->fill(['finance_account_id' => $account->id])->save();

        if (! $tillSession->cash_location_id) {
            $tillSession->update(['cash_location_id' => $location->id]);
        }

        return $location->load('financeAccount');
    }

    /**
     * @return Collection<int, array{method: string, expected_minor: int, collected_minor: int, movement_minor: int}>
     */
    private function tillBreakdown(SalesTillSession $tillSession, Tenant $tenant): Collection
    {
        $paymentMethods = collect($tenant->settings['payment_methods'] ?? ['Cash', 'Bank transfer', 'POS/Card', 'Cheque'])
            ->map(fn (string $method): string => trim($method))
            ->filter()
            ->values();

        if (! $paymentMethods->contains('Cash')) {
            $paymentMethods->prepend('Cash');
        }

        $payments = $tillSession->payments()
            ->selectRaw('payment_method, SUM(amount_minor) as amount_minor')
            ->groupBy('payment_method')
            ->pluck('amount_minor', 'payment_method')
            ->map(fn (mixed $amount): int => (int) $amount);

        $methods = $paymentMethods
            ->merge($payments->keys())
            ->unique(fn (string $method): string => strtolower($method))
            ->values();

        $cashMovementMinor = (int) $tillSession->movements()
            ->get()
            ->sum(function ($movement): int {
                $amount = (int) $movement->amount_minor;

                return in_array($movement->movement_type, ['cash_in'], true) ? $amount : -$amount;
            });

        return $methods->map(function (string $method) use ($tillSession, $payments, $cashMovementMinor): array {
            $collectedMinor = (int) ($payments[$method] ?? 0);
            $movementMinor = strtolower($method) === 'cash' ? $cashMovementMinor : 0;
            $expectedMinor = $collectedMinor + $movementMinor + (strtolower($method) === 'cash' ? (int) $tillSession->opening_float_minor : 0);

            return [
                'method' => $method,
                'expected_minor' => max(0, $expectedMinor),
                'collected_minor' => $collectedMinor,
                'movement_minor' => $movementMinor,
            ];
        });
    }

    private function refundAccountCodeFor(SalesOrder $order): string
    {
        $payment = $order->payments->first();

        if ($payment && $this->isCashMethod($payment->payment_method) && $payment->tillSession?->cashLocation?->financeAccount) {
            return $payment->tillSession->cashLocation->financeAccount->code;
        }

        return $this->nonCashAccountFor($payment?->payment_method);
    }

    private function isCashMethod(?string $paymentMethod): bool
    {
        return str_contains(strtolower((string) $paymentMethod), 'cash');
    }

    private function nonCashAccountFor(?string $paymentMethod): string
    {
        $method = strtolower((string) $paymentMethod);

        return match (true) {
            str_contains($method, 'pos'), str_contains($method, 'card') => '1050',
            str_contains($method, 'online'), str_contains($method, 'paystack'), str_contains($method, 'gateway') => '1060',
            default => '1040',
        };
    }

    private function paymentAccountsFor(string $tenantId, ?int $branchId = null): EloquentCollection
    {
        return BusinessPaymentAccount::query()
            ->with(['branch', 'financeAccount'])
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->when($branchId !== null, fn ($query) => $query->where(fn ($inner) => $inner->whereNull('branch_id')->orWhere('branch_id', $branchId)))
            ->orderBy('sort_order')
            ->orderBy('identifier')
            ->get();
    }

    private function moneyToMinor(mixed $value): int
    {
        return (int) round(((float) (is_string($value) ? str_replace(',', '', $value) : ($value ?: 0))) * 100);
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
