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
                <form class="report-filter" method="GET" action="{{ route('admin.finance.index') }}">
                    <input type="hidden" name="tenant" value="{{ $tenant->id }}">
                    <input type="hidden" name="report" value="{{ $selectedReport }}">
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
                    <div class="field"><label>Generated report</label><select name="report">@foreach ($reports as $key => $label)<option value="{{ $key }}" @selected($key === $selectedReport)>{{ $label }}</option>@endforeach</select></div>
                    <div class="button-row" style="margin-top: 0; justify-content: flex-start;"><button class="btn secondary" type="submit">Generate</button></div>
                </form>

                <div class="report-menu">
                    @foreach ($reports as $key => $label)
                        <a class="report-card-link {{ $key === $selectedReport ? 'active' : '' }}" href="{{ route('admin.finance.index', ['tenant' => $tenant->id, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'branch_id' => $selectedBranchId, 'report' => $key]) }}">
                            <span>{{ $label }}</span>
                            <span class="report-arrow">›</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">{{ $activeReport }}</h2>
                    <p class="subtle">{{ \Carbon\CarbonImmutable::parse($dateFrom)->format('M j, Y') }} to {{ \Carbon\CarbonImmutable::parse($dateTo)->format('M j, Y') }}</p>
                </div>
            </div>
            <div class="panel-body">
                @if ($selectedReport === 'profit-loss')
                    <div class="report-lines">
                        <div class="report-line"><span>Revenue</span><strong>{{ $money($summary['revenue_minor']) }}</strong></div>
                        <div class="report-line danger"><span>Sales returns and refunds</span><strong>-{{ $money($summary['refund_minor']) }}</strong></div>
                        <div class="report-line danger"><span>Cost of goods sold</span><strong>-{{ $money($summary['cogs_minor']) }}</strong></div>
                        <div class="report-line total"><span>Gross profit</span><strong>{{ $money($summary['gross_profit_minor']) }}</strong></div>
                        <div class="report-line danger"><span>Operating expenses</span><strong>-{{ $money($summary['expense_minor']) }}</strong></div>
                        <div class="report-line total"><span>Net profit</span><strong>{{ $money($summary['net_profit_minor']) }}</strong></div>
                    </div>
                @elseif ($selectedReport === 'cash-flow')
                    <div class="report-lines" style="margin-bottom: 18px;">
                        <div class="report-line total"><span>Cash inflow from customers</span><strong>{{ $money($summary['cash_in_minor']) }}</strong></div>
                        <div class="report-line danger"><span>Cash outflow to vendors</span><strong>-{{ $money($summary['cash_out_minor']) }}</strong></div>
                        <div class="report-line total"><span>Net cash flow</span><strong>{{ $money($summary['net_cash_flow_minor']) }}</strong></div>
                    </div>
                    <div class="report-table-wrap">
                        <table class="table">
                            <thead><tr><th>Date</th><th>Type</th><th>Party</th><th>Reference</th><th>Amount</th></tr></thead>
                            <tbody>
                                @forelse ($salesPayments->map(fn ($payment) => ['date' => $payment->payment_date, 'type' => 'Cash in', 'party' => $payment->order?->customer?->name ?? 'Customer', 'reference' => $payment->reference_number ?: $payment->order?->order_number, 'amount' => $payment->amount_minor])->merge($vendorPayments->map(fn ($payment) => ['date' => $payment->payment_date, 'type' => 'Cash out', 'party' => $payment->vendor?->name ?? 'Vendor', 'reference' => $payment->reference_number ?: $payment->purchaseOrder?->po_number, 'amount' => -$payment->amount_minor]))->sortByDesc('date') as $row)
                                    <tr><td>{{ $row['date']->format('M j, Y') }}</td><td>{{ $row['type'] }}</td><td>{{ $row['party'] }}</td><td>{{ $row['reference'] ?: 'Not set' }}</td><td>{{ $money($row['amount']) }}</td></tr>
                                @empty
                                    <tr><td colspan="5"><div class="empty">No cash movement for this period.</div></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @elseif ($selectedReport === 'revenue')
                    <div class="report-table-wrap">
                        <table class="table">
                            <thead><tr><th>Order</th><th>Date</th><th>Customer</th><th>Branch</th><th>Total</th><th>Paid</th><th>Receivable</th></tr></thead>
                            <tbody>
                                @forelse ($orders as $order)
                                    <tr><td>{{ $order->order_number }}</td><td>{{ $order->order_date->format('M j, Y') }}</td><td>{{ $order->customer?->name ?? 'Walk-In' }}</td><td>{{ $order->branch?->name ?? 'Unassigned' }}</td><td>{{ $money($order->total_minor) }}</td><td>{{ $money($order->paid_minor) }}</td><td>{{ $money($order->balance_minor) }}</td></tr>
                                @empty
                                    <tr><td colspan="7"><div class="empty">No revenue records for this period.</div></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @elseif ($selectedReport === 'expense')
                    <div class="report-table-wrap">
                        <table class="table">
                            <thead><tr><th>Record</th><th>Date</th><th>Payee / vendor</th><th>Status</th><th>Total</th><th>Paid</th><th>Payable</th></tr></thead>
                            <tbody>
                                @foreach ($operationalExpenses as $expense)
                                    <tr><td>{{ $expense->expense_number }}<br><span class="subtle">{{ $expense->category->name }}</span></td><td>{{ $expense->expense_date->format('M j, Y') }}</td><td>{{ $expense->payee_name ?: 'Not set' }}</td><td>{{ $headline($expense->payment_status) }}</td><td>{{ $money($expense->amount_minor) }}</td><td>{{ $money($expense->paid_minor) }}</td><td>{{ $money(max(0, $expense->amount_minor - $expense->paid_minor)) }}</td></tr>
                                @endforeach
                                @forelse ($purchaseOrders as $purchaseOrder)
                                    <tr><td>{{ $purchaseOrder->po_number }}</td><td>{{ $purchaseOrder->order_date->format('M j, Y') }}</td><td>{{ $purchaseOrder->vendor?->name ?? 'Vendor' }}</td><td>{{ $purchaseOrder->status->label() }}</td><td>{{ $money($purchaseOrder->total_minor) }}</td><td>{{ $money($purchaseOrder->paid_minor) }}</td><td>{{ $money($purchaseOrder->balance_minor) }}</td></tr>
                                @empty
                                    @if ($operationalExpenses->isEmpty())<tr><td colspan="7"><div class="empty">No expense records for this period.</div></td></tr>@endif
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @elseif ($selectedReport === 'gross-profit')
                    <div class="report-lines">
                        <div class="report-line"><span>Revenue less refunds</span><strong>{{ $money($summary['revenue_minor'] - $summary['refund_minor']) }}</strong></div>
                        <div class="report-line danger"><span>Cost of goods sold</span><strong>-{{ $money($summary['cogs_minor']) }}</strong></div>
                        <div class="report-line total"><span>Gross profit</span><strong>{{ $money($summary['gross_profit_minor']) }}</strong></div>
                    </div>
                @elseif ($selectedReport === 'net-profit')
                    <div class="report-lines">
                        <div class="report-line total"><span>Gross profit</span><strong>{{ $money($summary['gross_profit_minor']) }}</strong></div>
                        <div class="report-line danger"><span>Expenses</span><strong>-{{ $money($summary['expense_minor']) }}</strong></div>
                        <div class="report-line total"><span>Net profit</span><strong>{{ $money($summary['net_profit_minor']) }}</strong></div>
                    </div>
                @elseif ($selectedReport === 'balance-sheet')
                    <div class="summary-grid">
                        <div class="summary-item"><span>Cash</span><strong>{{ $money($summary['cash_minor']) }}</strong></div>
                        <div class="summary-item"><span>Petty cash</span><strong>{{ $money($summary['petty_cash_minor']) }}</strong></div>
                        <div class="summary-item"><span>Accounts receivable</span><strong>{{ $money($summary['accounts_receivable_minor']) }}</strong></div>
                        <div class="summary-item"><span>Inventory value</span><strong>{{ $money($summary['inventory_value_minor']) }}</strong></div>
                        <div class="summary-item"><span>Total assets</span><strong>{{ $money($summary['assets_minor']) }}</strong></div>
                        <div class="summary-item"><span>Accounts payable</span><strong>{{ $money($summary['accounts_payable_minor']) }}</strong></div>
                        <div class="summary-item"><span>Owner equity</span><strong>{{ $money($summary['equity_minor']) }}</strong></div>
                    </div>
                @elseif ($selectedReport === 'branch-profitability')
                    <div class="report-table-wrap">
                        <table class="table">
                            <thead><tr><th>Branch</th><th>Orders</th><th>Revenue</th><th>Refunds</th><th>COGS</th><th>Profit</th></tr></thead>
                            <tbody>
                                @forelse ($branchProfitability as $row)
                                    <tr><td>{{ $row['name'] }}</td><td>{{ $row['orders'] }}</td><td>{{ $money($row['revenue_minor']) }}</td><td>{{ $money($row['refund_minor']) }}</td><td>{{ $money($row['cogs_minor']) }}</td><td><strong>{{ $money($row['profit_minor']) }}</strong></td></tr>
                                @empty
                                    <tr><td colspan="6"><div class="empty">No branch profitability data for this period.</div></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @elseif ($selectedReport === 'product-profitability')
                    <div class="report-table-wrap">
                        <table class="table">
                            <thead><tr><th>Product</th><th>SKU</th><th>Qty sold</th><th>Revenue</th><th>COGS</th><th>Profit</th></tr></thead>
                            <tbody>
                                @forelse ($productProfitability as $row)
                                    <tr><td>{{ $row['name'] }}</td><td>{{ $row['sku'] ?: 'Not set' }}</td><td>{{ $row['quantity'] }}</td><td>{{ $money($row['revenue_minor']) }}</td><td>{{ $money($row['cogs_minor']) }}</td><td><strong>{{ $money($row['profit_minor']) }}</strong></td></tr>
                                @empty
                                    <tr><td colspan="6"><div class="empty">No product profitability data for this period.</div></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>
    </div>
</x-layouts.admin>
