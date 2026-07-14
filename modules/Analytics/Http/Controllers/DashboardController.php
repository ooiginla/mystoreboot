<?php

declare(strict_types=1);

namespace Modules\Analytics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ActiveBranchManager;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Modules\Access\Enums\MembershipStatus;
use Modules\Catalog\Models\Product;
use Modules\Customers\Models\Customer;
use Modules\Finance\Models\FinanceExpense;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\Vendor;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderItem;
use Modules\Sales\Models\SalesOrderPayment;
use Modules\Tenancy\Models\Tenant;

final class DashboardController extends Controller
{
    private const CANCELLED = 'cancelled';

    public function index(Request $request, ActiveBranchManager $branchManager): View
    {
        /** @var User $user */
        $user = $request->user();
        $isPlatformAdmin = (bool) $user->is_platform_admin;

        $visibleTenants = $this->visibleTenants($user, $isPlatformAdmin);
        $tenant = $this->resolveTenant($request, $visibleTenants);

        if (! $tenant) {
            return view('analytics::admin.index', [
                'tenant' => null,
                'isPlatformAdmin' => $isPlatformAdmin,
                'visibleTenants' => $visibleTenants,
            ]);
        }

        // ---- Branch filter -------------------------------------------------
        $branches = $branchManager->branchesFor($user, $tenant);
        $canPickAllBranches = $branches->count() > 1;
        $requestedBranch = (string) $request->query('branch', 'all');
        $selectedBranchId = null;

        if ($branches->count() === 1) {
            $selectedBranchId = (int) $branches->first()->id;
        } elseif ($requestedBranch !== '' && $requestedBranch !== 'all') {
            $match = $branches->firstWhere('id', (int) $requestedBranch);
            $selectedBranchId = $match ? (int) $match->id : null;
        }

        // ---- Period filter -------------------------------------------------
        [$period, $from, $to] = $this->resolvePeriod($request);

        $currency = $tenant->currency_code ?: 'NGN';

        // ---- Core order collections ---------------------------------------
        $orders = SalesOrder::query()
            ->where('tenant_id', $tenant->id)
            ->when($selectedBranchId, fn ($q) => $q->where('branch_id', $selectedBranchId))
            ->whereBetween('order_date', [$from->toDateString(), $to->toDateString()])
            ->get();

        $liveOrders = $orders->where('order_status', '!=', self::CANCELLED);

        // ---- Headline counts ----------------------------------------------
        $productCount = Product::query()->where('tenant_id', $tenant->id)->where('status', 'active')->count();
        $customerCount = Customer::query()->where('tenant_id', $tenant->id)->where('status', 'active')->count();
        $supplierCount = Vendor::query()->where('tenant_id', $tenant->id)->where('status', 'active')->count();

        // ---- Order status groups (count + value) --------------------------
        $group = fn (string $status): array => [
            'count' => $orders->where('order_status', $status)->count(),
            'valueMinor' => (int) $orders->where('order_status', $status)->sum('total_minor'),
        ];
        $pending = $group('pending');
        $completed = $group('completed');
        $cancelled = $group(self::CANCELLED);

        // ---- Money KPIs ----------------------------------------------------
        $revenueMinor = (int) $liveOrders->sum('total_minor');
        $refundMinor = (int) $liveOrders->sum('refunded_minor');

        $items = SalesOrderItem::query()
            ->where('tenant_id', $tenant->id)
            ->whereHas('order', function ($q) use ($tenant, $selectedBranchId, $from, $to): void {
                $q->where('tenant_id', $tenant->id)
                    ->where('order_status', '!=', self::CANCELLED)
                    ->whereBetween('order_date', [$from->toDateString(), $to->toDateString()]);
                if ($selectedBranchId) {
                    $q->where('branch_id', $selectedBranchId);
                }
            })
            ->with('variant.product')
            ->get();

        $cogsMinor = (int) $items->sum(fn (SalesOrderItem $item): int => max(0, $item->quantity - $item->quantity_returned) * $this->unitCostMinor($item));

        $expenses = FinanceExpense::query()
            ->where('tenant_id', $tenant->id)
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->with('category')
            ->get();
        $expenseMinor = (int) $expenses->sum('amount_minor');

        $grossProfitMinor = $revenueMinor - $refundMinor - $cogsMinor;
        $netProfitMinor = $grossProfitMinor - $expenseMinor;

        // ---- Balance-sheet style snapshots (as at $to) --------------------
        $inventoryMinor = (int) InventoryStockLevel::query()
            ->where('tenant_id', $tenant->id)
            ->when($selectedBranchId, function ($q) use ($tenant, $selectedBranchId): void {
                $q->whereHas('location', fn ($loc) => $loc->where('tenant_id', $tenant->id)->where('branch_id', $selectedBranchId));
            })
            ->get()
            ->sum('stock_value_minor');

        $receivableMinor = (int) SalesOrder::query()
            ->where('tenant_id', $tenant->id)
            ->when($selectedBranchId, fn ($q) => $q->where('branch_id', $selectedBranchId))
            ->where('order_status', '!=', self::CANCELLED)
            ->whereDate('order_date', '<=', $to->toDateString())
            ->get()
            ->sum(fn (SalesOrder $order): int => (int) $order->balance_minor);

        $payableMinor = (int) PurchaseOrder::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', '!=', self::CANCELLED)
            ->whereDate('order_date', '<=', $to->toDateString())
            ->get()
            ->sum(fn (PurchaseOrder $po): int => (int) $po->balance_minor);

        // ---- Charts --------------------------------------------------------
        $statusLabels = [
            'pending' => 'Pending', 'draft' => 'Draft', 'completed' => 'Completed',
            'cancelled' => 'Cancelled', 'returned' => 'Returned', 'partially_returned' => 'Partially returned',
        ];
        $orderStatusChart = $orders
            ->groupBy('order_status')
            ->map(fn (Collection $g, string $status): array => [
                'label' => $statusLabels[$status] ?? ucfirst(str_replace('_', ' ', $status)),
                'value' => $g->count(),
            ])
            ->values();

        $revenueSeries = $this->revenueSeries($liveOrders, $from, $to);

        $channelLabels = ['in_store' => 'In-store / POS', 'online' => 'Online store', 'storefront' => 'Storefront'];
        $channelChart = $liveOrders
            ->groupBy(fn (SalesOrder $o) => $o->source ?: 'in_store')
            ->map(fn (Collection $g, string $source): array => [
                'label' => $channelLabels[$source] ?? ucfirst(str_replace('_', ' ', $source)),
                'value' => round($g->sum('total_minor') / 100, 2),
            ])
            ->values();

        $payments = SalesOrderPayment::query()
            ->where('tenant_id', $tenant->id)
            ->when($selectedBranchId, fn ($q) => $q->whereHas('order', fn ($o) => $o->where('branch_id', $selectedBranchId)))
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->with('paymentAccount')
            ->get();
        $paymentChart = $payments
            ->groupBy(fn (SalesOrderPayment $p) => $p->payment_method ?: 'Unspecified')
            ->map(fn (Collection $g, string $method): array => [
                'label' => $method,
                'value' => round($g->sum('amount_minor') / 100, 2),
            ])
            ->values();

        $paymentAccountChart = $payments
            ->groupBy(fn (SalesOrderPayment $p) => $p->paymentAccount?->identifier ?: 'Cash / Till')
            ->map(fn (Collection $g, string $label): array => [
                'label' => $label,
                'value' => round($g->sum('amount_minor') / 100, 2),
            ])
            ->sortByDesc('value')
            ->values();

        $expenseChart = $expenses
            ->groupBy(fn (FinanceExpense $e) => $e->category?->name ?: 'Uncategorised')
            ->map(fn (Collection $g, string $name): array => [
                'label' => $name,
                'value' => round($g->sum('amount_minor') / 100, 2),
            ])
            ->sortByDesc('value')
            ->values();

        // ---- Top 10 products ----------------------------------------------
        $topProducts = $items
            ->groupBy('product_variant_id')
            ->map(function (Collection $g): array {
                $first = $g->first();

                return [
                    'name' => $first->item_name,
                    'sku' => $first->variant?->sku,
                    'qty' => (int) $g->sum(fn (SalesOrderItem $i) => max(0, $i->quantity - $i->quantity_returned)),
                    'revenueMinor' => (int) $g->sum('line_total_minor'),
                ];
            })
            ->sortByDesc('revenueMinor')
            ->take(10)
            ->values();

        return view('analytics::admin.index', [
            'tenant' => $tenant,
            'currency' => $currency,
            'isPlatformAdmin' => $isPlatformAdmin,
            'visibleTenants' => $visibleTenants,
            'branches' => $branches,
            'canPickAllBranches' => $canPickAllBranches,
            'selectedBranchId' => $selectedBranchId,
            'period' => $period,
            'periods' => $this->periodOptions(),
            'from' => $from,
            'to' => $to,
            'rangeLabel' => $from->isSameDay($to)
                ? $from->format('M j, Y')
                : $from->format('M j, Y').' – '.$to->format('M j, Y'),
            'kpi' => [
                'products' => $productCount,
                'customers' => $customerCount,
                'suppliers' => $supplierCount,
                'revenueMinor' => $revenueMinor,
                'expenseMinor' => $expenseMinor,
                'inventoryMinor' => $inventoryMinor,
                'payableMinor' => $payableMinor,
                'receivableMinor' => $receivableMinor,
                'cogsMinor' => $cogsMinor,
                'grossProfitMinor' => $grossProfitMinor,
                'netProfitMinor' => $netProfitMinor,
            ],
            'orderGroups' => ['pending' => $pending, 'completed' => $completed, 'cancelled' => $cancelled],
            'charts' => [
                'orderStatus' => $orderStatusChart,
                'revenue' => $revenueSeries,
                'channel' => $channelChart,
                'payment' => $paymentChart,
                'paymentAccount' => $paymentAccountChart,
                'expense' => $expenseChart,
            ],
            'topProducts' => $topProducts,
        ]);
    }

