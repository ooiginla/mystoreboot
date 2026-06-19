<?php

declare(strict_types=1);

namespace Modules\Sales\Http\Controllers;

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
            ->with(['product', 'product.category'])
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
                    ['account_code' => $till->financeAccount->code, 'debit_minor' => $openingFloatMinor, 'memo' => 'Cash issued to cashier till.'],
                    ['account_code' => $vault->financeAccount->code, 'credit_minor' => $openingFloatMinor, 'memo' => 'Cash issued from branch safe vault.'],
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
                    ['account_code' => $till->financeAccount->code, 'debit_minor' => $amountMinor, 'memo' => 'Cash received into cashier till.'],
                    ['account_code' => $vault->financeAccount->code, 'credit_minor' => $amountMinor, 'memo' => 'Cash issued from branch safe vault.'],
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
                    ['account_code' => $vault->financeAccount->code, 'debit_minor' => $amountMinor, 'memo' => 'Cash received into branch safe vault.'],
                    ['account_code' => $till->financeAccount->code, 'credit_minor' => $amountMinor, 'memo' => 'Cash remitted from cashier till.'],
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
                    ['account_code' => '1010', 'debit_minor' => $amountMinor, 'memo' => 'Petty cash funded from cashier till.'],
                    ['account_code' => $till->financeAccount->code, 'credit_minor' => $amountMinor, 'memo' => 'Cash withdrawn from cashier till.'],
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
                    ['account_code' => $vault->financeAccount->code, 'debit_minor' => $cashExpectedMinor, 'memo' => 'Balanced cash received into branch safe vault.'],
                    ['account_code' => $till->financeAccount->code, 'credit_minor' => $cashExpectedMinor, 'memo' => 'Balanced cash handed over from cashier till.'],
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
            return $tillSession->vaultCashLocation;
        }

        $code = 'BV-'.$tillSession->branch_id;
        $account = FinanceAccount::query()->firstOrCreate([
            'tenant_id' => $tillSession->tenant_id,
            'code' => $code,
        ], [
            'name' => 'Branch Safe Vault - '.($tillSession->branch?->name ?? 'Branch '.$tillSession->branch_id),
            'type' => 'asset',
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
            return $tillSession->cashLocation;
        }

        $code = 'CT-'.$tillSession->id;
        $account = FinanceAccount::query()->firstOrCreate([
            'tenant_id' => $tillSession->tenant_id,
            'code' => $code,
        ], [
            'name' => 'Cashier Till '.$tillSession->session_number,
            'type' => 'asset',
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
