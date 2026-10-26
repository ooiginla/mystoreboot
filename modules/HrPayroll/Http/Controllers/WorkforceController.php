<?php

declare(strict_types=1);

namespace Modules\HrPayroll\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Access\Enums\MembershipStatus;
use Modules\Business\Models\Branch;
use Modules\HrPayroll\Models\HrAttendanceRecord;
use Modules\HrPayroll\Models\HrShiftAssignment;
use Modules\HrPayroll\Models\HrStaff;
use Modules\HrPayroll\Models\HrWorkShift;
use Modules\Tenancy\Models\Tenant;

final class WorkforceController extends Controller
{
    public function index(Request $request): View
    {
        [$tenant, $tenants, $user] = $this->context($request);
        $branches = Branch::query()->where('tenant_id', $tenant->id)->orderByDesc('is_primary')->orderBy('name')->get();
        $branchId = (int) ($request->integer('branch') ?: $branches->first()?->id);
        abort_unless($branches->contains('id', $branchId), 403);
        $weekStart = CarbonImmutable::parse($request->string('week')->toString() ?: now()->toDateString())->startOfWeek();
        $weekEnd = $weekStart->endOfWeek();

        $staff = HrStaff::query()->where('tenant_id', $tenant->id)->where('branch_id', $branchId)
            ->where('status', 'active')->orderBy('first_name')->orderBy('last_name')->get();
        $shifts = HrWorkShift::query()->where('tenant_id', $tenant->id)->where('branch_id', $branchId)->orderBy('starts_at')->get();
        $assignments = HrShiftAssignment::query()->with(['staff', 'shift'])
            ->where('tenant_id', $tenant->id)->where('branch_id', $branchId)
            ->whereBetween('work_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->orderBy('work_date')->get();
        $attendance = HrAttendanceRecord::query()->with(['staff', 'assignment.shift'])
            ->where('tenant_id', $tenant->id)->where('branch_id', $branchId)
            ->whereBetween('work_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->latest('clocked_in_at')->get();

        return view('hr-payroll::admin.workforce', compact(
            'tenant', 'tenants', 'user', 'branches', 'branchId', 'weekStart', 'weekEnd',
            'staff', 'shifts', 'assignments', 'attendance',
        ));
    }

    public function storeShift(Request $request): RedirectResponse
    {
        [$tenant] = $this->context($request);
        $data = $this->validateShift($request, $tenant);
        HrWorkShift::query()->create(['tenant_id' => $tenant->id, ...$data]);

        return $this->backToWorkforce($tenant, (int) $data['branch_id'], 'Shift created.');
    }

    public function updateShift(Request $request, HrWorkShift $shift): RedirectResponse
    {
        [$tenant] = $this->context($request);
        abort_unless($shift->tenant_id === $tenant->id, 404);
        $shift->update($this->validateShift($request, $tenant, $shift->id));

        return $this->backToWorkforce($tenant, (int) $shift->branch_id, 'Shift updated.');
    }

    public function storeAssignment(Request $request): RedirectResponse
    {
        [$tenant] = $this->context($request);
        $data = $request->validate([
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('tenant_id', $tenant->id)],
            'hr_staff_ids' => ['required', 'array', 'min:1'],
            'hr_staff_ids.*' => ['integer'],
            'hr_work_shift_id' => ['required', 'integer'],
            'work_dates' => ['required', 'array', 'min:1', 'max:31'],
            'work_dates.*' => ['date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $branchId = (int) $data['branch_id'];
        $shift = HrWorkShift::query()->where('tenant_id', $tenant->id)->where('branch_id', $branchId)->findOrFail($data['hr_work_shift_id']);
        $staff = HrStaff::query()->where('tenant_id', $tenant->id)->where('branch_id', $branchId)
            ->where('status', 'active')->whereIn('id', $data['hr_staff_ids'])->get();
        if ($staff->count() !== count(array_unique($data['hr_staff_ids']))) {
            throw ValidationException::withMessages(['hr_staff_ids' => 'Every selected staff member must be active in this branch.']);
        }

        foreach ($data['work_dates'] as $date) {
            foreach ($staff as $employee) {
                HrShiftAssignment::query()->updateOrCreate([
                    'hr_staff_id' => $employee->id, 'work_date' => $date, 'hr_work_shift_id' => $shift->id,
                ], [
                    'tenant_id' => $tenant->id, 'branch_id' => $branchId, 'status' => 'scheduled', 'notes' => $data['notes'] ?? null,
                ]);
            }
        }

        return $this->backToWorkforce($tenant, $branchId, 'Rota assignment saved.', (string) $data['work_dates'][0]);
    }

    public function destroyAssignment(Request $request, HrShiftAssignment $assignment): RedirectResponse
    {
        [$tenant] = $this->context($request);
        abort_unless($assignment->tenant_id === $tenant->id, 404);
        $branchId = (int) $assignment->branch_id;
        $date = $assignment->work_date->toDateString();
        $assignment->delete();

        return $this->backToWorkforce($tenant, $branchId, 'Rota assignment removed.', $date);
    }

    public function clockIn(Request $request): RedirectResponse
    {
        [$tenant, , $user] = $this->context($request);
        $data = $request->validate([
            'hr_shift_assignment_id' => ['required', 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $assignment = HrShiftAssignment::query()->with('staff')->where('tenant_id', $tenant->id)->findOrFail($data['hr_shift_assignment_id']);
        abort_if($assignment->work_date->isFuture(), 422, 'Attendance cannot be recorded before the scheduled day.');
        abort_if(HrAttendanceRecord::query()->where('hr_shift_assignment_id', $assignment->id)->exists(), 422, 'This shift already has an attendance record.');

        HrAttendanceRecord::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $assignment->branch_id,
            'hr_staff_id' => $assignment->hr_staff_id,
            'hr_shift_assignment_id' => $assignment->id,
            'work_date' => $assignment->work_date->toDateString(),
            'clocked_in_at' => now(),
            'status' => 'present',
            'notes' => $data['notes'] ?? null,
            'recorded_by' => $user->id,
        ]);
        $assignment->update(['status' => 'in_progress']);

        return $this->backToWorkforce($tenant, (int) $assignment->branch_id, $assignment->staff->name.' clocked in.', $assignment->work_date->toDateString());
    }

    public function clockOut(Request $request, HrAttendanceRecord $attendance): RedirectResponse
    {
        [$tenant] = $this->context($request);
        abort_unless($attendance->tenant_id === $tenant->id, 404);
        abort_if(! $attendance->clocked_in_at || $attendance->clocked_out_at, 422, 'This attendance record cannot be clocked out.');
        $data = $request->validate(['break_minutes' => ['nullable', 'integer', 'min:0', 'max:1440']]);

        DB::transaction(function () use ($attendance, $data): void {
            $attendance->update(['clocked_out_at' => now(), 'break_minutes' => $data['break_minutes'] ?? 0]);
            $attendance->assignment?->update(['status' => 'completed']);
        });

        return $this->backToWorkforce($tenant, (int) $attendance->branch_id, 'Staff clocked out.', $attendance->work_date->toDateString());
    }

    private function validateShift(Request $request, Tenant $tenant, ?int $ignoreId = null): array
    {
        return $request->validate([
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('tenant_id', $tenant->id)],
            'name' => ['required', 'string', 'max:120', Rule::unique('hr_work_shifts')->where(fn ($q) => $q->where('tenant_id', $tenant->id)->where('branch_id', $request->integer('branch_id')))->ignore($ignoreId)],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'different:starts_at'],
            'colour' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function backToWorkforce(Tenant $tenant, int $branchId, string $message, ?string $date = null): RedirectResponse
    {
        return redirect()->to(route('admin.hr-payroll.workforce.index', [
            'tenant' => $tenant->id, 'branch' => $branchId, 'week' => $date,
        ]).'#rota')->with('status', $message);
    }

    /** @return array{Tenant, EloquentCollection<int, Tenant>, User} */
    private function context(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();
        $tenants = $user->is_platform_admin
            ? Tenant::query()->orderBy('name')->get()
            : Tenant::query()->whereHas('memberships', fn ($q) => $q->where('user_id', $user->id)->where('status', MembershipStatus::Active->value))->orderBy('name')->get();
        $tenantId = $request->string('tenant')->toString() ?: $request->string('tenant_id')->toString();
        $tenant = $tenantId !== '' ? $tenants->firstWhere('id', $tenantId) : $tenants->first();
        abort_if(! $tenant, 403);

        return [$tenant, $tenants, $user];
    }
}
