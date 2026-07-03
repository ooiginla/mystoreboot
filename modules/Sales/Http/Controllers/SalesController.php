<?php

declare(strict_types=1);

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Access\Enums\MembershipStatus;
use Modules\Access\Models\TenantMembership;
use Modules\Business\Models\Branch;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Models\ProductVariant;
use Modules\Customers\Models\Customer;
use Modules\Finance\Actions\PostJournalEntryAction;
use Modules\Finance\Models\FinanceAccount;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Sales\Actions\CreateSalesOrderAction;
use Modules\Sales\Actions\ProcessSalesReturnAction;
use Modules\Sales\Actions\RecordSalesPaymentAction;
use Modules\Sales\Enums\DiscountType;
use Modules\Sales\Enums\SalesOrderStatus;
use Modules\Sales\Enums\SalesPaymentStatus;
use Modules\Sales\Http\Requests\SalesCouponRequest;
use Modules\Sales\Http\Requests\SalesOrderRequest;
use Modules\Sales\Http\Requests\SalesPaymentRequest;
use Modules\Sales\Http\Requests\SalesReturnRequest;
use Modules\Sales\Http\Requests\TillCloseRequest;
use Modules\Sales\Http\Requests\TillMovementRequest;
use Modules\Sales\Http\Requests\TillOpenRequest;
use Modules\Sales\Models\OnlineCollectedPayment;
use Modules\Sales\Models\OnlinePaymentSettlement;
use Modules\Sales\Models\SalesCoupon;
use Modules\Sales\Models\SalesCashLocation;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesTillSession;
use Modules\Tenancy\Models\Tenant;

