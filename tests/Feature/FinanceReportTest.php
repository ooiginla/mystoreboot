<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('Accounts Receivable');

        $this->actingAs($user)
            ->get(route('admin.finance.expenses', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('Expenses')
            ->assertSee('Expense categories')
            ->assertSee('Petty cash management')
            ->assertSee('Log expense');
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
                'finance_expense_category_id' => $category->id,
                'expense_date' => '2026-06-08',
                'payee_name' => 'Power Company',
                'payment_method' => 'Cash',
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
                    ['account_code' => '1000', 'debit' => '5000', 'credit' => '', 'memo' => 'Cash received'],
                    ['account_code' => '3000', 'debit' => '', 'credit' => '5000', 'memo' => 'Owner equity'],
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
    }
}