    private function unitCostMinor(SalesOrderItem $item): int
    {
        return (int) ($item->unit_cost_minor
            ?? $item->variant?->cost_price_minor
            ?? $item->variant?->product?->base_cost_price_minor
            ?? 0);
    }

    /**
     * Build a daily (or monthly for long spans) revenue series for the area chart.
     *
     * @param  Collection<int, SalesOrder>  $orders
     * @return Collection<int, array{x: string, y: float}>
     */
    private function revenueSeries(Collection $orders, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $byMonth = $from->diffInDays($to) > 62;

        $buckets = [];
        $cursor = $from;
        while ($cursor->lessThanOrEqualTo($to)) {
            $key = $byMonth ? $cursor->format('Y-m') : $cursor->toDateString();
            $buckets[$key] = 0;
            $cursor = $byMonth ? $cursor->addMonth()->startOfMonth() : $cursor->addDay();
        }

        foreach ($orders as $order) {
            $date = CarbonImmutable::parse($order->order_date);
            $key = $byMonth ? $date->format('Y-m') : $date->toDateString();
            if (array_key_exists($key, $buckets)) {
                $buckets[$key] += (int) $order->total_minor;
            }
        }

        return collect($buckets)->map(fn (int $minor, string $key): array => [
            'x' => $byMonth ? CarbonImmutable::parse($key.'-01')->format('M Y') : CarbonImmutable::parse($key)->format('M j'),
            'y' => round($minor / 100, 2),
        ])->values();
    }

