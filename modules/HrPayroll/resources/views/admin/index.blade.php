@php
    $money = fn (?int $minor): string => $tenant->currency_code.' '.number_format(($minor ?? 0) / 100, 2);
    $headline = fn (?string $value): string => \Illuminate\Support\Str::headline((string) $value);
@endphp

<x-layouts.admin title="HR & Payroll">
    <style>
        .payroll-filter { display: grid; grid-template-columns: minmax(180px, 260px) auto; gap: 10px; align-items: end; margin-bottom: 16px; }
        .payslip { border: 1px solid var(--line); border-radius: 8px; padding: 22px; background: #fff; display: grid; gap: 14px; }
        .payslip-head { display: flex; justify-content: space-between; gap: 16px; border-bottom: 1px solid var(--line); padding-bottom: 14px; }
        .payslip-title { margin: 0; color: var(--brand-dark); font-size: 22px; }
        @media print {
            body:has(dialog[open]) .sidebar, body:has(dialog[open]) .topbar, body:has(dialog[open]) .tab-layout, body:has(dialog[open]) .stats-grid { display: none; }
            dialog[open] { display: block; position: static; width: 100%; max-width: none; box-shadow: none; }
            dialog[open]::backdrop, dialog[open] .dialog-header .icon-btn, dialog[open] [data-print-dialog], dialog[open] [data-dialog-close] { display: none; }
            .dialog-body { max-height: none; overflow: visible; }
        }
        @media (max-width: 700px) { .payroll-filter { grid-template-columns: 1fr; } }
    </style>

    <div class="topbar">
        <div>
            <div class="eyebrow">Module 9</div>
            <h1>HR & Payroll</h1>
            <p class="subtle">Staff records, branch assignment, deductions, monthly salary schedules, payroll posting, and payslips for {{ $tenant->name }}.</p>
        </div>
        @if ($isPlatformAdmin)
            <form method="GET" action="{{ route('admin.hr-payroll.index') }}" style="min-width: 260px;">
                <input type="hidden" name="payroll_month" value="{{ $payrollMonth }}">
                <select name="tenant" onchange="this.form.submit()">
                    @foreach ($tenants as $visibleTenant)
                        <option value="{{ $visibleTenant->id }}" @selected($visibleTenant->id === $tenant->id)>{{ $visibleTenant->name }}</option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    @if (session('status'))
        <div class="alert">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert errors"><strong>Check the HR details.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="stats-grid" style="margin-bottom: 18px;">
        <div class="stat"><span class="subtle">Staff</span><strong>{{ $stats['staff'] }}</strong></div>
        <div class="stat"><span class="subtle">Active staff</span><strong>{{ $stats['active_staff'] }}</strong></div>
        <div class="stat"><span class="subtle">Gross salaries</span><strong>{{ $money($stats['gross_minor']) }}</strong></div>
        <div class="stat"><span class="subtle">Pending deductions</span><strong>{{ $money($stats['deduction_minor']) }}</strong></div>
    </div>

    <div class="tab-layout">
        <nav class="pill-nav" aria-label="HR and payroll sections" role="tablist">
            <a href="#staff" role="tab" data-tab-target="staff">Staff <span class="badge neutral">{{ $staff->count() }}</span></a>
            <a href="#deductions" role="tab" data-tab-target="deductions">Deductions <span class="badge neutral">{{ $deductions->where('status', 'pending')->count() }}</span></a>
            <a href="#salaries" role="tab" data-tab-target="salaries">Salaries</a>
            <a href="#payslips" role="tab" data-tab-target="payslips">Payslips</a>
        </nav>

        <div class="content-stack">
            <section class="panel tab-panel" id="staff" role="tabpanel" data-tab-panel>
                <div class="panel-header">
                    <div><h2 class="panel-title">Staff records</h2><p class="subtle">Create staff under branches and update staff branch, salary, and status.</p></div>
                    <button class="btn accent" type="button" data-dialog-open="staff-dialog">Add staff</button>
                </div>
                <div class="panel-body" style="overflow-x: auto;">
                    <table class="table">
                        <thead><tr><th>Staff</th><th>Branch</th><th>Role</th><th>Monthly salary</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($staff as $employee)
                                <tr>
                                    <td>{{ $employee->name }}<br><span class="subtle">{{ $employee->staff_number }} · {{ $employee->phone ?: 'No phone' }}</span></td>
                                    <td>{{ $employee->branch?->name ?? 'Unassigned' }}</td>
                                    <td>{{ $employee->job_title ?: 'Not set' }}</td>
                                    <td>{{ $money($employee->monthly_salary_minor) }}</td>
                                    <td><span class="badge {{ $employee->status === 'active' ? '' : 'neutral' }}">{{ $headline($employee->status) }}</span></td>
                                    <td><button class="btn secondary" type="button" data-dialog-open="staff-edit-{{ $employee->id }}">Edit</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><div class="empty">No staff records yet.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel tab-panel" id="deductions" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header">
                    <div><h2 class="panel-title">Staff deductions</h2><p class="subtle">Post fines, salary advances, and other deductions against a payroll month.</p></div>
                    <button class="btn accent" type="button" data-dialog-open="deduction-dialog">Post deduction</button>
                </div>
                <div class="panel-body" style="overflow-x: auto;">
                    <table class="table">
                        <thead><tr><th>Staff</th><th>Month</th><th>Date</th><th>Type</th><th>Amount</th><th>Status</th><th>Reason</th></tr></thead>
                        <tbody>
                            @forelse ($deductions as $deduction)
                                <tr><td>{{ $deduction->staff->name }}</td><td>{{ $deduction->deduction_month }}</td><td>{{ $deduction->deduction_date->format('M j, Y') }}</td><td>{{ $headline($deduction->deduction_type) }}</td><td>{{ $money($deduction->amount_minor) }}</td><td><span class="badge {{ $deduction->status === 'pending' ? 'neutral' : '' }}">{{ $headline($deduction->status) }}</span></td><td>{{ $deduction->reason ?: 'Not set' }}</td></tr>
                            @empty
                                <tr><td colspan="7"><div class="empty">No staff deductions yet.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel tab-panel" id="salaries" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header">
                    <div><h2 class="panel-title">Monthly salary schedule</h2><p class="subtle">Calculate gross salary, deductions, and net pay for all active staff before posting the month.</p></div>
                </div>
                <div class="panel-body">
                    <form class="payroll-filter" method="GET" action="{{ route('admin.hr-payroll.index') }}#salaries">
                        <input type="hidden" name="tenant" value="{{ $tenant->id }}">
                        <div class="field"><label>Payroll month</label><input type="month" name="payroll_month" value="{{ $payrollMonth }}" required></div>
                        <div class="button-row" style="margin-top: 0; justify-content: flex-start;"><button class="btn secondary" type="submit">Calculate</button></div>
                    </form>
                    <div style="overflow-x: auto;">
                        <table class="table">
                            <thead><tr><th>Staff</th><th>Branch</th><th>Gross salary</th><th>Deductions</th><th>Net salary</th><th>Deduction detail</th></tr></thead>
                            <tbody>
                                @forelse ($scheduleRows as $row)
                                    <tr>
                                        <td>{{ $row['staff']->name }}<br><span class="subtle">{{ $row['staff']->staff_number }}</span></td>
                                        <td>{{ $row['staff']->branch?->name ?? 'Unassigned' }}</td>
                                        <td>{{ $money($row['gross_minor']) }}</td>
                                        <td>{{ $money($row['deduction_minor']) }}</td>
                                        <td><strong>{{ $money($row['net_minor']) }}</strong></td>
                                        <td>@forelse ($row['deductions'] as $deduction)<div>{{ $headline($deduction->deduction_type) }}: {{ $money($deduction->amount_minor) }}</div>@empty<span class="subtle">No deductions</span>@endforelse</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6"><div class="empty">No active staff to calculate.</div></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <form method="POST" action="{{ route('admin.hr-payroll.payroll-runs.store') }}" style="margin-top: 16px;">
                        @csrf
                        <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                        <input type="hidden" name="payroll_month" value="{{ $payrollMonth }}">
                        <div class="form-grid">
                            <div class="field">
                                <label>Pay wages from</label>
                                <select name="funding_account_code" required>
                                    @foreach ($payrollFundingAccounts as $account)
                                        <option value="{{ $account->code }}" @selected(old('funding_account_code') === $account->code)>{{ $account->code }} · {{ $account->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field full"><label>Posting note</label><textarea name="notes" placeholder="Optional note for this monthly payroll">{{ old('notes') }}</textarea></div>
                            <div class="summary-item"><span>Overall total net</span><strong>{{ $money((int) $scheduleRows->sum('net_minor')) }}</strong></div>
                        </div>
                        <div class="button-row">
                            @if ($savedPayroll)
                                <span class="badge">Posted for {{ $payrollMonth }}</span>
                            @else
                                <button class="btn accent" type="submit" @disabled($scheduleRows->isEmpty())>Post Payroll for {{ $payrollMonth }}</button>
                            @endif
                        </div>
                    </form>
                    @if ($payrollRuns->isNotEmpty())
                        <h3 class="panel-title" style="margin-top: 22px;">Posted payroll months</h3>
                        <table class="table"><thead><tr><th>Month</th><th>Staff</th><th>Gross</th><th>Deductions</th><th>Net</th><th>Posted</th></tr></thead><tbody>@foreach ($payrollRuns as $run)<tr><td>{{ $run->payroll_month }}</td><td>{{ $run->items_count }}</td><td>{{ $money($run->gross_salary_minor) }}</td><td>{{ $money($run->deduction_minor) }}</td><td>{{ $money($run->net_salary_minor) }}</td><td>{{ $run->posted_at->format('M j, Y') }}</td></tr>@endforeach</tbody></table>
                    @endif
                </div>
            </section>

            <section class="panel tab-panel" id="payslips" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header"><div><h2 class="panel-title">Payslips</h2><p class="subtle">Generate an employee payslip from a saved payroll month.</p></div></div>
                <div class="panel-body" style="overflow-x: auto;">
                    <table class="table">
                        <thead><tr><th>Employee</th><th>Month</th><th>Branch</th><th>Gross</th><th>Deductions</th><th>Net</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($payslipItems as $item)
                                <tr><td>{{ $item->staff->name }}<br><span class="subtle">{{ $item->staff->staff_number }}</span></td><td>{{ $item->run->payroll_month }}</td><td>{{ $item->branch?->name ?? $item->staff->branch?->name ?? 'Unassigned' }}</td><td>{{ $money($item->gross_salary_minor) }}</td><td>{{ $money($item->deduction_minor) }}</td><td><strong>{{ $money($item->net_salary_minor) }}</strong></td><td><button class="btn secondary" type="button" data-dialog-open="payslip-{{ $item->id }}">Generate payslip</button></td></tr>
                            @empty
                                <tr><td colspan="7"><div class="empty">No saved payroll items yet.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    @include('hr-payroll::admin.partials.staff-dialog')
    @foreach ($staff as $employee)
        @include('hr-payroll::admin.partials.staff-dialog', ['dialogId' => 'staff-edit-'.$employee->id, 'selectedStaff' => $employee])
    @endforeach
    @include('hr-payroll::admin.partials.deduction-dialog')
    @foreach ($payslipItems as $item)
        @include('hr-payroll::admin.partials.payslip-dialog', ['item' => $item])
    @endforeach
</x-layouts.admin>
