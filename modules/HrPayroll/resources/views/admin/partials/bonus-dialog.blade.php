<dialog class="dialog" id="bonus-dialog">
    <div class="dialog-header">
        <div>
            <h2 class="panel-title">Add bonus</h2>
            <p class="subtle">Add a one-time payment that increases the employee's net pay for a payroll month.</p>
        </div>
        <button class="icon-btn" type="button" data-dialog-close>&times;</button>
    </div>
    <div class="dialog-body">
        <form class="mini-form" method="POST" action="{{ route('admin.hr-payroll.bonuses.store') }}">
            @csrf
            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
            <div class="form-grid">
                <div class="field"><label>Staff</label><select name="hr_staff_id" required>@foreach ($staff->where('status', 'active') as $employee)<option value="{{ $employee->id }}">{{ $employee->name }} · {{ $employee->branch?->name ?? 'Unassigned' }}</option>@endforeach</select></div>
                <div class="field"><label>Bonus type</label><select name="bonus_type"><option value="performance">Performance</option><option value="commission">Commission</option><option value="overtime">Overtime</option><option value="holiday">Holiday</option><option value="other">Other</option></select></div>
                <div class="field"><label>Payroll month</label><input name="bonus_month" type="month" value="{{ $payrollMonth }}" required></div>
                <div class="field"><label>Bonus date</label><input name="bonus_date" type="date" value="{{ now()->toDateString() }}" required></div>
                <div class="field"><label>Amount</label><input name="amount" inputmode="decimal" data-money-input required></div>
                <div class="field full"><label>Reason</label><textarea name="reason" placeholder="Why is this bonus being awarded?"></textarea></div>
            </div>
            <div class="button-row">
                <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                <button class="btn accent" type="submit">Add bonus</button>
            </div>
        </form>
    </div>
</dialog>
