@php
    /** @var \Closure $money */
    /** @var \Closure $percent */
    $period = $dateFrom->format('M j, Y').' - '.$dateTo->format('M j, Y');
    $downloadQuery = fn (string $format): array => array_merge($query, ['format' => $format]);
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Product Profitability Report</title>
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
        .detail-table, .report-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .detail-table th, .detail-table td { border-bottom: 1px solid var(--line); padding: 7px 14px; text-align: left; font-size: 15px; font-weight: 500; overflow-wrap: anywhere; }
        .detail-table th { width: 34%; color: #3a4350; background: #fff; }
        .detail-table td { background: var(--fill); color: #232b38; }
        .report-section { margin-top: 38px; }
        .report-table th, .report-table td { border-bottom: 1px solid var(--line); padding: 8px 10px; text-align: left; vertical-align: top; font-size: 13px; overflow-wrap: anywhere; }
        .report-table th { color: var(--muted); font-size: 12px; font-weight: 700; }
        .report-table tbody tr:nth-child(odd) td { background: var(--fill); }
        .report-table .number, .report-table .money { text-align: right; white-space: nowrap; }
        .report-table tfoot td { background: #fff; border-top: 2px solid var(--line-strong); font-weight: 800; }
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
            .report-table { min-width: 1040px; }
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
    <div class="toolbar" aria-label="Product profitability report actions">
        <strong>Product Profitability Report</strong>
        <div class="toolbar-actions">
            <a class="action primary" href="{{ route('admin.finance.reports.download', ['report' => 'product-profitability'] + $downloadQuery('pdf')) }}">Download PDF</a>
            <a class="action" href="{{ route('admin.finance.reports.download', ['report' => 'product-profitability'] + $downloadQuery('excel')) }}">Download Excel</a>
            <a class="action" href="{{ route('admin.finance.reports.download', ['report' => 'product-profitability'] + $downloadQuery('word')) }}">Download Word</a>
            <button class="action" type="button" onclick="window.print()">Print</button>
        </div>
    </div>

    <main class="sheet">
        <div class="report-top">
            <h1>Product Profitability Report</h1>
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
            <h2 class="section-title">Product Details</h2>
            <div class="table-scroll">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th style="width: 22%;">Product</th>
                            <th style="width: 12%;">SKU</th>
                            <th style="width: 8%;" class="number">Qty Sold</th>
                            <th style="width: 9%;" class="number">Qty Returned</th>
                            <th style="width: 8%;" class="number">Net Qty</th>
                            <th style="width: 11%;" class="money">Sales Revenue</th>
                            <th style="width: 10%;" class="money">COGS</th>
                            <th style="width: 11%;" class="money">Gross Profit</th>
                            <th style="width: 9%;" class="money">Gross Margin</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['sku'] ?: 'Not set' }}</td>
                                <td class="number">{{ number_format($row['quantity_sold']) }}</td>
                                <td class="number">{{ number_format($row['quantity_returned']) }}</td>
                                <td class="number">{{ number_format($row['net_quantity']) }}</td>
                                <td class="money">{{ $money($row['revenue_minor']) }}</td>
                                <td class="money">{{ $money($row['cogs_minor']) }}</td>
                                <td class="money">{{ $money($row['profit_minor']) }}</td>
                                <td class="money">{{ $percent($row['margin_percent']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9"><div class="empty">No product sales for this period.</div></td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2">Total</td>
                            <td class="number">{{ number_format($totals['quantity_sold']) }}</td>
                            <td class="number">{{ number_format($totals['quantity_returned']) }}</td>
                            <td class="number">{{ number_format($totals['net_quantity']) }}</td>
                            <td class="money">{{ $money($totals['net_revenue_minor']) }}</td>
                            <td class="money">{{ $money($totals['cogs_minor']) }}</td>
                            <td class="money">{{ $money($totals['profit_minor']) }}</td>
                            <td class="money">{{ $percent($totals['margin_percent']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <section class="total-band" aria-label="Product profitability totals">
            <div class="total-cell">
                <span>Products</span>
                <strong>{{ number_format($totals['products']) }}</strong>
            </div>
            <div class="total-cell">
                <span>Net Revenue</span>
                <strong>{{ $money($totals['net_revenue_minor']) }}</strong>
            </div>
            <div class="total-cell">
                <span>Gross Profit</span>
                <strong>{{ $money($totals['profit_minor']) }}</strong>
            </div>
            <div class="total-cell">
                <span>Gross Margin</span>
                <strong>{{ $percent($totals['margin_percent']) }}</strong>
            </div>
        </section>
    </main>
</body>
</html>
