@php
    $money = fn (int $minor): string => number_format($minor / 100, 2);
    $signed = fn (int $minor): string => ($minor < 0 ? '-' : '').number_format(abs($minor) / 100, 2);
@endphp

<x-layouts.admin title="Dashboard">
    @if (! $tenant)
        <div class="topbar"><div><div class="eyebrow">Business analytics</div><h1>Dashboard</h1></div></div>
        <div class="panel"><div class="panel-body"><div class="empty">No organization is available for your account yet.</div></div></div>
    @else
    <style>
        .dash-filters { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; }
        .dash-filter { display: grid; gap: 5px; }
        .dash-filter > span { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); }
        .dash-filter select, .dash-filter input { padding: 8px 11px; border-radius: 9px; min-width: 150px; }
        .dash-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-bottom: 16px; }
        .kpi { position: relative; overflow: hidden; border: 1px solid var(--line); border-radius: var(--radius); background: #fff; padding: 18px; box-shadow: var(--shadow-sm); display: flex; flex-direction: column; gap: 10px; }
        .kpi-top { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .kpi-label { color: var(--muted); font-size: 12.5px; font-weight: 650; }
        .kpi-ico { width: 40px; height: 40px; border-radius: 11px; display: grid; place-items: center; flex: 0 0 auto; }
        .kpi-ico svg { width: 20px; height: 20px; }
        .kpi-value { font-size: 24px; font-weight: 800; letter-spacing: -.02em; line-height: 1.05; font-variant-numeric: tabular-nums; }
        .kpi-sub { font-size: 12.5px; color: var(--muted); font-variant-numeric: tabular-nums; }
        .kpi.hero { color: #fff; border: 0; }
        .kpi.hero .kpi-label { color: rgba(255,255,255,.85); }
        .kpi.hero .kpi-sub { color: rgba(255,255,255,.8); }
        .kpi.hero .kpi-ico { background: rgba(255,255,255,.18); color: #fff; }
        .kpi.grad-green { background: linear-gradient(135deg, #009a53, #027a45); }
        .kpi.grad-dark { background: linear-gradient(135deg, #0f1f18, #16281f); }
        .kpi.grad-blue { background: linear-gradient(135deg, #2563eb, #1d4ed8); }
        .kpi.grad-amber { background: linear-gradient(135deg, #d97706, #b45309); }
        .ico-green { background: var(--brand-050); color: var(--brand-strong); }
        .ico-blue { background: #e0ecff; color: #1d4ed8; }
        .ico-violet { background: #ede9fe; color: #7c3aed; }
        .ico-teal { background: #ccfbf1; color: #0f766e; }
        .ico-amber { background: #fef3c7; color: #b45309; }
        .ico-red { background: #fee2e2; color: #dc2626; }
        .dash-orders { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 14px; margin-bottom: 16px; }
        .order-card { border: 1px solid var(--line); border-radius: var(--radius); background: #fff; padding: 18px; box-shadow: var(--shadow-sm); border-left: 5px solid var(--line); }
        .order-card.pending { border-left-color: #f59e0b; }
        .order-card.completed { border-left-color: #06c168; }
        .order-card.cancelled { border-left-color: #dc2626; }
        .order-card .oc-count { font-size: 30px; font-weight: 800; letter-spacing: -.02em; font-variant-numeric: tabular-nums; }
        .order-card .oc-label { color: var(--muted); font-size: 12.5px; font-weight: 650; text-transform: uppercase; letter-spacing: .03em; }
        .order-card .oc-value { margin-top: 6px; font-size: 14px; font-weight: 650; font-variant-numeric: tabular-nums; }
        .dash-charts { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 16px; margin-bottom: 16px; }
        .chart-card { border: 1px solid var(--line); border-radius: var(--radius); background: #fff; box-shadow: var(--shadow-sm); padding: 18px 18px 8px; min-width: 0; }
        .chart-card.span2 { grid-column: span 2; }
        .chart-card h3 { margin: 0 0 6px; font-size: 15px; font-weight: 700; }
        .chart-card .subtle { margin-bottom: 6px; }
        .chart-empty { color: var(--muted); background: var(--panel-soft); border-radius: 9px; padding: 28px; text-align: center; font-size: 13px; margin: 6px 0 12px; }
        .rank { display: inline-grid; place-items: center; width: 24px; height: 24px; border-radius: 7px; background: var(--brand-050); color: var(--brand-strong); font-weight: 800; font-size: 12px; }
        @media (max-width: 1100px) { .dash-kpis { grid-template-columns: repeat(2, minmax(0,1fr)); } .dash-charts { grid-template-columns: 1fr; } .chart-card.span2 { grid-column: auto; } .dash-orders { grid-template-columns: 1fr; } }
        @media (max-width: 620px) { .dash-kpis { grid-template-columns: 1fr; } }
    </style>

    <div class="topbar">
        <div>
            <div class="eyebrow">Business analytics</div>
            <h1>Dashboard</h1>
            <p class="subtle">{{ $tenant->name }} · {{ $selectedBranchId ? ($branches->firstWhere('id', $selectedBranchId)?->name ?? 'Branch') : 'All branches' }} · {{ $rangeLabel }}</p>
        </div>

        <form method="GET" action="{{ route('admin.analytics.index') }}" class="dash-filters" data-no-search>
            @if ($isPlatformAdmin && $visibleTenants->count() > 1)
                <label class="dash-filter">
                    <span>Organization</span>
                    <select name="tenant" onchange="this.form.submit()" aria-label="Organization">
                        @foreach ($visibleTenants as $t)
                            <option value="{{ $t->id }}" @selected($t->id === $tenant->id)>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            @if ($canPickAllBranches)
                <label class="dash-filter">
                    <span>Branch</span>
                    <select name="branch" onchange="this.form.submit()" aria-label="Branch">
                        <option value="all" @selected(! $selectedBranchId)>All branches</option>
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}" @selected($selectedBranchId === (int) $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            <label class="dash-filter">
                <span>Period</span>
                <select name="period" onchange="this.form.submit()" aria-label="Period">
                    @foreach ($periods as $key => $label)
                        <option value="{{ $key }}" @selected($period === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="dash-filter" data-custom-range @if($period !== 'custom') hidden @endif>
                <span>From</span>
                <input type="date" name="from" value="{{ $from->toDateString() }}">
            </label>
            <label class="dash-filter" data-custom-range @if($period !== 'custom') hidden @endif>
                <span>To</span>
                <input type="date" name="to" value="{{ $to->toDateString() }}">
            </label>
            <button class="btn primary" type="submit" data-custom-range @if($period !== 'custom') hidden @endif>Apply</button>
        </form>
    </div>

    {{-- ===== Hero money KPIs ===== --}}
    <div class="dash-kpis">
        <div class="kpi hero grad-green">
            <div class="kpi-top"><span class="kpi-label">Total Revenue</span><span class="kpi-ico"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18m5-14H9.5a2.5 2.5 0 0 0 0 5h5a2.5 2.5 0 0 1 0 5H6"/></svg></span></div>
            <div class="kpi-value">{{ $currency }} {{ $money($kpi['revenueMinor']) }}</div>
            <div class="kpi-sub">Cost of goods: {{ $currency }} {{ $money($kpi['cogsMinor']) }}</div>
        </div>
        <div class="kpi hero grad-dark">
            <div class="kpi-top"><span class="kpi-label">Gross Profit</span><span class="kpi-ico"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 14l3-3 3 3 5-6"/></svg></span></div>
            <div class="kpi-value">{{ $currency }} {{ $signed($kpi['grossProfitMinor']) }}</div>
            <div class="kpi-sub">Net Profit: {{ $currency }} {{ $signed($kpi['netProfitMinor']) }}</div>
        </div>
        <div class="kpi hero grad-amber">
            <div class="kpi-top"><span class="kpi-label">Total Expenses</span><span class="kpi-ico"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v18l2-1 2 1 2-1 2 1 2-1 2 1V3l-2 1-2-1-2 1-2-1-2 1-2-1ZM9 8h6M9 12h6"/></svg></span></div>
            <div class="kpi-value">{{ $currency }} {{ $money($kpi['expenseMinor']) }}</div>
            <div class="kpi-sub">Operating expenses this period</div>
        </div>
        <div class="kpi hero grad-blue">
            <div class="kpi-top"><span class="kpi-label">Inventory Value</span><span class="kpi-ico"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3 2 8l10 5 10-5-10-5ZM2 13l10 5 10-5"/></svg></span></div>
            <div class="kpi-value">{{ $currency }} {{ $money($kpi['inventoryMinor']) }}</div>
            <div class="kpi-sub">Stock on hand at cost</div>
        </div>
    </div>

    {{-- ===== Counts + position ===== --}}
    <div class="dash-kpis">
        <div class="kpi">
            <div class="kpi-top"><span class="kpi-label">Total Products</span><span class="kpi-ico ico-green"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2 3 7v10l9 5 9-5V7l-9-5ZM3 7l9 5 9-5M12 12v10"/></svg></span></div>
            <div class="kpi-value">{{ number_format($kpi['products']) }}</div>
            <div class="kpi-sub">Active products &amp; services</div>
        </div>
        <div class="kpi">
            <div class="kpi-top"><span class="kpi-label">Total Customers</span><span class="kpi-ico ico-violet"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M16 20v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 20v-2a4 4 0 0 0-3-3.87"/></svg></span></div>
            <div class="kpi-value">{{ number_format($kpi['customers']) }}</div>
            <div class="kpi-sub">Active customer records</div>
        </div>
        <div class="kpi">
            <div class="kpi-top"><span class="kpi-label">Total Suppliers</span><span class="kpi-ico ico-teal"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6h11v10H3zM14 9h4l3 3v4h-7M7.5 20a1.7 1.7 0 1 0 0-3.4 1.7 1.7 0 0 0 0 3.4Zm10 0a1.7 1.7 0 1 0 0-3.4 1.7 1.7 0 0 0 0 3.4Z"/></svg></span></div>
            <div class="kpi-value">{{ number_format($kpi['suppliers']) }}</div>
            <div class="kpi-sub">Active vendors</div>
        </div>
        <div class="kpi">
            <div class="kpi-top"><span class="kpi-label">Receivables / Payables</span><span class="kpi-ico ico-amber"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 0 1 2-2h13v3M3 7v10a2 2 0 0 0 2 2h14a1 1 0 0 0 1-1v-3M3 7h16a1 1 0 0 1 1 1v3M17 13.5h.01"/></svg></span></div>
            <div class="kpi-value" style="color:#067647;">{{ $currency }} {{ $money($kpi['receivableMinor']) }}</div>
            <div class="kpi-sub">Owed to you · <strong style="color:#dc2626;">{{ $currency }} {{ $money($kpi['payableMinor']) }}</strong> you owe</div>
        </div>
    </div>

    {{-- ===== Order groups ===== --}}
    <div class="dash-orders">
        <div class="order-card pending">
            <div class="oc-label">Pending Orders</div>
            <div class="oc-count">{{ number_format($orderGroups['pending']['count']) }}</div>
            <div class="oc-value">{{ $currency }} {{ $money($orderGroups['pending']['valueMinor']) }}</div>
        </div>
        <div class="order-card completed">
            <div class="oc-label">Completed Orders</div>
            <div class="oc-count">{{ number_format($orderGroups['completed']['count']) }}</div>
            <div class="oc-value">{{ $currency }} {{ $money($orderGroups['completed']['valueMinor']) }}</div>
        </div>
        <div class="order-card cancelled">
            <div class="oc-label">Cancelled Orders</div>
            <div class="oc-count">{{ number_format($orderGroups['cancelled']['count']) }}</div>
            <div class="oc-value">{{ $currency }} {{ $money($orderGroups['cancelled']['valueMinor']) }}</div>
        </div>
    </div>

    {{-- ===== Charts row 1 ===== --}}
    <div class="dash-charts">
        <div class="chart-card">
            <h3>Order Status</h3>
            <p class="subtle">Orders by status this period</p>
            @if ($charts['orderStatus']->isEmpty())
                <div class="chart-empty">No orders in this period.</div>
            @else
                <div id="chart-order-status"></div>
            @endif
        </div>
        <div class="chart-card span2">
            <h3>Sales Revenue</h3>
            <p class="subtle">Revenue trend over the selected period</p>
            <div id="chart-revenue"></div>
        </div>
    </div>

    {{-- ===== Charts row 2 ===== --}}
    <div class="dash-charts">
        <div class="chart-card">
            <h3>Sales by Channel</h3>
            <p class="subtle">Where your sales come from</p>
            @if ($charts['channel']->isEmpty())
                <div class="chart-empty">No sales in this period.</div>
            @else
                <div id="chart-channel"></div>
            @endif
        </div>
        <div class="chart-card">
            <h3>Payment Methods</h3>
            <p class="subtle">Collections by method</p>
            @if ($charts['payment']->isEmpty())
                <div class="chart-empty">No payments in this period.</div>
            @else
                <div id="chart-payment"></div>
            @endif
        </div>
        <div class="chart-card">
            <h3>Expense Breakdown</h3>
            <p class="subtle">Spending by category</p>
            @if ($charts['expense']->isEmpty())
                <div class="chart-empty">No expenses in this period.</div>
            @else
                <div id="chart-expense"></div>
            @endif
        </div>
    </div>

    {{-- ===== Top products ===== --}}
    <div class="panel">
        <div class="panel-header">
            <div><h2 class="panel-title">Top 10 Selling Products</h2><p class="subtle">Ranked by revenue for the selected period</p></div>
        </div>
        <div class="panel-body" style="padding-top:6px;">
            <table class="table">
                <thead><tr><th style="width:44px;">#</th><th>Product</th><th>SKU</th><th style="text-align:right;">Qty sold</th><th style="text-align:right;">Revenue</th></tr></thead>
                <tbody>
                    @forelse ($topProducts as $i => $product)
                        <tr>
                            <td><span class="rank">{{ $i + 1 }}</span></td>
                            <td><strong>{{ $product['name'] }}</strong></td>
                            <td class="subtle">{{ $product['sku'] ?: '—' }}</td>
                            <td style="text-align:right; font-variant-numeric:tabular-nums;">{{ number_format($product['qty']) }}</td>
                            <td style="text-align:right; font-weight:700; font-variant-numeric:tabular-nums;">{{ $currency }} {{ $money($product['revenueMinor']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty">No products sold in this period yet.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        window.storebootDashboard = {
            currency: @json($currency),
            orderStatus: @json($charts['orderStatus']),
            revenue: @json($charts['revenue']),
            channel: @json($charts['channel']),
            payment: @json($charts['payment']),
            expense: @json($charts['expense']),
        };
    </script>
    <script src="{{ asset('vendor/apexcharts/apexcharts.min.js') }}"></script>
    <script src="{{ asset('js/dashboard-charts.js') }}?v=1"></script>
    @endif
</x-layouts.admin>
