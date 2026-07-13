<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Business\Models\Branch;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceBankMovement;
use Modules\Finance\Models\FinanceExpense;
use Modules\Finance\Models\FinanceExpenseCategory;
use Modules\Finance\Models\FinanceJournalEntry;
use Modules\Finance\Models\FinanceJournalLine;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderItem;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

class FinanceReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_generate_financial_reports(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Finance Shop',
            'slug' => 'finance-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);

        $user = User::factory()->create([
            'is_platform_admin' => true,
        ]);
        $this->actingAs($user)
            ->get(route('admin.finance.index', [
                'tenant' => $tenant->id,
                'report' => 'balance-sheet',
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-08',
            ]))
            ->assertOk()
            ->assertSee('Report')
            ->assertSee('Financial Reports')
            ->assertSee('Balance Sheet')
            ->assertSee('Sales Report')
            ->assertDontSee('Revenue report')
            ->assertDontSee('Cash Flow statement')
            ->assertDontSee('Branch profitability report')
            ->assertSee('target="_blank"', false)
            ->assertSee('Reports open in a new tab with export and print actions.');

        $branch = Branch::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'status' => 'active',
            'is_primary' => true,
        ]);
        $orderWithReferences = SalesOrder::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'order_number' => 'SO-SALES-001',
            'invoice_number' => 'INV-SALES-001',
            'receipt_number' => 'RCT-SALES-001',
            'order_status' => 'completed',
            'payment_status' => 'paid',
            'order_date' => '2026-06-02',
            'subtotal_minor' => 100000,
            'tax_minor' => 5000,
            'shipping_minor' => 0,
            'coupon_discount_minor' => 10000,
            'admin_discount_minor' => 0,
            'total_minor' => 95000,
            'paid_minor' => 95000,
            'refunded_minor' => 0,
            'payment_method' => 'POS',
        ]);
        $invoiceOnlyOrder = SalesOrder::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'order_number' => '',
            'invoice_number' => 'INV-ONLY-002',
            'receipt_number' => 'RCT-SALES-002',
            'order_status' => 'completed',
            'payment_status' => 'partially_paid',
            'order_date' => '2026-06-03',
            'subtotal_minor' => 20000,
            'tax_minor' => 0,
            'shipping_minor' => 0,
            'coupon_discount_minor' => 0,
            'admin_discount_minor' => 0,
            'total_minor' => 20000,
            'paid_minor' => 5000,
            'refunded_minor' => 0,
            'payment_method' => 'Cash',
        ]);
        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Premium Rice 10kg',
            'slug' => 'premium-rice-10kg',
            'base_price_minor' => 10000,
            'base_cost_price_minor' => 6000,
            'has_variants' => true,
        ]);
        $variant = ProductVariant::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'variant_name' => 'Default',
            'sku' => 'RICE-10KG',
            'selling_price_minor' => 10000,
            'cost_price_minor' => 6000,
        ]);
        SalesOrderItem::query()->create([
            'tenant_id' => $tenant->id,
            'sales_order_id' => $orderWithReferences->id,
            'product_variant_id' => $variant->id,
            'item_name' => 'Premium Rice 10kg',
            'sku' => 'RICE-10KG',
            'quantity' => 5,
            'quantity_returned' => 1,
            'unit_price_minor' => 10000,
            'unit_cost_minor' => 6000,
            'tax_minor' => 0,
            'line_total_minor' => 50000,
        ]);
        SalesOrderItem::query()->create([
            'tenant_id' => $tenant->id,
            'sales_order_id' => $invoiceOnlyOrder->id,
            'product_variant_id' => $variant->id,
            'item_name' => 'Premium Rice 10kg',
            'sku' => 'RICE-10KG',
            'quantity' => 2,
            'quantity_returned' => 0,
            'unit_price_minor' => 10000,
            'unit_cost_minor' => 6000,
            'tax_minor' => 0,
            'line_total_minor' => 20000,
        ]);
        $accounts = FinanceAccount::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('code', ['1000', '1100', '1200', '4000', 'EXP-5000', 'EXP-6130'])
            ->get()
            ->keyBy('code');
        $journal = FinanceJournalEntry::query()->create([
            'tenant_id' => $tenant->id,
            'entry_number' => 'JE-PL-001',
            'entry_date' => '2026-06-02',
            'source_type' => 'manual_journal',
            'source_event' => 'posted',
            'memo' => 'Profit and loss test activity',
        ]);
        foreach ([
            ['account' => '1100', 'debit_minor' => 100000, 'credit_minor' => 0],
            ['account' => '4000', 'debit_minor' => 0, 'credit_minor' => 100000],
            ['account' => 'EXP-5000', 'debit_minor' => 40000, 'credit_minor' => 0],
            ['account' => '1200', 'debit_minor' => 0, 'credit_minor' => 40000],
            ['account' => 'EXP-6130', 'debit_minor' => 10000, 'credit_minor' => 0],
            ['account' => '1000', 'debit_minor' => 0, 'credit_minor' => 10000],
        ] as $line) {
            FinanceJournalLine::query()->create([
                'tenant_id' => $tenant->id,
                'finance_journal_entry_id' => $journal->id,
                'finance_account_id' => $accounts[$line['account']]->id,
                'branch_id' => $branch->id,
                'debit_minor' => $line['debit_minor'],
                'credit_minor' => $line['credit_minor'],
            ]);
        }

        $this->actingAs($user)
            ->get(route('admin.finance.reports.show', [
                'report' => 'profit-loss',
                'tenant' => $tenant->id,
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-08',
                'branch_id' => $branch->id,
            ]))
            ->assertOk()
            ->assertSee('Income Statement')
            ->assertSee('Profit and Loss Statement')
            ->assertSee('Sales Revenue')
            ->assertSee('Cost of Goods Sold')
            ->assertSee('Gross Profit')
            ->assertSee('Net Profit')
            ->assertSee('NGN 600.00')
            ->assertSee('NGN 500.00')
            ->assertSee('Inventory Movement Note')
            ->assertSee('Download PDF')
            ->assertSee('Download Excel')
            ->assertSee('Download Word')
            ->assertSee('Print');

        $this->actingAs($user)
            ->get(route('admin.finance.reports.show', [
                'report' => 'balance-sheet',
                'tenant' => $tenant->id,
                'date_to' => '2026-06-08',
                'branch_id' => $branch->id,
            ]))
            ->assertOk()
            ->assertSee('Balance Sheet')
            ->assertSee('Balance Sheet As On')
            ->assertSee('Assets')
            ->assertSee('Current Assets')
            ->assertSee('Accounts Receivable')
            ->assertSee('Liabilities')
            ->assertSee('Equity')
            ->assertSee('Current Year Earnings')
            ->assertSee('Total Liabilities + Equity')
            ->assertSee('NGN 500.00')
            ->assertSee('Download PDF')
            ->assertSee('Download Excel')
            ->assertSee('Download Word')
            ->assertSee('Print');

        $this->actingAs($user)
            ->get(route('admin.finance.reports.download', [
                'report' => 'balance-sheet',
                'tenant' => $tenant->id,
                'date_to' => '2026-06-08',
                'branch_id' => $branch->id,
                'format' => 'excel',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8')
            ->assertSee('Total Liabilities + Equity');

        $this->actingAs($user)
            ->get(route('admin.finance.reports.show', [
                'report' => 'sales',
                'tenant' => $tenant->id,
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-08',
            ]))
            ->assertOk()
            ->assertSee('Sales Report')
            ->assertSee('Sales Details')
            ->assertSee('Reference')
            ->assertSee('Payment Method')
            ->assertSee('Payment Status')
            ->assertSee('Subtotal')
            ->assertSee('Discount')
            ->assertSee('Total Sales')
            ->assertSee('SO-SALES-001')
            ->assertDontSee('INV-SALES-001')
            ->assertSee('INV-ONLY-002')
            ->assertSee('POS')
            ->assertSee('₦950.00')
            ->assertDontSee('NGN 950.00');

        $this->actingAs($user)
            ->get(route('admin.finance.reports.show', [
                'report' => 'revenue',
                'tenant' => $tenant->id,
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-08',
            ]))
            ->assertOk()
            ->assertSee('Sales Report')
            ->assertSee('SO-SALES-001')
            ->assertDontSee('INV-SALES-001');

        $salesExport = $this->actingAs($user)
            ->get(route('admin.finance.reports.download', [
                'report' => 'sales',
                'tenant' => $tenant->id,
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-08',
                'format' => 'excel',
            ]));

        $salesExport->assertOk();
        $salesExport->assertSee('SO-SALES-001');
        $salesExport->assertDontSee('INV-SALES-001');
        $salesExport->assertSee('INV-ONLY-002');
        $salesExport->assertSee('₦950.00');

        $this->actingAs($user)
            ->get(route('admin.finance.reports.show', [
                'report' => 'product-profitability',
                'tenant' => $tenant->id,
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-08',
                'branch_id' => $branch->id,
            ]))
            ->assertOk()
            ->assertSee('Product Profitability Report')
            ->assertSee('Company / Branch Information')
            ->assertSee('Product Details')
            ->assertSee('Premium Rice 10kg')
            ->assertSee('RICE-10KG')
            ->assertSee('Qty Sold')
            ->assertSee('Qty Returned')
            ->assertSee('Net Qty')
            ->assertSee('Sales Revenue')
            ->assertSee('COGS')
            ->assertSee('Gross Profit')
            ->assertSee('Gross Margin')
            ->assertSee('₦600.00')
            ->assertSee('₦360.00')
            ->assertSee('₦240.00')
            ->assertSee('40.00%')
            ->assertSee('Download PDF')
            ->assertSee('Download Excel')
            ->assertSee('Download Word')
            ->assertSee('Print');

        $this->actingAs($user)
            ->get(route('admin.finance.reports.download', [
                'report' => 'product-profitability',
                'tenant' => $tenant->id,
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-08',
                'branch_id' => $branch->id,
                'format' => 'excel',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8')
            ->assertSee('Product Profitability Report')
            ->assertSee('Premium Rice 10kg')
            ->assertSee('₦240.00')
            ->assertSee('40.00%');

        $this->actingAs($user)
            ->get(route('admin.finance.reports.show', [
                'report' => 'expense',
                'tenant' => $tenant->id,
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-08',
            ]))
            ->assertOk()
            ->assertSee('Expense Report')
            ->assertSee('Company / Branch Information')
            ->assertSee('Download PDF')
            ->assertSee('Download Excel')
            ->assertSee('Download Word')
            ->assertSee('Print')
            ->assertSee('<th style="width: 10%;">Date</th>', false)
            ->assertSee('<th style="width: 14%;">Category</th>', false)
            ->assertSee('<th style="width: 24%;">Description</th>', false)
            ->assertSee('<th style="width: 15%;">Payee</th>', false)
            ->assertSee('Amount')
            ->assertSee('Paid')
            ->assertSee('Payable')
            ->assertSee('Status')
            ->assertSee('Total Expense')
            ->assertSee('Total Paid')
            ->assertSee('Total Payable')
            ->assertDontSee('FinancialAha')
            ->assertDontSee('Approvals')
            ->assertDontSee('amount owed to employee');

        $this->actingAs($user)
            ->get(route('admin.finance.reports.download', [
                'report' => 'expense',
                'tenant' => $tenant->id,
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-08',
                'format' => 'excel',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');

        $this->actingAs($user)
            ->get(route('admin.finance.reports.download', [
                'report' => 'expense',
                'tenant' => $tenant->id,
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-08',
                'format' => 'pdf',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($user)
            ->get(route('admin.finance.chart-of-accounts', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('Chart of Accounts')
            ->assertSee('Accounts Receivable')
            ->assertSee('Search accounts')
            ->assertSee('data-chart-search', false)
            ->assertSee('data-chart-account-row', false)
            ->assertSee('EXP-6100')
            ->assertSee('Category')
            ->assertSee('Description')
            ->assertSee('Rent &amp; Lease', false)
            ->assertSee('Facilities &amp; Utility', false)
            ->assertSee('Monthly payments for office space.')
            ->assertSee('EXP-6000')
            ->assertSee('General Office &amp; Administrative Expense', false)
            ->assertSee('EXP-6300')
            ->assertSee('Travelling &amp; Transportation', false)
            ->assertSee('EXP-6360')
            ->assertSee('Meals &amp; Entertainment', false)
            ->assertDontSee('EXP-6260')
            ->assertDontSee('Office Expense / General Admin')
            ->assertDontSee('Travel and entertainment')
            ->assertSee('IT and Software Subscription')
            ->assertSee('Cloud tools, CRM, and accounting platforms.');

        $this->actingAs($user)
            ->get(route('admin.finance.expenses', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('Expenses')
            ->assertSee('Petty cash')
            ->assertSee('Log expense')
            ->assertSee('Filter by date')
            ->assertDontSee('Expense Categories')
            ->assertDontSee('Journal Entries')
            ->assertDontSee('Petty cash management')
            ->assertDontSee('Post petty cash');

        $this->actingAs($user)
            ->get(route('admin.finance.journals', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('Journals')
            ->assertSee('Chart of Accounts')
            ->assertSee('Expense Categories')
            ->assertSee('Journal Entries');
    }

    public function test_operational_expense_posts_balanced_journal_entry(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Expense Shop',
            'slug' => 'expense-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);

        $user = User::factory()->create([
            'is_platform_admin' => true,
        ]);

        $this->actingAs($user)
            ->get(route('admin.finance.index', ['tenant' => $tenant->id]))
            ->assertOk();

        $category = FinanceExpenseCategory::query()
            ->where('tenant_id', $tenant->id)
            ->where('code', 'utilities')
            ->firstOrFail();

        $this->actingAs($user)
            ->post(route('admin.finance.expenses.store'), [
                'tenant_id' => $tenant->id,
                'expense_category' => $category->account->category,
                'expense_account_code' => $category->account->code,
                'payment_account_code' => '1000',
                'expense_date' => '2026-06-08',
                'payee_name' => 'Power Company',
                'payment_status' => 'paid',
                'amount' => '125.50',
                'paid_amount' => '125.50',
                'reference_number' => 'UTIL-1',
                'description' => 'Monthly utility bill',
            ])
            ->assertRedirect();

        $entry = FinanceJournalEntry::query()
            ->with('lines.account')
            ->where('tenant_id', $tenant->id)
            ->where('source_type', 'finance_expense')
            ->where('source_event', 'recorded')
            ->firstOrFail();

        $this->assertSame(12550, (int) $entry->lines->sum('debit_minor'));
        $this->assertSame(12550, (int) $entry->lines->sum('credit_minor'));
        $this->assertTrue($entry->lines->contains(fn ($line): bool => $line->account->code === $category->account->code && $line->debit_minor === 12550));
        $this->assertTrue($entry->lines->contains(fn ($line): bool => $line->account->code === '1000' && $line->credit_minor === 12550));
        $expense = FinanceExpense::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame($category->account->id, $expense->finance_account_id);
        $this->assertNotNull($expense->payment_finance_account_id);
    }

    public function test_manual_journal_can_be_posted_from_journals_page(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Journal Shop',
            'slug' => 'journal-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);

        $user = User::factory()->create([
            'is_platform_admin' => true,
        ]);
        $branch = Branch::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->actingAs($user)
            ->get(route('admin.finance.journals', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('Journal Entries')
            ->assertSee('Post journal');

        $this->actingAs($user)
            ->post(route('admin.finance.journals.store'), [
                'tenant_id' => $tenant->id,
                'entry_date' => '2026-06-08',
                'memo' => 'Owner cash injection',
                'lines' => [
                    ['account_code' => '1000', 'branch_id' => $branch->id, 'debit' => '5000', 'credit' => '', 'memo' => 'Cash received'],
                    ['account_code' => '3000', 'branch_id' => $branch->id, 'debit' => '', 'credit' => '5000', 'memo' => 'Owner equity'],
                    ['account_code' => '', 'debit' => '', 'credit' => '', 'memo' => ''],
                ],
            ])
            ->assertRedirect();

        $entry = FinanceJournalEntry::query()
            ->with('lines.account')
            ->where('tenant_id', $tenant->id)
            ->where('source_type', 'manual_journal')
            ->firstOrFail();

        $this->assertSame(500000, (int) $entry->lines->sum('debit_minor'));
        $this->assertSame(500000, (int) $entry->lines->sum('credit_minor'));
        $this->assertTrue($entry->lines->contains(fn ($line): bool => $line->account->code === '1000' && $line->debit_minor === 500000));
        $this->assertTrue($entry->lines->every(fn ($line): bool => $line->branch_id === $branch->id));

        $this->actingAs($user)
            ->get(route('admin.finance.journals', ['tenant' => $tenant->id]).'#journal-entries')
            ->assertOk()
            ->assertSee('name="journal_date_from"', false)
            ->assertSee('name="journal_date_to"', false)
            ->assertSee('name="journal_category"', false)
            ->assertSee('name="journal_type"', false)
            ->assertSee('name="journal_account"', false)
            ->assertSee('Download')
            ->assertSee('DATE')
            ->assertSee('PARTICULARS')
            ->assertSee('POST REF')
            ->assertSee('DEBIT (DR)')
            ->assertSee('CREDIT (CR)')
            ->assertSee('Branch:</strong> Main Branch', false)
            ->assertSee('Memo:</strong> Owner cash injection', false)
            ->assertSee('Entry No:</strong> '.$entry->entry_number, false)
            ->assertSee('Cash received')
            ->assertSee('5,000');

        $this->actingAs($user)
            ->post(route('admin.finance.journals.store'), [
                'tenant_id' => $tenant->id,
                'entry_date' => '2026-06-09',
                'memo' => 'Bank fee accrual',
                'lines' => [
                    ['account_code' => 'EXP-6000', 'branch_id' => $branch->id, 'debit' => '100', 'credit' => '', 'memo' => 'Bank fee'],
                    ['account_code' => '2000', 'branch_id' => $branch->id, 'debit' => '', 'credit' => '100', 'memo' => 'Accrued payable'],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('admin.finance.journals', [
                'tenant' => $tenant->id,
                'journal_account' => '3000',
            ]).'#journal-entries')
            ->assertOk()
            ->assertSee('Owner cash injection')
            ->assertDontSee('Bank fee accrual');

        $download = $this->actingAs($user)
            ->get(route('admin.finance.journals.download', [
                'tenant' => $tenant->id,
                'journal_account' => '3000',
            ]));

        $download->assertOk();
        $download->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $download->streamedContent();
        $this->assertStringContainsString('Owner equity', $csv);
        $this->assertStringContainsString('Owner cash injection', $csv);
        $this->assertStringNotContainsString('Bank fee accrual', $csv);
    }

    public function test_banking_movement_settles_pos_clearing_to_bank_with_charges(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Banking Shop',
            'slug' => 'banking-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
        $user = User::factory()->create(['is_platform_admin' => true]);
        $branch = Branch::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->actingAs($user)
            ->get(route('admin.finance.journals', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('Banking')
            ->assertSee('Move to bank')
            ->assertSee('Settle POS/Card');

        FinanceAccount::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'BANK-1001',
            'name' => 'GTBank Main Account',
            'type' => 'asset',
            'category' => 'Current Assets',
            'description' => 'Business bank account.',
            'normal_balance' => 'debit',
            'is_system' => false,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('admin.finance.journals.store'), [
                'tenant_id' => $tenant->id,
                'entry_date' => '2026-07-08',
                'memo' => 'POS receipts awaiting settlement',
                'lines' => [
                    ['account_code' => '1050', 'branch_id' => $branch->id, 'debit' => '1000', 'credit' => '', 'memo' => 'POS receipts'],
                    ['account_code' => '4000', 'branch_id' => $branch->id, 'debit' => '', 'credit' => '1000', 'memo' => 'Sales'],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('admin.finance.bank-movements.store'), [
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'movement_type' => 'settle_pos',
                'source_account_code' => '1050',
                'destination_account_code' => 'BANK-1001',
                'movement_date' => '2026-07-08',
                'gross_amount' => '1000',
                'fee_amount' => '25',
                'reference_number' => 'POS-BATCH-1',
                'notes' => 'Settled POS batch',
            ])
            ->assertRedirect(route('admin.finance.journals', ['tenant' => $tenant->id]).'#banking');

        $movement = FinanceBankMovement::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('settle_pos', $movement->movement_type);
        $this->assertSame(100000, $movement->gross_amount_minor);
        $this->assertSame(2500, $movement->fee_amount_minor);
        $this->assertSame(97500, $movement->net_amount_minor);

        $entry = FinanceJournalEntry::query()
            ->with('lines.account')
            ->where('tenant_id', $tenant->id)
            ->where('source_type', 'finance_bank_movement')
            ->where('source_id', $movement->id)
            ->firstOrFail();

        $this->assertTrue($entry->lines->contains(fn ($line): bool => $line->account->code === 'BANK-1001' && $line->debit_minor === 97500));
        $this->assertTrue($entry->lines->contains(fn ($line): bool => $line->account->code === 'EXP-6350' && $line->debit_minor === 2500));
        $this->assertTrue($entry->lines->contains(fn ($line): bool => $line->account->code === '1050' && $line->credit_minor === 100000));

        $this->actingAs($user)
            ->get(route('admin.finance.journals', ['tenant' => $tenant->id]).'#banking')
            ->assertOk()
            ->assertSee('POS-BATCH-1')
            ->assertSee('GTBank Main Account')
            ->assertSee('Banking movement');
    }

    public function test_banking_movement_cannot_exceed_source_balance(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Vault Shop',
            'slug' => 'vault-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($user)
            ->get(route('admin.finance.journals', ['tenant' => $tenant->id]))
            ->assertOk();

        FinanceAccount::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'BANK-1002',
            'name' => 'Access Bank Main Account',
            'type' => 'asset',
            'category' => 'Current Assets',
            'description' => 'Business bank account.',
            'normal_balance' => 'debit',
            'is_system' => false,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('admin.finance.journals.store'), [
                'tenant_id' => $tenant->id,
                'entry_date' => '2026-07-08',
                'memo' => 'Cash in branch vault',
                'lines' => [
                    ['account_code' => '1030', 'debit' => '10000', 'credit' => '', 'memo' => 'Vault cash'],
                    ['account_code' => '3000', 'debit' => '', 'credit' => '10000', 'memo' => 'Opening equity'],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->from(route('admin.finance.journals', ['tenant' => $tenant->id]).'#banking')
            ->post(route('admin.finance.bank-movements.store'), [
                'tenant_id' => $tenant->id,
                'movement_type' => 'bank_cash',
                'source_account_code' => '1030',
                'destination_account_code' => 'BANK-1002',
                'movement_date' => '2026-07-08',
                'gross_amount' => '20000',
                'fee_amount' => '',
            ])
            ->assertRedirect(route('admin.finance.journals', ['tenant' => $tenant->id]).'#banking')
            ->assertSessionHasErrors('gross_amount');

        $this->assertFalse(FinanceBankMovement::query()->where('tenant_id', $tenant->id)->exists());
        $this->assertFalse(FinanceJournalEntry::query()
            ->where('tenant_id', $tenant->id)
            ->where('source_type', 'finance_bank_movement')
            ->exists());
    }

    public function test_journals_and_reports_make_branch_reporting_visible(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Branch Ledger Shop',
            'slug' => 'branch-ledger-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
        $user = User::factory()->create(['is_platform_admin' => true]);
        $main = Branch::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'status' => 'active',
            'is_primary' => true,
        ]);
        $annex = Branch::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Annex Branch',
            'code' => 'ANNEX',
            'status' => 'active',
            'is_primary' => false,
        ]);

        $this->actingAs($user)
            ->get(route('admin.finance.journals', ['tenant' => $tenant->id]))
            ->assertOk();

        $this->actingAs($user)
            ->post(route('admin.finance.journals.store'), [
                'tenant_id' => $tenant->id,
                'entry_date' => '2026-07-08',
                'memo' => 'Main branch cash',
                'lines' => [
                    ['account_code' => '1000', 'branch_id' => $main->id, 'debit' => '5000', 'credit' => '', 'memo' => 'Main cash'],
                    ['account_code' => '3000', 'branch_id' => $main->id, 'debit' => '', 'credit' => '5000', 'memo' => 'Main equity'],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('admin.finance.journals.store'), [
                'tenant_id' => $tenant->id,
                'entry_date' => '2026-07-08',
                'memo' => 'Annex branch cash',
                'lines' => [
                    ['account_code' => '1000', 'branch_id' => $annex->id, 'debit' => '2500', 'credit' => '', 'memo' => 'Annex cash'],
                    ['account_code' => '3000', 'branch_id' => $annex->id, 'debit' => '', 'credit' => '2500', 'memo' => 'Annex equity'],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('admin.finance.journals', [
                'tenant' => $tenant->id,
                'journal_branch_id' => $main->id,
            ]).'#journal-entries')
            ->assertOk()
            ->assertSee('name="journal_branch_id"', false)
            ->assertSee('Main branch cash')
            ->assertDontSee('Annex branch cash');

        $this->actingAs($user)
            ->get(route('admin.finance.journals', [
                'tenant' => $tenant->id,
                'journal_date_from' => '2026-07-08',
                'journal_date_to' => '2026-07-08',
                'journal_branch_id' => $main->id,
            ]).'#reports')
            ->assertOk()
            ->assertSee('Branch ledger snapshot')
            ->assertSee('Main Branch')
            ->assertSee('NGN 5,000.00')
            ->assertDontSee('NGN 2,500.00');
    }
}
