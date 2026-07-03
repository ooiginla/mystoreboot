<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Business\Models\Branch;
use Modules\Finance\Models\FinanceExpense;
use Modules\Finance\Models\FinanceExpenseCategory;
use Modules\Finance\Models\FinanceJournalEntry;
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
            ->assertSee('Accounts receivable');

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
            ->assertSee('IT and Software Subscription')
            ->assertSee('Cloud tools, CRM, and accounting platforms.');

        $this->actingAs($user)
            ->get(route('admin.finance.expenses', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('Expenses')
            ->assertSee('Expense categories')
            ->assertSee('Petty cash')
            ->assertSee('Log expense')
            ->assertSee('Journal entries')
            ->assertDontSee('Petty cash management')
            ->assertDontSee('Post petty cash');
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

    public function test_manual_journal_can_be_posted_from_expenses_page(): void
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
            ->get(route('admin.finance.expenses', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('Journal entries')
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
            ->get(route('admin.finance.expenses', ['tenant' => $tenant->id]).'#journals')
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
            ->get(route('admin.finance.expenses', [
                'tenant' => $tenant->id,
                'journal_account' => '3000',
            ]).'#journals')
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
}
