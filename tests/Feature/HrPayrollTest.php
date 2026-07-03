<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Business\Models\Branch;
use Modules\Finance\Models\FinanceJournalEntry;
use Modules\HrPayroll\Models\HrPayrollItem;
use Modules\HrPayroll\Models\HrPayrollRun;
use Modules\HrPayroll\Models\HrStaff;
use Modules\HrPayroll\Models\HrStaffDeduction;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

class HrPayrollTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_deduction_and_monthly_payroll_can_be_saved(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Payroll Shop',
            'slug' => 'payroll-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
        $branch = Branch::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'status' => 'active',
            'is_primary' => true,
        ]);
        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($user)
            ->get(route('admin.hr-payroll.index', ['tenant' => $tenant->id, 'payroll_month' => '2026-06']))
            ->assertOk()
            ->assertSee('HR &amp; Payroll', false)
            ->assertSee('Staff records')
            ->assertSee('Monthly salary schedule')
            ->assertSee('Pay wages from')
            ->assertSee('Overall total net')
            ->assertSee('Post Payroll');

        $this->actingAs($user)
            ->post(route('admin.hr-payroll.staff.store'), [
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'staff_number' => 'STF-001',
                'first_name' => 'Ada',
                'last_name' => 'Okafor',
                'email' => 'ada@example.test',
                'phone' => '08000000000',
                'job_title' => 'Cashier',
                'hire_date' => '2026-06-01',
                'monthly_salary' => '150000',
                'status' => 'active',
            ])
            ->assertRedirect();

        $staff = HrStaff::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->actingAs($user)
            ->post(route('admin.hr-payroll.deductions.store'), [
                'tenant_id' => $tenant->id,
                'hr_staff_id' => $staff->id,
                'deduction_type' => 'salary_advance',
                'deduction_month' => '2026-06',
                'deduction_date' => '2026-06-08',
                'amount' => '25000',
                'reason' => 'Salary advance',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('admin.hr-payroll.payroll-runs.store'), [
                'tenant_id' => $tenant->id,
                'payroll_month' => '2026-06',
                'funding_account_code' => '1000',
                'notes' => 'June payroll',
            ])
            ->assertRedirect();

        $run = HrPayrollRun::query()->where('tenant_id', $tenant->id)->where('payroll_month', '2026-06')->firstOrFail();
        $item = HrPayrollItem::query()->where('hr_payroll_run_id', $run->id)->where('hr_staff_id', $staff->id)->firstOrFail();

        $this->assertSame(15000000, $item->gross_salary_minor);
        $this->assertSame(2500000, $item->deduction_minor);
        $this->assertSame(12500000, $item->net_salary_minor);
        $this->assertSame('applied', HrStaffDeduction::query()->firstOrFail()->status);

        $deductionJournal = FinanceJournalEntry::query()->with('lines')->where('tenant_id', $tenant->id)->where('source_type', 'hr_staff_deduction')->where('source_event', 'posted')->firstOrFail();
        $this->assertTrue($deductionJournal->lines->every(fn ($line): bool => $line->branch_id === $branch->id));
        $payrollJournal = FinanceJournalEntry::query()
            ->with('lines.account')
            ->where('tenant_id', $tenant->id)
            ->where('source_type', 'hr_payroll_run')
            ->where('source_event', 'posted')
            ->firstOrFail();

        $this->assertSame(15000000, (int) $payrollJournal->lines->sum('debit_minor'));
        $this->assertSame(15000000, (int) $payrollJournal->lines->sum('credit_minor'));
        $this->assertTrue($payrollJournal->lines->contains(fn ($line): bool => $line->account->code === 'EXP-6030' && $line->debit_minor === 15000000));
        $this->assertTrue($payrollJournal->lines->contains(fn ($line): bool => $line->account->code === '1000' && $line->credit_minor === 12500000));
        $this->assertTrue($payrollJournal->lines->every(fn ($line): bool => $line->branch_id === $branch->id));
    }
}
