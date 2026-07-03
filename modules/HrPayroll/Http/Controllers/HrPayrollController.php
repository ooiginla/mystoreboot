<?php

declare(strict_types=1);

namespace Modules\HrPayroll\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\Access\Enums\MembershipStatus;
use Modules\Access\Models\TenantMembership;
use Modules\Business\Models\Branch;
use Modules\Finance\Actions\EnsureDefaultChartOfAccountsAction;
use Modules\Finance\Actions\PostJournalEntryAction;
use Modules\Finance\Models\FinanceAccount;
use Modules\HrPayroll\Http\Requests\PayrollRunRequest;
use Modules\HrPayroll\Http\Requests\StaffDeductionRequest;
use Modules\HrPayroll\Http\Requests\StaffRequest;
use Modules\HrPayroll\Models\HrPayrollItem;
use Modules\HrPayroll\Models\HrPayrollRun;
use Modules\HrPayroll\Models\HrStaff;
use Modules\HrPayroll\Models\HrStaffBranchTransfer;
use Modules\HrPayroll\Models\HrStaffDeduction;
use Modules\Tenancy\Models\Tenant;

final class HrPayrollController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $tenants = $this->visibleTenantsFor($user);
        $tenant = $this->resolveTenant($request, $tenants);

        abort_if(! $tenant, 403);

        $payrollMonth = $request->string('payroll_month')->toString() ?: now()->format('Y-m');
        $branches = Branch::query()->where('tenant_id', $tenant->id)->orderByDesc('is_primary')->orderBy('name')->get();
        $staff = HrStaff::query()->with(['branch', 'deductions'])->where('tenant_id', $tenant->id)->orderBy('first_name')->get();
        $activeStaff = $staff->where('status', 'active')->values();
        $deductions = HrStaffDeduction::query()->with('staff.branch')->where('tenant_id', $tenant->id)->latest('deduction_date')->get();
        app(EnsureDefaultChartOfAccountsAction::class)->execute($tenant->id);
        $payrollFundingAccounts = FinanceAccount::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', 'asset')
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
        $savedPayroll = HrPayrollRun::query()
            ->with(['items.staff.branch', 'items.branch'])
            ->where('tenant_id', $tenant->id)
            ->where('payroll_month', $payrollMonth)
            ->first();
        $payrollRuns = HrPayrollRun::query()->withCount('items')->where('tenant_id', $tenant->id)->latest('posted_at')->get();
        $payslipItems = HrPayrollItem::query()
            ->with(['run', 'staff.branch', 'branch'])
            ->where('tenant_id', $tenant->id)
            ->latest()
            ->limit(120)
            ->get();

        return view('hr-payroll::admin.index', [
            'tenant' => $tenant,
            'tenants' => $tenants,
            'isPlatformAdmin' => $user->is_platform_admin,
            'branches' => $branches,
            'staff' => $staff,
            'deductions' => $deductions,
            'payrollMonth' => $payrollMonth,
            'scheduleRows' => $this->salarySchedule($activeStaff, $payrollMonth),
            'payrollFundingAccounts' => $payrollFundingAccounts,
            'savedPayroll' => $savedPayroll,
            'payrollRuns' => $payrollRuns,
            'payslipItems' => $payslipItems,
            'stats' => [
                'staff' => $staff->count(),
                'active_staff' => $activeStaff->count(),
                'gross_minor' => $activeStaff->sum('monthly_salary_minor'),
                'deduction_minor' => $deductions->where('deduction_month', $payrollMonth)->where('status', 'pending')->sum('amount_minor'),
            ],
        ]);
    }

    public function storeStaff(StaffRequest $request): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());
        $data = $request->validated();

        $staff = HrStaff::query()->create($this->staffValues($data));

        if ($staff->branch_id) {
            HrStaffBranchTransfer::query()->create([
                'tenant_id' => $staff->tenant_id,
                'hr_staff_id' => $staff->id,
                'from_branch_id' => null,
                'to_branch_id' => $staff->branch_id,
                'effective_date' => $staff->hire_date ?? now()->toDateString(),
                'notes' => 'Initial branch assignment.',
            ]);
        }

        return redirect()->to(route('admin.hr-payroll.index', ['tenant' => $staff->tenant_id]).'#staff')->with('status', "{$staff->name} created.");
    }

    public function updateStaff(StaffRequest $request, HrStaff $staff): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $staff->tenant_id);
        $data = $request->validated();
        abort_unless($data['tenant_id'] === $staff->tenant_id, 403);
        $fromBranchId = $staff->branch_id;

        $staff->update($this->staffValues($data, $staff));

        if ($fromBranchId !== $staff->branch_id) {
            HrStaffBranchTransfer::query()->create([
                'tenant_id' => $staff->tenant_id,
                'hr_staff_id' => $staff->id,
                'from_branch_id' => $fromBranchId,
                'to_branch_id' => $staff->branch_id,
                'effective_date' => now()->toDateString(),
                'notes' => 'Branch updated from staff record.',
            ]);
        }

        return redirect()->to(route('admin.hr-payroll.index', ['tenant' => $staff->tenant_id]).'#staff')->with('status', "{$staff->name} updated.");
    }

    public function storeDeduction(StaffDeductionRequest $request, PostJournalEntryAction $postJournalEntry): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());
        $data = $request->validated();
        $amountMinor = $this->moneyToMinor($data['amount']);

        $deduction = DB::transaction(function () use ($data, $amountMinor, $postJournalEntry): HrStaffDeduction {
            $deduction = HrStaffDeduction::query()->create([
                'tenant_id' => $data['tenant_id'],
                'hr_staff_id' => $data['hr_staff_id'],
                'deduction_type' => $data['deduction_type'],
                'deduction_month' => $data['deduction_month'],
                'deduction_date' => $data['deduction_date'],
                'amount_minor' => $amountMinor,
                'status' => 'pending',
                'reason' => $data['reason'] ?? null,
            ]);

            $postJournalEntry->execute(
                $deduction->tenant_id,
                $deduction->deduction_date->toDateString(),
                'Staff '.$deduction->deduction_type.' deduction',
                $this->deductionPostingLines($deduction),
                'hr_staff_deduction',
                $deduction->id,
                'posted',
            );

            return $deduction;
        });

        return redirect()->to(route('admin.hr-payroll.index', ['tenant' => $deduction->tenant_id, 'payroll_month' => $deduction->deduction_month]).'#deductions')->with('status', 'Staff deduction posted.');
    }

    public function storePayrollRun(PayrollRunRequest $request, PostJournalEntryAction $postJournalEntry): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());
        $data = $request->validated();

        $payrollRun = DB::transaction(function () use ($request, $data, $postJournalEntry): HrPayrollRun {
            $existing = HrPayrollRun::query()
                ->where('tenant_id', $data['tenant_id'])
                ->where('payroll_month', $data['payroll_month'])
                ->first();

            abort_if($existing, 422, 'Payroll has already been posted for this month.');

            $staff = HrStaff::query()
                ->with('deductions')
                ->where('tenant_id', $data['tenant_id'])
                ->where('status', 'active')
                ->orderBy('first_name')
                ->get();
            $rows = $this->salarySchedule($staff, $data['payroll_month']);

            $run = HrPayrollRun::query()->create([
                'tenant_id' => $data['tenant_id'],
                'payroll_month' => $data['payroll_month'],
                'posted_at' => now()->toDateString(),
                'gross_salary_minor' => $rows->sum('gross_minor'),
                'deduction_minor' => $rows->sum('deduction_minor'),
                'net_salary_minor' => $rows->sum('net_minor'),
                'posted_by' => $request->user()?->id,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($rows as $row) {
                $run->items()->create([
                    'tenant_id' => $run->tenant_id,
                    'hr_staff_id' => $row['staff']->id,
                    'branch_id' => $row['staff']->branch_id,
                    'gross_salary_minor' => $row['gross_minor'],
                    'deduction_minor' => $row['deduction_minor'],
                    'net_salary_minor' => $row['net_minor'],
                    'deduction_breakdown' => $row['deductions']->map(fn (HrStaffDeduction $deduction): array => [
                        'id' => $deduction->id,
                        'type' => $deduction->deduction_type,
                        'amount_minor' => $deduction->amount_minor,
                        'reason' => $deduction->reason,
                    ])->values()->all(),
                ]);

                HrStaffDeduction::query()
                    ->whereIn('id', $row['deductions']->pluck('id'))
                    ->update(['status' => 'applied']);
            }

            $payrollLines = $rows
                ->groupBy(fn (array $row): string => (string) ($row['staff']->branch_id ?? ''))
                ->flatMap(function ($branchRows, string $branchId) use ($data) {
                    $branchIdValue = $branchId !== '' ? (int) $branchId : null;
                    $branchSalaryAdvanceMinor = $branchRows->sum(fn (array $row): int => (int) $row['deductions']->where('deduction_type', 'salary_advance')->sum('amount_minor'));
                    $branchDeductionReceivableMinor = $branchRows->sum(fn (array $row): int => (int) $row['deductions']->whereIn('deduction_type', ['fine', 'other'])->sum('amount_minor'));

                    return [
                        ['account_code' => 'EXP-6030', 'branch_id' => $branchIdValue, 'debit_minor' => $branchRows->sum('gross_minor'), 'memo' => 'Gross salaries and wages'],
                        ['account_code' => $data['funding_account_code'], 'branch_id' => $branchIdValue, 'credit_minor' => $branchRows->sum('net_minor'), 'memo' => 'Net wages paid from funding account'],
                        ['account_code' => '1300', 'branch_id' => $branchIdValue, 'credit_minor' => $branchSalaryAdvanceMinor, 'memo' => 'Clear salary advances'],
                        ['account_code' => '1310', 'branch_id' => $branchIdValue, 'credit_minor' => $branchDeductionReceivableMinor, 'memo' => 'Clear staff deduction receivables'],
                    ];
                })
                ->values()
                ->all();

            $postJournalEntry->execute(
                $run->tenant_id,
                $run->posted_at->toDateString(),
                'Payroll posting '.$run->payroll_month,
                $payrollLines,
                'hr_payroll_run',
                $run->id,
                'posted',
            );

            return $run;
        });

        return redirect()->to(route('admin.hr-payroll.index', ['tenant' => $payrollRun->tenant_id, 'payroll_month' => $payrollRun->payroll_month]).'#salaries')->with('status', 'Monthly payroll posted.');
    }

    /**
     * @param  EloquentCollection<int, HrStaff>|\Illuminate\Support\Collection<int, HrStaff>  $staff
     */
    private function salarySchedule($staff, string $payrollMonth): \Illuminate\Support\Collection
    {
        return collect($staff)->map(function (HrStaff $staff) use ($payrollMonth): array {
            $deductions = $staff->deductions
                ->where('deduction_month', $payrollMonth)
                ->where('status', 'pending')
                ->values();
            $deductionMinor = (int) $deductions->sum('amount_minor');
            $grossMinor = (int) $staff->monthly_salary_minor;

            return [
                'staff' => $staff,
                'gross_minor' => $grossMinor,
                'deduction_minor' => $deductionMinor,
                'net_minor' => max(0, $grossMinor - $deductionMinor),
                'deductions' => $deductions,
            ];
        })->values();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function staffValues(array $data, ?HrStaff $staff = null): array
    {
        return [
            'tenant_id' => $data['tenant_id'],
            'branch_id' => $data['branch_id'] ?? null,
            'staff_number' => $data['staff_number'] ?: ($staff?->staff_number ?? $this->staffNumber($data['tenant_id'])),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'hire_date' => $data['hire_date'] ?? null,
            'monthly_salary_minor' => $this->moneyToMinor($data['monthly_salary']),
            'status' => $data['status'],
            'address' => $data['address'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function staffNumber(string $tenantId): string
    {
        return 'STF-'.now()->format('Ymd').'-'.str_pad((string) (HrStaff::query()->where('tenant_id', $tenantId)->count() + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function deductionPostingLines(HrStaffDeduction $deduction): array
    {
        $branchId = $deduction->loadMissing('staff')->staff?->branch_id;

        return match ($deduction->deduction_type) {
            'salary_advance' => [
                ['account_code' => '1300', 'branch_id' => $branchId, 'debit_minor' => $deduction->amount_minor, 'party_type' => 'staff', 'party_id' => $deduction->hr_staff_id, 'memo' => $deduction->reason],
                ['account_code' => '1000', 'branch_id' => $branchId, 'credit_minor' => $deduction->amount_minor, 'party_type' => 'staff', 'party_id' => $deduction->hr_staff_id, 'memo' => 'Salary advance paid'],
            ],
            'fine' => [
                ['account_code' => '1310', 'branch_id' => $branchId, 'debit_minor' => $deduction->amount_minor, 'party_type' => 'staff', 'party_id' => $deduction->hr_staff_id, 'memo' => $deduction->reason],
                ['account_code' => '4100', 'branch_id' => $branchId, 'credit_minor' => $deduction->amount_minor, 'party_type' => 'staff', 'party_id' => $deduction->hr_staff_id, 'memo' => 'Staff fine'],
            ],
            default => [
                ['account_code' => '1310', 'branch_id' => $branchId, 'debit_minor' => $deduction->amount_minor, 'party_type' => 'staff', 'party_id' => $deduction->hr_staff_id, 'memo' => $deduction->reason],
                ['account_code' => '4110', 'branch_id' => $branchId, 'credit_minor' => $deduction->amount_minor, 'party_type' => 'staff', 'party_id' => $deduction->hr_staff_id, 'memo' => 'Other payroll deduction'],
            ],
        };
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

    private function moneyToMinor(mixed $value): int
    {
        return (int) round(((float) (is_string($value) ? str_replace(',', '', $value) : ($value ?: 0))) * 100);
    }
}