final class SalesController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $tenants = $this->visibleTenantsFor($user);
        $tenant = $this->resolveTenant($request, $tenants);

        abort_if(! $tenant, 403);

        $walkInCustomer = $this->walkInCustomer($tenant);
        $orderSearch = trim($request->string('order_search')->toString());
        $branches = Branch::query()->where('tenant_id', $tenant->id)->orderByDesc('is_primary')->orderBy('name')->get();
        $locations = InventoryLocation::query()->where('tenant_id', $tenant->id)->orderBy('name')->get();
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
            'locations' => $locations,
            'activeTill' => $activeTill,
            'activeTillRows' => $activeTillRows,
            'recentTillSessions' => $recentTillSessions,
            'customers' => $customers,
            'variants' => $variants,
            'orders' => $orders,
            'allOrders' => $allOrders,
            'coupons' => $coupons,
            'orderSearch' => $orderSearch,
            'paymentMethods' => $tenant->settings['payment_methods'] ?? ['Cash', 'Bank transfer', 'POS/Card', 'Cheque'],
            'deliveryMethods' => $branches->flatMap(fn (Branch $branch) => collect($branch->settings['delivery_methods'] ?? []))->where('status', 'active')->values(),
            'discountTypes' => DiscountType::cases(),
            'orderStatuses' => SalesOrderStatus::cases(),
            'paymentStatuses' => SalesPaymentStatus::cases(),
            'stats' => [
                'orders' => $allOrders->count(),
                'revenue_minor' => $allOrders->sum('paid_minor'),
                'credit_minor' => $allOrders->sum(fn (SalesOrder $order): int => $order->balance_minor),
                'returns_minor' => $allOrders->sum('refunded_minor'),
            ],
        ]);
    }

    public function storeOrder(SalesOrderRequest $request, CreateSalesOrderAction $action): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());
        $order = $action->execute($request->validated(), $request->user()->id);

        return redirect()
            ->to(route('admin.sales.index', ['tenant' => $order->tenant_id]).'#orders')
            ->with('status', "Sales order {$order->order_number} created.")
            ->with('receipt_order_id', $order->id);
    }

    public function storeCoupon(SalesCouponRequest $request): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());
        $data = $request->validated();
        $coupon = SalesCoupon::query()->create(collect($data)->except('discount_value')->all() + [
            'discount_value_minor' => $data['discount_type'] === DiscountType::Amount->value ? $this->moneyToMinor($data['discount_value']) : 0,
            'discount_percent' => $data['discount_type'] === DiscountType::Percentage->value ? $data['discount_value'] : null,
        ]);

        return redirect()->to(route('admin.sales.index', ['tenant' => $coupon->tenant_id]).'#coupons')->with('status', "Coupon {$coupon->code} created.");
    }

    public function storePayment(SalesPaymentRequest $request, SalesOrder $order, RecordSalesPaymentAction $action): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $order->tenant_id);
        $action->execute($order, $request->validated(), $request->user()->id);

        return redirect()->to(route('admin.sales.index', ['tenant' => $order->tenant_id]).'#orders')->with('status', "Payment recorded for {$order->order_number}.");
    }

    public function cancelOrder(Request $request, SalesOrder $order, PostJournalEntryAction $postJournalEntry): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $order->tenant_id);

        DB::transaction(function () use ($order, $postJournalEntry): void {
            $lockedOrder = SalesOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($lockedOrder->order_status !== SalesOrderStatus::Pending) {
                throw ValidationException::withMessages([
                    'order' => 'Only pending orders can be cancelled from here.',
                ]);
            }

            $creditMinor = max(0, (int) $lockedOrder->paid_minor - (int) $lockedOrder->refunded_minor);
            $lockedOrder->update([
                'order_status' => SalesOrderStatus::Cancelled->value,
                'payment_status' => $creditMinor > 0
                    ? SalesPaymentStatus::CustomerCredit->value
                    : SalesPaymentStatus::Unpaid->value,
            ]);

            if ($creditMinor > 0) {
                $postJournalEntry->execute(
                    $lockedOrder->tenant_id,
                    now()->toDateString(),
                    'Customer credit from cancelled order '.$lockedOrder->order_number,
                    [
                        ['account_code' => '1100', 'branch_id' => $lockedOrder->branch_id, 'debit_minor' => $creditMinor, 'party_type' => 'customer', 'party_id' => $lockedOrder->customer_id],
                        ['account_code' => '2300', 'branch_id' => $lockedOrder->branch_id, 'credit_minor' => $creditMinor, 'party_type' => 'customer', 'party_id' => $lockedOrder->customer_id],
                    ],
                    'sales_order',
                    $lockedOrder->id,
                    'cancelled_to_customer_credit',
                );
            }
        });

        return redirect()->to(route('admin.sales.index', ['tenant' => $order->tenant_id]).'#orders')->with('status', "Order {$order->order_number} cancelled.");
    }

    public function markOrderRefunded(Request $request, SalesOrder $order, PostJournalEntryAction $postJournalEntry): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $order->tenant_id);

        DB::transaction(function () use ($order, $postJournalEntry): void {
            $lockedOrder = SalesOrder::query()
                ->with(['payments.tillSession.cashLocation.financeAccount'])
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($lockedOrder->order_status !== SalesOrderStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'order' => 'Only cancelled orders can be marked as refunded.',
                ]);
            }

            $refundMinor = max(0, (int) $lockedOrder->paid_minor - (int) $lockedOrder->refunded_minor);

            if ($refundMinor <= 0) {
                throw ValidationException::withMessages([
                    'order' => 'There is no customer credit left to refund for this order.',
                ]);
            }

            $refundAccountCode = $this->refundAccountCodeFor($lockedOrder);
            $lockedOrder->update([
                'refunded_minor' => (int) $lockedOrder->paid_minor,
                'payment_status' => SalesPaymentStatus::Refunded->value,
            ]);

            $postJournalEntry->execute(
                $lockedOrder->tenant_id,
                now()->toDateString(),
                'Refund for cancelled order '.$lockedOrder->order_number,
                [
                    ['account_code' => '2300', 'branch_id' => $lockedOrder->branch_id, 'debit_minor' => $refundMinor, 'party_type' => 'customer', 'party_id' => $lockedOrder->customer_id],
                    ['account_code' => $refundAccountCode, 'branch_id' => $lockedOrder->branch_id, 'credit_minor' => $refundMinor, 'party_type' => 'customer', 'party_id' => $lockedOrder->customer_id],
                ],
                'sales_order',
                $lockedOrder->id,
                'refunded_cancelled_order',
            );

            $cashPayment = $lockedOrder->payments->first(fn ($payment): bool => $this->isCashMethod($payment->payment_method));

            if ($cashPayment?->tillSession?->cashLocation) {
                $cashPayment->tillSession->cashLocation->decrement('balance_minor', $refundMinor);
            }
        });

        return redirect()->to(route('admin.sales.index', ['tenant' => $order->tenant_id]).'#orders')->with('status', "Order {$order->order_number} marked as refunded.");
    }

    public function settlements(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $tenants = $this->visibleTenantsFor($user);
        $tenant = $this->resolveTenant($request, $tenants);

        abort_if(! $tenant, 403);

        $settlements = OnlinePaymentSettlement::query()
            ->where('tenant_id', $tenant->id)
            ->latest('settlement_date')
            ->latest()
            ->get();
        $unsettledPayments = OnlineCollectedPayment::query()
            ->with('order.customer')
            ->where('tenant_id', $tenant->id)
            ->where('status', 'successful')
            ->where('is_settled', false)
            ->latest('collected_at')
            ->get();

        return view('sales::admin.settlements.index', [
            'tenant' => $tenant,
            'tenants' => $tenants,
            'isPlatformAdmin' => $user->is_platform_admin,
            'settlements' => $settlements,
            'unsettledPayments' => $unsettledPayments,
            'stats' => [
                'unsettled_count' => $unsettledPayments->count(),
                'unsettled_minor' => $unsettledPayments->sum('amount_minor'),
                'settled_minor' => $settlements->sum('total_settled_minor'),
                'total_gateway_charge_minor' => $settlements->sum('total_gateway_charge_minor'),
                'storeboot_charges_minor' => $settlements->sum('storeboot_charges_minor'),
            ],
        ]);
    }

    public function adminSettlements(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->is_platform_admin, 403);

        $tenants = Tenant::query()->orderBy('name')->get();
        $selectedTenant = $request->filled('tenant')
            ? Tenant::query()->find($request->string('tenant')->toString())
            : $tenants->first();
        $selectedTenant ??= $tenants->first();
        $filters = [
            'tenant' => $selectedTenant?->id,
            'reference' => trim($request->string('reference')->toString()),
            'provider' => trim($request->string('provider')->toString()),
            'status' => trim($request->string('status')->toString()),
            'currency' => trim($request->string('currency')->toString()),
            'settlement_date_from' => $request->string('settlement_date_from')->toString(),
            'settlement_date_to' => $request->string('settlement_date_to')->toString(),
            'created_from' => $request->string('created_from')->toString(),
            'created_to' => $request->string('created_to')->toString(),
            'settled_from' => $request->string('settled_from')->toString(),
            'settled_to' => $request->string('settled_to')->toString(),
            'notes' => trim($request->string('notes')->toString()),
        ];
        $paymentFilters = [
            'tenant' => $selectedTenant?->id,
            'order' => trim($request->string('payment_order')->toString()),
            'provider_reference' => trim($request->string('payment_provider_reference')->toString()),
            'provider' => trim($request->string('payment_provider')->toString()),
            'payment_method' => trim($request->string('payment_method')->toString()),
            'status' => trim($request->string('payment_status')->toString()),
            'settlement_status' => trim($request->string('payment_settlement_status')->toString()),
            'currency' => trim($request->string('payment_currency')->toString()),
            'customer' => trim($request->string('payment_customer')->toString()),
            'collected_from' => $request->string('payment_collected_from')->toString(),
            'collected_to' => $request->string('payment_collected_to')->toString(),
            'verified_from' => $request->string('payment_verified_from')->toString(),
            'verified_to' => $request->string('payment_verified_to')->toString(),
        ];

        $settlements = OnlinePaymentSettlement::query()
            ->with('tenant')
            ->when($selectedTenant, fn ($query) => $query->where('tenant_id', $selectedTenant->id))
            ->when($filters['reference'] !== '', fn ($query) => $query->where('reference', 'like', '%'.$filters['reference'].'%'))
            ->when($filters['provider'] !== '', fn ($query) => $query->where('provider', $filters['provider']))
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['currency'] !== '', fn ($query) => $query->where('currency', strtoupper($filters['currency'])))
            ->when($filters['settlement_date_from'] !== '', fn ($query) => $query->whereDate('settlement_date', '>=', $filters['settlement_date_from']))
            ->when($filters['settlement_date_to'] !== '', fn ($query) => $query->whereDate('settlement_date', '<=', $filters['settlement_date_to']))
            ->when($filters['created_from'] !== '', fn ($query) => $query->whereDate('created_at', '>=', $filters['created_from']))
            ->when($filters['created_to'] !== '', fn ($query) => $query->whereDate('created_at', '<=', $filters['created_to']))
            ->when($filters['settled_from'] !== '', fn ($query) => $query->whereDate('settled_at', '>=', $filters['settled_from']))
            ->when($filters['settled_to'] !== '', fn ($query) => $query->whereDate('settled_at', '<=', $filters['settled_to']))
            ->when($filters['notes'] !== '', fn ($query) => $query->where('notes', 'like', '%'.$filters['notes'].'%'))
            ->latest('settlement_date')
            ->latest()
            ->get();
        $unsettledPayments = OnlineCollectedPayment::query()
            ->with('order.customer')
            ->when($selectedTenant, fn ($query) => $query->where('tenant_id', $selectedTenant->id))
            ->where('status', 'successful')
            ->where('is_settled', false)
            ->latest('collected_at')
            ->get();
        $onlinePayments = OnlineCollectedPayment::query()
            ->with(['order.customer', 'settlement'])
            ->when($selectedTenant, fn ($query) => $query->where('tenant_id', $selectedTenant->id))
            ->when($paymentFilters['order'] !== '', fn ($query) => $query->whereHas('order', fn ($orderQuery) => $orderQuery->where('order_number', 'like', '%'.$paymentFilters['order'].'%')))
            ->when($paymentFilters['provider_reference'] !== '', fn ($query) => $query->where('provider_reference', 'like', '%'.$paymentFilters['provider_reference'].'%'))
            ->when($paymentFilters['provider'] !== '', fn ($query) => $query->where('provider', $paymentFilters['provider']))
            ->when($paymentFilters['payment_method'] !== '', fn ($query) => $query->where('payment_method', $paymentFilters['payment_method']))
            ->when($paymentFilters['status'] !== '', fn ($query) => $query->where('status', $paymentFilters['status']))
            ->when($paymentFilters['settlement_status'] === 'settled', fn ($query) => $query->where('is_settled', true))
            ->when($paymentFilters['settlement_status'] === 'unsettled', fn ($query) => $query->where('is_settled', false))
            ->when($paymentFilters['currency'] !== '', fn ($query) => $query->where('currency', strtoupper($paymentFilters['currency'])))
            ->when($paymentFilters['customer'] !== '', fn ($query) => $query->where(function ($query) use ($paymentFilters): void {
                $query->where('customer_email', 'like', '%'.$paymentFilters['customer'].'%')
                    ->orWhereHas('order.customer', fn ($customerQuery) => $customerQuery->where('first_name', 'like', '%'.$paymentFilters['customer'].'%')->orWhere('last_name', 'like', '%'.$paymentFilters['customer'].'%')->orWhere('phone', 'like', '%'.$paymentFilters['customer'].'%'));
            }))
            ->when($paymentFilters['collected_from'] !== '', fn ($query) => $query->whereDate('collected_at', '>=', $paymentFilters['collected_from']))
            ->when($paymentFilters['collected_to'] !== '', fn ($query) => $query->whereDate('collected_at', '<=', $paymentFilters['collected_to']))
            ->when($paymentFilters['verified_from'] !== '', fn ($query) => $query->whereDate('verified_at', '>=', $paymentFilters['verified_from']))
            ->when($paymentFilters['verified_to'] !== '', fn ($query) => $query->whereDate('verified_at', '<=', $paymentFilters['verified_to']))
            ->latest('collected_at')
            ->get();

        return view('sales::admin.admin-settlements.index', [
            'tenants' => $tenants,
            'selectedTenant' => $selectedTenant,
            'filters' => $filters,
            'paymentFilters' => $paymentFilters,
            'settlements' => $settlements,
            'unsettledPayments' => $unsettledPayments,
            'onlinePayments' => $onlinePayments,
            'settlementPreview' => $request->session()->get('admin_settlement_preview'),
            'stats' => [
                'unsettled_count' => $unsettledPayments->count(),
                'unsettled_minor' => $unsettledPayments->sum('amount_minor'),
                'settled_minor' => $settlements->sum('total_settled_minor'),
                'total_gateway_charge_minor' => $settlements->sum('total_gateway_charge_minor'),
                'storeboot_charges_minor' => $settlements->sum('storeboot_charges_minor'),
            ],
        ]);
    }

    public function storeAdminSettlement(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->is_platform_admin, 403);

        $data = $request->validate([
            'settlement_file' => ['required', 'file', 'max:5120'],
        ]);

        $file = $request->file('settlement_file');
        $extension = strtolower((string) $file?->getClientOriginalExtension());

        if (! in_array($extension, ['csv', 'xlsx'], true)) {
            return back()->withErrors(['settlement_file' => 'Upload a CSV or XLSX file.'])->withInput();
        }

        try {
            $rows = $this->settlementUploadRows($file->getRealPath(), $extension);
            $preview = $this->validatedSettlementPreview($rows);
        } catch (\Throwable $exception) {
            return back()->withErrors(['settlement_file' => $exception->getMessage()])->withInput();
        }

        $request->session()->put('admin_settlement_preview', $preview);

        return redirect()
            ->to(route('admin.sales.admin-settlements.index', ['tenant' => array_key_first($preview['tenants'])]).'#create')
            ->with('status', 'Settlement upload validated. Review the summary, then post settlements.');
    }

    public function postAdminSettlements(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->is_platform_admin, 403);

        $preview = $request->session()->get('admin_settlement_preview');

        if (! is_array($preview) || empty($preview['payment_ids'])) {
            return back()->withErrors(['settlement' => 'Upload and validate a settlement spreadsheet before posting.']);
        }

        try {
            $freshPreview = $this->validatedSettlementPreview($preview['rows'] ?? []);
        } catch (\Throwable $exception) {
            return back()->withErrors(['settlement' => $exception->getMessage()]);
        }

        $settlements = DB::transaction(function () use ($freshPreview) {
            return collect($freshPreview['tenants'])->map(function (array $tenantPreview, string $tenantId): OnlinePaymentSettlement {
                $tenant = Tenant::query()->findOrFail($tenantId);
                $payments = OnlineCollectedPayment::query()
                    ->where('tenant_id', $tenantId)
                    ->whereKey($tenantPreview['payment_ids'])
                    ->lockForUpdate()
                    ->get();

                $settlement = $this->createOnlinePaymentSettlementFor($tenant, $payments, now()->toDateString(), 'Uploaded admin settlement batch.');

                OnlineCollectedPayment::query()
                    ->whereKey($payments->pluck('id'))
                    ->update([
                        'online_payment_settlement_id' => $settlement->id,
                        'is_settled' => true,
                        'updated_at' => now(),
                    ]);

                return $settlement;
            })->values();
        });

        $request->session()->forget('admin_settlement_preview');

        return redirect()
            ->to(route('admin.sales.admin-settlements.index').'#create')
            ->with('status', $settlements->count().' settlement batch(es) posted successfully.');
    }

    public function cancelAdminSettlementPreview(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->is_platform_admin, 403);

        $request->session()->forget('admin_settlement_preview');

        return redirect()
            ->to(route('admin.sales.admin-settlements.index').'#create')
            ->with('status', 'Settlement upload preview cleared.');
    }

    public function showSettlement(Request $request, OnlinePaymentSettlement $settlement): View
    {
        $this->authorizeTenantIdAccess($request->user(), $settlement->tenant_id);
        $settlement->load(['payments.order.customer', 'payments.orderPayment']);

        return view('sales::admin.settlements.show', [
            'settlement' => $settlement,
            'tenant' => Tenant::query()->findOrFail($settlement->tenant_id),
            'backRoute' => route('admin.sales.settlements.index', ['tenant' => $settlement->tenant_id]),
        ]);
    }

    public function showAdminSettlement(Request $request, OnlinePaymentSettlement $settlement): View
    {
        abort_unless($request->user()?->is_platform_admin, 403);
        $settlement->load(['payments.order.customer', 'payments.orderPayment']);

        return view('sales::admin.settlements.show', [
            'settlement' => $settlement,
            'tenant' => Tenant::query()->findOrFail($settlement->tenant_id),
            'backRoute' => route('admin.sales.admin-settlements.index', ['tenant' => $settlement->tenant_id]),
        ]);
    }

    public function downloadSettlement(Request $request, OnlinePaymentSettlement $settlement)
    {
        $this->authorizeTenantIdAccess($request->user(), $settlement->tenant_id);
        $settlement->load(['payments.order.customer', 'payments.orderPayment']);
        $filename = 'settlement-'.$settlement->reference.'.csv';

        return response()->streamDownload(function () use ($settlement): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Settlement', 'Order', 'Payment Reference', 'Customer', 'Email', 'Collected At', 'Currency', 'Product Amount', 'Shipping Amount', 'Gateway Charge', 'Amount', 'Fees', 'Net', 'Status']);

            foreach ($settlement->payments as $payment) {
                fputcsv($handle, [
                    $settlement->reference,
                    $payment->order?->order_number,
                    $payment->provider_reference,
                    $payment->order?->customer?->name,
                    $payment->customer_email,
                    optional($payment->collected_at)->toDateTimeString(),
                    $payment->currency,
                    number_format($payment->product_amount_minor / 100, 2, '.', ''),
                    number_format($payment->shipping_amount_minor / 100, 2, '.', ''),
                    number_format($payment->gateway_charge_minor / 100, 2, '.', ''),
                    number_format($payment->amount_minor / 100, 2, '.', ''),
                    number_format($payment->fees_minor / 100, 2, '.', ''),
                    number_format($payment->net_amount_minor / 100, 2, '.', ''),
                    $payment->status,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
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
                ($movement->movement_type === 'cash_deposit' ? 'Cash remittance from ' : 'Cash out from ').$tillSession->session_number,
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

        if ($varianceTotalMinor !== 0 || $tillSession->closingCounts()->where('variance_minor', '!=', 0)->exists()) {
            return redirect()
                ->to(route('admin.sales.index', ['tenant' => $tillSession->tenant_id]).'#till')
                ->withErrors(['actuals' => 'All till variances must be 0 before the till can be closed.'])
                ->withInput();
        }

        $cashExpectedMinor = (int) ($rows->firstWhere('method', 'Cash')['expected_minor'] ?? 0);

        $tillSession->update([
            'status' => 'closed',
            'expected_cash_minor' => $cashExpectedMinor,
            'expected_total_minor' => (int) $rows->sum('expected_minor'),
            'actual_total_minor' => $actualTotalMinor,
            'variance_total_minor' => 0,
            'closed_at' => now(),
            'closing_note' => $data['closing_note'] ?? null,
        ]);

        if ($cashExpectedMinor > 0) {
            $till = $this->ensureTillCashLocation($tillSession);
            $vault = $this->ensureBranchVault($tillSession);

            $postJournalEntry->execute(
                $tillSession->tenant_id,
                now()->toDateString(),
                'Till close cash handover for '.$tillSession->session_number,
                [
                    ['account_code' => $vault->financeAccount->code, 'branch_id' => $tillSession->branch_id, 'debit_minor' => $cashExpectedMinor, 'memo' => 'Balanced cash received into branch safe vault.'],
                    ['account_code' => $till->financeAccount->code, 'branch_id' => $tillSession->branch_id, 'credit_minor' => $cashExpectedMinor, 'memo' => 'Balanced cash handed over from cashier till.'],
                ],
                'sales_till_session',
                $tillSession->id,
                'closed_remitted',
            );

            $till->decrement('balance_minor', $cashExpectedMinor);
            $vault->increment('balance_minor', $cashExpectedMinor);
        }

        return redirect()->to(route('admin.sales.index', ['tenant' => $tillSession->tenant_id]).'#till')->with('status', "Till {$tillSession->session_number} closed.");
    }

    public function updateDeliveryStatus(Request $request, SalesOrder $order): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $order->tenant_id);
        $data = $request->validate([
            'delivery_status' => ['required', 'in:pending,processing,out_for_delivery,delivered,failed,returned'],
        ]);

        $order->update(['delivery_status' => $data['delivery_status']]);

        return redirect()->to(route('admin.sales.index', ['tenant' => $order->tenant_id]).'#orders')->with('status', "Delivery status updated for {$order->order_number}.");
    }

    public function storeReturn(SalesReturnRequest $request, SalesOrder $order, ProcessSalesReturnAction $action): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $order->tenant_id);
        $salesReturn = $action->execute($order->load('items.variant', 'customer', 'branch'), $request->validated());

        return redirect()->to(route('admin.sales.index', ['tenant' => $salesReturn->tenant_id]).'#returns')->with('status', "Return {$salesReturn->return_number} processed.");
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
        if ($tillSession->vaultCashLocation?->financeAccount) {
            $tillSession->vaultCashLocation->financeAccount->fill([
                'type' => 'asset',
                'category' => 'Current Assets',
                'description' => 'Cash held in a branch safe vault.',
                'normal_balance' => 'debit',
            ])->save();

            return $tillSession->vaultCashLocation;
        }

        $code = 'BV-'.$tillSession->branch_id;
        $account = FinanceAccount::query()->firstOrCreate([
            'tenant_id' => $tillSession->tenant_id,
            'code' => $code,
        ], [
            'name' => 'Branch Safe Vault - '.($tillSession->branch?->name ?? 'Branch '.$tillSession->branch_id),
            'type' => 'asset',
            'category' => 'Current Assets',
            'description' => 'Cash held in a branch safe vault.',
            'normal_balance' => 'debit',
            'is_system' => true,
            'is_active' => true,
        ]);

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

        if (! $tillSession->vault_cash_location_id) {
            $tillSession->update(['vault_cash_location_id' => $location->id]);
        }

        return $location->load('financeAccount');
    }

    private function ensureTillCashLocation(SalesTillSession $tillSession): SalesCashLocation
    {
        if ($tillSession->cashLocation?->financeAccount) {
            $tillSession->cashLocation->financeAccount->fill([
                'type' => 'asset',
                'category' => 'Current Assets',
                'description' => 'Cash held in a cashier till for point-of-sale transactions.',
                'normal_balance' => 'debit',
            ])->save();

            return $tillSession->cashLocation;
        }

        $code = 'CT-'.$tillSession->id;
        $account = FinanceAccount::query()->firstOrCreate([
            'tenant_id' => $tillSession->tenant_id,
            'code' => $code,
        ], [
            'name' => 'Cashier Till '.$tillSession->session_number,
            'type' => 'asset',
            'category' => 'Current Assets',
            'description' => 'Cash held in a cashier till for point-of-sale transactions.',
            'normal_balance' => 'debit',
            'is_system' => true,
            'is_active' => true,
        ]);

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

        if (! $tillSession->cash_location_id) {
            $tillSession->update(['cash_location_id' => $location->id]);
        }

        return $location->load('financeAccount');
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{method: string, expected_minor: int, collected_minor: int, movement_minor: int}>
     */
    private function tillBreakdown(SalesTillSession $tillSession, Tenant $tenant): \Illuminate\Support\Collection
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
        $cashPayment = $order->payments->first(fn ($payment): bool => $this->isCashMethod($payment->payment_method));

        if ($cashPayment?->tillSession?->cashLocation?->financeAccount) {
            return $cashPayment->tillSession->cashLocation->financeAccount->code;
        }

        return '1000';
    }

    private function isCashMethod(?string $paymentMethod): bool
    {
        return str_contains(strtolower((string) $paymentMethod), 'cash');
    }

    private function moneyToMinor(mixed $value): int
    {
        return (int) round(((float) (is_string($value) ? str_replace(',', '', $value) : ($value ?: 0))) * 100);
    }

    private function settlementNumber(string $tenantId): string
    {
        return 'SETT-'.now()->format('Ymd').'-'.str_pad((string) (OnlinePaymentSettlement::query()->where('tenant_id', $tenantId)->count() + 1), 5, '0', STR_PAD_LEFT);
    }

    private function createOnlinePaymentSettlementFor(Tenant $tenant, $payments, string $settlementDate, ?string $notes = null): OnlinePaymentSettlement
    {
        $totalProductAmountMinor = (int) $payments->sum('product_amount_minor');
        $totalShippingAmountMinor = (int) $payments->sum('shipping_amount_minor');
        $totalGatewayChargeMinor = (int) $payments->sum('gateway_charge_minor');
        $totalFeesMinor = (int) $payments->sum('fees_minor');
        $totalNetAmountMinor = (int) $payments->sum('net_amount_minor');
        $storebootChargesMinor = $this->storebootChargeMinor($tenant->id, $totalNetAmountMinor);
        $totalSettledMinor = max(0, $totalNetAmountMinor - $storebootChargesMinor);

        return OnlinePaymentSettlement::query()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'paystack',
            'reference' => $this->settlementNumber($tenant->id),
            'status' => 'settled',
            'currency' => $tenant->currency_code ?? 'NGN',
            'total_product_amount_minor' => $totalProductAmountMinor,
            'total_shipping_amount_minor' => $totalShippingAmountMinor,
            'total_gateway_charge_minor' => $totalGatewayChargeMinor,
            'total_fees_minor' => $totalFeesMinor,
            'total_net_amount_minor' => $totalNetAmountMinor,
            'storeboot_charges_minor' => $storebootChargesMinor,
            'total_settled_minor' => $totalSettledMinor,
            'payment_count' => $payments->count(),
            'settlement_date' => $settlementDate,
            'settled_at' => now(),
            'notes' => $notes,
        ]);
    }

    /**
     * @return list<array{online_collected_payment_id: string, tenant_id: string, gateway_reference: string}>
     */
    private function settlementUploadRows(string $path, string $extension): array
    {
        $rows = $extension === 'csv'
            ? $this->settlementCsvRows($path)
            : $this->settlementXlsxRows($path);

        if ($rows === []) {
            throw new \RuntimeException('The spreadsheet does not contain any payment rows.');
        }

        return $rows;
    }

    /**
     * @return list<array{online_collected_payment_id: string, tenant_id: string, gateway_reference: string}>
     */
    private function settlementCsvRows(string $path): array
    {
        $handle = fopen($path, 'r');

        if (! $handle) {
            throw new \RuntimeException('The uploaded CSV could not be opened.');
        }

        $headers = null;
        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            if ($this->spreadsheetRowIsBlank($row)) {
                continue;
            }

            if ($headers === null) {
                $headers = $this->normalizedSettlementHeaders($row);

                continue;
            }

            $rows[] = $this->mappedSettlementRow($headers, $row);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return list<array{online_collected_payment_id: string, tenant_id: string, gateway_reference: string}>
     */
    private function settlementXlsxRows(string $path): array
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('XLSX uploads require the PHP zip extension. Upload CSV instead.');
        }

        $zip = new \ZipArchive();

        if ($zip->open($path) !== true) {
            throw new \RuntimeException('The uploaded XLSX file could not be opened.');
        }

        $sharedStrings = $this->xlsxSharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if (! is_string($sheetXml) || $sheetXml === '') {
            throw new \RuntimeException('The uploaded XLSX file does not contain a first worksheet.');
        }

        $sheet = simplexml_load_string($sheetXml);

        if (! $sheet) {
            throw new \RuntimeException('The uploaded XLSX worksheet could not be read.');
        }

        $headers = null;
        $rows = [];

        foreach ($sheet->sheetData->row as $xmlRow) {
            $row = [];

            foreach ($xmlRow->c as $cell) {
                $reference = (string) $cell['r'];
                $column = $this->xlsxColumnIndex($reference);
                $type = (string) $cell['t'];
                $value = '';

                if ($type === 's') {
                    $value = $sharedStrings[(int) $cell->v] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = trim((string) ($cell->is->t ?? ''));
                } else {
                    $value = trim((string) ($cell->v ?? ''));
                }

                $row[$column] = $value;
            }

            if ($row === []) {
                continue;
            }

            ksort($row);
            $lastColumn = max(array_keys($row));
            $row = collect(range(0, $lastColumn))
                ->map(fn (int $column): string => $row[$column] ?? '')
                ->all();

            if ($this->spreadsheetRowIsBlank($row)) {
                continue;
            }

            if ($headers === null) {
                $headers = $this->normalizedSettlementHeaders($row);

                continue;
            }

            $rows[] = $this->mappedSettlementRow($headers, $row);
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function xlsxSharedStrings(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if (! is_string($xml) || $xml === '') {
            return [];
        }

        $shared = simplexml_load_string($xml);

        if (! $shared) {
            return [];
        }

        $strings = [];

        foreach ($shared->si as $item) {
            if (isset($item->t)) {
                $strings[] = trim((string) $item->t);

                continue;
            }

            $text = '';

            foreach ($item->r as $run) {
                $text .= (string) ($run->t ?? '');
            }

            $strings[] = trim($text);
        }

        return $strings;
    }

    private function xlsxColumnIndex(string $reference): int
    {
        preg_match('/^[A-Z]+/i', $reference, $matches);
        $letters = strtoupper($matches[0] ?? 'A');
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }

    /**
     * @param  array<int, mixed>  $headers
     * @return array<string, int>
     */
    private function normalizedSettlementHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $index => $header) {
            $key = Str::of((string) $header)->trim()->lower()->replace([' ', '-'], '_')->toString();

            if ($key !== '') {
                $normalized[$key] = $index;
            }
        }

        foreach (['online_collected_payment_id', 'tenant_id', 'gateway_reference'] as $required) {
            if (! array_key_exists($required, $normalized)) {
                throw new \RuntimeException("The spreadsheet must include the {$required} column.");
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, int>  $headers
     * @param  array<int, mixed>  $row
     * @return array{online_collected_payment_id: string, tenant_id: string, gateway_reference: string}
     */
    private function mappedSettlementRow(array $headers, array $row): array
    {
        return [
            'online_collected_payment_id' => trim((string) ($row[$headers['online_collected_payment_id']] ?? '')),
            'tenant_id' => trim((string) ($row[$headers['tenant_id']] ?? '')),
            'gateway_reference' => trim((string) ($row[$headers['gateway_reference']] ?? '')),
        ];
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function spreadsheetRowIsBlank(array $row): bool
    {
        return collect($row)->every(fn ($value): bool => trim((string) $value) === '');
    }

    /**
     * @param  list<array{online_collected_payment_id: string, tenant_id: string, gateway_reference: string}>  $rows
     * @return array{rows: array, payment_ids: array, tenants: array, overall: array}
     */
    private function validatedSettlementPreview(array $rows): array
    {
        $errors = [];
        $normalizedRows = [];
        $seenPaymentIds = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $paymentId = $row['online_collected_payment_id'];
            $tenantId = $row['tenant_id'];
            $gatewayReference = $row['gateway_reference'];

            if ($paymentId === '' || ! ctype_digit($paymentId)) {
                $errors[] = "Row {$line}: online_collected_payment_id is required and must be numeric.";
            }

            if ($tenantId === '') {
                $errors[] = "Row {$line}: tenant_id is required.";
            }

            if ($gatewayReference === '') {
                $errors[] = "Row {$line}: gateway_reference is required.";
            }

            if (isset($seenPaymentIds[$paymentId])) {
                $errors[] = "Row {$line}: duplicate online_collected_payment_id {$paymentId}.";
            }

            $seenPaymentIds[$paymentId] = true;
            $normalizedRows[] = [
                'online_collected_payment_id' => $paymentId,
                'tenant_id' => $tenantId,
                'gateway_reference' => $gatewayReference,
            ];
        }

        if ($errors !== []) {
            throw new \RuntimeException(implode(' ', $errors));
        }

        $paymentIds = collect($normalizedRows)->pluck('online_collected_payment_id')->map(fn (string $id): int => (int) $id)->values();
        $payments = OnlineCollectedPayment::query()
            ->whereKey($paymentIds)
            ->get()
            ->keyBy('id');
        $tenants = Tenant::query()
            ->whereIn('id', collect($normalizedRows)->pluck('tenant_id')->unique()->values())
            ->get()
            ->keyBy('id');

        foreach ($normalizedRows as $index => $row) {
            $line = $index + 2;
            $payment = $payments->get((int) $row['online_collected_payment_id']);

            if (! $tenants->has($row['tenant_id'])) {
                $errors[] = "Row {$line}: tenant {$row['tenant_id']} does not exist.";
            }

            if (! $payment) {
                $errors[] = "Row {$line}: payment {$row['online_collected_payment_id']} does not exist.";

                continue;
            }

            if ($payment->tenant_id !== $row['tenant_id']) {
                $errors[] = "Row {$line}: payment {$payment->id} does not belong to tenant {$row['tenant_id']}.";
            }

            if ((string) $payment->gateway_reference !== $row['gateway_reference']) {
                $errors[] = "Row {$line}: gateway_reference does not match payment {$payment->id}.";
            }

            if ($payment->is_settled || $payment->online_payment_settlement_id) {
                $errors[] = "Row {$line}: payment {$payment->id} has already been settled.";
            }

            if ($payment->status !== 'successful') {
                $errors[] = "Row {$line}: payment {$payment->id} is not successful.";
            }
        }

        if ($errors !== []) {
            throw new \RuntimeException(implode(' ', $errors));
        }

        $tenantSummaries = [];

        foreach ($payments as $payment) {
            $tenant = $tenants->get($payment->tenant_id);
            $tenantSummaries[$payment->tenant_id] ??= [
                'tenant_id' => $payment->tenant_id,
                'tenant_name' => $tenant?->name ?? $payment->tenant_id,
                'currency_code' => $tenant?->currency_code ?? 'NGN',
                'payment_count' => 0,
                'total_gateway_charge_minor' => 0,
                'total_net_amount_minor' => 0,
                'payment_ids' => [],
            ];
            $tenantSummaries[$payment->tenant_id]['payment_count']++;
            $tenantSummaries[$payment->tenant_id]['total_gateway_charge_minor'] += (int) $payment->gateway_charge_minor;
            $tenantSummaries[$payment->tenant_id]['total_net_amount_minor'] += (int) $payment->net_amount_minor;
            $tenantSummaries[$payment->tenant_id]['payment_ids'][] = $payment->id;
        }

        return [
            'rows' => $normalizedRows,
            'payment_ids' => $paymentIds->all(),
            'tenants' => $tenantSummaries,
            'overall' => [
                'tenant_count' => count($tenantSummaries),
                'payment_count' => $payments->count(),
                'total_gateway_charge_minor' => array_sum(array_column($tenantSummaries, 'total_gateway_charge_minor')),
                'total_net_amount_minor' => array_sum(array_column($tenantSummaries, 'total_net_amount_minor')),
            ],
        ];
    }

    private function storebootChargeMinor(string $tenantId, int $totalNetAmountMinor): int
    {
        $config = DB::table('global_configs')
            ->where('key', 'ONLINE_STOREBOOT_CHARGE')
            ->where('tenant_id', $tenantId)
            ->value('value');

        $config ??= DB::table('global_configs')
            ->where('key', 'ONLINE_STOREBOOT_CHARGE')
            ->whereNull('tenant_id')
            ->value('value');

        $values = is_string($config) && $config !== ''
            ? json_decode($config, true)
            : [];

        if (! is_array($values)) {
            $values = [];
        }

        $percentageRate = (float) ($values['percentage_rate'] ?? 1.5);
        $fixedAmountMinor = (int) ($values['fixed_amount_minor'] ?? 100000);

        return max(0, (int) round($totalNetAmountMinor * ($percentageRate / 100)) + $fixedAmountMinor);
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
