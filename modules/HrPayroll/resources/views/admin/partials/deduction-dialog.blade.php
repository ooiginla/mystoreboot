<dialog class="dialog" id="deduction-dialog">
    <div class="dialog-header">
        <div>
            <h2 class="panel-title">Post deduction</h2>
            <p class="subtle">Post fines, salary advances, or other deductions against the expected monthly salary.</p>
        </div>
        <button class="icon-btn" type="button" data-dialog-close>&times;</button>
    </div>
    <div class="dialog-body">
        <form class="mini-form" method="POST" action="{{ route('admin.hr-payroll.deductions.store') }}">
            @csrf
            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
            <div class="form-grid">
                <div class="field"><label>Staff</label><select name="hr_staff_id" required>@foreach ($staff->where('status', 'active') as $employee)<option value="{{ $employee->id }}">{{ $employee->name }} · {{ $employee->branch?->name ?? 'Unassigned' }}</option>@endforeach</select></div>
                <div class="field"><label>Deduction type</label><select name="deduction_type"><option value="fine">Fine</option><option value="salary_advance">Salary advance</option><option value="other">Other</option></select></div>
                <div class="field"><label>Payroll month</label><input name="deduction_month" type="month" value="{{ $payrollMonth }}" required></div>
                <div class="field"><label>Deduction date</label><input name="deduction_date" type="date" value="{{ now()->toDateString() }}" required></div>
                <div class="field"><label>Amount</label><input name="amount" inputmode="decimal" data-money-input required></div>
                <div class="field full"><label>Reason</label><textarea name="reason"></textarea></div>
            </div>
            <div class="button-row">
                <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                <button class="btn accent" type="submit">Post deduction</button>
            </div>
        </form>
    </div>
</dialog>
