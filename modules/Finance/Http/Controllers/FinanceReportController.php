<?php

declare(strict_types=1);

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Modules\Access\Enums\MembershipStatus;
use Modules\Access\Models\TenantMembership;
use Modules\Customers\Models\Customer;
use Modules\Finance\Actions\EnsureDefaultChartOfAccountsAction;
use Modules\Finance\Actions\PostJournalEntryAction;
use Modules\Finance\Http\Requests\ExpenseCategoryRequest;
use Modules\Finance\Http\Requests\ExpenseRequest;
use Modules\Finance\Http\Requests\ManualJournalEntryRequest;
use Modules\Finance\Http\Requests\PettyCashTransactionRequest;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceExpense;
use Modules\Finance\Models\FinanceExpenseCategory;
use Modules\Finance\Models\FinanceJournalEntry;
use Modules\Finance\Models\FinanceJournalLine;
use Modules\Finance\Models\FinancePettyCashTransaction;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Procurement\Enums\PurchaseOrderStatus;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\Vendor;
use Modules\Procurement\Models\VendorPayment;
use Modules\Sales\Enums\SalesOrderStatus;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderItem;
use Modules\Sales\Models\SalesOrderPayment;
use Modules\Tenancy\Models\Tenant;

final class FinanceReportController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const REPORTS = [
        'profit-loss' => 'Profit and Loss statement',
        'cash-flow' => 'Cash Flow statement',
        'revenue' => 'Revenue report',
        'expense' => 'Expense report',
        'gross-profit' => 'Gross profit report',
        'net-profit' => 'Net profit report',
        'balance-sheet' => 'Balance Sheet',
        'branch-profitability' => 'Branch profitability report',
        'product-profitability' => 'Product profitability report',
    ];

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $tenants = $this->visibleTenantsFor($user);
        $tenant = $this->resolveTenant($request, $tenants);

        abort_if(! $tenant, 403);
        app(EnsureDefaultChartOfAccountsAction::class)->execute($tenant->id);

        $dateFrom = CarbonImmutable::parse($request->string('date_from')->toString() ?: now()->startOfMonth()->toDateString())->startOfDay();
        $dateTo = CarbonImmutable::parse($request->string('date_to')->toString() ?: now()->toDateString())->endOfDay();
        $selectedReport = $request->string('report')->toString();

        if (! array_key_exists($selectedReport, self::REPORTS)) {
            $selectedReport = array_key_first(self::REPORTS);
        }

        $orders = SalesOrder::query()
            ->with(['branch', 'customer', 'items.variant.product'])
            ->where('tenant_id', $tenant->id)
            ->where('order_status', '!=', SalesOrderStatus::Cancelled->value)
            ->whereBetween('order_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->latest('order_date')
            ->get();

        $purchaseOrders = PurchaseOrder::query()
            ->with('vendor')
            ->where('tenant_id', $tenant->id)
            ->where('status', '!=', PurchaseOrderStatus::Cancelled->value)
            ->whereBetween('order_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->latest('order_date')
            ->get();

        $salesPayments = SalesOrderPayment::query()
            ->with('order.customer')
            ->where('tenant_id', $tenant->id)
            ->whereBetween('payment_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->latest('payment_date')
            ->get();

        $vendorPayments = VendorPayment::query()
            ->with(['vendor', 'purchaseOrder'])
            ->where('tenant_id', $tenant->id)
            ->whereBetween('payment_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->latest('payment_date')
            ->get();

        $salesToDate = SalesOrder::query()
            ->where('tenant_id', $tenant->id)
            ->where('order_status', '!=', SalesOrderStatus::Cancelled->value)
            ->whereDate('order_date', '<=', $dateTo->toDateString())
            ->get();

        $purchaseOrdersToDate = PurchaseOrder::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', '!=', PurchaseOrderStatus::Cancelled->value)
            ->whereDate('order_date', '<=', $dateTo->toDateString())
            ->get();

        $salesItems = SalesOrderItem::query()
            ->with(['order.branch', 'variant.product'])
            ->where('tenant_id', $tenant->id)
            ->whereHas('order', fn ($query) => $query
                ->where('order_status', '!=', SalesOrderStatus::Cancelled->value)
                ->whereBetween('order_date', [$dateFrom->toDateString(), $dateTo->toDateString()]))
            ->get();
        $expenseCategories = FinanceExpenseCategory::query()
            ->with('account')
            ->where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get();
        $operationalExpenses = FinanceExpense::query()
            ->with('category')
            ->where('tenant_id', $tenant->id)
            ->whereBetween('expense_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->latest('expense_date')
            ->get();
        $pettyCashTransactions = FinancePettyCashTransaction::query()
            ->with('category')
            ->where('tenant_id', $tenant->id)
            ->latest('transaction_date')
            ->limit(50)
            ->get();
        $accounts = FinanceAccount::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('code')
            ->get();
        $journalEntries = FinanceJournalEntry::query()
            ->with('lines.account')
            ->where('tenant_id', $tenant->id)
            ->latest('entry_date')
            ->limit(40)
            ->get();

        $revenueMinor = (int) $orders->sum('total_minor');
        $discountMinor = (int) $orders->sum(fn (SalesOrder $order): int => $order->coupon_discount_minor + $order->admin_discount_minor);
        $refundMinor = (int) $orders->sum('refunded_minor');
        $expenseMinor = (int) $operationalExpenses->sum('amount_minor');
        $purchaseSpendMinor = (int) $purchaseOrders->sum('total_minor');
        $cashInMinor = (int) $salesPayments->sum('amount_minor');
        $cashOutMinor = (int) $vendorPayments->sum('amount_minor') + (int) $operationalExpenses->sum('paid_minor');
        $cogsMinor = $this->costOfGoodsSold($salesItems);
        $grossProfitMinor = $revenueMinor - $refundMinor - $cogsMinor;
        $netProfitMinor = $grossProfitMinor - $expenseMinor;
        $accountsReceivableMinor = (int) $salesToDate->sum(fn (SalesOrder $order): int => $order->balance_minor);
        $accountsPayableMinor = (int) $purchaseOrdersToDate->sum(fn (PurchaseOrder $order): int => $order->balance_minor)
            + (int) FinanceExpense::query()->where('tenant_id', $tenant->id)->whereDate('expense_date', '<=', $dateTo->toDateString())->get()->sum(fn (FinanceExpense $expense): int => max(0, $expense->amount_minor - $expense->paid_minor));
        $inventoryValueMinor = (int) InventoryStockLevel::query()->where('tenant_id', $tenant->id)->get()->sum('stock_value_minor');
        $cashMinor = max(0, $cashInMinor - $cashOutMinor);
        $pettyCashBalanceMinor = $this->accountBalance($tenant->id, '1010');

        return view('finance::admin.index', [
            'tenant' => $tenant,
            'tenants' => $tenants,
            'isPlatformAdmin' => $user->is_platform_admin,
            'reports' => self::REPORTS,
            'selectedReport' => $selectedReport,
            'dateFrom' => $dateFrom->toDateString(),
            'dateTo' => $dateTo->toDateString(),
            'summary' => [
                'revenue_minor' => $revenueMinor,
                'refund_minor' => $refundMinor,
                'discount_minor' => $discountMinor,
                'expense_minor' => $expenseMinor,
                'purchase_spend_minor' => $purchaseSpendMinor,
                'cogs_minor' => $cogsMinor,
                'gross_profit_minor' => $grossProfitMinor,
                'net_profit_minor' => $netProfitMinor,
                'cash_in_minor' => $cashInMinor,
                'cash_out_minor' => $cashOutMinor,
                'net_cash_flow_minor' => $cashInMinor - $cashOutMinor,
                'accounts_receivable_minor' => $accountsReceivableMinor,
                'accounts_payable_minor' => $accountsPayableMinor,
                'inventory_value_minor' => $inventoryValueMinor,
                'cash_minor' => $cashMinor,
                'petty_cash_minor' => $pettyCashBalanceMinor,
                'assets_minor' => $cashMinor + $accountsReceivableMinor + $inventoryValueMinor,
                'equity_minor' => ($cashMinor + $accountsReceivableMinor + $inventoryValueMinor) - $accountsPayableMinor,
            ],
            'orders' => $orders,
            'purchaseOrders' => $purchaseOrders,
            'salesPayments' => $salesPayments,
            'vendorPayments' => $vendorPayments,
            'branchProfitability' => $this->branchProfitability($salesItems, $orders),
            'productProfitability' => $this->productProfitability($salesItems),
            'accounts' => $accounts,
            'expenseCategories' => $expenseCategories,
            'operationalExpenses' => $operationalExpenses,
            'pettyCashTransactions' => $pettyCashTransactions,
            'journalEntries' => $journalEntries,
            'customerBalances' => $this->customerBalances($tenant->id),
            'vendorBalances' => $this->vendorBalances($tenant->id),
        ]);
    }

    public function chartOfAccounts(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $tenants = $this->visibleTenantsFor($user);
        $tenant = $this->resolveTenant($request, $tenants);

        abort_if(! $tenant, 403);
        app(EnsureDefaultChartOfAccountsAction::class)->execute($tenant->id);

        return view('finance::admin.chart-of-accounts', [
            'tenant' => $tenant,
            'tenants' => $tenants,
            'isPlatformAdmin' => $user->is_platform_admin,
            'accounts' => FinanceAccount::query()->where('tenant_id', $tenant->id)->orderBy('code')->get(),
        ]);
    }

    public function expenses(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $tenants = $this->visibleTenantsFor($user);
        $tenant = $this->resolveTenant($request, $tenants);

        abort_if(! $tenant, 403);
        app(EnsureDefaultChartOfAccountsAction::class)->execute($tenant->id);

        $expenseCategories = FinanceExpenseCategory::query()
            ->with('account')
            ->where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get();
        $operationalExpenses = FinanceExpense::query()
            ->with('category')
            ->where('tenant_id', $tenant->id)
            ->latest('expense_date')
            ->limit(100)
            ->get();
        $pettyCashTransactions = FinancePettyCashTransaction::query()
            ->with('category')
            ->where('tenant_id', $tenant->id)
            ->latest('transaction_date')
            ->limit(100)
            ->get();
        $journalEntries = FinanceJournalEntry::query()
            ->with('lines.account')
            ->where('tenant_id', $tenant->id)
            ->latest('entry_date')
            ->limit(100)
            ->get();
        $accounts = FinanceAccount::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return view('finance::admin.expenses', [
            'tenant' => $tenant,
            'tenants' => $tenants,
            'isPlatformAdmin' => $user->is_platform_admin,
            'expenseCategories' => $expenseCategories,
            'operationalExpenses' => $operationalExpenses,
            'pettyCashTransactions' => $pettyCashTransactions,
            'journalEntries' => $journalEntries,
            'accounts' => $accounts,
            'pettyCashBalanceMinor' => $this->accountBalance($tenant->id, '1010'),
        ]);
    }

    public function storeExpenseCategory(ExpenseCategoryRequest $request): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());
        app(EnsureDefaultChartOfAccountsAction::class)->execute($request->string('tenant_id')->toString());

        $data = $request->validated();

        $account = FinanceAccount::query()->firstOrCreate([
            'tenant_id' => $data['tenant_id'],
            'code' => 'EXP-'.Str::upper(Str::slug($data['code'], '')),
        ], [
            'name' => $data['name'].' Expense',
            'type' => 'expense',
            'normal_balance' => 'debit',
            'is_system' => false,
            'is_active' => true,
        ]);

        $category = FinanceExpenseCategory::query()->create([
            'tenant_id' => $data['tenant_id'],
            'finance_account_id' => $account->id,
            'name' => $data['name'],
            'code' => Str::slug($data['code']),
            'description' => $data['description'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return redirect()->to(route('admin.finance.expenses', ['tenant' => $category->tenant_id]).'#categories')->with('status', 'Expense category created.');
    }

    public function updateExpenseCategory(ExpenseCategoryRequest $request, FinanceExpenseCategory $category): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $category->tenant_id);
        $data = $request->validated();
        abort_unless($data['tenant_id'] === $category->tenant_id, 403);

        $category->update([
            'name' => $data['name'],
            'code' => Str::slug($data['code']),
            'description' => $data['description'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);
        $category->account?->update(['name' => $data['name'].' Expense', 'is_active' => (bool) ($data['is_active'] ?? false)]);

        return redirect()->to(route('admin.finance.expenses', ['tenant' => $category->tenant_id]).'#categories')->with('status', 'Expense category updated.');
    }

    public function storeExpense(ExpenseRequest $request, PostJournalEntryAction $postJournalEntry): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());
        $data = $request->validated();
        $category = FinanceExpenseCategory::query()->with('account')->where('tenant_id', $data['tenant_id'])->findOrFail($data['finance_expense_category_id']);
        $amountMinor = $this->moneyToMinor($data['amount']);
        $paidMinor = match ($data['payment_status']) {
            'paid' => $amountMinor,
            'unpaid' => 0,
            default => min($amountMinor, $this->moneyToMinor($data['paid_amount'] ?? 0)),
        };

        $expense = DB::transaction(function () use ($data, $category, $amountMinor, $paidMinor, $postJournalEntry): FinanceExpense {
            $expense = FinanceExpense::query()->create([
                'tenant_id' => $data['tenant_id'],
                'finance_expense_category_id' => $category->id,
                'expense_number' => $this->number('EXP', $data['tenant_id'], FinanceExpense::class),
                'expense_date' => $data['expense_date'],
                'payee_name' => $data['payee_name'] ?? null,
                'payment_method' => $data['payment_method'],
                'payment_status' => $data['payment_status'],
                'amount_minor' => $amountMinor,
                'paid_minor' => $paidMinor,
                'reference_number' => $data['reference_number'] ?? null,
                'description' => $data['description'] ?? null,
            ]);

            $postJournalEntry->execute(
                $expense->tenant_id,
                $expense->expense_date->toDateString(),
                'Operational expense '.$expense->expense_number,
                [
                    ['account_code' => $category->account->code, 'debit_minor' => $amountMinor, 'memo' => $expense->description],
                    ['account_code' => $this->cashAccountFor($expense->payment_method), 'credit_minor' => $paidMinor, 'memo' => $expense->payment_method],
                    ['account_code' => '2000', 'credit_minor' => max(0, $amountMinor - $paidMinor), 'party_type' => 'payee', 'memo' => $expense->payee_name],
                ],
                'finance_expense',
                $expense->id,
                'recorded',
            );

            return $expense;
        });

        return redirect()->to(route('admin.finance.expenses', ['tenant' => $expense->tenant_id]).'#expense-list')->with('status', 'Expense recorded and journal posted.');
    }

    public function storePettyCashTransaction(PettyCashTransactionRequest $request, PostJournalEntryAction $postJournalEntry): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());
        $data = $request->validated();
        $amountMinor = $this->moneyToMinor($data['amount']);

        if ($data['transaction_type'] === 'expense' && empty($data['finance_expense_category_id'])) {
            return back()->withErrors(['finance_expense_category_id' => 'Choose an expense category for petty cash expenses.'])->withInput();
        }

        $transaction = DB::transaction(function () use ($data, $amountMinor, $postJournalEntry): FinancePettyCashTransaction {
            $transaction = FinancePettyCashTransaction::query()->create([
                'tenant_id' => $data['tenant_id'],
                'finance_expense_category_id' => $data['finance_expense_category_id'] ?? null,
                'transaction_number' => $this->number('PC', $data['tenant_id'], FinancePettyCashTransaction::class, 'transaction_number'),
                'transaction_date' => $data['transaction_date'],
                'transaction_type' => $data['transaction_type'],
                'amount_minor' => $amountMinor,
                'payee_name' => $data['payee_name'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'description' => $data['description'] ?? null,
            ]);
            $category = $transaction->category?->load('account');

            $lines = match ($transaction->transaction_type) {
                'top_up' => [
                    ['account_code' => '1010', 'debit_minor' => $amountMinor],
                    ['account_code' => '1000', 'credit_minor' => $amountMinor],
                ],
                'return_to_bank' => [
                    ['account_code' => '1000', 'debit_minor' => $amountMinor],
                    ['account_code' => '1010', 'credit_minor' => $amountMinor],
                ],
                default => [
                    ['account_code' => $category?->account?->code ?? '6000', 'debit_minor' => $amountMinor, 'memo' => $transaction->description],
                    ['account_code' => '1010', 'credit_minor' => $amountMinor],
                ],
            };

            $postJournalEntry->execute(
                $transaction->tenant_id,
                $transaction->transaction_date->toDateString(),
                'Petty cash '.$transaction->transaction_number,
                $lines,
                'finance_petty_cash_transaction',
                $transaction->id,
                $transaction->transaction_type,
            );

            return $transaction;
        });

        return redirect()->to(route('admin.finance.expenses', ['tenant' => $transaction->tenant_id]).'#petty-cash')->with('status', 'Petty cash transaction posted.');
    }

    public function storeJournalEntry(ManualJournalEntryRequest $request, PostJournalEntryAction $postJournalEntry): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());
        $data = $request->validated();

        $postJournalEntry->execute(
            $data['tenant_id'],
            $data['entry_date'],
            $data['memo'],
            collect($data['lines'])->map(fn (array $line): array => [
                'account_code' => $line['account_code'],
                'debit_minor' => $this->moneyToMinor($line['debit'] ?? 0),
                'credit_minor' => $this->moneyToMinor($line['credit'] ?? 0),
                'memo' => $line['memo'] ?? null,
            ])->all(),
            'manual_journal',
            null,
            null,
        );

        return redirect()->to(route('admin.finance.expenses', ['tenant' => $data['tenant_id']]).'#journals')->with('status', 'Journal entry posted.');
    }

    private function visibleTenantsFor(User $user): EloquentCollection
    {
        if ($user->is_platform_admin) {
            return Tenant::query()->orderBy('name')->get();
        }

        return Tenant::query()
            ->whereHas('memberships', fn ($query) => $query->where('user_id', $user->id)->where('status', MembershipStatus::Active->value))
            ->orderBy('name')
            ->get();
    }

    private function authorizeTenantIdAccess(?User $user, string $tenantId): void
    {
        abort_unless($user instanceof User, 403);

        if ($user->is_platform_admin) {
            return;
        }

        abort_unless(TenantMembership::query()->where('tenant_id', $tenantId)->where('user_id', $user->id)->where('status', MembershipStatus::Active->value)->exists(), 403);
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

    private function costOfGoodsSold(Collection $salesItems): int
    {
        return (int) $salesItems->sum(fn (SalesOrderItem $item): int => max(0, $item->quantity - $item->quantity_returned) * $this->unitCostMinor($item));
    }

    private function unitCostMinor(SalesOrderItem $item): int
    {
        return (int) ($item->unit_cost_minor ?: $item->variant?->cost_price_minor ?: $item->variant?->product?->base_cost_price_minor ?: 0);
    }

    private function branchProfitability(Collection $salesItems, Collection $orders): Collection
    {
        return $orders
            ->groupBy(fn (SalesOrder $order): string => (string) ($order->branch_id ?: 'unassigned'))
            ->map(function (Collection $branchOrders, string $branchId) use ($salesItems): array {
                $branchItems = $salesItems->filter(fn (SalesOrderItem $item): bool => (string) ($item->order?->branch_id ?: 'unassigned') === $branchId);
                $revenueMinor = (int) $branchOrders->sum('total_minor');
                $refundMinor = (int) $branchOrders->sum('refunded_minor');
                $cogsMinor = $this->costOfGoodsSold($branchItems);

                return [
                    'name' => $branchOrders->first()?->branch?->name ?? 'Unassigned branch',
                    'orders' => $branchOrders->count(),
                    'revenue_minor' => $revenueMinor,
                    'refund_minor' => $refundMinor,
                    'cogs_minor' => $cogsMinor,
                    'profit_minor' => $revenueMinor - $refundMinor - $cogsMinor,
                ];
            })
            ->sortByDesc('profit_minor')
            ->values();
    }

    private function productProfitability(Collection $salesItems): Collection
    {
        return $salesItems
            ->groupBy('product_variant_id')
            ->map(function (Collection $items): array {
                /** @var SalesOrderItem $first */
                $first = $items->first();
                $quantity = (int) $items->sum(fn (SalesOrderItem $item): int => max(0, $item->quantity - $item->quantity_returned));
                $revenueMinor = (int) $items->sum('line_total_minor');
                $cogsMinor = $this->costOfGoodsSold($items);

                return [
                    'name' => trim(($first->variant?->product?->name ?? $first->item_name).' / '.($first->variant?->variant_name ?? $first->sku ?? 'Default')),
                    'sku' => $first->sku ?? $first->variant?->sku,
                    'quantity' => $quantity,
                    'revenue_minor' => $revenueMinor,
                    'cogs_minor' => $cogsMinor,
                    'profit_minor' => $revenueMinor - $cogsMinor,
                ];
            })
            ->sortByDesc('profit_minor')
            ->values();
    }

    private function cashAccountFor(?string $paymentMethod): string
    {
        return str_contains(Str::lower((string) $paymentMethod), 'petty') ? '1010' : '1000';
    }

    private function accountBalance(string $tenantId, string $accountCode): int
    {
        $account = FinanceAccount::query()->where('tenant_id', $tenantId)->where('code', $accountCode)->first();

        if (! $account) {
            return 0;
        }

        $debitMinor = (int) FinanceJournalLine::query()->where('tenant_id', $tenantId)->where('finance_account_id', $account->id)->sum('debit_minor');
        $creditMinor = (int) FinanceJournalLine::query()->where('tenant_id', $tenantId)->where('finance_account_id', $account->id)->sum('credit_minor');

        return $account->normal_balance === 'credit' ? $creditMinor - $debitMinor : $debitMinor - $creditMinor;
    }

    private function customerBalances(string $tenantId): Collection
    {
        $customers = Customer::query()->where('tenant_id', $tenantId)->orderBy('first_name')->get()->keyBy('id');
        $account = FinanceAccount::query()->where('tenant_id', $tenantId)->where('code', '1100')->first();

        if (! $account) {
            return collect();
        }

        return FinanceJournalLine::query()
            ->where('tenant_id', $tenantId)
            ->where('finance_account_id', $account->id)
            ->where('party_type', 'customer')
            ->get()
            ->groupBy('party_id')
            ->map(function (Collection $lines, int|string $customerId) use ($customers): array {
                $balance = (int) $lines->sum('debit_minor') - (int) $lines->sum('credit_minor');

                return [
                    'customer' => $customers->get((int) $customerId),
                    'debt_minor' => max(0, $balance),
                    'credit_minor' => max(0, -$balance),
                ];
            })
            ->filter(fn (array $row): bool => $row['customer'] !== null)
            ->values();
    }

    private function vendorBalances(string $tenantId): Collection
    {
        $vendors = Vendor::query()->where('tenant_id', $tenantId)->orderBy('name')->get()->keyBy('id');
        $account = FinanceAccount::query()->where('tenant_id', $tenantId)->where('code', '2000')->first();

        if (! $account) {
            return collect();
        }

        return FinanceJournalLine::query()
            ->where('tenant_id', $tenantId)
            ->where('finance_account_id', $account->id)
            ->where('party_type', 'vendor')
            ->get()
            ->groupBy('party_id')
            ->map(function (Collection $lines, int|string $vendorId) use ($vendors): array {
                $balance = (int) $lines->sum('credit_minor') - (int) $lines->sum('debit_minor');

                return [
                    'vendor' => $vendors->get((int) $vendorId),
                    'payable_minor' => max(0, $balance),
                    'prepaid_minor' => max(0, -$balance),
                ];
            })
            ->filter(fn (array $row): bool => $row['vendor'] !== null)
            ->values();
    }

    private function number(string $prefix, string $tenantId, string $modelClass, string $column = 'expense_number'): string
    {
        return $prefix.'-'.now()->format('Ymd').'-'.str_pad((string) ($modelClass::query()->where('tenant_id', $tenantId)->count() + 1), 5, '0', STR_PAD_LEFT);
    }

    private function moneyToMinor(mixed $value): int
    {
        return (int) round(((float) (is_string($value) ? str_replace(',', '', $value) : ($value ?: 0))) * 100);
    }
}