    /**
     * @return array{0: string, 1: CarbonImmutable, 2: CarbonImmutable}
     */
    private function resolvePeriod(Request $request): array
    {
        $today = CarbonImmutable::now()->endOfDay();
        $period = (string) $request->query('period', 'this_month');

        return match ($period) {
            'last_7_days' => [$period, $today->subDays(6)->startOfDay(), $today],
            'last_14_days' => [$period, $today->subDays(13)->startOfDay(), $today],
            'this_week' => [$period, $today->startOfWeek(), $today->endOfWeek()],
            'last_month' => [$period, $today->subMonth()->startOfMonth(), $today->subMonth()->endOfMonth()],
            'this_year' => [$period, $today->startOfYear(), $today->endOfYear()],
            'custom' => $this->customRange($request, $today),
            default => ['this_month', $today->startOfMonth(), $today->endOfMonth()],
        };
    }

    /**
     * @return array{0: string, 1: CarbonImmutable, 2: CarbonImmutable}
     */
    private function customRange(Request $request, CarbonImmutable $today): array
    {
        try {
            $from = $request->filled('from') ? CarbonImmutable::parse((string) $request->query('from'))->startOfDay() : $today->startOfMonth();
            $to = $request->filled('to') ? CarbonImmutable::parse((string) $request->query('to'))->endOfDay() : $today;
        } catch (\Throwable) {
            return ['this_month', $today->startOfMonth(), $today->endOfMonth()];
        }

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        return ['custom', $from, $to];
    }

    /** @return array<string, string> */
    private function periodOptions(): array
    {
        return [
            'last_7_days' => 'Last 7 days',
            'last_14_days' => 'Last 2 weeks',
            'this_week' => 'This week',
            'this_month' => 'This month',
            'last_month' => 'Last month',
            'this_year' => 'This year',
            'custom' => 'Custom range',
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Tenant>  $visibleTenants
     */
    private function resolveTenant(Request $request, Collection $visibleTenants): ?Tenant
    {
        $requested = (string) $request->query('tenant', '');

        if ($requested !== '') {
            $match = $visibleTenants->firstWhere('id', $requested);
            if ($match) {
                $request->session()->put('active_tenant_id', $match->id);

                return $match;
            }
        }

        $stored = $request->session()->get('active_tenant_id');
        if ($stored) {
            $match = $visibleTenants->firstWhere('id', $stored);
            if ($match) {
                return $match;
            }
        }

        return $visibleTenants->first();
    }

    /**
     * @return Collection<int, Tenant>
     */
    private function visibleTenants(User $user, bool $isPlatformAdmin): Collection
    {
        if ($isPlatformAdmin) {
            return Tenant::query()->orderBy('name')->get();
        }

        return Tenant::query()
            ->whereHas('memberships', fn ($q) => $q->where('user_id', $user->id)->where('status', MembershipStatus::Active->value))
            ->orderBy('name')
            ->get();
    }
}
