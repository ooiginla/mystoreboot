@php
    $money = fn (?int $minor): string => $tenant->currency_code.' '.number_format(($minor ?? 0) / 100, 2);
    $activeReport = $reports[$selectedReport] ?? 'Financial report';
    $headline = fn (?string $value): string => \Illuminate\Support\Str::headline((string) $value);
@endphp

<x-layouts.admin title="Report">
    <style>
        .report-filter { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)) auto; gap: 10px; align-items: end; margin-bottom: 18px; }
        .report-menu { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
        .report-card-link { border: 1px solid var(--line); border-radius: 8px; background: #fff; padding: 14px; display: flex; justify-content: space-between; gap: 12px; align-items: center; font-weight: 850; color: #344054; }
        .report-card-link:hover, .report-card-link.active { border-color: var(--brand); color: var(--brand-dark); box-shadow: 0 0 0 3px rgba(15,118,110,.12); }
        .report-card-link.active { background: #f0fdfa; }
        .report-arrow { color: var(--muted); font-size: 18px; }
        .report-heading { display: flex; justify-content: space-between; gap: 16px; align-items: start; margin-bottom: 16px; }
        .report-lines { display: grid; gap: 10px; }
        .report-line { border: 1px solid var(--line); border-radius: 8px; background: #fff; padding: 12px 14px; display: flex; justify-content: space-between; gap: 12px; }
        .report-line.total { background: #f0fdfa; border-color: #99f6e4; color: var(--brand-dark); font-weight: 900; }
        .report-line.warning { background: #fffaeb; border-color: #fedf89; color: #93370d; font-weight: 900; }
        .report-line.danger { background: #fef3f2; border-color: #fecdca; color: #b42318; font-weight: 900; }
        .report-table-wrap { overflow-x: auto; }
        @media (max-width: 1100px) { .report-menu { grid-template-columns: repeat(2, minmax(0, 1fr)); } .report-filter { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 700px) { .report-menu, .report-filter { grid-template-columns: 1fr; } .report-heading { display: grid; } }
    </style>

    <div class="topbar">
        <div>
            <div class="eyebrow">Finance, accounting & expense management</div>
            <h1>Report</h1>
            <p class="subtle">Generate financial reports for income, expenses, receivables, payables, profitability, cash flow, and balance sheet for {{ $tenant->name }}.</p>
        </div>
        @if ($isPlatformAdmin)
            <form method="GET" action="{{ route('admin.finance.index') }}" style="min-width: 260px;">
                <input type="hidden" name="report" value="{{ $selectedReport }}">
                <input type="hidden" name="date_from" value="{{ $dateFrom }}">
                <input type="hidden" name="date_to" value="{{ $dateTo }}">
                <input type="hidden" name="branch_id" value="{{ $selectedBranchId }}">
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
        <div class="alert errors"><strong>Check the finance details.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="stats-grid" style="margin-bottom: 18px;">
        <div class="stat"><span class="subtle">Revenue</span><strong>{{ $money($summary['revenue_minor']) }}</strong></div>
        <div class="stat"><span class="subtle">Expenses</span><strong>{{ $money($summary['expense_minor']) }}</strong></div>
        <div class="stat"><span class="subtle">Gross profit</span><strong>{{ $money($summary['gross_profit_minor']) }}</strong></div>
        <div class="stat"><span class="subtle">{{ $selectedBranch ? 'Branch' : 'Branch scope' }}</span><strong>{{ $selectedBranch?->name ?? 'All branches' }}</strong></div>
    </div>

    <div class="stack">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Financial Reports</h2>
                    <p class="subtle">Click a report to generate it for the selected period.</p>
                </div>
            </div>
            <div class="panel-body">
                <form class="report-filter" method="GET" action="{{ route('admin.finance.reports.show', ['report' => $selectedReport]) }}" target="_blank" data-report-generator>
                    <input type="hidden" name="tenant" value="{{ $tenant->id }}">
                    <div class="field"><label>From</label><input name="date_from" type="date" value="{{ $dateFrom }}"></div>
                    <div class="field"><label>To</label><input name="date_to" type="date" value="{{ $dateTo }}"></div>
                    <div class="field">
                        <label>Branch</label>
                        <select name="branch_id">
                            <option value="">All branches</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected((string) $branch->id === $selectedBranchId)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field"><label>Generated report</label><select name="report_choice" data-report-choice>@foreach ($reports as $key => $label)<option value="{{ $key }}" @selected($key === $selectedReport)>{{ $label }}</option>@endforeach</select></div>
                    <div class="button-row" style="margin-top: 0; justify-content: flex-start;"><button class="btn secondary" type="submit">Generate</button></div>
                </form>

                <div class="report-menu">
                    @foreach ($reports as $key => $label)
                        <a class="report-card-link {{ $key === $selectedReport ? 'active' : '' }}" target="_blank" rel="noopener" href="{{ route('admin.finance.reports.show', ['report' => $key, 'tenant' => $tenant->id, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'branch_id' => $selectedBranchId]) }}">
                            <span>{{ $label }}</span>
                            <span class="report-arrow">›</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-body">
                <div class="empty">Reports open in a new tab with export and print actions.</div>
            </div>
        </section>
    </div>

    <script>
        document.addEventListener('submit', (event) => {
            const form = event.target.closest('[data-report-generator]');

            if (!form) return;

            const choice = form.querySelector('[data-report-choice]');
            const report = choice?.value || 'expense';
            form.action = "{{ route('admin.finance.reports.show', ['report' => '__REPORT__']) }}".replace('__REPORT__', encodeURIComponent(report));
        });
    </script>
</x-layouts.admin>
