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
use Modules\Business\Models\BusinessPaymentAccount;
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
        'sales' => 'Sales Report',
        'expense' => 'Expense report',
        'balance-sheet' => 'Balance Sheet',
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
        if ($selectedReport === 'revenue') {
            $selectedReport = 'sales';
        }

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

    public function showReport(Request $request, string $report): View|RedirectResponse
    {
        if ($report === 'revenue') {
            $report = 'sales';
        }

        if (! in_array($report, ['expense', 'profit-loss', 'sales', 'balance-sheet', 'product-profitability'], true)) {
            return redirect()->route('admin.finance.index', [
                'tenant' => $request->string('tenant')->toString(),
                'report' => $report,
                'date_from' => $request->string('date_from')->toString(),
                'date_to' => $request->string('date_to')->toString(),
                'branch_id' => $request->string('branch_id')->toString(),
            ]);
        }

        if ($report === 'sales') {
            return view('finance::admin.reports.sales', $this->salesReportData($request));
        }

        if ($report === 'profit-loss') {
            return view('finance::admin.reports.profit-loss', $this->profitLossReportData($request));
        }

        if ($report === 'balance-sheet') {
            return view('finance::admin.reports.balance-sheet', $this->balanceSheetReportData($request));
        }

        if ($report === 'product-profitability') {
            return view('finance::admin.reports.product-profitability', $this->productProfitabilityReportData($request));
        }

        return view('finance::admin.reports.expense', $this->expenseReportData($request));
    }

    public function downloadReport(Request $request, string $report): StreamedResponse|\Illuminate\Http\Response|RedirectResponse
    {
        if ($report === 'revenue') {
            $report = 'sales';
        }

        if (! in_array($report, ['expense', 'profit-loss', 'sales', 'balance-sheet', 'product-profitability'], true)) {
            return redirect()->route('admin.finance.index', [
                'tenant' => $request->string('tenant')->toString(),
                'report' => $report,
                'date_from' => $request->string('date_from')->toString(),
                'date_to' => $request->string('date_to')->toString(),
                'branch_id' => $request->string('branch_id')->toString(),
            ]);
        }

        $data = match ($report) {
            'sales' => $this->salesReportData($request),
            'profit-loss' => $this->profitLossReportData($request),
            'balance-sheet' => $this->balanceSheetReportData($request),
            'product-profitability' => $this->productProfitabilityReportData($request),
            default => $this->expenseReportData($request),
        };
        $format = $request->string('format')->toString();
        $filenameDateFrom = $data['dateFrom'] ?? $data['dateTo'];
        $filename = $report.'-report-'.$data['tenant']->slug.'-'.$filenameDateFrom->format('Ymd').'-'.$data['dateTo']->format('Ymd');

        if ($format === 'pdf') {
            $pdf = match ($report) {
                'sales' => $this->salesReportPdf($data),
                'profit-loss' => $this->profitLossReportPdf($data),
                'balance-sheet' => $this->balanceSheetReportPdf($data),
                'product-profitability' => $this->productProfitabilityReportPdf($data),
                default => $this->expenseReportPdf($data),
            };

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"',
            ]);
        }

        if ($format === 'word') {
            $html = match ($report) {
                'sales' => $this->salesReportExportHtml($data),
                'profit-loss' => $this->profitLossReportExportHtml($data),
                'balance-sheet' => $this->balanceSheetReportExportHtml($data),
                'product-profitability' => $this->productProfitabilityReportExportHtml($data),
                default => $this->expenseReportExportHtml($data),
            };

            return response($html, 200, [
                'Content-Type' => 'application/msword; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$filename.'.doc"',
            ]);
        }

        $html = match ($report) {
            'sales' => $this->salesReportExportHtml($data),
            'profit-loss' => $this->profitLossReportExportHtml($data),
            'balance-sheet' => $this->balanceSheetReportExportHtml($data),
            'product-profitability' => $this->productProfitabilityReportExportHtml($data),
            default => $this->expenseReportExportHtml($data),
        };

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.xls"',
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
        $paymentAccountFinanceIds = BusinessPaymentAccount::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->pluck('finance_account_id');
        $bankAccounts = FinanceAccount::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', 'asset')
            ->where(fn ($query) => $query->where('code', 'like', 'BANK-%')->orWhereIn('id', $paymentAccountFinanceIds))
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

    /**
     * @return array<string, mixed>
     */
    private function profitLossReportData(Request $request): array
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
        $rows = $this->profitLossAccountRows($tenant->id, $dateFrom, $dateTo, $selectedBranchId);
        $operatingRevenue = $rows->filter(fn (array $row): bool => $row['type'] === 'income' && $row['category'] === 'Operating Income')->values();
        $contraRevenue = $rows->filter(fn (array $row): bool => $row['type'] === 'income' && $row['category'] === 'Contra Income')->values();
        $otherIncome = $rows->filter(fn (array $row): bool => $row['type'] === 'income' && ! in_array($row['category'], ['Operating Income', 'Contra Income'], true))->values();
        $directCosts = $rows->filter(fn (array $row): bool => $row['type'] === 'expense' && $row['category'] === 'Direct Costs')->values();
        $operatingExpenses = $rows->filter(fn (array $row): bool => $row['type'] === 'expense' && ! in_array($row['category'], ['Direct Costs', 'Non-Operating Expenses'], true))->values();
        $nonOperatingExpenses = $rows->filter(fn (array $row): bool => $row['type'] === 'expense' && $row['category'] === 'Non-Operating Expenses')->values();

        $totalOperatingRevenueMinor = (int) $operatingRevenue->sum('amount_minor');
        $totalContraRevenueMinor = (int) $contraRevenue->sum('amount_minor');
        $netRevenueMinor = $totalOperatingRevenueMinor - $totalContraRevenueMinor;
        $totalDirectCostsMinor = (int) $directCosts->sum('amount_minor');
        $grossProfitMinor = $netRevenueMinor - $totalDirectCostsMinor;
        $totalOperatingExpensesMinor = (int) $operatingExpenses->sum('amount_minor');
        $operatingProfitMinor = $grossProfitMinor - $totalOperatingExpensesMinor;
        $totalOtherIncomeMinor = (int) $otherIncome->sum('amount_minor');
        $totalNonOperatingExpensesMinor = (int) $nonOperatingExpenses->sum('amount_minor');
        $netProfitMinor = $operatingProfitMinor + $totalOtherIncomeMinor - $totalNonOperatingExpensesMinor;
        $query = [
            'tenant' => $tenant->id,
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'branch_id' => $selectedBranchId,
        ];

        return [
            'tenant' => $tenant,
            'branches' => $branches,
            'selectedBranch' => $selectedBranch,
            'selectedBranchId' => $selectedBranchId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'sections' => [
                'operatingRevenue' => $operatingRevenue,
                'contraRevenue' => $contraRevenue,
                'directCosts' => $directCosts,
                'operatingExpenses' => $operatingExpenses,
                'otherIncome' => $otherIncome,
                'nonOperatingExpenses' => $nonOperatingExpenses,
            ],
            'inventoryNote' => $this->profitLossInventoryNote($tenant->id, $dateFrom, $dateTo, $selectedBranchId),
            'totals' => [
                'operating_revenue_minor' => $totalOperatingRevenueMinor,
                'contra_revenue_minor' => $totalContraRevenueMinor,
                'net_revenue_minor' => $netRevenueMinor,
                'direct_costs_minor' => $totalDirectCostsMinor,
                'gross_profit_minor' => $grossProfitMinor,
                'operating_expenses_minor' => $totalOperatingExpensesMinor,
                'operating_profit_minor' => $operatingProfitMinor,
                'other_income_minor' => $totalOtherIncomeMinor,
                'non_operating_expenses_minor' => $totalNonOperatingExpensesMinor,
                'net_profit_minor' => $netProfitMinor,
            ],
            'reportNumber' => 'PL-'.$dateFrom->format('Ym').'-'.$dateTo->format('d'),
            'generatedAt' => now(),
            'query' => $query,
            'money' => fn (?int $minor): string => $tenant->currency_code.' '.number_format(($minor ?? 0) / 100, 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function salesReportData(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();
        $tenants = $this->visibleTenantsFor($user);
        $tenant = $this->resolveTenant($request, $tenants);

        abort_if(! $tenant, 403);

        $dateFrom = CarbonImmutable::parse($request->string('date_from')->toString() ?: now()->startOfMonth()->toDateString())->startOfDay();
        $dateTo = CarbonImmutable::parse($request->string('date_to')->toString() ?: now()->toDateString())->endOfDay();
        $branches = Branch::query()->where('tenant_id', $tenant->id)->orderBy('name')->get();
        $selectedBranchId = $request->string('branch_id')->toString();

        if ($selectedBranchId !== '') {
            abort_unless($branches->contains('id', (int) $selectedBranchId), 403);
        }

        $selectedBranch = $selectedBranchId !== '' ? $branches->firstWhere('id', (int) $selectedBranchId) : null;
        $orders = SalesOrder::query()
            ->with(['branch', 'customer'])
            ->where('tenant_id', $tenant->id)
            ->where('order_status', '!=', SalesOrderStatus::Cancelled->value)
            ->whereBetween('order_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($selectedBranchId !== '', fn ($query) => $query->where('branch_id', $selectedBranchId))
            ->oldest('order_date')
            ->oldest('id')
            ->get();

        $paymentSummary = $orders
            ->groupBy(fn (SalesOrder $order): string => $order->payment_method ?: 'Not set')
            ->map(fn (Collection $items, string $method): array => [
                'method' => $method,
                'orders' => $items->count(),
                'total_minor' => (int) $items->sum('total_minor'),
                'paid_minor' => (int) $items->sum('paid_minor'),
                'balance_minor' => (int) $items->sum(fn (SalesOrder $order): int => $order->balance_minor),
            ])
            ->sortBy('method')
            ->values();
        $branchSummary = $orders
            ->groupBy(fn (SalesOrder $order): string => (string) ($order->branch_id ?: 'unassigned'))
            ->map(fn (Collection $items): array => [
                'branch' => $items->first()?->branch?->name ?? 'Unassigned branch',
                'orders' => $items->count(),
                'total_minor' => (int) $items->sum('total_minor'),
                'paid_minor' => (int) $items->sum('paid_minor'),
                'balance_minor' => (int) $items->sum(fn (SalesOrder $order): int => $order->balance_minor),
            ])
            ->sortBy('branch')
            ->values();
        $discountMinor = (int) $orders->sum(fn (SalesOrder $order): int => $order->coupon_discount_minor + $order->admin_discount_minor);
        $totalSalesMinor = (int) $orders->sum('total_minor');
        $refundedMinor = (int) $orders->sum('refunded_minor');
        $query = [
            'tenant' => $tenant->id,
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'branch_id' => $selectedBranchId,
        ];

        return [
            'tenant' => $tenant,
            'branches' => $branches,
            'selectedBranch' => $selectedBranch,
            'selectedBranchId' => $selectedBranchId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'orders' => $orders,
            'paymentSummary' => $paymentSummary,
            'branchSummary' => $branchSummary,
            'totals' => [
                'orders' => $orders->count(),
                'subtotal_minor' => (int) $orders->sum('subtotal_minor'),
                'discount_minor' => $discountMinor,
                'tax_minor' => (int) $orders->sum('tax_minor'),
                'shipping_minor' => (int) $orders->sum('shipping_minor'),
                'sales_minor' => $totalSalesMinor,
                'paid_minor' => (int) $orders->sum('paid_minor'),
                'balance_minor' => (int) $orders->sum(fn (SalesOrder $order): int => $order->balance_minor),
                'refunded_minor' => $refundedMinor,
                'net_sales_minor' => $totalSalesMinor - $refundedMinor,
            ],
            'reportNumber' => 'SAL-'.$dateFrom->format('Ym').'-'.str_pad((string) max(1, $orders->count()), 3, '0', STR_PAD_LEFT),
            'generatedAt' => now(),
            'query' => $query,
            'currencySymbol' => $this->currencySymbol($tenant->currency_code),
            'money' => fn (?int $minor): string => $this->currencySymbol($tenant->currency_code).number_format(($minor ?? 0) / 100, 2),
            'salesReference' => fn (SalesOrder $order): string => $this->salesReportReference($order),
            'statusLabel' => fn (?string $value): string => Str::headline((string) $value),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function balanceSheetReportData(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();
        $tenants = $this->visibleTenantsFor($user);
        $tenant = $this->resolveTenant($request, $tenants);

        abort_if(! $tenant, 403);
        app(EnsureDefaultChartOfAccountsAction::class)->execute($tenant->id);

        $dateTo = CarbonImmutable::parse($request->string('date_to')->toString() ?: now()->toDateString())->endOfDay();
        $branches = Branch::query()->where('tenant_id', $tenant->id)->orderBy('name')->get();
        $selectedBranchId = $request->string('branch_id')->toString();

        if ($selectedBranchId !== '') {
            abort_unless($branches->contains('id', (int) $selectedBranchId), 403);
        }

        $selectedBranch = $selectedBranchId !== '' ? $branches->firstWhere('id', (int) $selectedBranchId) : null;
        $rows = $this->balanceSheetAccountRows($tenant->id, $dateTo, $selectedBranchId);
        $assetRows = $rows->where('type', 'asset')->values();
        $liabilityRows = $rows->where('type', 'liability')->values();
        $equityRows = $rows->where('type', 'equity')->values();
        $currentAssets = $assetRows->filter(fn (array $row): bool => $this->isCurrentAssetCategory($row['category']))->values();
        $longTermAssets = $assetRows->reject(fn (array $row): bool => $this->isCurrentAssetCategory($row['category']))->values();
        $currentLiabilities = $liabilityRows->filter(fn (array $row): bool => str_contains(Str::lower($row['category']), 'current'))->values();
        $longTermLiabilities = $liabilityRows->reject(fn (array $row): bool => str_contains(Str::lower($row['category']), 'current'))->values();
        $currentEarningsMinor = $this->currentEarningsToDate($tenant->id, $dateTo, $selectedBranchId);

        if ($currentEarningsMinor !== 0) {
            $equityRows->push([
                'code' => '',
                'name' => $currentEarningsMinor >= 0 ? 'Current Year Earnings' : 'Current Year Loss',
                'type' => 'equity',
                'category' => 'Equity',
                'normal_balance' => 'credit',
                'amount_minor' => $currentEarningsMinor,
            ]);
        }

        $totalAssetsMinor = (int) $assetRows->sum('amount_minor');
        $totalLiabilitiesMinor = (int) $liabilityRows->sum('amount_minor');
        $totalEquityMinor = (int) $equityRows->sum('amount_minor');
        $totalLiabilitiesEquityMinor = $totalLiabilitiesMinor + $totalEquityMinor;
        $query = [
            'tenant' => $tenant->id,
            'date_from' => $request->string('date_from')->toString(),
            'date_to' => $dateTo->toDateString(),
            'branch_id' => $selectedBranchId,
        ];

        return [
            'tenant' => $tenant,
            'branches' => $branches,
            'selectedBranch' => $selectedBranch,
            'selectedBranchId' => $selectedBranchId,
            'dateTo' => $dateTo,
            'sections' => [
                'currentAssets' => $currentAssets,
                'longTermAssets' => $longTermAssets,
                'currentLiabilities' => $currentLiabilities,
                'longTermLiabilities' => $longTermLiabilities,
                'equity' => $equityRows,
            ],
            'totals' => [
                'current_assets_minor' => (int) $currentAssets->sum('amount_minor'),
                'long_term_assets_minor' => (int) $longTermAssets->sum('amount_minor'),
                'assets_minor' => $totalAssetsMinor,
                'current_liabilities_minor' => (int) $currentLiabilities->sum('amount_minor'),
                'long_term_liabilities_minor' => (int) $longTermLiabilities->sum('amount_minor'),
                'liabilities_minor' => $totalLiabilitiesMinor,
                'equity_minor' => $totalEquityMinor,
                'liabilities_equity_minor' => $totalLiabilitiesEquityMinor,
                'difference_minor' => $totalAssetsMinor - $totalLiabilitiesEquityMinor,
            ],
            'reportNumber' => 'BS-'.$dateTo->format('Ym').'-'.$dateTo->format('d'),
            'generatedAt' => now(),
            'query' => $query,
            'money' => fn (?int $minor): string => $tenant->currency_code.' '.number_format(($minor ?? 0) / 100, 2),
            'statementMoney' => fn (?int $minor): string => $this->statementMoney($tenant->currency_code, $minor ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function expenseReportData(Request $request): array
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
        $expenses = FinanceExpense::query()
            ->with(['category', 'expenseAccount', 'paymentAccount'])
            ->where('tenant_id', $tenant->id)
            ->whereBetween('expense_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($selectedBranchId !== '', fn ($query) => $query->whereIn('id', FinanceJournalEntry::query()
                ->select('source_id')
                ->where('tenant_id', $tenant->id)
                ->where('source_type', 'finance_expense')
                ->whereNotNull('source_id')
                ->whereHas('lines', fn ($lineQuery) => $lineQuery->where('branch_id', $selectedBranchId))))
            ->oldest('expense_date')
            ->oldest('id')
            ->get();

        $categorySummary = $expenses
            ->groupBy(fn (FinanceExpense $expense): string => $expense->category?->name ?? 'Uncategorized')
            ->map(fn (Collection $items, string $category): array => [
                'category' => $category,
                'amount_minor' => (int) $items->sum('amount_minor'),
                'paid_minor' => (int) $items->sum('paid_minor'),
                'payable_minor' => (int) $items->sum(fn (FinanceExpense $expense): int => max(0, $expense->amount_minor - $expense->paid_minor)),
            ])
            ->sortBy('category')
            ->values();

        $totalExpenseMinor = (int) $expenses->sum('amount_minor');
        $totalPaidMinor = (int) $expenses->sum('paid_minor');
        $totalPayableMinor = (int) $expenses->sum(fn (FinanceExpense $expense): int => max(0, $expense->amount_minor - $expense->paid_minor));
        $query = [
            'tenant' => $tenant->id,
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'branch_id' => $selectedBranchId,
        ];

        return [
            'tenant' => $tenant,
            'branches' => $branches,
            'selectedBranch' => $selectedBranch,
            'selectedBranchId' => $selectedBranchId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'expenses' => $expenses,
            'categorySummary' => $categorySummary,
            'totals' => [
                'expense_minor' => $totalExpenseMinor,
                'paid_minor' => $totalPaidMinor,
                'payable_minor' => $totalPayableMinor,
            ],
            'reportNumber' => 'EXP-'.$dateFrom->format('Ym').'-'.str_pad((string) max(1, $expenses->count()), 3, '0', STR_PAD_LEFT),
            'generatedAt' => now(),
            'query' => $query,
            'money' => fn (?int $minor): string => $tenant->currency_code.' '.number_format(($minor ?? 0) / 100, 2),
            'statusLabel' => fn (?string $value): string => Str::headline((string) $value),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productProfitabilityReportData(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();
        $tenants = $this->visibleTenantsFor($user);
        $tenant = $this->resolveTenant($request, $tenants);

        abort_if(! $tenant, 403);

        $dateFrom = CarbonImmutable::parse($request->string('date_from')->toString() ?: now()->startOfMonth()->toDateString())->startOfDay();
        $dateTo = CarbonImmutable::parse($request->string('date_to')->toString() ?: now()->toDateString())->endOfDay();
        $branches = Branch::query()->where('tenant_id', $tenant->id)->orderBy('name')->get();
        $selectedBranchId = $request->string('branch_id')->toString();

        if ($selectedBranchId !== '') {
            abort_unless($branches->contains('id', (int) $selectedBranchId), 403);
        }

        $selectedBranch = $selectedBranchId !== '' ? $branches->firstWhere('id', (int) $selectedBranchId) : null;
        $salesItems = SalesOrderItem::query()
            ->with(['order.branch', 'variant.product'])
            ->where('tenant_id', $tenant->id)
            ->whereHas('order', fn ($query) => $query
                ->when($selectedBranchId !== '', fn ($orderQuery) => $orderQuery->where('branch_id', $selectedBranchId))
                ->where('order_status', '!=', SalesOrderStatus::Cancelled->value)
                ->whereBetween('order_date', [$dateFrom->toDateString(), $dateTo->toDateString()]))
            ->get();
        $rows = $this->productProfitability($salesItems);
        $netRevenueMinor = (int) $rows->sum('revenue_minor');
        $cogsMinor = (int) $rows->sum('cogs_minor');
        $profitMinor = (int) $rows->sum('profit_minor');
        $query = [
            'tenant' => $tenant->id,
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'branch_id' => $selectedBranchId,
        ];

        return [
            'tenant' => $tenant,
            'branches' => $branches,
            'selectedBranch' => $selectedBranch,
            'selectedBranchId' => $selectedBranchId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'rows' => $rows,
            'totals' => [
                'products' => $rows->count(),
                'quantity_sold' => (int) $rows->sum('quantity_sold'),
                'quantity_returned' => (int) $rows->sum('quantity_returned'),
                'net_quantity' => (int) $rows->sum('net_quantity'),
                'gross_revenue_minor' => (int) $rows->sum('gross_revenue_minor'),
                'returned_revenue_minor' => (int) $rows->sum('returned_revenue_minor'),
                'net_revenue_minor' => $netRevenueMinor,
                'cogs_minor' => $cogsMinor,
                'profit_minor' => $profitMinor,
                'margin_percent' => $netRevenueMinor > 0 ? ($profitMinor / $netRevenueMinor) * 100 : 0.0,
            ],
            'reportNumber' => 'PP-'.$dateFrom->format('Ym').'-'.str_pad((string) max(1, $rows->count()), 3, '0', STR_PAD_LEFT),
            'generatedAt' => now(),
            'query' => $query,
            'currencySymbol' => $this->currencySymbol($tenant->currency_code),
            'money' => fn (?int $minor): string => $this->currencySymbol($tenant->currency_code).number_format(($minor ?? 0) / 100, 2),
            'percent' => fn (float|int $value): string => number_format((float) $value, 2).'%',
        ];
    }

    private function profitLossAccountRows(string $tenantId, CarbonImmutable $dateFrom, CarbonImmutable $dateTo, string $branchId): Collection
    {
        $accounts = FinanceAccount::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('type', ['income', 'expense'])
            ->orderBy('code')
            ->get();

        $activity = FinanceJournalLine::query()
            ->with('account')
            ->where('tenant_id', $tenantId)
            ->when($branchId !== '', fn ($query) => $query->where('branch_id', $branchId))
            ->whereHas('entry', fn ($entryQuery) => $entryQuery
                ->whereDate('entry_date', '>=', $dateFrom->toDateString())
                ->whereDate('entry_date', '<=', $dateTo->toDateString()))
            ->whereHas('account', fn ($accountQuery) => $accountQuery->whereIn('type', ['income', 'expense']))
            ->get()
            ->groupBy('finance_account_id');

        return $accounts
            ->map(function (FinanceAccount $account) use ($activity): array {
                $lines = $activity->get($account->id, collect());
                $debitMinor = (int) $lines->sum('debit_minor');
                $creditMinor = (int) $lines->sum('credit_minor');
                $amountMinor = $account->type === 'income'
                    ? $creditMinor - $debitMinor
                    : $debitMinor - $creditMinor;

                if ($account->category === 'Contra Income') {
                    $amountMinor = $debitMinor - $creditMinor;
                }

                return [
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                    'category' => $account->category ?: 'Uncategorized',
                    'debit_minor' => $debitMinor,
                    'credit_minor' => $creditMinor,
                    'amount_minor' => $amountMinor,
                ];
            })
            ->values();
    }

    private function balanceSheetAccountRows(string $tenantId, CarbonImmutable $dateTo, string $branchId): Collection
    {
        $accounts = FinanceAccount::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('type', ['asset', 'liability', 'equity'])
            ->orderBy('code')
            ->get();

        $activity = FinanceJournalLine::query()
            ->where('tenant_id', $tenantId)
            ->when($branchId !== '', fn ($query) => $query->where('branch_id', $branchId))
            ->whereHas('entry', fn ($entryQuery) => $entryQuery->whereDate('entry_date', '<=', $dateTo->toDateString()))
            ->whereHas('account', fn ($accountQuery) => $accountQuery->whereIn('type', ['asset', 'liability', 'equity']))
            ->get()
            ->groupBy('finance_account_id');

        return $accounts
            ->map(function (FinanceAccount $account) use ($activity): array {
                $lines = $activity->get($account->id, collect());
                $debitMinor = (int) $lines->sum('debit_minor');
                $creditMinor = (int) $lines->sum('credit_minor');
                $balanceMinor = $account->normal_balance === 'credit'
                    ? $creditMinor - $debitMinor
                    : $debitMinor - $creditMinor;
                $amountMinor = $account->type === 'equity' && $account->normal_balance === 'debit'
                    ? -$balanceMinor
                    : $balanceMinor;

                return [
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                    'category' => $account->category ?: 'Uncategorized',
                    'normal_balance' => $account->normal_balance,
                    'amount_minor' => $amountMinor,
                ];
            })
            ->values();
    }

    private function currentEarningsToDate(string $tenantId, CarbonImmutable $dateTo, string $branchId): int
    {
        $rows = $this->profitLossAccountRows(
            $tenantId,
            CarbonImmutable::create(1900, 1, 1)->startOfDay(),
            $dateTo,
            $branchId,
        );

        return (int) $rows->sum(function (array $row): int {
            if ($row['type'] === 'income') {
                return $row['category'] === 'Contra Income' ? -$row['amount_minor'] : $row['amount_minor'];
            }

            return -$row['amount_minor'];
        });
    }

    private function isCurrentAssetCategory(string $category): bool
    {
        $category = Str::lower($category);

        return str_contains($category, 'current') || str_contains($category, 'receivable');
    }

    private function profitLossInventoryNote(string $tenantId, CarbonImmutable $dateFrom, CarbonImmutable $dateTo, string $branchId): array
    {
        $openingInventoryMinor = $this->accountBalanceOnDate($tenantId, '1200', $dateFrom->subDay()->toDateString(), $branchId);
        $closingInventoryMinor = $this->accountBalanceOnDate($tenantId, '1200', $dateTo->toDateString(), $branchId);
        $periodLines = $this->accountLinesForPeriod($tenantId, '1200', $dateFrom, $dateTo, $branchId);
        $inventoryAdditionsMinor = (int) $periodLines->sum('debit_minor');
        $inventoryReductionsMinor = (int) $periodLines->sum('credit_minor');

        return [
            'opening_inventory_minor' => $openingInventoryMinor,
            'inventory_additions_minor' => $inventoryAdditionsMinor,
            'inventory_reductions_minor' => $inventoryReductionsMinor,
            'closing_inventory_minor' => $closingInventoryMinor,
        ];
    }

    private function accountLinesForPeriod(string $tenantId, string $accountCode, CarbonImmutable $dateFrom, CarbonImmutable $dateTo, string $branchId): Collection
    {
        $account = FinanceAccount::query()->where('tenant_id', $tenantId)->where('code', $accountCode)->first();

        if (! $account) {
            return collect();
        }

        return FinanceJournalLine::query()
            ->where('tenant_id', $tenantId)
            ->where('finance_account_id', $account->id)
            ->when($branchId !== '', fn ($query) => $query->where('branch_id', $branchId))
            ->whereHas('entry', fn ($entryQuery) => $entryQuery
                ->whereDate('entry_date', '>=', $dateFrom->toDateString())
                ->whereDate('entry_date', '<=', $dateTo->toDateString()))
            ->get();
    }

    private function accountBalanceOnDate(string $tenantId, string $accountCode, string $dateTo, string $branchId): int
    {
        $account = FinanceAccount::query()->where('tenant_id', $tenantId)->where('code', $accountCode)->first();

        if (! $account) {
            return 0;
        }

        $query = FinanceJournalLine::query()
            ->where('tenant_id', $tenantId)
            ->where('finance_account_id', $account->id)
            ->when($branchId !== '', fn ($lineQuery) => $lineQuery->where('branch_id', $branchId))
            ->whereHas('entry', fn ($entryQuery) => $entryQuery->whereDate('entry_date', '<=', $dateTo));
        $debitMinor = (int) (clone $query)->sum('debit_minor');
        $creditMinor = (int) $query->sum('credit_minor');

        return $account->normal_balance === 'credit' ? $creditMinor - $debitMinor : $debitMinor - $creditMinor;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function balanceSheetReportExportHtml(array $data): string
    {
        $money = $data['statementMoney'];
        $sectionRows = function (Collection $rows) use ($money): string {
            if ($rows->isEmpty()) {
                return '<tr><td colspan="3">No accounts in this section.</td></tr>';
            }

            return $rows->map(fn (array $row): string => '<tr>'
                .'<td>'.e($row['code']).'</td>'
                .'<td>'.e($row['name']).'</td>'
                .'<td>'.e($money($row['amount_minor'])).'</td>'
                .'</tr>')->implode('');
        };

        return '<!doctype html><html><head><meta charset="utf-8"><title>Balance Sheet</title></head><body>'
            .'<h1>Balance Sheet</h1>'
            .'<p><strong>Company:</strong> '.e($data['tenant']->name).'</p>'
            .'<p><strong>Branch:</strong> '.e($data['selectedBranch']?->name ?? 'All branches').'</p>'
            .'<p><strong>As at:</strong> '.e($data['dateTo']->format('F j, Y')).'</p>'
            .'<table border="1" cellspacing="0" cellpadding="6"><thead><tr><th>GL Code</th><th>Account</th><th>Amount</th></tr></thead><tbody>'
            .'<tr><th colspan="3">Assets</th></tr>'
            .'<tr><th colspan="3">Current Assets</th></tr>'.$sectionRows($data['sections']['currentAssets'])
            .'<tr><th colspan="2">Total Current Assets</th><th>'.e($money($data['totals']['current_assets_minor'])).'</th></tr>'
            .'<tr><th colspan="3">Long-term Assets</th></tr>'.$sectionRows($data['sections']['longTermAssets'])
            .'<tr><th colspan="2">Total Assets</th><th>'.e($money($data['totals']['assets_minor'])).'</th></tr>'
            .'<tr><th colspan="3">Liabilities</th></tr>'
            .'<tr><th colspan="3">Current Liabilities</th></tr>'.$sectionRows($data['sections']['currentLiabilities'])
            .'<tr><th colspan="2">Total Current Liabilities</th><th>'.e($money($data['totals']['current_liabilities_minor'])).'</th></tr>'
            .'<tr><th colspan="3">Long-term Liabilities</th></tr>'.$sectionRows($data['sections']['longTermLiabilities'])
            .'<tr><th colspan="2">Total Liabilities</th><th>'.e($money($data['totals']['liabilities_minor'])).'</th></tr>'
            .'<tr><th colspan="3">Equity</th></tr>'.$sectionRows($data['sections']['equity'])
            .'<tr><th colspan="2">Total Equity</th><th>'.e($money($data['totals']['equity_minor'])).'</th></tr>'
            .'<tr><th colspan="2">Total Liabilities + Equity</th><th>'.e($money($data['totals']['liabilities_equity_minor'])).'</th></tr>'
            .'</tbody></table>'
            .'</body></html>';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function profitLossReportExportHtml(array $data): string
    {
        $money = $data['money'];
        $sectionRows = function (Collection $rows) use ($money): string {
            if ($rows->isEmpty()) {
                return '<tr><td colspan="4">No accounts in this section.</td></tr>';
            }

            return $rows->map(fn (array $row): string => '<tr>'
                .'<td>'.e($row['code']).'</td>'
                .'<td>'.e($row['name']).'</td>'
                .'<td>'.e($row['category']).'</td>'
                .'<td>'.e($money($row['amount_minor'])).'</td>'
                .'</tr>')->implode('');
        };

        return '<!doctype html><html><head><meta charset="utf-8"><title>Profit and Loss Statement</title></head><body>'
            .'<h1>Profit and Loss Statement</h1>'
            .'<p><strong>Company:</strong> '.e($data['tenant']->name).'</p>'
            .'<p><strong>Branch:</strong> '.e($data['selectedBranch']?->name ?? 'All branches').'</p>'
            .'<p><strong>Period:</strong> '.e($data['dateFrom']->format('M j, Y')).' to '.e($data['dateTo']->format('M j, Y')).'</p>'
            .'<table border="1" cellspacing="0" cellpadding="6"><thead><tr><th>GL Code</th><th>Account</th><th>Category</th><th>Amount</th></tr></thead>'
            .'<tbody><tr><th colspan="4">Revenue</th></tr>'.$sectionRows($data['sections']['operatingRevenue'])
            .'<tr><th colspan="4">Less Contra Revenue</th></tr>'.$sectionRows($data['sections']['contraRevenue'])
            .'<tr><th colspan="3">Net Revenue</th><th>'.e($money($data['totals']['net_revenue_minor'])).'</th></tr>'
            .'<tr><th colspan="4">Cost of Goods Sold / Direct Costs</th></tr>'.$sectionRows($data['sections']['directCosts'])
            .'<tr><th colspan="3">Gross Profit</th><th>'.e($money($data['totals']['gross_profit_minor'])).'</th></tr>'
            .'<tr><th colspan="4">Operating Expenses</th></tr>'.$sectionRows($data['sections']['operatingExpenses'])
            .'<tr><th colspan="3">Operating Profit</th><th>'.e($money($data['totals']['operating_profit_minor'])).'</th></tr>'
            .'<tr><th colspan="4">Other Income</th></tr>'.$sectionRows($data['sections']['otherIncome'])
            .'<tr><th colspan="4">Non-Operating Expenses</th></tr>'.$sectionRows($data['sections']['nonOperatingExpenses'])
            .'<tr><th colspan="3">'.($data['totals']['net_profit_minor'] >= 0 ? 'Net Profit' : 'Net Loss').'</th><th>'.e($money($data['totals']['net_profit_minor'])).'</th></tr>'
            .'</tbody></table>'
            .'<h2>Inventory Movement Note</h2>'
            .'<p>Opening Inventory: '.e($money($data['inventoryNote']['opening_inventory_minor']))
            .' | Inventory Purchases / Additions: '.e($money($data['inventoryNote']['inventory_additions_minor']))
            .' | Inventory Reductions: '.e($money($data['inventoryNote']['inventory_reductions_minor']))
            .' | Closing Inventory: '.e($money($data['inventoryNote']['closing_inventory_minor'])).'</p>'
            .'</body></html>';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function salesReportExportHtml(array $data): string
    {
        $money = $data['money'];
        $statusLabel = $data['statusLabel'];
        $rows = '';

        foreach ($data['orders'] as $order) {
            $discountMinor = (int) $order->coupon_discount_minor + (int) $order->admin_discount_minor;
            $rows .= '<tr>'
                .'<td>'.e($order->order_date->format('M j, Y')).'</td>'
                .'<td>'.e($data['salesReference']($order)).'</td>'
                .'<td>'.e($order->customer?->name ?? 'Walk-In').'</td>'
                .'<td>'.e($order->branch?->name ?? 'Unassigned').'</td>'
                .'<td>'.e($order->payment_method ?: 'Not set').'</td>'
                .'<td>'.e($statusLabel($order->payment_status->value ?? (string) $order->payment_status)).'</td>'
                .'<td>'.e($money($order->subtotal_minor)).'</td>'
                .'<td>'.e($money($discountMinor)).'</td>'
                .'<td>'.e($money($order->tax_minor)).'</td>'
                .'<td>'.e($money($order->total_minor)).'</td>'
                .'<td>'.e($money($order->paid_minor)).'</td>'
                .'<td>'.e($money($order->balance_minor)).'</td>'
                .'</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="12">No sales records for this period.</td></tr>';
        }

        return '<!doctype html><html><head><meta charset="utf-8"><title>Sales Report</title></head><body>'
            .'<h1>Sales Report</h1>'
            .'<p><strong>Company:</strong> '.e($data['tenant']->name).'</p>'
            .'<p><strong>Branch:</strong> '.e($data['selectedBranch']?->name ?? 'All branches').'</p>'
            .'<p><strong>Period:</strong> '.e($data['dateFrom']->format('M j, Y')).' to '.e($data['dateTo']->format('M j, Y')).'</p>'
            .'<table border="1" cellspacing="0" cellpadding="6"><thead><tr><th>Date</th><th>Reference</th><th>Customer</th><th>Branch</th><th>Payment Method</th><th>Payment Status</th><th>Subtotal</th><th>Discount</th><th>Tax</th><th>Total</th><th>Paid</th><th>Balance</th></tr></thead><tbody>'.$rows.'</tbody></table>'
            .'<p><strong>Total Sales:</strong> '.e($money($data['totals']['sales_minor'])).' <strong>Total Paid:</strong> '.e($money($data['totals']['paid_minor'])).' <strong>Total Balance:</strong> '.e($money($data['totals']['balance_minor'])).'</p>'
            .'</body></html>';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function expenseReportExportHtml(array $data): string
    {
        $money = $data['money'];
        $statusLabel = $data['statusLabel'];
        $rows = '';

        foreach ($data['expenses'] as $expense) {
            $rows .= '<tr>'
                .'<td>'.e($expense->expense_date->format('M j, Y')).'</td>'
                .'<td>'.e($expense->category?->name ?? 'Uncategorized').'</td>'
                .'<td>'.e($expense->description ?: $expense->expense_number).'</td>'
                .'<td>'.e($expense->payee_name ?: 'Not set').'</td>'
                .'<td>'.e($money($expense->amount_minor)).'</td>'
                .'<td>'.e($money($expense->paid_minor)).'</td>'
                .'<td>'.e($money(max(0, $expense->amount_minor - $expense->paid_minor))).'</td>'
                .'<td>'.e($statusLabel($expense->payment_status)).'</td>'
                .'</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="8">No expense records for this period.</td></tr>';
        }

        return '<!doctype html><html><head><meta charset="utf-8"><title>Expense Report</title></head><body>'
            .'<h1>Expense Report</h1>'
            .'<p><strong>Company:</strong> '.e($data['tenant']->name).'</p>'
            .'<p><strong>Branch:</strong> '.e($data['selectedBranch']?->name ?? 'All branches').'</p>'
            .'<p><strong>Period:</strong> '.e($data['dateFrom']->format('M j, Y')).' to '.e($data['dateTo']->format('M j, Y')).'</p>'
            .'<table border="1" cellspacing="0" cellpadding="6"><thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Payee</th><th>Amount</th><th>Paid</th><th>Payable</th><th>Status</th></tr></thead><tbody>'.$rows.'</tbody></table>'
            .'<p><strong>Total Expense:</strong> '.e($money($data['totals']['expense_minor'])).' <strong>Total Paid:</strong> '.e($money($data['totals']['paid_minor'])).' <strong>Total Payable:</strong> '.e($money($data['totals']['payable_minor'])).'</p>'
            .'</body></html>';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function productProfitabilityReportExportHtml(array $data): string
    {
        $money = $data['money'];
        $percent = $data['percent'];
        $rows = '';

        foreach ($data['rows'] as $row) {
            $rows .= '<tr>'
                .'<td>'.e($row['name']).'</td>'
                .'<td>'.e($row['sku'] ?: 'Not set').'</td>'
                .'<td>'.e((string) $row['quantity_sold']).'</td>'
                .'<td>'.e((string) $row['quantity_returned']).'</td>'
                .'<td>'.e((string) $row['net_quantity']).'</td>'
                .'<td>'.e($money($row['revenue_minor'])).'</td>'
                .'<td>'.e($money($row['cogs_minor'])).'</td>'
                .'<td>'.e($money($row['profit_minor'])).'</td>'
                .'<td>'.e($percent($row['margin_percent'])).'</td>'
                .'</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="9">No product sales for this period.</td></tr>';
        }

        return '<!doctype html><html><head><meta charset="utf-8"><title>Product Profitability Report</title></head><body>'
            .'<h1>Product Profitability Report</h1>'
            .'<p><strong>Company:</strong> '.e($data['tenant']->name).'</p>'
            .'<p><strong>Branch:</strong> '.e($data['selectedBranch']?->name ?? 'All branches').'</p>'
            .'<p><strong>Period:</strong> '.e($data['dateFrom']->format('M j, Y')).' to '.e($data['dateTo']->format('M j, Y')).'</p>'
            .'<table border="1" cellspacing="0" cellpadding="6"><thead><tr><th>Product</th><th>SKU</th><th>Qty Sold</th><th>Qty Returned</th><th>Net Qty</th><th>Sales Revenue</th><th>COGS</th><th>Gross Profit</th><th>Gross Margin</th></tr></thead><tbody>'.$rows.'</tbody></table>'
            .'<p><strong>Net Revenue:</strong> '.e($money($data['totals']['net_revenue_minor'])).' <strong>COGS:</strong> '.e($money($data['totals']['cogs_minor'])).' <strong>Gross Profit:</strong> '.e($money($data['totals']['profit_minor'])).' <strong>Gross Margin:</strong> '.e($percent($data['totals']['margin_percent'])).'</p>'
            .'</body></html>';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function balanceSheetReportPdf(array $data): string
    {
        $money = $data['statementMoney'];
        $lines = [
            'Balance Sheet',
            'Company: '.$data['tenant']->name,
            'Branch: '.($data['selectedBranch']?->name ?? 'All branches'),
            'As at: '.$data['dateTo']->format('F j, Y'),
            'Total Assets: '.$money($data['totals']['assets_minor']),
            'Total Liabilities: '.$money($data['totals']['liabilities_minor']),
            'Total Equity: '.$money($data['totals']['equity_minor']),
            'Total Liabilities + Equity: '.$money($data['totals']['liabilities_equity_minor']),
            'Difference: '.$money($data['totals']['difference_minor']),
            '',
        ];

        foreach (['currentAssets', 'longTermAssets', 'currentLiabilities', 'longTermLiabilities', 'equity'] as $section) {
            $lines[] = Str::headline($section);
            foreach ($data['sections'][$section]->take(10) as $row) {
                $lines[] = trim($row['code'].' '.$row['name']).' | '.$money($row['amount_minor']);
            }
        }

        $content = "BT\n/F1 18 Tf\n50 780 Td\n(Balance Sheet) Tj\n/F1 10 Tf\n0 -28 Td\n";
        foreach (array_slice($lines, 1) as $line) {
            $content .= '('.$this->pdfText($line).") Tj\n0 -16 Td\n";
        }
        $content .= "ET\n";

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            "5 0 obj\n<< /Length ".strlen($content)." >>\nstream\n".$content."endstream\nendobj\n",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";

        return $pdf;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function profitLossReportPdf(array $data): string
    {
        $money = $data['money'];
        $lines = [
            'Profit and Loss Statement',
            'Company: '.$data['tenant']->name,
            'Branch: '.($data['selectedBranch']?->name ?? 'All branches'),
            'Period: '.$data['dateFrom']->format('M j, Y').' to '.$data['dateTo']->format('M j, Y'),
            'Net Revenue: '.$money($data['totals']['net_revenue_minor']),
            'Cost of Goods Sold / Direct Costs: '.$money($data['totals']['direct_costs_minor']),
            'Gross Profit: '.$money($data['totals']['gross_profit_minor']),
            'Operating Expenses: '.$money($data['totals']['operating_expenses_minor']),
            'Operating Profit: '.$money($data['totals']['operating_profit_minor']),
            'Other Income: '.$money($data['totals']['other_income_minor']),
            'Non-Operating Expenses: '.$money($data['totals']['non_operating_expenses_minor']),
            ($data['totals']['net_profit_minor'] >= 0 ? 'Net Profit: ' : 'Net Loss: ').$money($data['totals']['net_profit_minor']),
            '',
            'Inventory Note',
            'Opening Inventory: '.$money($data['inventoryNote']['opening_inventory_minor']),
            'Inventory Purchases / Additions: '.$money($data['inventoryNote']['inventory_additions_minor']),
            'Inventory Reductions: '.$money($data['inventoryNote']['inventory_reductions_minor']),
            'Closing Inventory: '.$money($data['inventoryNote']['closing_inventory_minor']),
        ];

        foreach (['operatingRevenue', 'contraRevenue', 'directCosts', 'operatingExpenses', 'otherIncome', 'nonOperatingExpenses'] as $section) {
            foreach ($data['sections'][$section]->take(10) as $row) {
                $lines[] = $row['code'].' '.$row['name'].' | '.$money($row['amount_minor']);
            }
        }

        $content = "BT\n/F1 18 Tf\n50 780 Td\n(Profit and Loss Statement) Tj\n/F1 10 Tf\n0 -28 Td\n";
        foreach (array_slice($lines, 1) as $line) {
            $content .= '('.$this->pdfText($line).") Tj\n0 -16 Td\n";
        }
        $content .= "ET\n";

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            "5 0 obj\n<< /Length ".strlen($content)." >>\nstream\n".$content."endstream\nendobj\n",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";

        return $pdf;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function salesReportPdf(array $data): string
    {
        $money = $data['money'];
        $lines = [
            'Sales Report',
            'Company: '.$data['tenant']->name,
            'Branch: '.($data['selectedBranch']?->name ?? 'All branches'),
            'Period: '.$data['dateFrom']->format('M j, Y').' to '.$data['dateTo']->format('M j, Y'),
            'Orders: '.$data['totals']['orders'],
            'Total Sales: '.$money($data['totals']['sales_minor']),
            'Total Paid: '.$money($data['totals']['paid_minor']),
            'Total Balance: '.$money($data['totals']['balance_minor']),
            '',
        ];

        foreach ($data['orders']->take(32) as $order) {
            $lines[] = $order->order_date->format('M j, Y').' | '.$data['salesReference']($order).' | '.($order->customer?->name ?? 'Walk-In').' | '.($order->branch?->name ?? 'Unassigned').' | '.$money($order->total_minor).' | '.$money($order->paid_minor).' | '.$money($order->balance_minor);
        }

        if ($data['orders']->isEmpty()) {
            $lines[] = 'No sales records for this period.';
        }

        $content = "BT\n/F1 18 Tf\n50 780 Td\n(Sales Report) Tj\n/F1 10 Tf\n0 -28 Td\n";
        foreach (array_slice($lines, 1) as $line) {
            $content .= '('.$this->pdfText($line).") Tj\n0 -16 Td\n";
        }
        $content .= "ET\n";

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            "5 0 obj\n<< /Length ".strlen($content)." >>\nstream\n".$content."endstream\nendobj\n",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";

        return $pdf;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function expenseReportPdf(array $data): string
    {
        $money = $data['money'];
        $lines = [
            'Expense Report',
            'Company: '.$data['tenant']->name,
            'Branch: '.($data['selectedBranch']?->name ?? 'All branches'),
            'Period: '.$data['dateFrom']->format('M j, Y').' to '.$data['dateTo']->format('M j, Y'),
            'Total Expense: '.$money($data['totals']['expense_minor']),
            'Total Paid: '.$money($data['totals']['paid_minor']),
            'Total Payable: '.$money($data['totals']['payable_minor']),
            '',
        ];

        foreach ($data['expenses']->take(34) as $expense) {
            $lines[] = $expense->expense_date->format('M j, Y').' | '.($expense->category?->name ?? 'Uncategorized').' | '.($expense->payee_name ?: 'Not set').' | '.$money($expense->amount_minor).' | '.$money($expense->paid_minor).' | '.$money(max(0, $expense->amount_minor - $expense->paid_minor));
        }

        if ($data['expenses']->isEmpty()) {
            $lines[] = 'No expense records for this period.';
        }

        $content = "BT\n/F1 18 Tf\n50 780 Td\n(Expense Report) Tj\n/F1 10 Tf\n0 -28 Td\n";
        foreach (array_slice($lines, 1) as $line) {
            $content .= '('.$this->pdfText($line).") Tj\n0 -16 Td\n";
        }
        $content .= "ET\n";

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            "5 0 obj\n<< /Length ".strlen($content)." >>\nstream\n".$content."endstream\nendobj\n",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";

        return $pdf;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function productProfitabilityReportPdf(array $data): string
    {
        $money = $data['money'];
        $percent = $data['percent'];
        $lines = [
            'Product Profitability Report',
            'Company: '.$data['tenant']->name,
            'Branch: '.($data['selectedBranch']?->name ?? 'All branches'),
            'Period: '.$data['dateFrom']->format('M j, Y').' to '.$data['dateTo']->format('M j, Y'),
            'Products: '.$data['totals']['products'],
            'Net Quantity: '.$data['totals']['net_quantity'],
            'Net Revenue: '.$money($data['totals']['net_revenue_minor']),
            'COGS: '.$money($data['totals']['cogs_minor']),
            'Gross Profit: '.$money($data['totals']['profit_minor']),
            'Gross Margin: '.$percent($data['totals']['margin_percent']),
            '',
        ];

        foreach ($data['rows']->take(32) as $row) {
            $lines[] = $row['name'].' | '.($row['sku'] ?: 'Not set').' | Qty '.$row['net_quantity'].' | Revenue '.$money($row['revenue_minor']).' | Profit '.$money($row['profit_minor']).' | '.$percent($row['margin_percent']);
        }

        if ($data['rows']->isEmpty()) {
            $lines[] = 'No product sales for this period.';
        }

        $content = "BT\n/F1 18 Tf\n50 780 Td\n(Product Profitability Report) Tj\n/F1 10 Tf\n0 -28 Td\n";
        foreach (array_slice($lines, 1) as $line) {
            $content .= '('.$this->pdfText($line).") Tj\n0 -16 Td\n";
        }
        $content .= "ET\n";

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            "5 0 obj\n<< /Length ".strlen($content)." >>\nstream\n".$content."endstream\nendobj\n",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";

        return $pdf;
    }

    private function pdfText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], Str::limit($text, 100, ''));
    }

    private function currencySymbol(string $currencyCode): string
    {
        return match (strtoupper($currencyCode)) {
            'NGN' => '₦',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'GHS' => '₵',
            'KES' => 'KSh ',
            'ZAR' => 'R',
            default => strtoupper($currencyCode).' ',
        };
    }

    private function statementMoney(string $currencyCode, int $minor): string
    {
        $formatted = $currencyCode.' '.number_format(abs($minor) / 100, 2);

        return $minor < 0 ? '('.$formatted.')' : $formatted;
    }

    private function salesReportReference(SalesOrder $order): string
    {
        $orderReference = trim((string) $order->order_number);

        if ($orderReference !== '') {
            return $orderReference;
        }

        $invoiceReference = trim((string) $order->invoice_number);

        return $invoiceReference !== '' ? $invoiceReference : 'Not set';
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
            ->groupBy(fn (SalesOrderItem $item): string => (string) ($item->product_variant_id ?: ($item->sku ?: $item->item_name)))
            ->map(function (Collection $items): array {
                /** @var SalesOrderItem $first */
                $first = $items->first();
                $quantitySold = (int) $items->sum('quantity');
                $quantityReturned = (int) $items->sum('quantity_returned');
                $netQuantity = (int) $items->sum(fn (SalesOrderItem $item): int => max(0, $item->quantity - $item->quantity_returned));
                $grossRevenueMinor = (int) $items->sum('line_total_minor');
                $returnedRevenueMinor = (int) $items->sum(function (SalesOrderItem $item): int {
                    $quantity = max(1, (int) $item->quantity);

                    return (int) round(((int) $item->line_total_minor / $quantity) * (int) $item->quantity_returned);
                });
                $revenueMinor = max(0, $grossRevenueMinor - $returnedRevenueMinor);
                $cogsMinor = $this->costOfGoodsSold($items);
                $profitMinor = $revenueMinor - $cogsMinor;

                return [
                    'name' => trim(($first->variant?->product?->name ?? $first->item_name).' / '.($first->variant?->variant_name ?? $first->sku ?? 'Default')),
                    'sku' => $first->sku ?? $first->variant?->sku,
                    'quantity_sold' => $quantitySold,
                    'quantity_returned' => $quantityReturned,
                    'net_quantity' => $netQuantity,
                    'quantity' => $netQuantity,
                    'gross_revenue_minor' => $grossRevenueMinor,
                    'returned_revenue_minor' => $returnedRevenueMinor,
                    'revenue_minor' => $revenueMinor,
                    'cogs_minor' => $cogsMinor,
                    'profit_minor' => $profitMinor,
                    'margin_percent' => $revenueMinor > 0 ? ($profitMinor / $revenueMinor) * 100 : 0.0,
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
