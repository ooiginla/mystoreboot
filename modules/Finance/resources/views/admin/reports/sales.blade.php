@php
    /** @var \Closure $money */
    /** @var \Closure $statusLabel */
    /** @var \Closure $salesReference */
    $period = $dateFrom->format('M j, Y').' - '.$dateTo->format('M j, Y');
    $downloadQuery = fn (string $format): array => array_merge($query, ['format' => $format]);
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sales Report</title>
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
        body { margin: 0; background: #eef1f5; color: var(--body); font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; line-height: 1.45; }
        .toolbar { position: sticky; top: 0; z-index: 10; display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 14px 24px; background: rgba(255, 255, 255, .94); border-bottom: 1px solid var(--line-strong); box-shadow: 0 8px 24px rgba(15, 23, 42, .08); backdrop-filter: blur(10px); }
        .toolbar strong { color: var(--ink); font-size: 15px; }
        .toolbar-actions { display: flex; flex-wrap: wrap; gap: 8px; }
        .action { display: inline-flex; align-items: center; justify-content: center; min-height: 36px; border: 1px solid var(--line-strong); border-radius: 7px; background: #fff; color: #263142; padding: 8px 12px; font-weight: 700; font-size: 13px; text-decoration: none; cursor: pointer; }
        .action.primary { background: var(--brand); border-color: var(--brand); color: #fff; }
        .sheet { width: min(1240px, calc(100% - 32px)); margin: 28px auto; padding: 30px 28px 34px; background: var(--paper); box-shadow: 0 16px 42px rgba(15, 23, 42, .10); }
        .report-top { display: flex; justify-content: space-between; gap: 24px; align-items: start; margin-bottom: 42px; }
        h1 { margin: 0; color: var(--ink); font-size: 42px; line-height: 1.04; font-weight: 500; letter-spacing: 0; }
        .currency { color: var(--muted); font-size: 15px; font-weight: 700; white-space: nowrap; }
        .info-grid { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(0, .9fr); gap: 16px; margin-bottom: 44px; }
        .section-title { margin: 0 0 10px; color: var(--muted); font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .02em; }
        .detail-table, .report-table, .summary-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .detail-table th, .detail-table td { border-bottom: 1px solid var(--line); padding: 7px 14px; text-align: left; font-size: 15px; font-weight: 500; overflow-wrap: anywhere; }
        .detail-table th { width: 34%; color: #3a4350; background: #fff; }
        .detail-table td { background: var(--fill); color: #232b38; }
        .report-section { margin-top: 38px; }
        .report-table th, .report-table td { border-bottom: 1px solid var(--line); padding: 8px 10px; text-align: left; vertical-align: top; font-size: 13px; overflow-wrap: anywhere; }
        .report-table th { color: var(--muted); font-size: 12px; font-weight: 700; }
        .report-table tbody tr:nth-child(odd) td { background: var(--fill); }
        .report-table .money, .report-table .status, .summary-table .money { text-align: right; white-space: nowrap; }
        .report-table .status { color: #394150; }
        .report-table tfoot td, .summary-table tfoot td { background: #fff; border-top: 2px solid var(--line-strong); font-weight: 800; }
        .summary-table th, .summary-table td { border-bottom: 1px solid var(--line); padding: 9px 14px; text-align: left; font-size: 14px; }
        .summary-table th { color: var(--muted); font-weight: 700; }
        .total-band { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); margin-top: 28px; background: var(--fill); border: 1px solid #f0f1f4; }
        .total-cell { min-height: 128px; display: grid; place-items: center; gap: 8px; padding: 18px 16px; text-align: center; border-right: 1px solid #e8ebef; }
        .total-cell:last-child { border-right: 0; }
        .total-cell span { color: var(--muted); font-size: 13px; font-weight: 800; text-transform: uppercase; }
        .total-cell strong { color: var(--ink); font-size: 30px; line-height: 1; font-weight: 500; letter-spacing: 0; }
        .empty { padding: 22px; text-align: center; color: var(--muted); background: var(--fill); }
        @media (max-width: 850px) {
            .toolbar { align-items: stretch; flex-direction: column; }
            .toolbar-actions { width: 100%; }
            .action { flex: 1 1 120px; }
            .info-grid, .total-band { grid-template-columns: 1fr; }
            .total-cell { border-right: 0; border-bottom: 1px solid #e8ebef; }
            .total-cell:last-child { border-bottom: 0; }
            .sheet { width: 100%; margin: 0; padding: 24px 16px; box-shadow: none; }
            h1 { font-size: 34px; }
            .report-table { min-width: 1180px; }
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
    <div class="toolbar" aria-label="Sales report actions">
        <strong>Sales Report</strong>
        <div class="toolbar-actions">
            <a class="action primary" href="{{ route('admin.finance.reports.download', ['report' => 'sales'] + $downloadQuery('pdf')) }}">Download PDF</a>
            <a class="action" href="{{ route('admin.finance.reports.download', ['report' => 'sales'] + $downloadQuery('excel')) }}">Download Excel</a>
            <a class="action" href="{{ route('admin.finance.reports.download', ['report' => 'sales'] + $downloadQuery('word')) }}">Download Word</a>
            <button class="action" type="button" onclick="window.print()">Print</button>
        </div>
    </div>

    <main class="sheet">
        <div class="report-top">
            <h1>Sales Report</h1>
            <div class="currency">Currency: {{ $currencySymbol }}</div>
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
            <h2 class="section-title">Sales Details</h2>
            <div class="table-scroll">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th style="width: 8%;">Date</th>
                            <th style="width: 13%;">Reference</th>
                            <th style="width: 12%;">Customer</th>
                            <th style="width: 11%;">Branch</th>
                            <th style="width: 10%;">Payment</th>
                            <th style="width: 9%;" class="status">Payment Status</th>
                            <th style="width: 9%;" class="money">Subtotal</th>
                            <th style="width: 8%;" class="money">Discount</th>
                            <th style="width: 7%;" class="money">Tax</th>
                            <th style="width: 9%;" class="money">Total</th>
                            <th style="width: 9%;" class="money">Paid</th>
                            <th style="width: 9%;" class="money">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $order)
                            @php $discountMinor = (int) $order->coupon_discount_minor + (int) $order->admin_discount_minor; @endphp
                            <tr>
                                <td>{{ $order->order_date->format('M j, Y') }}</td>
                                <td>{{ $salesReference($order) }}</td>
                                <td>{{ $order->customer?->name ?? 'Walk-In' }}</td>
                                <td>{{ $order->branch?->name ?? 'Unassigned' }}</td>
                                <td>{{ $order->payment_method ?: 'Not set' }}</td>
                                <td class="status">{{ $statusLabel($order->payment_status->value ?? (string) $order->payment_status) }}</td>
                                <td class="money">{{ $money($order->subtotal_minor) }}</td>
                                <td class="money">{{ $money($discountMinor) }}</td>
                                <td class="money">{{ $money($order->tax_minor) }}</td>
                                <td class="money">{{ $money($order->total_minor) }}</td>
                                <td class="money">{{ $money($order->paid_minor) }}</td>
                                <td class="money">{{ $money($order->balance_minor) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="12"><div class="empty">No sales records for this period.</div></td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6">Total sales</td>
                            <td class="money">{{ $money($totals['subtotal_minor']) }}</td>
                            <td class="money">{{ $money($totals['discount_minor']) }}</td>
                            <td class="money">{{ $money($totals['tax_minor']) }}</td>
                            <td class="money">{{ $money($totals['sales_minor']) }}</td>
                            <td class="money">{{ $money($totals['paid_minor']) }}</td>
                            <td class="money">{{ $money($totals['balance_minor']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <section class="report-section">
            <h2 class="section-title">Payment Summary</h2>
            <table class="summary-table">
                <thead><tr><th>Payment Method</th><th class="money">Orders</th><th class="money">Total</th><th class="money">Paid</th><th class="money">Balance</th></tr></thead>
                <tbody>
                    @forelse ($paymentSummary as $row)
                        <tr><td>{{ $row['method'] }}</td><td class="money">{{ $row['orders'] }}</td><td class="money">{{ $money($row['total_minor']) }}</td><td class="money">{{ $money($row['paid_minor']) }}</td><td class="money">{{ $money($row['balance_minor']) }}</td></tr>
                    @empty
                        <tr><td colspan="5"><div class="empty">No payment summary for this period.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="report-section">
            <h2 class="section-title">Branch Summary</h2>
            <table class="summary-table">
                <thead><tr><th>Branch</th><th class="money">Orders</th><th class="money">Total</th><th class="money">Paid</th><th class="money">Balance</th></tr></thead>
                <tbody>
                    @forelse ($branchSummary as $row)
                        <tr><td>{{ $row['branch'] }}</td><td class="money">{{ $row['orders'] }}</td><td class="money">{{ $money($row['total_minor']) }}</td><td class="money">{{ $money($row['paid_minor']) }}</td><td class="money">{{ $money($row['balance_minor']) }}</td></tr>
                    @empty
                        <tr><td colspan="5"><div class="empty">No branch summary for this period.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="total-band" aria-label="Sales totals">
            <div class="total-cell">
                <span>Total Sales</span>
                <strong>{{ $money($totals['sales_minor']) }}</strong>
            </div>
            <div class="total-cell">
                <span>Total Paid</span>
                <strong>{{ $money($totals['paid_minor']) }}</strong>
            </div>
            <div class="total-cell">
                <span>Outstanding</span>
                <strong>{{ $money($totals['balance_minor']) }}</strong>
            </div>
            <div class="total-cell">
                <span>Net After Refunds</span>
                <strong>{{ $money($totals['net_sales_minor']) }}</strong>
            </div>
        </section>
    </main>
</body>
</html>
