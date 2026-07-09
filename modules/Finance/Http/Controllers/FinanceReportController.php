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
use Symfony\Component\HttpFoundation\StreamedResponse;
use Modules\Access\Enums\MembershipStatus;
use Modules\Access\Models\TenantMembership;
use Modules\Business\Models\Branch;
use Modules\Customers\Models\Customer;
use Modules\Finance\Actions\EnsureDefaultChartOfAccountsAction;
use Modules\Finance\Actions\PostJournalEntryAction;
use Modules\Finance\Http\Requests\BankMovementRequest;
use Modules\Finance\Http\Requests\ExpenseCategoryRequest;
use Modules\Finance\Http\Requests\ExpenseRequest;
use Modules\Finance\Http\Requests\ManualJournalEntryRequest;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceBankMovement;
use Modules\Finance\Models\FinanceExpense;
use Modules\Finance\Models\FinanceExpenseCategory;
use Modules\Finance\Models\FinanceJournalEntry;
use Modules\Finance\Models\FinanceJournalLine;
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
        $branches = Branch::query()->where('tenant_id', $tenant->id)->orderBy('name')->get();
        $selectedBranchId = $request->string('branch_id')->toString();
        if ($selectedBranchId !== '') {
            abort_unless($branches->contains('id', (int) $selectedBranchId), 403);
        }
        $selectedBranch = $selectedBranchId !== '' ? $branches->firstWhere('id', (int) $selectedBranchId) : null;
        $selectedReport = $request->string('report')->toString();

        if (! array_key_exists($selectedReport, self::REPORTS)) {
            $selectedReport = array_key_first(self::REPORTS);
        }

        $orders = SalesOrder::query()
            ->with(['branch', 'customer', 'items.variant.product'])
            ->where('tenant_id', $tenant->id)
            ->when($selectedBranchId !== '', fn ($query) => $query->where('branch_id', $selectedBranchId))
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
            ->when($selectedBranchId !== '', fn ($query) => $query->whereHas('order', fn ($orderQuery) => $orderQuery->where('branch_id', $selectedBranchId)))
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
            ->when($selectedBranchId !== '', fn ($query) => $query->where('branch_id', $selectedBranchId))
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
                ->when($selectedBranchId !== '', fn ($orderQuery) => $orderQuery->where('branch_id', $selectedBranchId))
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
            'branches' => $branches,
            'selectedBranchId' => $selectedBranchId,
            'selectedBranch' => $selectedBranch,
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
            'journalEntries' => $journalEntries,
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

    public function journals(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $tenants = $this->visibleTenantsFor($user);
        $tenant = $this->resolveTenant($request, $tenants);

        abort_if(! $tenant, 403);
        app(EnsureDefaultChartOfAccountsAction::class)->execute($tenant->id);

        $accounts = FinanceAccount::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('code')
            ->get();
        $expenseCategories = FinanceExpenseCategory::query()
            ->with('account')
            ->where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get();
        $journalFilters = $this->journalFilters($request);
        $journalEntries = $this->journalEntriesQuery($tenant->id, $journalFilters)
            ->paginate(25, ['*'], 'journals_page')
            ->withQueryString()
            ->fragment('journal-entries');
        $bankMovementSources = collect([
            '1030' => ['label' => 'Bank Cash from Vault', 'description' => 'Move physical cash already handed over from tills into an actual bank account.'],
            '1040' => ['label' => 'Reconcile Bank Transfer', 'description' => 'Move confirmed customer transfer receipts from clearing into the receiving bank account.'],
            '1050' => ['label' => 'Settle POS/Card', 'description' => 'Move POS or card settlement from clearing into the bank account, with optional charges.'],
            '1060' => ['label' => 'Settle Online Payment', 'description' => 'Move gateway settlement from clearing into the bank account, with optional charges.'],
        ])->map(function (array $source, string $code) use ($tenant): array {
            $account = FinanceAccount::query()->where('tenant_id', $tenant->id)->where('code', $code)->first();

            return [
                ...$source,
                'code' => $code,
                'account' => $account,
                'balance_minor' => $this->accountBalance($tenant->id, $code),
            ];
        });
        $bankAccounts = FinanceAccount::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', 'asset')
            ->where('code', 'like', 'BANK-%')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $bankMovements = FinanceBankMovement::query()
            ->with(['branch', 'sourceAccount', 'destinationAccount', 'feeAccount', 'postedBy'])
            ->where('tenant_id', $tenant->id)
            ->when($journalFilters['branch'] !== '', fn ($query) => $query->where('branch_id', $journalFilters['branch']))
            ->latest('movement_date')
            ->latest('id')
            ->limit(20)
            ->get();

        // Branch ledger snapshot + party balances (moved here from the Report page).
        $ledgerDateFrom = CarbonImmutable::parse($journalFilters['date_from'] ?: now()->startOfMonth()->toDateString())->startOfDay();
        $ledgerDateTo = CarbonImmutable::parse($journalFilters['date_to'] ?: now()->toDateString())->endOfDay();
        $ledgerBranchId = $journalFilters['branch'];
        $ledgerBranch = $ledgerBranchId !== ''
            ? Branch::query()->where('tenant_id', $tenant->id)->find((int) $ledgerBranchId)
            : null;

        return view('finance::admin.journals', [
            'tenant' => $tenant,
            'tenants' => $tenants,
            'isPlatformAdmin' => $user->is_platform_admin,
            'accounts' => $accounts,
            'expenseCategories' => $expenseCategories,
            'journalEntries' => $journalEntries,
            'journalFilters' => $journalFilters,
            'journalAccountCategories' => $accounts->pluck('category')->filter()->unique()->sort()->values(),
            'journalAccountTypes' => $accounts->pluck('type')->filter()->unique()->sort()->values(),
            'branches' => Branch::query()->where('tenant_id', $tenant->id)->orderBy('name')->get(),
            'bankMovementSources' => $bankMovementSources,
            'bankAccounts' => $bankAccounts,
            'bankMovements' => $bankMovements,
            'ledgerDateFrom' => $ledgerDateFrom->toDateString(),
            'ledgerDateTo' => $ledgerDateTo->toDateString(),
            'selectedBranch' => $ledgerBranch,
            'branchLedgerSummary' => $this->branchLedgerSummary($tenant->id, $ledgerDateFrom->toDateString(), $ledgerDateTo->toDateString(), $ledgerBranchId),
            'customerBalances' => $this->customerBalances($tenant->id),
            'vendorBalances' => $this->vendorBalances($tenant->id),
            'partySummary' => $this->partyBalanceSummary($tenant->id, $ledgerBranchId, $ledgerDateTo->toDateString()),
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

        $expenseFilters = $this->expenseFilters($request);
        $expenseCategories = FinanceExpenseCategory::query()
            ->with('account')
            ->where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get();
        $operationalExpenses = $this->expensesQuery($tenant->id, $expenseFilters)
            ->paginate(25, ['*'], 'expenses_page')
            ->withQueryString()
            ->fragment('expense-list');
        $expenseAccounts = FinanceAccount::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', 'expense')
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('code')
            ->get();
        $expenseAccountCategories = $expenseAccounts
            ->pluck('category')
            ->filter()
            ->unique()
            ->sort()
            ->values();
        $assetAccounts = FinanceAccount::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', 'asset')
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return view('finance::admin.expenses', [
            'tenant' => $tenant,
            'tenants' => $tenants,
            'isPlatformAdmin' => $user->is_platform_admin,
            'expenseCategories' => $expenseCategories,
            'operationalExpenses' => $operationalExpenses,
            'expenseFilters' => $expenseFilters,
            'expenseAccounts' => $expenseAccounts,
            'expenseAccountCategories' => $expenseAccountCategories,
            'assetAccounts' => $assetAccounts,
            'branches' => Branch::query()->where('tenant_id', $tenant->id)->orderBy('name')->get(),
            'pettyCashBalanceMinor' => $this->accountBalance($tenant->id, '1010'),
        ]);
    }

    public function downloadJournalEntries(Request $request): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tenants = $this->visibleTenantsFor($user);
        $tenant = $this->resolveTenant($request, $tenants);

        abort_if(! $tenant, 403);
        app(EnsureDefaultChartOfAccountsAction::class)->execute($tenant->id);

        $journalFilters = $this->journalFilters($request);
        $entries = $this->journalEntriesQuery($tenant->id, $journalFilters)->get();
        $filename = 'journal-entries-'.$tenant->slug.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($entries): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Source', 'Particulars', 'Post Ref', 'Debit (DR)', 'Credit (CR)', 'Branch', 'Memo', 'Entry No']);

            foreach ($entries as $entry) {
                foreach ($entry->lines as $line) {
                    fputcsv($handle, [
                        $entry->entry_date->format('Y-M-d'),
                        $this->journalSourceLabel($entry->source_type),
                        $line->memo ?: $line->account->name,
                        $line->account->code,
                        $line->debit_minor > 0 ? number_format($line->debit_minor / 100, 2, '.', '') : '',
                        $line->credit_minor > 0 ? number_format($line->credit_minor / 100, 2, '.', '') : '',
                        $line->branch?->name ?? 'Unassigned branch',
                        $entry->memo,
                        $entry->entry_number,
                    ]);
                }
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
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

        return redirect()->to(route('admin.finance.journals', ['tenant' => $category->tenant_id]).'#expense-categories')->with('status', 'Expense category created.');
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

        return redirect()->to(route('admin.finance.journals', ['tenant' => $category->tenant_id]).'#expense-categories')->with('status', 'Expense category updated.');
    }

    public function storeExpense(ExpenseRequest $request, PostJournalEntryAction $postJournalEntry): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());
        $data = $request->validated();
        $expenseAccount = FinanceAccount::query()->where('tenant_id', $data['tenant_id'])->where('code', $data['expense_account_code'])->firstOrFail();
        $category = FinanceExpenseCategory::query()->firstOrCreate([
            'tenant_id' => $data['tenant_id'],
            'code' => Str::slug($data['expense_category']),
        ], [
            'finance_account_id' => $expenseAccount->id,
            'name' => $data['expense_category'],
            'description' => 'Expense category for '.$data['expense_category'].'.',
            'is_active' => true,
        ]);
        $amountMinor = $this->moneyToMinor($data['amount']);
        $paidMinor = match ($data['payment_status']) {
            'paid' => $amountMinor,
            'unpaid' => 0,
            default => min($amountMinor, $this->moneyToMinor($data['paid_amount'] ?? 0)),
        };
        $paymentAccount = $paidMinor > 0
            ? FinanceAccount::query()->where('tenant_id', $data['tenant_id'])->where('code', $data['payment_account_code'])->firstOrFail()
            : null;

        $expense = DB::transaction(function () use ($data, $category, $expenseAccount, $paymentAccount, $amountMinor, $paidMinor, $postJournalEntry): FinanceExpense {
            $expense = FinanceExpense::query()->create([
                'tenant_id' => $data['tenant_id'],
                'finance_expense_category_id' => $category->id,
                'finance_account_id' => $expenseAccount->id,
                'payment_finance_account_id' => $paymentAccount?->id,
                'expense_number' => $this->number('EXP', $data['tenant_id'], FinanceExpense::class),
                'expense_date' => $data['expense_date'],
                'payee_name' => $data['payee_name'] ?? null,
                'payment_method' => $paymentAccount?->code ?? 'Unpaid',
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
                    ['account_code' => $expenseAccount->code, 'branch_id' => $data['branch_id'] ?? null, 'debit_minor' => $amountMinor, 'memo' => $expense->description],
                    ['account_code' => $paymentAccount?->code ?? '1000', 'branch_id' => $data['branch_id'] ?? null, 'credit_minor' => $paidMinor, 'memo' => $paymentAccount?->name],
                    ['account_code' => '2000', 'branch_id' => $data['branch_id'] ?? null, 'credit_minor' => max(0, $amountMinor - $paidMinor), 'party_type' => 'payee', 'memo' => $expense->payee_name],
                ],
                'finance_expense',
                $expense->id,
                'recorded',
            );

            return $expense;
        });

        return redirect()->to(route('admin.finance.expenses', ['tenant' => $expense->tenant_id]).'#expense-list')->with('status', 'Expense recorded and journal posted.');
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
                'branch_id' => $line['branch_id'] ?? null,
                'memo' => $line['memo'] ?? null,
            ])->all(),
            'manual_journal',
            null,
            null,
        );

        return redirect()->to(route('admin.finance.journals', ['tenant' => $data['tenant_id']]).'#journal-entries')->with('status', 'Journal entry posted.');
    }

    public function storeBankMovement(BankMovementRequest $request, PostJournalEntryAction $postJournalEntry): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());
        app(EnsureDefaultChartOfAccountsAction::class)->execute($request->string('tenant_id')->toString());

        $data = $request->validated();
        $grossMinor = $this->moneyToMinor($data['gross_amount']);
        $feeMinor = $this->moneyToMinor($data['fee_amount'] ?? 0);
        $netMinor = $grossMinor - $feeMinor;
        $sourceAccount = FinanceAccount::query()->where('tenant_id', $data['tenant_id'])->where('code', $data['source_account_code'])->firstOrFail();
        $destinationAccount = FinanceAccount::query()->where('tenant_id', $data['tenant_id'])->where('code', $data['destination_account_code'])->firstOrFail();
        $feeAccount = $feeMinor > 0
            ? FinanceAccount::query()->where('tenant_id', $data['tenant_id'])->where('code', 'EXP-6350')->firstOrFail()
            : null;
        $memo = $this->bankMovementLabel($data['movement_type']).' '.$sourceAccount->code.' to '.$destinationAccount->code;

        $movement = DB::transaction(function () use ($data, $grossMinor, $feeMinor, $netMinor, $sourceAccount, $destinationAccount, $feeAccount, $memo, $postJournalEntry): FinanceBankMovement {
            $movement = FinanceBankMovement::query()->create([
                'tenant_id' => $data['tenant_id'],
                'branch_id' => $data['branch_id'] ?? null,
                'source_finance_account_id' => $sourceAccount->id,
                'destination_finance_account_id' => $destinationAccount->id,
                'fee_finance_account_id' => $feeAccount?->id,
                'movement_number' => $this->number('BNK', $data['tenant_id'], FinanceBankMovement::class, 'movement_number'),
                'movement_type' => $data['movement_type'],
                'movement_date' => $data['movement_date'],
                'gross_amount_minor' => $grossMinor,
                'fee_amount_minor' => $feeMinor,
                'net_amount_minor' => $netMinor,
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'posted_by' => auth()->id(),
            ]);

            $lines = [
                ['account_code' => $destinationAccount->code, 'branch_id' => $data['branch_id'] ?? null, 'debit_minor' => $netMinor, 'memo' => 'Net amount received into bank.'],
                ['account_code' => $sourceAccount->code, 'branch_id' => $data['branch_id'] ?? null, 'credit_minor' => $grossMinor, 'memo' => 'Amount cleared from '.$sourceAccount->name.'.'],
            ];

            if ($feeMinor > 0 && $feeAccount) {
                $lines[] = ['account_code' => $feeAccount->code, 'branch_id' => $data['branch_id'] ?? null, 'debit_minor' => $feeMinor, 'memo' => 'Settlement charges.'];
            }

            $journal = $postJournalEntry->execute(
                $data['tenant_id'],
                $data['movement_date'],
                $memo,
                $lines,
                'finance_bank_movement',
                $movement->id,
                'posted',
            );

            $movement->update(['finance_journal_entry_id' => $journal?->id]);

            return $movement;
        });

        return redirect()->to(route('admin.finance.journals', ['tenant' => $movement->tenant_id]).'#banking')->with('status', 'Banking movement posted.');
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

    /**
     * @return array{date_from: string, date_to: string, category: string, status: string, expense_account: string, payment_account: string, payee: string, reference: string}
     */
    private function expenseFilters(Request $request): array
    {
        return [
            'date_from' => $request->string('expense_date_from')->toString(),
            'date_to' => $request->string('expense_date_to')->toString(),
            'category' => $request->string('expense_category_id')->toString(),
            'status' => $request->string('expense_payment_status')->toString(),
            'expense_account' => $request->string('expense_account_code')->toString(),
            'payment_account' => $request->string('expense_payment_account_code')->toString(),
            'payee' => $request->string('expense_payee')->toString(),
            'reference' => $request->string('expense_reference')->toString(),
        ];
    }

    /**
     * @param  array{date_from: string, date_to: string, category: string, status: string, expense_account: string, payment_account: string, payee: string, reference: string}  $expenseFilters
     */
    private function expensesQuery(string $tenantId, array $expenseFilters)
    {
        return FinanceExpense::query()
            ->with(['category', 'expenseAccount', 'paymentAccount'])
            ->where('tenant_id', $tenantId)
            ->when($expenseFilters['date_from'] !== '', fn ($query) => $query->whereDate('expense_date', '>=', $expenseFilters['date_from']))
            ->when($expenseFilters['date_to'] !== '', fn ($query) => $query->whereDate('expense_date', '<=', $expenseFilters['date_to']))
            ->when($expenseFilters['category'] !== '', fn ($query) => $query->where('finance_expense_category_id', $expenseFilters['category']))
            ->when($expenseFilters['status'] !== '', fn ($query) => $query->where('payment_status', $expenseFilters['status']))
            ->when($expenseFilters['expense_account'] !== '', fn ($query) => $query->whereHas('expenseAccount', fn ($accountQuery) => $accountQuery->where('code', $expenseFilters['expense_account'])))
            ->when($expenseFilters['payment_account'] !== '', fn ($query) => $query->whereHas('paymentAccount', fn ($accountQuery) => $accountQuery->where('code', $expenseFilters['payment_account'])))
            ->when($expenseFilters['payee'] !== '', fn ($query) => $query->where('payee_name', 'like', '%'.$expenseFilters['payee'].'%'))
            ->when($expenseFilters['reference'] !== '', function ($query) use ($expenseFilters): void {
                $search = '%'.$expenseFilters['reference'].'%';

                $query->where(function ($inner) use ($search): void {
                    $inner->where('reference_number', 'like', $search)
                        ->orWhere('expense_number', 'like', $search)
                        ->orWhere('description', 'like', $search);
                });
            })
            ->latest('expense_date')
            ->latest('id');
    }

    /**
     * @return array{date_from: string, date_to: string, category: string, type: string, account: string, branch: string}
     */
    private function journalFilters(Request $request): array
    {
        return [
            'date_from' => $request->string('journal_date_from')->toString(),
            'date_to' => $request->string('journal_date_to')->toString(),
            'category' => $request->string('journal_category')->toString(),
            'type' => $request->string('journal_type')->toString(),
            'account' => $request->string('journal_account')->toString(),
            'branch' => $request->string('journal_branch_id')->toString(),
        ];
    }

    /**
     * @param  array{date_from: string, date_to: string, category: string, type: string, account: string, branch: string}  $journalFilters
     */
    private function journalEntriesQuery(string $tenantId, array $journalFilters)
    {
        return FinanceJournalEntry::query()
            ->with(['lines.account', 'lines.branch'])
            ->where('tenant_id', $tenantId)
            ->when($journalFilters['date_from'] !== '', fn ($query) => $query->whereDate('entry_date', '>=', $journalFilters['date_from']))
            ->when($journalFilters['date_to'] !== '', fn ($query) => $query->whereDate('entry_date', '<=', $journalFilters['date_to']))
            ->when($journalFilters['branch'] !== '', fn ($query) => $query->whereHas('lines', fn ($lineQuery) => $lineQuery->where('branch_id', $journalFilters['branch'])))
            ->when($journalFilters['category'] !== '', fn ($query) => $query->whereHas('lines.account', fn ($accountQuery) => $accountQuery->where('category', $journalFilters['category'])))
            ->when($journalFilters['type'] !== '', fn ($query) => $query->whereHas('lines.account', fn ($accountQuery) => $accountQuery->where('type', $journalFilters['type'])))
            ->when($journalFilters['account'] !== '', function ($query) use ($journalFilters): void {
                $search = '%'.$journalFilters['account'].'%';

                $query->whereHas('lines.account', fn ($accountQuery) => $accountQuery
                    ->where('code', 'like', $search)
                    ->orWhere('name', 'like', $search));
            })
            ->latest('entry_date')
            ->latest('id');
    }

    private function journalSourceLabel(?string $sourceType): string
    {
        return match ($sourceType) {
            'hr_payroll_run' => 'Payroll posting',
            'finance_expense' => 'Expense posting',
            'vendor_payment' => 'Vendor payment posting',
            'purchase_order' => 'Purchase posting',
            'sales_order' => 'Sales posting',
            'sales_payment' => 'Sales payment posting',
            'sales_return' => 'Sales return posting',
            'inventory_movement' => 'Inventory posting',
            'till_movement' => 'Till posting',
            'finance_bank_movement' => 'Banking movement',
            'manual_journal' => 'Manual journal',
            null => 'Manual journal',
            default => Str::headline($sourceType),
        };
    }

    private function bankMovementLabel(string $movementType): string
    {
        return match ($movementType) {
            'bank_cash' => 'Bank cash from vault',
            'reconcile_transfer' => 'Reconcile bank transfer',
            'settle_pos' => 'Settle POS/Card',
            'settle_online' => 'Settle online payment',
            default => Str::headline($movementType),
        };
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

    /**
     * @return array{accounts_receivable_minor: int, accounts_payable_minor: int, petty_cash_minor: int}
     */
    private function partyBalanceSummary(string $tenantId, string $branchId, string $dateTo): array
    {
        $accountsReceivableMinor = (int) SalesOrder::query()
            ->where('tenant_id', $tenantId)
            ->when($branchId !== '', fn ($query) => $query->where('branch_id', $branchId))
            ->where('order_status', '!=', SalesOrderStatus::Cancelled->value)
            ->whereDate('order_date', '<=', $dateTo)
            ->get()
            ->sum(fn (SalesOrder $order): int => $order->balance_minor);

        $accountsPayableMinor = (int) PurchaseOrder::query()
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', PurchaseOrderStatus::Cancelled->value)
            ->whereDate('order_date', '<=', $dateTo)
            ->get()
            ->sum(fn (PurchaseOrder $order): int => $order->balance_minor)
            + (int) FinanceExpense::query()
                ->where('tenant_id', $tenantId)
                ->whereDate('expense_date', '<=', $dateTo)
                ->get()
                ->sum(fn (FinanceExpense $expense): int => max(0, $expense->amount_minor - $expense->paid_minor));

        return [
            'accounts_receivable_minor' => $accountsReceivableMinor,
            'accounts_payable_minor' => $accountsPayableMinor,
            'petty_cash_minor' => $this->accountBalance($tenantId, '1010'),
        ];
    }

    private function branchLedgerSummary(string $tenantId, string $dateFrom, string $dateTo, string $branchId): Collection
    {
        return FinanceJournalLine::query()
            ->with('account')
            ->where('tenant_id', $tenantId)
            ->when($branchId !== '', fn ($query) => $query->where('branch_id', $branchId))
            ->whereHas('entry', fn ($entryQuery) => $entryQuery
                ->whereDate('entry_date', '>=', $dateFrom)
                ->whereDate('entry_date', '<=', $dateTo))
            ->get()
            ->groupBy('finance_account_id')
            ->map(function (Collection $lines): array {
                $account = $lines->first()?->account;
                $debitMinor = (int) $lines->sum('debit_minor');
                $creditMinor = (int) $lines->sum('credit_minor');
                $netMinor = $account?->normal_balance === 'credit'
                    ? $creditMinor - $debitMinor
                    : $debitMinor - $creditMinor;

                return [
                    'account' => $account,
                    'debit_minor' => $debitMinor,
                    'credit_minor' => $creditMinor,
                    'net_minor' => $netMinor,
                ];
            })
            ->filter(fn (array $row): bool => $row['account'] !== null)
            ->sortBy(fn (array $row): string => $row['account']->code)
            ->values();
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
