@php
    $days = collect(range(0, 6))->map(fn ($offset) => $weekStart->addDays($offset));
    $attendanceByAssignment = $attendance->keyBy('hr_shift_assignment_id');
@endphp

<x-layouts.admin title="Workforce Scheduling">
    <style>
        .week-grid { display:grid; grid-template-columns:180px repeat(7,minmax(135px,1fr)); min-width:1130px; }
        .week-grid > div { border-right:1px solid var(--line); border-bottom:1px solid var(--line); padding:10px; }
        .week-head { background:var(--panel-soft); font-weight:750; }
        .rota-card { border-left:4px solid var(--shift-colour); background:#fff; padding:8px; border-radius:7px; margin-bottom:7px; box-shadow:var(--shadow-sm); }
        .rota-card form { margin-top:6px; }
        .inline-actions { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
    </style>
    <div class="topbar">
        <div><div class="eyebrow">HR &amp; Payroll</div><h1>Shifts, rota &amp; attendance</h1><p class="subtle">Plan who works when and capture actual clock-in and clock-out for {{ $branches->firstWhere('id', $branchId)?->name }}.</p></div>
        <a class="btn secondary" href="{{ route('admin.hr-payroll.index', ['tenant' => $tenant->id]) }}">Staff &amp; payroll</a>
    </div>
    @if(session('status'))<div class="alert">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="alert errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <form method="GET" action="{{ route('admin.hr-payroll.workforce.index') }}" class="form-grid" style="margin-bottom:16px;">
        <input type="hidden" name="tenant" value="{{ $tenant->id }}">
        <div class="field"><label>Branch</label><select name="branch">@foreach($branches as $b)<option value="{{ $b->id }}" @selected($branchId === $b->id)>{{ $b->name }}</option>@endforeach</select></div>
        <div class="field"><label>Week containing</label><input type="date" name="week" value="{{ $weekStart->toDateString() }}"></div>
        <div class="field" style="align-self:end;"><button class="btn primary" type="submit">Show week</button></div>
    </form>

    <div class="tab-layout">
        <nav class="pill-nav" role="tablist">
            <a href="#rota" role="tab" data-tab-target="rota">Rota &amp; attendance</a>
            <a href="#shifts" role="tab" data-tab-target="shifts">Shift setup <span class="badge neutral">{{ $shifts->count() }}</span></a>
        </nav>
        <div class="content-stack">
            <section class="panel tab-panel" id="rota" data-tab-panel>
                <div class="panel-header"><div><h2 class="panel-title">{{ $weekStart->format('M j') }} – {{ $weekEnd->format('M j, Y') }}</h2><p class="subtle">Attendance buttons become available on the scheduled day.</p></div><button class="btn accent" type="button" data-dialog-open="rota-dialog">Assign rota</button></div>
                <div class="panel-body" style="overflow-x:auto; padding:0;">
                    <div class="week-grid">
                        <div class="week-head">Staff</div>@foreach($days as $day)<div class="week-head">{{ $day->format('D') }}<br><span class="subtle">{{ $day->format('M j') }}</span></div>@endforeach
                        @forelse($staff as $employee)
                            <div><strong>{{ $employee->name }}</strong><br><span class="subtle">{{ $employee->job_title ?: $employee->staff_number }}</span></div>
                            @foreach($days as $day)
                                <div>
                                    @php
                                        $rows = $assignments->where('hr_staff_id', $employee->id)->filter(fn($row) => $row->work_date->isSameDay($day));
                                    @endphp
                                    @forelse($rows as $row)
                                        @php $att = $attendanceByAssignment->get($row->id); @endphp
                                        <div class="rota-card" style="--shift-colour:{{ $row->shift->colour }}">
                                            <strong>{{ $row->shift->name }}</strong><div class="subtle">{{ substr($row->shift->starts_at,0,5) }}–{{ substr($row->shift->ends_at,0,5) }}</div>
                                            @if($att)
                                                <div class="subtle">In: {{ $att->clocked_in_at?->format('H:i') }} · Out: {{ $att->clocked_out_at?->format('H:i') ?? '—' }}</div>
                                                @if(!$att->clocked_out_at)
                                                    <form method="POST" action="{{ route('admin.hr-payroll.workforce.attendance.clock-out', $att) }}">@csrf<input type="hidden" name="tenant" value="{{ $tenant->id }}"><button class="btn primary" type="submit" style="padding:4px 8px;">Clock out</button></form>
                                                @endif
                                            @elseif($day->lte(today()))
                                                <form method="POST" action="{{ route('admin.hr-payroll.workforce.attendance.clock-in') }}">@csrf<input type="hidden" name="tenant" value="{{ $tenant->id }}"><input type="hidden" name="hr_shift_assignment_id" value="{{ $row->id }}"><button class="btn accent" type="submit" style="padding:4px 8px;">Clock in</button></form>
                                            @endif
                                            @if(!$att)<form method="POST" action="{{ route('admin.hr-payroll.workforce.rota.destroy', $row) }}">@csrf @method('DELETE')<input type="hidden" name="tenant" value="{{ $tenant->id }}"><button class="btn ghost" type="submit" style="padding:3px 7px;">Remove</button></form>@endif
                                        </div>
                                    @empty<span class="subtle">—</span>@endforelse
                                </div>
                            @endforeach
                        @empty
                            <div style="grid-column:1/-1;" class="empty">No active staff assigned to this branch.</div>
                        @endforelse
                    </div>
                </div>
            </section>
            <section class="panel tab-panel" id="shifts" data-tab-panel hidden>
                <div class="panel-header"><div><h2 class="panel-title">Shift definitions</h2><p class="subtle">Reusable working hours for rota and production planning.</p></div><button class="btn accent" type="button" data-dialog-open="shift-dialog">Add shift</button></div>
                <div class="panel-body"><table class="table"><thead><tr><th>Shift</th><th>Hours</th><th>Status</th></tr></thead><tbody>@forelse($shifts as $shift)<tr><td><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:{{ $shift->colour }}"></span> {{ $shift->name }}</td><td>{{ substr($shift->starts_at,0,5) }}–{{ substr($shift->ends_at,0,5) }}</td><td><span class="badge {{ $shift->is_active ? '' : 'neutral' }}">{{ $shift->is_active ? 'Active' : 'Inactive' }}</span></td></tr>@empty<tr><td colspan="3"><div class="empty">Create the first shift.</div></td></tr>@endforelse</tbody></table></div>
            </section>
        </div>
    </div>

    <dialog class="dialog" id="shift-dialog"><div class="dialog-header"><h2 class="panel-title">Add work shift</h2><button class="icon-btn" type="button" data-dialog-close>✕</button></div><div class="dialog-body"><form class="mini-form" method="POST" action="{{ route('admin.hr-payroll.workforce.shifts.store') }}">@csrf<input type="hidden" name="tenant" value="{{ $tenant->id }}"><input type="hidden" name="branch_id" value="{{ $branchId }}"><input type="hidden" name="is_active" value="1"><div class="form-grid"><div class="field full"><label>Name</label><input name="name" required placeholder="Morning shift"></div><div class="field"><label>Starts</label><input name="starts_at" type="time" required></div><div class="field"><label>Ends</label><input name="ends_at" type="time" required></div><div class="field"><label>Colour</label><input name="colour" type="color" value="#027a45"></div></div><div class="dialog-actions"><button class="btn" type="button" data-dialog-close>Cancel</button><button class="btn primary">Save shift</button></div></form></div></dialog>
    <dialog class="dialog" id="rota-dialog"><div class="dialog-header"><h2 class="panel-title">Assign staff to rota</h2><button class="icon-btn" type="button" data-dialog-close>✕</button></div><div class="dialog-body"><form class="mini-form" method="POST" action="{{ route('admin.hr-payroll.workforce.rota.store') }}">@csrf<input type="hidden" name="tenant" value="{{ $tenant->id }}"><input type="hidden" name="branch_id" value="{{ $branchId }}"><div class="form-grid"><div class="field"><label>Shift</label><select name="hr_work_shift_id" required><option value="">Select</option>@foreach($shifts->where('is_active',true) as $shift)<option value="{{ $shift->id }}">{{ $shift->name }} · {{ substr($shift->starts_at,0,5) }}</option>@endforeach</select></div><div class="field full"><label>Staff</label>@foreach($staff as $employee)<label class="inline-check"><input type="checkbox" name="hr_staff_ids[]" value="{{ $employee->id }}"> {{ $employee->name }}</label>@endforeach</div><div class="field full"><label>Days</label>@foreach($days as $day)<label class="inline-check"><input type="checkbox" name="work_dates[]" value="{{ $day->toDateString() }}"> {{ $day->format('D, M j') }}</label>@endforeach</div><div class="field full"><label>Notes</label><textarea name="notes"></textarea></div></div><div class="dialog-actions"><button class="btn" type="button" data-dialog-close>Cancel</button><button class="btn primary">Save rota</button></div></form></div></dialog>
</x-layouts.admin>
