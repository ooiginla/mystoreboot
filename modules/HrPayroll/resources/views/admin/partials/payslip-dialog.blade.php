@php
    $money = fn (?int $minor): string => $tenant->currency_code.' '.number_format(($minor ?? 0) / 100, 2);
    $headline = fn (?string $value): string => \Illuminate\Support\Str::headline((string) $value);
@endphp

<dialog class="dialog" id="payslip-{{ $item->id }}">
    <div class="dialog-header">
        <div>
            <h2 class="panel-title">Employee payslip</h2>
            <p class="subtle">{{ $item->staff->name }} · {{ $item->run->payroll_month }}</p>
        </div>
        <div style="display: flex; gap: 8px;">
            <button class="btn secondary" type="button" data-print-dialog>Print</button>
            <button class="icon-btn" type="button" data-dialog-close>&times;</button>
        </div>
    </div>
    <div class="dialog-body">
        <div class="payslip">
            <div class="payslip-head">
                <div>
                    <h3 class="payslip-title">Payslip</h3>
                    <div class="subtle">{{ $tenant->name }}</div>
                </div>
                <div style="text-align: right;">
                    <strong>{{ $item->run->payroll_month }}</strong><br>
                    <span class="subtle">Posted {{ $item->run->posted_at->format('M j, Y') }}</span>
                </div>
            </div>
            <div class="summary-grid">
                <div class="summary-item"><span>Employee</span><strong>{{ $item->staff->name }}</strong></div>
                <div class="summary-item"><span>Staff number</span><strong>{{ $item->staff->staff_number }}</strong></div>
                <div class="summary-item"><span>Branch</span><strong>{{ $item->branch?->name ?? $item->staff->branch?->name ?? 'Unassigned' }}</strong></div>
            </div>
            <table class="table">
                <tbody>
                    <tr><th>Gross salary</th><td>{{ $money($item->gross_salary_minor) }}</td></tr>
                    <tr><th>Deductions</th><td>{{ $money($item->deduction_minor) }}</td></tr>
                    <tr><th>Net salary</th><td><strong>{{ $money($item->net_salary_minor) }}</strong></td></tr>
                </tbody>
            </table>
            <table class="table">
                <thead><tr><th>Deduction</th><th>Reason</th><th>Amount</th></tr></thead>
                <tbody>
                    @forelse (($item->deduction_breakdown ?? []) as $deduction)
                        <tr><td>{{ $headline($deduction['type'] ?? '') }}</td><td>{{ $deduction['reason'] ?? 'Not set' }}</td><td>{{ $money($deduction['amount_minor'] ?? 0) }}</td></tr>
                    @empty
                        <tr><td colspan="3"><div class="empty">No deductions for this payroll item.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</dialog>
