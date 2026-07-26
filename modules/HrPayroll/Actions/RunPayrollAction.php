<?php

declare(strict_types=1);

namespace Modules\HrPayroll\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Actions\PostJournalEntryAction;
use Modules\HrPayroll\Models\HrPayrollRun;
use Modules\HrPayroll\Models\HrStaff;
use Modules\HrPayroll\Models\HrStaffDeduction;

/**
 * Posts a monthly payroll run and its journal. Shared by the controller (direct)
 * and the approval executor (deferred) so both behave identically. Salary figures
 * are computed from live staff data at execution time.
 */
final class RunPayrollAction
{
    public function __construct(private readonly PostJournalEntryAction $postJournalEntry) {}

    /**
     * @param  array<string, mixed>  $data  validated PayrollRunRequest data
     */
    public function execute(array $data, ?int $userId = null): HrPayrollRun
    {
        return DB::transaction(function () use ($data, $userId): HrPayrollRun {
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
                'posted_by' => $userId,
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

            $this->postJournalEntry->execute(
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
    }

    /**
     * The projected net payroll (for the approval amount) without persisting.
     *
     * @param  array<string, mixed>  $data
     */
    public function projectedNetMinor(array $data): int
    {
        $staff = HrStaff::query()
            ->with('deductions')
            ->where('tenant_id', $data['tenant_id'])
            ->where('status', 'active')
            ->get();

        return (int) $this->salarySchedule($staff, $data['payroll_month'])->sum('net_minor');
    }

    /**
     * @param  Collection<int, HrStaff>|\Illuminate\Database\Eloquent\Collection<int, HrStaff>  $staff
     * @return Collection<int, array<string, mixed>>
     */
    private function salarySchedule($staff, string $payrollMonth): Collection
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
}
