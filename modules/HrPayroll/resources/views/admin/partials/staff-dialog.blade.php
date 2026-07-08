@php
    $dialogId = $dialogId ?? 'staff-dialog';
    $selectedStaff = $selectedStaff ?? null;
@endphp

<dialog class="dialog" id="{{ $dialogId }}">
    <div class="dialog-header">
        <div>
            <h2 class="panel-title">{{ $selectedStaff ? 'Edit staff' : 'Add staff' }}</h2>
            <p class="subtle">Assign staff to a branch and capture expected monthly salary.</p>
        </div>
        <button class="icon-btn" type="button" data-dialog-close>&times;</button>
    </div>
    <div class="dialog-body">
        <form class="mini-form" method="POST" action="{{ $selectedStaff ? route('admin.hr-payroll.staff.update', $selectedStaff) : route('admin.hr-payroll.staff.store') }}">
            @csrf
            @if ($selectedStaff)
                @method('PUT')
            @endif
            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
            <div class="form-grid">
                <div class="field"><label>Staff number</label><input name="staff_number" value="{{ old('staff_number', $selectedStaff?->staff_number) }}" placeholder="Auto-generated if blank"></div>
                <div class="field"><label>Branch</label><select name="branch_id"><option value="">Unassigned</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected((int) old('branch_id', $selectedStaff ? $selectedStaff->branch_id : $activeBranchForView?->id) === $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
                <div class="field"><label>First name</label><input name="first_name" value="{{ old('first_name', $selectedStaff?->first_name) }}" required></div>
                <div class="field"><label>Last name</label><input name="last_name" value="{{ old('last_name', $selectedStaff?->last_name) }}" required></div>
                <div class="field"><label>Email</label><input name="email" type="email" value="{{ old('email', $selectedStaff?->email) }}"></div>
                <div class="field"><label>Phone</label><input name="phone" value="{{ old('phone', $selectedStaff?->phone) }}"></div>
                <div class="field"><label>Job title</label><input name="job_title" value="{{ old('job_title', $selectedStaff?->job_title) }}"></div>
                <div class="field"><label>Hire date</label><input name="hire_date" type="date" value="{{ old('hire_date', $selectedStaff?->hire_date?->toDateString()) }}"></div>
                <div class="field"><label>Monthly salary</label><input name="monthly_salary" inputmode="decimal" data-money-input value="{{ old('monthly_salary', $selectedStaff ? number_format($selectedStaff->monthly_salary_minor / 100, 2) : '') }}" required></div>
                <div class="field"><label>Status</label><select name="status"><option value="active" @selected(old('status', $selectedStaff?->status ?? 'active') === 'active')>Active</option><option value="inactive" @selected(old('status', $selectedStaff?->status) === 'inactive')>Inactive</option><option value="terminated" @selected(old('status', $selectedStaff?->status) === 'terminated')>Terminated</option></select></div>
                <div class="field full"><label>Address</label><textarea name="address">{{ old('address', $selectedStaff?->address) }}</textarea></div>
                <div class="field full"><label>Notes</label><textarea name="notes">{{ old('notes', $selectedStaff?->notes) }}</textarea></div>
            </div>
            <div class="button-row">
                <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                <button class="btn accent" type="submit">{{ $selectedStaff ? 'Save staff' : 'Create staff' }}</button>
            </div>
        </form>
    </div>
</dialog>
