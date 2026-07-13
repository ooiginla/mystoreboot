@php
    /** @var \Closure $money */
    /** @var \Closure $statusLabel */
    $period = $dateFrom->format('M j, Y').' - '.$dateTo->format('M j, Y');
    $downloadQuery = fn (string $format): array => array_merge($query, ['format' => $format]);
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Expense Report</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #121a2b;
            --body: #2d3340;
            --muted: #7b818d;
            --line: #eceff3;
            --line-strong: #d8dde5;
            --fill: #f5f6f8;
            --paper: #ffffff;
            --brand: #0f766e;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #eef1f5;
            color: var(--body);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.45;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 14px 24px;
            background: rgba(255, 255, 255, .94);
            border-bottom: 1px solid var(--line-strong);
            box-shadow: 0 8px 24px rgba(15, 23, 42, .08);
            backdrop-filter: blur(10px);
        }

        .toolbar strong { color: var(--ink); font-size: 15px; }
        .toolbar-actions { display: flex; flex-wrap: wrap; gap: 8px; }
        .action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 36px;
            border: 1px solid var(--line-strong);
            border-radius: 7px;
            background: #fff;
            color: #263142;
            padding: 8px 12px;
            font-weight: 700;
            font-size: 13px;
            text-decoration: none;
            cursor: pointer;
        }
        .action.primary { background: var(--brand); border-color: var(--brand); color: #fff; }

        .sheet {
            width: min(1180px, calc(100% - 32px));
            margin: 28px auto;
            padding: 30px 28px 34px;
            background: var(--paper);
            box-shadow: 0 16px 42px rgba(15, 23, 42, .10);
        }

        .report-top {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            align-items: start;
            margin-bottom: 42px;
        }

        h1 {
            margin: 0;
            color: var(--ink);
            font-size: 42px;
            line-height: 1.04;
            font-weight: 500;
            letter-spacing: 0;
        }

        .currency {
            color: var(--muted);
            font-size: 15px;
            font-weight: 700;
            white-space: nowrap;
        }

        .info-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(0, .9fr);
            gap: 16px;
            margin-bottom: 44px;
        }

        .section-title {
            margin: 0 0 10px;
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .02em;
        }

        .detail-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .detail-table th,
        .detail-table td {
            border-bottom: 1px solid var(--line);
            padding: 7px 14px;
            text-align: left;
            font-size: 15px;
            font-weight: 500;
            overflow-wrap: anywhere;
        }

        .detail-table th {
            width: 34%;
            color: #3a4350;
            background: #fff;
        }

        .detail-table td {
            background: var(--fill);
            color: #232b38;
        }

        .report-section { margin-top: 38px; }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .report-table th,
        .report-table td {
            border-bottom: 1px solid var(--line);
            padding: 8px 14px;
            text-align: left;
            vertical-align: top;
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        .report-table th {
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
        }

        .report-table tbody tr:nth-child(odd) td { background: var(--fill); }
        .report-table .money,
        .report-table .status,
        .summary-table .money { text-align: right; white-space: nowrap; }
        .report-table .status { color: #394150; }
        .report-table tfoot td {
            background: #fff;
            border-top: 2px solid var(--line-strong);
            font-weight: 800;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .summary-table th,
        .summary-table td {
            border-bottom: 1px solid var(--line);
            padding: 9px 14px;
            text-align: left;
            font-size: 14px;
        }

        .summary-table th {
            color: var(--muted);
            font-weight: 700;
        }

        .summary-table tfoot td {
            border-top: 2px solid var(--line-strong);
            font-weight: 800;
        }

        .total-band {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            margin-top: 28px;
            background: var(--fill);
            border: 1px solid #f0f1f4;
        }

        .total-cell {
            min-height: 128px;
            display: grid;
            place-items: center;
            gap: 8px;
            padding: 18px 16px;
            text-align: center;
            border-right: 1px solid #e8ebef;
        }

        .total-cell:last-child { border-right: 0; }
        .total-cell span {
            color: var(--muted);
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .total-cell strong {
            color: var(--ink);
            font-size: 34px;
            line-height: 1;
            font-weight: 500;
            letter-spacing: 0;
        }

        .empty {
            padding: 22px;
            text-align: center;
            color: var(--muted);
            background: var(--fill);
        }

        @media (max-width: 850px) {
            .toolbar { align-items: stretch; flex-direction: column; }
            .toolbar-actions { width: 100%; }
            .action { flex: 1 1 120px; }
            .info-grid, .total-band { grid-template-columns: 1fr; }
            .total-cell { border-right: 0; border-bottom: 1px solid #e8ebef; }
            .total-cell:last-child { border-bottom: 0; }
            .sheet { width: 100%; margin: 0; padding: 24px 16px; box-shadow: none; }
            h1 { font-size: 34px; }
            .report-table { min-width: 900px; }
            .table-scroll { overflow-x: auto; }
        }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { width: 100%; margin: 0; padding: 0; box-shadow: none; }
            @page { margin: 16mm 12mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar" aria-label="Expense report actions">
        <strong>Expense Report</strong>
        <div class="toolbar-actions">
            <a class="action primary" href="{{ route('admin.finance.reports.download', ['report' => 'expense'] + $downloadQuery('pdf')) }}">Download PDF</a>
            <a class="action" href="{{ route('admin.finance.reports.download', ['report' => 'expense'] + $downloadQuery('excel')) }}">Download Excel</a>
            <a class="action" href="{{ route('admin.finance.reports.download', ['report' => 'expense'] + $downloadQuery('word')) }}">Download Word</a>
            <button class="action" type="button" onclick="window.print()">Print</button>
        </div>
    </div>

    <main class="sheet">
        <div class="report-top">
            <h1>Expense Report</h1>
            <div class="currency">Currency: {{ $tenant->currency_code }}</div>
        </div>

        <section class="info-grid" aria-label="Report information">
            <div>
                <h2 class="section-title">Company / Branch Information</h2>
                <table class="detail-table">
                    <tbody>
                        <tr><th>Company name</th><td>{{ $tenant->name }}</td></tr>
                        <tr><th>Branch</th><td>{{ $selectedBranch?->name ?? 'All branches' }}</td></tr>
                        <tr><th>Phone</th><td>{{ $selectedBranch?->phone ?: ($tenant->phone ?: 'Not set') }}</td></tr>
                        <tr><th>Email</th><td>{{ $selectedBranch?->email ?: ($tenant->email ?: 'Not set') }}</td></tr>
                        <tr><th>Address</th><td>{{ $selectedBranch?->address ?: ($tenant->address ?: 'Not set') }}</td></tr>
                    </tbody>
                </table>
            </div>
            <div>
                <h2 class="section-title">Report Details</h2>
                <table class="detail-table">
                    <tbody>
                        <tr><th>Report #</th><td>{{ $reportNumber }}</td></tr>
                        <tr><th>Generate date</th><td>{{ $generatedAt->format('F j, Y') }}</td></tr>
                        <tr><th>Period</th><td>{{ $period }}</td></tr>
                        <tr><th>Scope</th><td>{{ $selectedBranch?->name ?? 'All branches' }}</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="report-section">
            <h2 class="section-title">Expense Details</h2>
            <div class="table-scroll">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th style="width: 10%;">Date</th>
                            <th style="width: 14%;">Category</th>
                            <th style="width: 24%;">Description</th>
                            <th style="width: 15%;">Payee</th>
                            <th style="width: 11%;" class="money">Amount</th>
                            <th style="width: 10%;" class="money">Paid</th>
                            <th style="width: 10%;" class="money">Payable</th>
                            <th style="width: 10%;" class="status">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($expenses as $expense)
                            <tr>
                                <td>{{ $expense->expense_date->format('M j, Y') }}</td>
                                <td>{{ $expense->category?->name ?? 'Uncategorized' }}</td>
                                <td>{{ $expense->description ?: $expense->expense_number }}</td>
                                <td>{{ $expense->payee_name ?: 'Not set' }}</td>
                                <td class="money">{{ $money($expense->amount_minor) }}</td>
                                <td class="money">{{ $money($expense->paid_minor) }}</td>
                                <td class="money">{{ $money(max(0, $expense->amount_minor - $expense->paid_minor)) }}</td>
                                <td class="status">{{ $statusLabel($expense->payment_status) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8"><div class="empty">No expense records for this period.</div></td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4">Total expenses</td>
                            <td class="money">{{ $money($totals['expense_minor']) }}</td>
                            <td class="money">{{ $money($totals['paid_minor']) }}</td>
                            <td class="money">{{ $money($totals['payable_minor']) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <section class="report-section">
            <h2 class="section-title">Category Summary</h2>
            <table class="summary-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th class="money">Amount</th>
                        <th class="money">Paid</th>
                        <th class="money">Payable</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categorySummary as $row)
                        <tr>
                            <td>{{ $row['category'] }}</td>
                            <td class="money">{{ $money($row['amount_minor']) }}</td>
                            <td class="money">{{ $money($row['paid_minor']) }}</td>
                            <td class="money">{{ $money($row['payable_minor']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="empty">No category summary for this period.</div></td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td>Total</td>
                        <td class="money">{{ $money($totals['expense_minor']) }}</td>
                        <td class="money">{{ $money($totals['paid_minor']) }}</td>
                        <td class="money">{{ $money($totals['payable_minor']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </section>

        <section class="total-band" aria-label="Expense totals">
            <div class="total-cell">
                <span>Total Expense</span>
                <strong>{{ $money($totals['expense_minor']) }}</strong>
            </div>
            <div class="total-cell">
                <span>Total Paid</span>
                <strong>{{ $money($totals['paid_minor']) }}</strong>
            </div>
            <div class="total-cell">
                <span>Total Payable</span>
                <strong>{{ $money($totals['payable_minor']) }}</strong>
            </div>
        </section>
    </main>
</body>
</html>
