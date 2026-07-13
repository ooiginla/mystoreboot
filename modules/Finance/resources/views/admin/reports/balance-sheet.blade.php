@php
    /** @var \Closure $statementMoney */
    $downloadQuery = fn (string $format): array => array_merge($query, ['format' => $format]);
    $sectionRows = function ($rows) use ($statementMoney): string {
        if ($rows->isEmpty()) {
            return '<tr><td class="label empty">No accounts in this section.</td><td class="amount"></td></tr>';
        }

        return $rows->map(fn (array $row): string => '<tr><td class="label"><span class="code">'.e($row['code']).'</span>'.e($row['name']).'</td><td class="amount">'.e($statementMoney($row['amount_minor'])).'</td></tr>')->implode('');
    };
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Balance Sheet</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #101828;
            --body: #252b37;
            --muted: #667085;
            --line: #d0d5dd;
            --fill: #f8fafc;
            --paper: #ffffff;
            --brand: #0f766e;
            --danger: #b42318;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef1f5; color: var(--body); font-family: Georgia, "Times New Roman", serif; line-height: 1.42; }
        .toolbar { position: sticky; top: 0; z-index: 10; display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 14px 24px; background: rgba(255,255,255,.95); border-bottom: 1px solid var(--line); box-shadow: 0 8px 24px rgba(15,23,42,.08); backdrop-filter: blur(10px); font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .toolbar strong { color: var(--ink); font-size: 15px; }
        .toolbar-actions { display: flex; flex-wrap: wrap; gap: 8px; }
        .action { display: inline-flex; align-items: center; justify-content: center; min-height: 36px; border: 1px solid var(--line); border-radius: 7px; background: #fff; color: #263142; padding: 8px 12px; font-weight: 700; font-size: 13px; text-decoration: none; cursor: pointer; }
        .action.primary { background: var(--brand); border-color: var(--brand); color: #fff; }
        .sheet { width: min(920px, calc(100% - 32px)); margin: 28px auto; padding: 52px 72px 48px; background: var(--paper); box-shadow: 0 16px 42px rgba(15,23,42,.10); }
        .statement-head { text-align: center; margin-bottom: 28px; color: #0f0f17; }
        .statement-head .company { margin: 0 0 6px; font-size: 26px; font-weight: 900; text-transform: uppercase; text-decoration: underline; text-underline-offset: 4px; }
        .statement-head .branch { margin: 0 0 4px; font-size: 17px; font-weight: 700; text-transform: uppercase; }
        .statement-head h1 { margin: 26px 0 0; font-size: 24px; font-weight: 900; text-transform: uppercase; text-decoration: underline; text-underline-offset: 4px; }
        .meta { display: grid; grid-template-columns: 1fr 170px; gap: 20px; align-items: end; margin-bottom: 20px; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .meta-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .meta-table th, .meta-table td { padding: 4px 7px; border-bottom: 1px solid #eaecf0; text-align: left; }
        .meta-table th { width: 34%; color: var(--muted); text-transform: uppercase; }
        .date-box { border: 2px solid #1f2937; padding: 7px 10px; text-align: center; font-weight: 900; font-size: 15px; }
        .statement-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .statement-table td { padding: 4px 8px; vertical-align: top; font-size: 18px; line-height: 1.25; }
        .statement-table .label { width: 72%; color: #111; }
        .statement-table .amount { width: 28%; text-align: right; white-space: nowrap; }
        .code { display: inline-block; min-width: 80px; color: #667085; font-size: 12px; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
        .major td { padding-top: 16px; font-size: 23px; font-weight: 900; }
        .section td { padding-top: 8px; font-size: 19px; font-weight: 800; }
        .subtotal td { padding-top: 8px; font-weight: 800; }
        .subtotal .amount { border-top: 2px solid #1f2937; }
        .grand td { padding-top: 14px; font-size: 20px; font-weight: 900; }
        .grand .amount { border-top: 2px solid #1f2937; border-bottom: 4px double #1f2937; }
        .difference { margin-top: 22px; padding: 12px 14px; border: 1px solid #fedf89; background: #fffaeb; color: #93370d; font-family: Inter, ui-sans-serif, system-ui, sans-serif; font-size: 13px; font-weight: 700; }
        .empty { color: var(--muted); font-style: italic; font-size: 14px; }
        .footnote { margin-top: 30px; padding-top: 14px; border-top: 1px solid #eaecf0; color: #475467; font-size: 12px; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
        @media (max-width: 850px) {
            .toolbar { align-items: stretch; flex-direction: column; }
            .toolbar-actions { width: 100%; }
            .action { flex: 1 1 120px; }
            .sheet { width: 100%; margin: 0; padding: 32px 18px; box-shadow: none; }
            .meta { grid-template-columns: 1fr; }
            .statement-head .company { font-size: 22px; }
            .statement-table td { font-size: 15px; padding: 5px 4px; }
            .statement-table .label { width: 64%; }
            .statement-table .amount { width: 36%; }
            .code { display: block; min-width: 0; margin-bottom: 2px; }
        }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { width: 100%; margin: 0; padding: 0; box-shadow: none; }
            .statement-head { margin-bottom: 16px; }
            .statement-head .company { font-size: 17pt; }
            .statement-head .branch { font-size: 10pt; }
            .statement-head h1 { margin-top: 18px; font-size: 15pt; }
            .meta { grid-template-columns: 1fr 130px; margin-bottom: 12px; }
            .meta-table { font-size: 7.2pt; }
            .date-box { font-size: 9pt; padding: 4px 7px; }
            .statement-table tr { break-inside: avoid; page-break-inside: avoid; }
            .statement-table td { padding: 2.5px 5px; font-size: 8.4pt; line-height: 1.08; white-space: nowrap; }
            .major td { padding-top: 7px; font-size: 11pt; }
            .section td { padding-top: 4px; font-size: 9.2pt; }
            .grand td { padding-top: 6px; font-size: 9.8pt; }
            .code { display: inline-block; min-width: 42px; margin-bottom: 0; font-size: 6.5pt; }
            .footnote, .difference { font-size: 7pt; margin-top: 12px; padding-top: 8px; }
            @page { margin: 14mm 12mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar" aria-label="Balance sheet actions">
        <strong>Balance Sheet</strong>
        <div class="toolbar-actions">
            <a class="action primary" href="{{ route('admin.finance.reports.download', ['report' => 'balance-sheet'] + $downloadQuery('pdf')) }}">Download PDF</a>
            <a class="action" href="{{ route('admin.finance.reports.download', ['report' => 'balance-sheet'] + $downloadQuery('excel')) }}">Download Excel</a>
            <a class="action" href="{{ route('admin.finance.reports.download', ['report' => 'balance-sheet'] + $downloadQuery('word')) }}">Download Word</a>
            <button class="action" type="button" onclick="window.print()">Print</button>
        </div>
    </div>

    <main class="sheet">
        <header class="statement-head">
            <p class="company">{{ $tenant->name }}</p>
            <p class="branch">{{ $selectedBranch?->name ?? 'All branches' }}</p>
            @if ($tenant->address)<p class="branch">{{ $tenant->address }}</p>@endif
            <h1>Balance Sheet As On {{ $dateTo->format('jS F Y') }}</h1>
        </header>

        <section class="meta" aria-label="Report information">
            <table class="meta-table">
                <tbody>
                    <tr><th>Report #</th><td>{{ $reportNumber }}</td></tr>
                    <tr><th>Generated</th><td>{{ $generatedAt->format('F j, Y g:i A') }}</td></tr>
                    <tr><th>Currency</th><td>{{ $tenant->currency_code }}</td></tr>
                </tbody>
            </table>
            <div class="date-box">{{ $dateTo->format('d-M-y') }}</div>
        </section>

        <table class="statement-table" aria-label="Balance sheet statement">
            <tbody>
                <tr class="major"><td class="label">Assets</td><td class="amount"></td></tr>
                <tr class="section"><td class="label">Current Assets</td><td class="amount"></td></tr>
                {!! $sectionRows($sections['currentAssets']) !!}
                <tr class="subtotal"><td class="label">Total Current Assets</td><td class="amount">{{ $statementMoney($totals['current_assets_minor']) }}</td></tr>

                <tr class="section"><td class="label">Long-term Assets</td><td class="amount"></td></tr>
                {!! $sectionRows($sections['longTermAssets']) !!}
                <tr class="grand"><td class="label">Total Assets</td><td class="amount">{{ $statementMoney($totals['assets_minor']) }}</td></tr>

                <tr class="major"><td class="label">Liabilities</td><td class="amount"></td></tr>
                <tr class="section"><td class="label">Current Liabilities</td><td class="amount"></td></tr>
                {!! $sectionRows($sections['currentLiabilities']) !!}
                <tr class="subtotal"><td class="label">Total Current Liabilities</td><td class="amount">{{ $statementMoney($totals['current_liabilities_minor']) }}</td></tr>

                <tr class="section"><td class="label">Long-term Liabilities</td><td class="amount"></td></tr>
                {!! $sectionRows($sections['longTermLiabilities']) !!}
                <tr class="grand"><td class="label">Total Liabilities</td><td class="amount">{{ $statementMoney($totals['liabilities_minor']) }}</td></tr>

                <tr class="major"><td class="label">Equity</td><td class="amount"></td></tr>
                {!! $sectionRows($sections['equity']) !!}
                <tr class="grand"><td class="label">Total Equity</td><td class="amount">{{ $statementMoney($totals['equity_minor']) }}</td></tr>

                <tr class="grand"><td class="label">Total Liabilities + Equity</td><td class="amount">{{ $statementMoney($totals['liabilities_equity_minor']) }}</td></tr>
            </tbody>
        </table>

        @if ($totals['difference_minor'] !== 0)
            <div class="difference">Balance check difference: {{ $statementMoney($totals['difference_minor']) }}. Review uncategorized postings, unclosed opening balances, or branch-only postings.</div>
        @endif

        <p class="footnote">This balance sheet is generated from posted ledger accounts as at the selected date. Income and expense activity not yet closed to retained earnings is included under equity as current year earnings or loss.</p>
    </main>
</body>
</html>
