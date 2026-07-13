@php
    /** @var \Closure $money */
    $period = $dateFrom->format('M j, Y').' - '.$dateTo->format('M j, Y');
    $downloadQuery = fn (string $format): array => array_merge($query, ['format' => $format]);
    $statementYear = $dateTo->format('Y');
    $netLabel = $totals['net_profit_minor'] >= 0 ? 'Net Profit' : 'Net Loss';
    $netClass = $totals['net_profit_minor'] >= 0 ? 'positive' : 'negative';
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profit and Loss Statement</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #111827;
            --body: #293241;
            --muted: #717784;
            --line: #e7eaf0;
            --line-strong: #cfd5df;
            --fill: #f6f7f9;
            --paper: #ffffff;
            --brand: #0f766e;
            --accent: #d9ecff;
            --danger: #b42318;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef1f5; color: var(--body); font-family: Georgia, "Times New Roman", serif; line-height: 1.45; }
        .toolbar { position: sticky; top: 0; z-index: 10; display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 14px 24px; background: rgba(255, 255, 255, .95); border-bottom: 1px solid var(--line-strong); box-shadow: 0 8px 24px rgba(15, 23, 42, .08); backdrop-filter: blur(10px); font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .toolbar strong { color: var(--ink); font-size: 15px; }
        .toolbar-actions { display: flex; flex-wrap: wrap; gap: 8px; }
        .action { display: inline-flex; align-items: center; justify-content: center; min-height: 36px; border: 1px solid var(--line-strong); border-radius: 7px; background: #fff; color: #263142; padding: 8px 12px; font-weight: 700; font-size: 13px; text-decoration: none; cursor: pointer; }
        .action.primary { background: var(--brand); border-color: var(--brand); color: #fff; }
        .sheet { width: min(980px, calc(100% - 32px)); margin: 28px auto; padding: 56px 64px 48px; background: var(--paper); box-shadow: 0 16px 42px rgba(15, 23, 42, .10); }
        .statement-head { text-align: center; margin-bottom: 34px; color: #0f0f17; }
        .statement-head h1 { margin: 0 0 8px; font-size: 31px; line-height: 1.1; font-weight: 800; text-transform: uppercase; letter-spacing: 0; text-decoration: underline; text-decoration-thickness: 1px; text-underline-offset: 4px; }
        .statement-head .company { margin: 0 0 12px; font-size: 25px; font-weight: 800; text-transform: uppercase; }
        .statement-head .period { margin: 0; font-size: 20px; font-weight: 800; text-transform: uppercase; text-decoration: underline; text-decoration-thickness: 1px; text-underline-offset: 4px; }
        .meta { display: grid; grid-template-columns: 1fr 220px; gap: 26px; align-items: end; margin-bottom: 18px; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .meta-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .meta-table th, .meta-table td { padding: 5px 8px; border-bottom: 1px solid var(--line); text-align: left; }
        .meta-table th { width: 34%; color: var(--muted); font-weight: 800; text-transform: uppercase; }
        .year-box { background: var(--accent); color: #07111f; text-align: center; padding: 8px 12px; font-size: 18px; font-weight: 900; }
        .statement-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .statement-table td { padding: 5px 10px; vertical-align: top; font-size: 19px; line-height: 1.25; }
        .statement-table .label { width: 60%; color: #111; }
        .statement-table .code { display: inline-block; min-width: 86px; color: #566070; font-size: 13px; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
        .statement-table .amount { width: 20%; text-align: right; white-space: nowrap; }
        .statement-table .final { width: 20%; text-align: right; white-space: nowrap; }
        .section-row td { padding-top: 14px; font-weight: 900; text-transform: uppercase; }
        .subtotal td { padding-top: 9px; font-weight: 900; }
        .subtotal .final, .subtotal .amount { border-top: 2px solid #171717; }
        .total-row td { padding-top: 10px; font-weight: 900; text-transform: uppercase; }
        .total-row .final { border-top: 2px solid #171717; border-bottom: 4px double #171717; }
        .total-row.negative .final { color: var(--danger); }
        .profit-band td { padding-top: 14px; font-weight: 900; text-transform: uppercase; }
        .profit-band .final { border-top: 2px solid #171717; }
        .empty { color: var(--muted); font-style: italic; font-size: 15px; }
        .note { margin-top: 34px; padding-top: 18px; border-top: 1px solid var(--line); font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .note h2 { margin: 0 0 10px; color: var(--muted); font-size: 13px; font-weight: 800; text-transform: uppercase; letter-spacing: .02em; }
        .note-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
        .note-cell { background: var(--fill); border: 1px solid var(--line); padding: 12px; }
        .note-cell span { display: block; color: var(--muted); font-size: 12px; font-weight: 800; text-transform: uppercase; }
        .note-cell strong { display: block; margin-top: 6px; color: var(--ink); font-size: 15px; }
        .footnote { margin: 28px 0 0; font-size: 13px; color: #404956; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
        @media (max-width: 850px) {
            .toolbar { align-items: stretch; flex-direction: column; }
            .toolbar-actions { width: 100%; }
            .action { flex: 1 1 120px; }
            .sheet { width: 100%; margin: 0; padding: 32px 18px; box-shadow: none; }
            .statement-head h1 { font-size: 26px; }
            .statement-head .company { font-size: 21px; }
            .statement-head .period { font-size: 17px; }
            .meta, .note-grid { grid-template-columns: 1fr; }
            .statement-table td { font-size: 16px; padding: 6px 4px; }
            .statement-table .code { display: block; min-width: 0; margin-bottom: 2px; }
        }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { width: 100%; margin: 0; padding: 0; box-shadow: none; }
            .statement-head { margin-bottom: 18px; }
            .statement-head .company { font-size: 17pt; margin-bottom: 7px; }
            .statement-head h1 { font-size: 20pt; margin-bottom: 6px; }
            .statement-head .period { font-size: 13pt; }
            .meta { grid-template-columns: 1fr 145px; gap: 12px; margin-bottom: 10px; }
            .meta-table { font-size: 7.8pt; }
            .meta-table th,
            .meta-table td { padding: 2.5px 5px; }
            .year-box { font-size: 12pt; padding: 4px 8px; }
            .statement-table { table-layout: fixed; }
            .statement-table tr {
                break-inside: avoid;
                page-break-inside: avoid;
            }
            .statement-table td {
                padding: 2.5px 5px;
                font-size: 8.8pt;
                line-height: 1.08;
                white-space: nowrap;
            }
            .statement-table .label {
                width: 66%;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .statement-table .code {
                min-width: 44px;
                font-size: 6.8pt;
            }
            .statement-table .amount,
            .statement-table .final { width: 17%; }
            .section-row td { padding-top: 6px; }
            .subtotal td,
            .total-row td,
            .profit-band td { padding-top: 5px; }
            .note { margin-top: 16px; padding-top: 10px; }
            .note h2 { font-size: 8pt; margin-bottom: 6px; }
            .note-grid { gap: 5px; }
            .note-cell { padding: 6px; }
            .note-cell span { font-size: 6.8pt; white-space: nowrap; }
            .note-cell strong { font-size: 8pt; margin-top: 3px; white-space: nowrap; }
            .footnote { margin-top: 14px; font-size: 7.2pt; }
            @page { margin: 14mm 10mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar" aria-label="Profit and loss statement actions">
        <strong>Profit and Loss Statement</strong>
        <div class="toolbar-actions">
            <a class="action primary" href="{{ route('admin.finance.reports.download', ['report' => 'profit-loss'] + $downloadQuery('pdf')) }}">Download PDF</a>
            <a class="action" href="{{ route('admin.finance.reports.download', ['report' => 'profit-loss'] + $downloadQuery('excel')) }}">Download Excel</a>
            <a class="action" href="{{ route('admin.finance.reports.download', ['report' => 'profit-loss'] + $downloadQuery('word')) }}">Download Word</a>
            <button class="action" type="button" onclick="window.print()">Print</button>
        </div>
    </div>

    <main class="sheet">
        <header class="statement-head">
            <p class="company">{{ $tenant->name }}</p>
            <h1>Income Statement</h1>
            <p class="period">For the period {{ $dateFrom->format('j F Y') }} to {{ $dateTo->format('j F Y') }}</p>
        </header>

        <section class="meta" aria-label="Report information">
            <table class="meta-table">
                <tbody>
                    <tr><th>Branch</th><td>{{ $selectedBranch?->name ?? 'All branches' }}</td></tr>
                    <tr><th>Report #</th><td>{{ $reportNumber }}</td></tr>
                    <tr><th>Generated</th><td>{{ $generatedAt->format('F j, Y g:i A') }}</td></tr>
                    <tr><th>Currency</th><td>{{ $tenant->currency_code }}</td></tr>
                </tbody>
            </table>
            <div class="year-box">{{ $statementYear }}</div>
        </section>

        <table class="statement-table" aria-label="Income statement lines">
            <tbody>
                <tr class="section-row"><td class="label">Revenue</td><td class="amount"></td><td class="final"></td></tr>
                @forelse ($sections['operatingRevenue'] as $row)
                    <tr><td class="label"><span class="code">{{ $row['code'] }}</span>{{ $row['name'] }}</td><td class="amount">{{ $money($row['amount_minor']) }}</td><td class="final"></td></tr>
                @empty
                    <tr><td class="label empty">No revenue accounts found.</td><td class="amount"></td><td class="final"></td></tr>
                @endforelse
                @foreach ($sections['contraRevenue'] as $row)
                    <tr><td class="label"><span class="code">{{ $row['code'] }}</span>Less {{ $row['name'] }}</td><td class="amount">({{ $money($row['amount_minor']) }})</td><td class="final"></td></tr>
                @endforeach
                <tr class="subtotal"><td class="label">Net Revenue</td><td class="amount"></td><td class="final">{{ $money($totals['net_revenue_minor']) }}</td></tr>

                <tr class="section-row"><td class="label">Cost of Goods Sold / Direct Costs</td><td class="amount"></td><td class="final"></td></tr>
                @forelse ($sections['directCosts'] as $row)
                    <tr><td class="label"><span class="code">{{ $row['code'] }}</span>{{ $row['name'] }}</td><td class="amount">{{ $money($row['amount_minor']) }}</td><td class="final"></td></tr>
                @empty
                    <tr><td class="label empty">No direct cost accounts found.</td><td class="amount"></td><td class="final"></td></tr>
                @endforelse
                <tr class="subtotal"><td class="label">Total Cost of Goods Sold / Direct Costs</td><td class="amount"></td><td class="final">{{ $money($totals['direct_costs_minor']) }}</td></tr>
                <tr class="profit-band"><td class="label">Gross Profit</td><td class="amount"></td><td class="final">{{ $money($totals['gross_profit_minor']) }}</td></tr>

                <tr class="section-row"><td class="label">Operating Expenses</td><td class="amount"></td><td class="final"></td></tr>
                @forelse ($sections['operatingExpenses'] as $row)
                    <tr><td class="label"><span class="code">{{ $row['code'] }}</span>{{ $row['name'] }}</td><td class="amount">{{ $money($row['amount_minor']) }}</td><td class="final"></td></tr>
                @empty
                    <tr><td class="label empty">No operating expense accounts found.</td><td class="amount"></td><td class="final"></td></tr>
                @endforelse
                <tr class="subtotal"><td class="label">Total Operating Expenses</td><td class="amount"></td><td class="final">{{ $money($totals['operating_expenses_minor']) }}</td></tr>
                <tr class="profit-band"><td class="label">Operating Profit</td><td class="amount"></td><td class="final">{{ $money($totals['operating_profit_minor']) }}</td></tr>

                <tr class="section-row"><td class="label">Other Income</td><td class="amount"></td><td class="final"></td></tr>
                @forelse ($sections['otherIncome'] as $row)
                    <tr><td class="label"><span class="code">{{ $row['code'] }}</span>{{ $row['name'] }}</td><td class="amount">{{ $money($row['amount_minor']) }}</td><td class="final"></td></tr>
                @empty
                    <tr><td class="label empty">No other income accounts found.</td><td class="amount"></td><td class="final"></td></tr>
                @endforelse

                <tr class="section-row"><td class="label">Non-Operating Expenses</td><td class="amount"></td><td class="final"></td></tr>
                @forelse ($sections['nonOperatingExpenses'] as $row)
                    <tr><td class="label"><span class="code">{{ $row['code'] }}</span>{{ $row['name'] }}</td><td class="amount">{{ $money($row['amount_minor']) }}</td><td class="final"></td></tr>
                @empty
                    <tr><td class="label empty">No non-operating expense accounts found.</td><td class="amount"></td><td class="final"></td></tr>
                @endforelse

                <tr class="total-row {{ $netClass }}"><td class="label">{{ $netLabel }}</td><td class="amount"></td><td class="final">{{ $money($totals['net_profit_minor']) }}</td></tr>
            </tbody>
        </table>

        <section class="note" aria-label="Inventory movement note">
            <h2>Inventory Movement Note</h2>
            <div class="note-grid">
                <div class="note-cell"><span>Opening Inventory</span><strong>{{ $money($inventoryNote['opening_inventory_minor']) }}</strong></div>
                <div class="note-cell"><span>Purchases / Additions</span><strong>{{ $money($inventoryNote['inventory_additions_minor']) }}</strong></div>
                <div class="note-cell"><span>Reductions</span><strong>{{ $money($inventoryNote['inventory_reductions_minor']) }}</strong></div>
                <div class="note-cell"><span>Closing Inventory</span><strong>{{ $money($inventoryNote['closing_inventory_minor']) }}</strong></div>
            </div>
        </section>

        <p class="footnote">This statement is generated from posted general ledger entries for the selected period. Inventory purchases and closing stock are shown as a movement note; profit uses the COGS and expense GLs to avoid double-counting inventory purchases.</p>
    </main>
</body>
</html>
