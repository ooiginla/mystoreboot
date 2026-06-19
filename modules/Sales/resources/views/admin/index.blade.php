@php
    $money = fn (?int $minor): string => number_format(($minor ?? 0) / 100, 2);
    $signedMoney = fn (int $minor): string => ($minor < 0 ? '-' : '').$tenant->currency_code.' '.number_format(abs($minor) / 100, 2);
    $posLocations = $activeTill
        ? $locations->filter(fn ($location) => $location->branch_id === null || $location->branch_id === $activeTill->branch_id)
        : collect();
    $movementTypes = [
        'cash_in' => 'Cash In',
        'cash_out' => 'Cash Out',
        'petty_cash_withdrawal' => 'Petty Cash Withdrawal',
        'cash_deposit' => 'Cash Deposit / Remittance',
    ];
    $variantLabel = fn ($variant): string => $variant->product?->name.' / '.$variant->variant_name.' ('.$variant->sku.')';
    $statusClass = fn (string $status): string => match ($status) {
        'completed', 'paid', 'delivered' => 'success',
        'partially_paid', 'partially_returned', 'partially_refunded', 'pending', 'processing', 'out_for_delivery' => 'warning',
        'cancelled', 'returned', 'refunded', 'unpaid', 'failed' => 'danger',
        default => 'neutral',
    };
    $deliveryStatusLabel = fn (?string $status): string => match ($status) {
        'pending' => 'Pending',
        'processing' => 'Processing',
        'out_for_delivery' => 'Out for delivery',
        'failed' => 'Failed delivery',
        'returned' => 'Returned',
        default => 'Delivered',
    };
    $deliveryStatuses = [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'out_for_delivery' => 'Out for delivery',
        'delivered' => 'Delivered',
        'failed' => 'Failed delivery',
        'returned' => 'Returned',
    ];
@endphp

<x-layouts.admin title="Sales, Invoicing & POS">
    <datalist id="sales-customer-options">
        @foreach ($customers as $customer)
            <option value="{{ $customer->name }} · {{ $customer->phone }}" data-customer-id="{{ $customer->id }}"></option>
        @endforeach
    </datalist>
    <datalist id="sales-product-options">
        @foreach ($variants as $variant)
            @php
                $taxRate = $variant->tax_behavior->value === 'taxable' ? (float) ($variant->tax_rate ?? $variant->product?->tax_rate ?? 0) : 0;
                $priceMinor = $variant->discount_price_minor ?? $variant->selling_price_minor;
            @endphp
            <option value="{{ $variantLabel($variant) }}" data-variant-id="{{ $variant->id }}" data-price="{{ $priceMinor / 100 }}" data-tax-rate="{{ $taxRate }}" data-sku="{{ $variant->sku }}"></option>
        @endforeach
    </datalist>

    <style>
        .sales-metrics { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 18px; }
        .sales-metric-card { border: 1px solid #cfd8d3; border-radius: 8px; background: #fff; padding: 20px 22px; min-height: 112px; display: flex; justify-content: space-between; align-items: center; gap: 18px; box-shadow: 0 1px 2px rgba(16,24,40,.04); }
        .sales-metric-card.danger { border-left: 5px solid #b42318; }
        .sales-metric-label { color: #526177; font-size: 13px; font-weight: 850; text-transform: uppercase; letter-spacing: .04em; }
        .sales-metric-value { display: block; margin-top: 18px; color: #111827; font-size: 22px; line-height: 1.1; font-weight: 900; }
        .sales-metric-card.danger .sales-metric-value { color: #b42318; }
        .sales-metric-icon { width: 54px; height: 54px; border-radius: 8px; display: grid; place-items: center; color: #fff; background: #147b66; font-size: 22px; font-weight: 900; flex: 0 0 auto; }
        .sales-metric-icon.soft { color: #334155; background: #dbe7fb; }
        .sales-metric-icon.danger { color: #b42318; background: #ffe2df; }
        .sales-meta-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
        .sales-context-row { display: flex; gap: 10px; flex-wrap: wrap; }
        .sales-context-tag { border: 1px solid #cfd8d3; border-radius: 8px; background: #f8fafc; padding: 10px 12px; min-width: 180px; display: grid; gap: 4px; }
        .sales-context-tag span { color: #526177; font-size: 12px; font-weight: 850; text-transform: uppercase; }
        .sales-context-tag strong { color: #111827; font-size: 14px; font-weight: 900; }
        .sales-grid { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(360px, .85fr); gap: 18px; align-items: start; }
        .sales-customer-grid { display: grid; grid-template-columns: 1fr; gap: 14px; }
        .sales-card { border: 1px solid #cfd8d3; border-radius: 8px; background: #fff; padding: 22px; box-shadow: 0 1px 2px rgba(16,24,40,.04); }
        .sales-card-title { margin: 0 0 18px; color: #111827; font-size: 18px; font-weight: 900; display: flex; align-items: center; gap: 10px; }
        .sales-card-icon { color: #006554; font-weight: 950; font-size: 22px; }
        .sales-primary-button { width: 100%; border: 0; border-radius: 7px; background: #006554; color: #fff; padding: 15px 18px; cursor: pointer; font-size: 18px; font-weight: 900; box-shadow: 0 8px 18px rgba(0, 101, 84, .18); }
        .sales-primary-button:hover { background: #005245; }
        .sales-summary-card { border: 1px solid #cfd8d3; border-radius: 8px; background: #fff; overflow: hidden; position: sticky; top: 24px; box-shadow: 0 1px 2px rgba(16,24,40,.04); }
        .sales-summary-header { background: #f0f2ff; border-bottom: 1px solid #cfd8d3; padding: 28px 30px; }
        .sales-summary-header h3 { margin: 0; color: #005245; font-size: 26px; font-weight: 950; }
        .sales-summary-body { padding: 28px 30px; }
        .sales-summary-discount { margin: 18px 0; border-radius: 8px; background: #f1f3ff; padding: 18px; display: grid; gap: 14px; }
        .sales-total-band { margin: 18px 0; border-radius: 8px; background: #137c68; color: #bcf7e7; padding: 28px 30px; display: flex; justify-content: space-between; align-items: center; gap: 16px; }
        .sales-total-band span { font-size: 16px; font-weight: 850; }
        .sales-total-band strong { font-size: 22px; line-height: 1.1; font-weight: 950; }
        .sales-change-box { border-radius: 8px; background: #dce8ff; color: #005245; min-height: 68px; display: grid; place-items: center; font-size: 22px; font-weight: 950; }
        .sales-note-card textarea { min-height: 70px; line-height: 22px; resize: vertical; }
        .sales-delivery-note { margin-top: 14px; padding-top: 14px; border-top: 1px solid #e4e7ec; }
        .sales-tag { display: inline-flex; border-radius: 6px; padding: 4px 8px; font-size: 12px; font-weight: 800; }
        .sales-tag.neutral { background: #eef2f6; color: #475467; }
        .sales-tag.success { background: #ecfdf3; color: #067647; }
        .sales-tag.warning { background: #fffaeb; color: #b54708; }
        .sales-tag.danger { background: #fef3f2; color: #b42318; }
        .link-button { border: 0; background: transparent; padding: 0; color: var(--accent); cursor: pointer; font-weight: 800; text-align: left; }
        .cart-row { border: 1px solid #b7d8ce; border-left: 5px solid #006554; border-radius: 8px; padding: 12px; display: grid; grid-template-columns: 1fr 80px 120px 34px; gap: 10px; align-items: center; background: #f1fbf7; }
        .cart-remove-button { border-color: #f6c7c2; background: #fff1f0; color: #b42318; font-weight: 950; }
        .cart-remove-button:hover { border-color: #b42318; background: #fee4e2; color: #912018; }
        .summary-cart-items { display: grid; gap: 8px; margin-bottom: 12px; }
        .summary-cart-item { display: grid; grid-template-columns: 1fr auto; gap: 10px; border: 1px solid #d6eee5; border-radius: 8px; background: #f7fdfb; padding: 10px 12px; font-size: 14px; color: #344054; }
        .summary-cart-item strong { color: #111827; font-weight: 850; }
        .summary-cart-item span { color: #006554; font-weight: 900; white-space: nowrap; }
        .summary-line { display: flex; justify-content: space-between; gap: 12px; padding: 8px 0; color: #526177; font-size: 17px; }
        .summary-line strong { color: #526177; font-weight: 900; }
        .summary-line.discount strong { color: #b42318; }
        .summary-divider { border-top: 1px solid #cfd8d3; margin: 16px 0; }
        .success-text { color: #067647; font-weight: 900; }
        .danger-text { color: #b42318; font-weight: 900; }
        .sales-inline-check { margin-top: 14px; display: inline-flex; align-items: center; gap: 9px; color: #344054; font-size: 14px; font-weight: 800; cursor: pointer; }
        .sales-inline-check input { width: auto; min-width: 16px; height: 16px; margin: 0; flex: 0 0 auto; accent-color: #006554; }
        .till-status-band { border: 1px solid #b7d8ce; border-left: 5px solid #006554; border-radius: 8px; background: #f1fbf7; padding: 14px; display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap; align-items: center; }
        .till-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .till-action-button { border: 0; border-radius: 8px; color: #fff; padding: 10px 13px; cursor: pointer; font-weight: 900; box-shadow: 0 8px 18px rgba(16,24,40,.12); }
        .till-action-button.cash-in { background: #0f766e; }
        .till-action-button.cash-out { background: #b42318; }
        .till-action-button.petty-cash-withdrawal { background: #9a3412; }
        .till-action-button.cash-deposit { background: #1d4ed8; }
        .till-action-button:hover { filter: brightness(.94); }
        .till-variance { font-weight: 900; }
        .till-variance.ok { color: #067647; }
        .till-variance.bad { color: #b42318; }
        .till-close-warning { border: 1px solid #fecdca; border-radius: 8px; background: #fef3f2; color: #b42318; padding: 10px 12px; font-weight: 850; }
        .till-breakdown-header { display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap; margin-top: 16px; }
        .till-breakdown-header h3 { margin: 0; font-size: 16px; font-weight: 900; color: #111827; }
        .till-locked-pos { border: 1px dashed #cfd8d3; border-radius: 8px; padding: 22px; text-align: center; background: #f8fafc; }
        .thermal-receipt-dialog { width: min(430px, calc(100vw - 24px)); }
        .thermal-receipt-dialog .dialog-body { background: #f3f4f6; }
        .thermal-receipt-paper { width: 80mm; max-width: 100%; margin: 0 auto; background: #fff; color: #111; padding: 12px 10px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; font-size: 11px; line-height: 1.35; box-shadow: 0 1px 4px rgba(16,24,40,.12); }
        .thermal-receipt-paper strong { font-weight: 900; }
        .receipt-business { display: block; font-size: 15px; text-transform: uppercase; }
        .receipt-center { display: grid; gap: 2px; text-align: center; }
        .receipt-rule { border-top: 1px dashed #111; margin: 8px 0; }
        .receipt-meta, .receipt-totals { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 3px 8px; }
        .receipt-meta strong, .receipt-totals strong { text-align: right; }
        .thermal-items { width: 100%; border-collapse: collapse; }
        .thermal-items th, .thermal-items td { padding: 3px 0; vertical-align: top; border: 0; color: #111; }
        .thermal-items th { border-bottom: 1px dashed #111; font-size: 10px; text-align: left; }
        .thermal-items th:nth-child(2), .thermal-items td:nth-child(2) { text-align: center; width: 28px; }
        .thermal-items th:nth-child(3), .thermal-items td:nth-child(3) { text-align: right; width: 58px; }
        .thermal-items td span { display: block; color: #333; font-size: 10px; }
        .receipt-grand-total { font-size: 14px; text-transform: uppercase; }
        .receipt-actions { margin-top: 16px; }
        .print-document { border: 1px solid var(--line); border-radius: 8px; padding: 22px; display: grid; gap: 16px; background: #fff; }
        .print-document-header { display: flex; justify-content: space-between; gap: 16px; border-bottom: 1px solid var(--line); padding-bottom: 14px; }
        .print-document-title { margin: 0; font-size: 24px; color: #005245; }
        .sales-submit-action { margin-top: 18px; }
        @media print {
            body:has(dialog[open]) .shell { display: block; }
            body:has(dialog[open]) .sidebar, body:has(dialog[open]) .topbar, body:has(dialog[open]) .tab-layout, body:has(dialog[open]) .sales-metrics { display: none; }
            dialog[open] { display: block; position: static; width: 100%; max-width: none; box-shadow: none; }
            dialog[open]::backdrop, dialog[open] .dialog-header .icon-btn, dialog[open] [data-print-dialog], dialog[open] [data-dialog-close] { display: none; }
            .dialog-body { max-height: none; overflow: visible; }
            dialog[open].thermal-receipt-dialog { width: 80mm; max-width: 80mm; margin: 0 auto; }
            dialog[open].thermal-receipt-dialog .dialog-header, dialog[open].thermal-receipt-dialog .receipt-actions { display: none; }
            dialog[open].thermal-receipt-dialog .dialog-body { padding: 0; background: #fff; }
            dialog[open].thermal-receipt-dialog .thermal-receipt-paper { width: 80mm; max-width: 80mm; padding: 4mm 3mm; box-shadow: none; }
        }
        @media (max-width: 1200px) { .sales-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); } .sales-grid { grid-template-columns: 1fr; } .sales-summary-card { position: static; } }
        @media (max-width: 700px) { .sales-metrics, .sales-meta-grid { grid-template-columns: 1fr; } .cart-row { grid-template-columns: 1fr; } }
    </style>

    <div class="topbar">
        <div>
            <div class="eyebrow">Sales, invoicing & point of sale</div>
            <h1>Sales & POS</h1>
            <p class="subtle">Create sales orders, collect payments, issue receipts, manage credit sales, coupons, returns, and refunds for {{ $tenant->name }}.</p>
        </div>
        @if ($isPlatformAdmin)
            <form method="GET" action="{{ route('admin.sales.index') }}" style="min-width: 260px;">
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
        <div class="alert errors"><strong>Check the sales details.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="tab-layout">
        <nav class="pill-nav" aria-label="Sales sections" role="tablist">
            <a href="#till" role="tab" data-tab-target="till">Till</a>
            <a href="#pos" role="tab" data-tab-target="pos">Point of Sale</a>
            <a href="#orders" role="tab" data-tab-target="orders">Orders <span class="badge neutral">{{ $orders->count() }}</span></a>
            <a href="#coupons" role="tab" data-tab-target="coupons">Coupons <span class="badge neutral">{{ $coupons->count() }}</span></a>
            <a href="#returns" role="tab" data-tab-target="returns">Returns</a>
        </nav>

        <div class="content-stack">
            <section class="panel tab-panel" id="till" role="tabpanel" data-tab-panel>
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Till Management</h2>
                        <p class="subtle">Open a branch till, track payment collections, record cash movement, and reconcile before closing.</p>
                    </div>
                </div>
                <div class="panel-body stack">
                    @if (! $activeTill)
                        <div class="sales-card">
                            <h3 class="sales-card-title">Open Till</h3>
                            <form class="mini-form" method="POST" action="{{ route('admin.sales.tills.open') }}">
                                @csrf
                                <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                                <div class="form-grid">
                                    <div class="field"><label>Branch</label><select name="branch_id" required>@foreach ($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></div>
                                    <div class="field"><label>Opening cash float</label><input name="opening_float" type="text" inputmode="decimal" data-money-input value="0.00"></div>
                                    <div class="field full"><label>Opening note</label><textarea name="opening_note" rows="2"></textarea></div>
                                </div>
                                <div class="button-row"><button class="btn primary" type="submit">Open till</button></div>
                            </form>
                        </div>
                    @else
                        <div class="till-status-band">
                            <div>
                                <strong>{{ $activeTill->session_number }}</strong>
                                <div class="subtle">{{ $activeTill->branch?->name }} · opened {{ $activeTill->opened_at->format('M j, Y H:i') }} · {{ $tenant->currency_code }} {{ $money($activeTill->opening_float_minor) }} opening float</div>
                                <div class="subtle">Cashier till: {{ $activeTill->cashLocation?->name ?? 'Pending' }} · Safe vault: {{ $activeTill->vaultCashLocation?->name ?? 'Pending' }}</div>
                            </div>
                            <div class="till-actions">
                                @foreach ($movementTypes as $value => $label)
                                    <button class="till-action-button {{ str_replace('_', '-', $value) }}" type="button" data-dialog-open="till-movement-{{ $value }}">{{ $label }}</button>
                                @endforeach
                            </div>
                        </div>

                        <form class="mini-form" method="POST" action="{{ route('admin.sales.tills.close', $activeTill) }}" data-till-close-form>
                            @csrf
                            <table class="table">
                                <thead><tr><th>Payment method</th><th>Collected</th><th>Cash movement</th><th>Expected</th><th>Actual counted</th><th>Variance</th></tr></thead>
                                <tbody>
                                    @foreach ($activeTillRows as $row)
                                        @php
                                            $actualValue = old('actuals.'.$row['method'], $money($row['expected_minor']));
                                        @endphp
                                        <tr>
                                            <td><strong>{{ $row['method'] }}</strong></td>
                                            <td>{{ $tenant->currency_code }} {{ $money($row['collected_minor']) }}</td>
                                            <td>{{ $signedMoney($row['movement_minor']) }}</td>
                                            <td><strong>{{ $tenant->currency_code }} {{ $money($row['expected_minor']) }}</strong></td>
                                            <td><input name="actuals[{{ $row['method'] }}]" type="text" inputmode="decimal" data-money-input data-till-actual data-expected="{{ $row['expected_minor'] / 100 }}" value="{{ $actualValue }}"></td>
                                            <td><input type="text" data-till-variance value="{{ $tenant->currency_code }} 0.00" disabled><span class="till-variance ok" data-till-variance-label hidden>0</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <div class="till-close-warning" data-till-close-warning hidden>Variance must be 0 for every payment method before this till can be closed.</div>
                            <div class="till-breakdown-header">
                                <h3>Actions</h3>
                                <button class="btn secondary" type="button" data-dialog-open="till-breakdown-dialog">Breakdown</button>
                            </div>
                            <div class="field"><label>Closing note</label><textarea name="closing_note" rows="2"></textarea></div>
                            <div class="button-row">
                                <button class="btn primary" type="submit" data-till-close-button>Close till</button>
                            </div>
                        </form>

                        <div class="sales-card">
                            <h3 class="sales-card-title">Cash movements</h3>
                            <table class="table">
                                <thead><tr><th>Time</th><th>Type</th><th>Amount</th><th>Reference</th><th>Note</th></tr></thead>
                                <tbody>
                                    @forelse ($activeTill->movements->sortByDesc('occurred_at') as $movement)
                                        <tr><td>{{ $movement->occurred_at->format('H:i') }}</td><td>{{ $movementTypes[$movement->movement_type] ?? $movement->movement_type }}</td><td>{{ $tenant->currency_code }} {{ $money($movement->amount_minor) }}</td><td>{{ $movement->reference_number ?: 'Not set' }}</td><td>{{ $movement->notes ?: 'Not set' }}</td></tr>
                                    @empty
                                        <tr><td colspan="5"><div class="empty">No till movements recorded.</div></td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif

                    <div class="sales-card">
                        <h3 class="sales-card-title">Recent till sessions</h3>
                        <table class="table">
                            <thead><tr><th>Session</th><th>Branch</th><th>Vault</th><th>Status</th><th>Opened</th><th>Closed</th><th>Expected</th><th>Actual</th><th>Variance</th></tr></thead>
                            <tbody>
                                @forelse ($recentTillSessions as $session)
                                    <tr><td>{{ $session->session_number }}</td><td>{{ $session->branch?->name ?? 'Not set' }}</td><td>{{ $session->vaultCashLocation?->name ?? 'Not set' }}</td><td><span class="sales-tag {{ $session->status === 'open' ? 'success' : 'neutral' }}">{{ ucfirst($session->status) }}</span></td><td>{{ $session->opened_at->format('M j, H:i') }}</td><td>{{ $session->closed_at?->format('M j, H:i') ?? 'Not closed' }}</td><td>{{ $tenant->currency_code }} {{ $money($session->expected_total_minor) }}</td><td>{{ $tenant->currency_code }} {{ $money($session->actual_total_minor) }}</td><td>{{ $signedMoney($session->variance_total_minor) }}</td></tr>
                                @empty
                                    <tr><td colspan="9"><div class="empty">No till session has been opened yet.</div></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="panel tab-panel" id="pos" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header"><div><h2 class="panel-title">Point of Sale</h2><p class="subtle">Search customer and products, build a cart, calculate totals, collect payment, and post stock-out.</p></div></div>
                <div class="panel-body">
                    @if (! $activeTill)
                        <div class="till-locked-pos">
                            <h3 class="panel-title">Open a till before selling</h3>
                            <p class="subtle">You must open a branch till session before the POS form is activated. Close the current till before opening another branch.</p>
                            <div class="button-row" style="justify-content: center;"><a class="btn primary" href="#till" data-tab-target="till">Go to Till</a></div>
                        </div>
                    @else
                    <form class="mini-form" method="POST" action="{{ route('admin.sales.orders.store') }}" data-pos-form>
                        @csrf
                        <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                        <input type="hidden" name="sales_till_session_id" value="{{ $activeTill->id }}">
                        <input type="hidden" name="branch_id" value="{{ $activeTill->branch_id }}">
                        <input type="hidden" name="inventory_location_id" value="{{ $posLocations->first()?->id }}">
                        <input type="hidden" name="order_date" value="{{ now()->toDateString() }}">
                        <div class="sales-card">
                            <div class="sales-context-row">
                                <div class="sales-context-tag"><span>Signed-in branch</span><strong>{{ $activeTill->branch?->name ?? 'Not set' }}</strong></div>
                                <div class="sales-context-tag"><span>Inventory location</span><strong>{{ $posLocations->first()?->name ?? 'No location' }}</strong></div>
                                <div class="sales-context-tag"><span>Order date</span><strong>{{ now()->toDateString() }}</strong></div>
                            </div>
                        </div>
                        <div class="sales-metrics">
                            <div class="sales-metric-card"><div><span class="sales-metric-label">Orders</span><strong class="sales-metric-value">{{ $stats['orders'] }}</strong></div><span class="sales-metric-icon">SO</span></div>
                            <div class="sales-metric-card"><div><span class="sales-metric-label">Revenue</span><strong class="sales-metric-value">{{ $tenant->currency_code }} {{ $money($stats['revenue_minor']) }}</strong></div><span class="sales-metric-icon">₦</span></div>
                            <div class="sales-metric-card"><div><span class="sales-metric-label">Credit balance</span><strong class="sales-metric-value">{{ $tenant->currency_code }} {{ $money($stats['credit_minor']) }}</strong></div><span class="sales-metric-icon soft">CR</span></div>
                            <div class="sales-metric-card danger"><div><span class="sales-metric-label">Returns</span><strong class="sales-metric-value">{{ $tenant->currency_code }} {{ $money($stats['returns_minor']) }}</strong></div><span class="sales-metric-icon danger">RT</span></div>
                        </div>
                        <div class="sales-grid">
                            <div class="stack">
                                <div class="sales-card">
                                    <h3 class="sales-card-title">Sale Information</h3>
                                    <div class="sales-customer-grid">
                                        <div class="field" data-sales-customer-picker><label>Customer</label><input type="text" list="sales-customer-options" data-sales-customer-search value="{{ $walkInCustomer->name }} · {{ $walkInCustomer->phone }}" required><input type="hidden" name="customer_id" data-sales-customer-value value="{{ $walkInCustomer->id }}"></div>
                                    </div>
                                </div>
                                <div class="sales-card">
                                    <h3 class="sales-card-title"><span class="sales-card-icon">+</span> Add Product to Cart</h3>
                                    <div style="display: grid; gap: 14px;">
                                        <div class="form-grid" style="grid-template-columns: minmax(0, 2fr) minmax(140px, .7fr);">
                                            <div class="field"><label>Search Product, Variant or SKU</label><input type="text" list="sales-product-options" data-sales-product-search placeholder="Type to search..."></div>
                                            <div class="field"><label>Quantity</label><input type="number" min="1" step="1" value="1" data-sales-product-qty></div>
                                        </div>
                                        <button class="sales-primary-button" type="button" data-sales-add-product>+ Add to Cart</button>
                                        <div data-sales-cart style="display: grid; gap: 8px;"></div>
                                        <div data-sales-items></div>
                                    </div>
                                </div>
                                <div class="sales-card sales-note-card">
                                    <h3 class="sales-card-title">Additional Note</h3>
                                    <textarea name="notes" rows="2" placeholder="Enter internal notes for this transaction..."></textarea>
                                    <div class="field sales-delivery-note"><label>Delivery Information</label><textarea name="delivery_address" rows="2" placeholder="Address, recipient contact..."></textarea></div>
                                    <div class="field" style="margin-top: 14px;"><label>Delivery Status</label><select name="delivery_status" required><option value="delivered" selected>Delivered</option><option value="pending">Pending</option><option value="processing">Processing</option><option value="out_for_delivery">Out for delivery</option><option value="failed">Failed delivery</option><option value="returned">Returned</option></select></div>
                                </div>
                            </div>
                            <div class="sales-summary-card">
                                <div class="sales-summary-header"><h3>Order Summary</h3></div>
                                <div class="sales-summary-body">
                                    <div class="summary-cart-items" data-sales-summary-items></div>
                                    <div class="summary-line"><span>Subtotal</span><strong data-sales-subtotal>{{ $tenant->currency_code }} 0.00</strong></div>
                                    <div class="summary-line"><span>Tax</span><strong data-sales-tax>{{ $tenant->currency_code }} 0.00</strong></div>
                                    <div class="summary-divider"></div>
                                    <div class="form-grid">
                                        <div class="field"><label>Delivery Method</label><select name="delivery_method" data-sales-delivery-method><option value="">No delivery</option>@foreach ($deliveryMethods as $method)<option value="{{ $method['name'] }}" data-price="{{ $method['price'] ?? 0 }}">{{ $method['name'] }}</option>@endforeach</select></div>
                                        <div class="field"><label>Shipping Fee</label><input name="shipping" type="text" inputmode="decimal" data-money-input data-sales-shipping value="0.00"></div>
                                    </div>
                                    <div class="sales-summary-discount">
                                        <div class="field"><label>Coupon Code</label><input name="coupon_code" data-sales-coupon-code placeholder="Enter code">@foreach ($coupons as $coupon)<span hidden data-sales-coupon data-code="{{ $coupon->code }}" data-type="{{ $coupon->discount_type->value }}" data-amount="{{ $coupon->discount_value_minor / 100 }}" data-percent="{{ $coupon->discount_percent }}"></span>@endforeach</div>
                                        <div class="form-grid">
                                            <div class="field"><label>Admin Discount</label><select name="admin_discount_type" data-sales-admin-discount-type><option value="amount">Amount (₦)</option><option value="percentage">Percentage (%)</option></select></div>
                                            <div class="field"><label>Value</label><input name="admin_discount_value" type="text" inputmode="decimal" data-money-input data-sales-admin-discount value="0"></div>
                                        </div>
                                    </div>
                                    <div class="summary-line discount"><span>Coupon Discount</span><strong data-sales-coupon-discount>-{{ $tenant->currency_code }} 0.00</strong></div>
                                    <div class="summary-line discount"><span>Admin Discount</span><strong data-sales-admin-discount-label>-{{ $tenant->currency_code }} 0.00</strong></div>
                                    <div class="sales-total-band"><span>Total</span><strong data-sales-total>{{ $tenant->currency_code }} 0.00</strong></div>
                                    <div class="summary-divider"></div>
                                    <div class="field"><label>Payment Method</label><select name="payment_method">@foreach ($paymentMethods as $method)<option value="{{ $method }}">{{ $method }}</option>@endforeach</select></div>
                                    <label class="sales-inline-check"><input type="checkbox" name="is_credit_sale" value="1"> <span>Mark as Credit Sale</span></label>
                                    <div class="form-grid" style="margin-top: 16px;">
                                        <div class="field"><label>Amount Paid</label><input name="amount_paid" type="text" inputmode="decimal" data-money-input data-sales-paid value="0.00" style="font-size: 22px; font-weight: 900;"></div>
                                        <div class="field"><label>Change</label><div class="sales-change-box" data-sales-change>{{ $tenant->currency_code }} 0.00</div></div>
                                    </div>
                                    <button class="sales-primary-button sales-submit-action" type="submit">Create sales order</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    @endif
                </div>
            </section>

            <section class="panel tab-panel" id="orders" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header"><div><h2 class="panel-title">Order listing</h2><p class="subtle">Sales orders, invoices, receipts, payment status, and credit balances.</p></div></div>
                <div class="panel-body">
                    <form class="form-grid" method="GET" action="{{ route('admin.sales.index') }}#orders" style="margin-bottom: 16px;">
                        <input type="hidden" name="tenant" value="{{ $tenant->id }}">
                        <div class="field"><label>Search orders</label><input name="order_search" value="{{ $orderSearch }}" placeholder="Order, invoice, receipt, customer, phone"></div>
                        <div class="button-row" style="justify-content: flex-start;"><button class="btn secondary" type="submit">Search</button><a class="btn secondary" href="{{ route('admin.sales.index', ['tenant' => $tenant->id]).'#orders' }}">Reset</a></div>
                    </form>
                    <table class="table">
                        <thead><tr><th>Order</th><th>Customer</th><th>Branch</th><th>Status</th><th>Payment</th><th>Total</th><th>Paid</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($orders as $order)
                                <tr>
                                    <td><button class="link-button" type="button" data-dialog-open="order-view-{{ $order->id }}">{{ $order->order_number }}</button><br><span class="subtle">{{ $order->order_date->format('M j, Y') }}</span></td>
                                    <td>{{ $order->customer?->name ?? 'Walk-In' }}</td>
                                    <td>{{ $order->branch?->name ?? 'Not set' }}</td>
                                    <td><span class="sales-tag {{ $statusClass($order->order_status->value) }}">{{ $order->order_status->label() }}</span></td>
                                    <td><span class="sales-tag {{ $statusClass($order->payment_status->value) }}">{{ $order->payment_status->label() }}</span></td>
                                    <td>{{ $tenant->currency_code }} {{ $money($order->total_minor) }}</td>
                                    <td>{{ $tenant->currency_code }} {{ $money($order->paid_minor) }}</td>
                                    <td style="display: flex; gap: 8px; flex-wrap: wrap;"><button class="btn secondary" type="button" data-dialog-open="order-view-{{ $order->id }}">View</button><button class="btn secondary" type="button" data-dialog-open="sales-receipt-{{ $order->id }}">Receipt</button>@if ($order->balance_minor > 0 && $activeTill && $activeTill->branch_id === $order->branch_id)<button class="btn secondary" type="button" data-dialog-open="order-payment-{{ $order->id }}">Add payment</button>@endif<button class="btn secondary" type="button" data-dialog-open="order-return-{{ $order->id }}">Return</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="8"><div class="empty">No sales orders yet.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel tab-panel" id="coupons" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header"><div><h2 class="panel-title">Coupon management</h2><p class="subtle">Create amount or percentage discounts for POS orders.</p></div><button class="btn accent" type="button" data-dialog-open="coupon-dialog">Add coupon</button></div>
                <div class="panel-body">
                    <table class="table"><thead><tr><th>Code</th><th>Type</th><th>Value</th><th>Validity</th><th>Status</th></tr></thead><tbody>@forelse ($coupons as $coupon)<tr><td>{{ $coupon->code }}</td><td>{{ $coupon->discount_type->label() }}</td><td>{{ $coupon->discount_type->value === 'percentage' ? $coupon->discount_percent.'%' : $tenant->currency_code.' '.$money($coupon->discount_value_minor) }}</td><td>{{ $coupon->starts_at?->format('M j, Y') ?? 'Now' }} - {{ $coupon->expires_at?->format('M j, Y') ?? 'No expiry' }}</td><td><span class="sales-tag {{ $coupon->is_active ? 'success' : 'neutral' }}">{{ $coupon->is_active ? 'Active' : 'Inactive' }}</span></td></tr>@empty<tr><td colspan="5"><div class="empty">No coupons yet.</div></td></tr>@endforelse</tbody></table>
                </div>
            </section>

            <section class="panel tab-panel" id="returns" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header"><div><h2 class="panel-title">Sales returns & refunds</h2><p class="subtle">Return history and refunded value from sales orders.</p></div></div>
                <div class="panel-body">
                    <table class="table"><thead><tr><th>Return</th><th>Order</th><th>Date</th><th>Refund</th><th>Reason</th></tr></thead><tbody>@forelse ($allOrders->flatMap->returns as $return)<tr><td>{{ $return->return_number }}</td><td>{{ $return->order->order_number }}</td><td>{{ $return->return_date->format('M j, Y') }}</td><td>{{ $tenant->currency_code }} {{ $money($return->refund_minor) }}</td><td>{{ $return->reason ?: 'Not set' }}</td></tr>@empty<tr><td colspan="5"><div class="empty">No returns yet.</div></td></tr>@endforelse</tbody></table>
                </div>
            </section>
        </div>
    </div>

    @include('sales::admin.partials.coupon-dialog')
    @if ($activeTill)
        @foreach ($movementTypes as $value => $label)
            <dialog class="dialog" id="till-movement-{{ $value }}">
                <div class="dialog-header"><div><h2 class="panel-title">{{ $label }}</h2><p class="subtle">{{ $activeTill->session_number }} · {{ $activeTill->branch?->name }}</p></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button></div>
                <div class="dialog-body">
                    <form class="mini-form" method="POST" action="{{ route('admin.sales.tills.movements.store', $activeTill) }}">
                        @csrf
                        <input type="hidden" name="movement_type" value="{{ $value }}">
                        <div class="form-grid">
                            <div class="field"><label>Amount</label><input name="amount" type="text" inputmode="decimal" data-money-input required></div>
                            <div class="field"><label>Reference</label><input name="reference_number"></div>
                            <div class="field full"><label>Notes</label><textarea name="notes" rows="2"></textarea></div>
                        </div>
                        <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Cancel</button><button class="btn primary" type="submit">Save movement</button></div>
                    </form>
                </div>
            </dialog>
        @endforeach
        <dialog class="dialog" id="till-breakdown-dialog">
            <div class="dialog-header">
                <div>
                    <h2 class="panel-title">Expected Till Breakdown</h2>
                    <p class="subtle">{{ $activeTill->session_number }} · {{ $activeTill->branch?->name }}</p>
                </div>
                <button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button>
            </div>
            <div class="dialog-body">
                <table class="table">
                    <thead><tr><th>Time</th><th>Type</th><th>Details</th><th>Method</th><th>Amount</th></tr></thead>
                    <tbody>
                        <tr>
                            <td>{{ $activeTill->opened_at->format('H:i') }}</td>
                            <td>Opening float</td>
                            <td>{{ $activeTill->opening_note ?: 'Till opened' }}</td>
                            <td>Cash</td>
                            <td class="success-text">+{{ $tenant->currency_code }} {{ $money($activeTill->opening_float_minor) }}</td>
                        </tr>
                        @foreach ($activeTill->payments->sortByDesc('created_at') as $payment)
                            <tr>
                                <td>{{ $payment->created_at?->format('H:i') ?? $payment->payment_date->format('H:i') }}</td>
                                <td>Sale payment</td>
                                <td>{{ $payment->order?->order_number ?? 'Order' }} · {{ $payment->order?->customer?->name ?? 'Walk-In' }}</td>
                                <td>{{ $payment->payment_method }}</td>
                                <td class="success-text">+{{ $tenant->currency_code }} {{ $money($payment->amount_minor) }}</td>
                            </tr>
                        @endforeach
                        @foreach ($activeTill->movements->sortByDesc('occurred_at') as $movement)
                            @php
                                $movementSign = $movement->movement_type === 'cash_in' ? 1 : -1;
                                $movementAmount = $movementSign * (int) $movement->amount_minor;
                            @endphp
                            <tr>
                                <td>{{ $movement->occurred_at->format('H:i') }}</td>
                                <td>{{ $movementTypes[$movement->movement_type] ?? $movement->movement_type }}</td>
                                <td>{{ $movement->reference_number ?: 'No reference' }} · {{ $movement->notes ?: 'No note' }}</td>
                                <td>{{ $movement->payment_method }}</td>
                                <td class="{{ $movementAmount < 0 ? 'danger-text' : 'success-text' }}">{{ $movementAmount < 0 ? '-' : '+' }}{{ $tenant->currency_code }} {{ $money(abs($movementAmount)) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="summary-grid" style="margin-top: 16px;">
                    @foreach ($activeTillRows as $row)
                        <div class="summary-item"><span>{{ $row['method'] }} expected</span><strong>{{ $tenant->currency_code }} {{ $money($row['expected_minor']) }}</strong></div>
                    @endforeach
                    <div class="summary-item"><span>Total expected</span><strong>{{ $tenant->currency_code }} {{ $money($activeTillRows->sum('expected_minor')) }}</strong></div>
                </div>
                <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Close</button></div>
            </div>
        </dialog>
    @endif
    @foreach ($allOrders as $order)
        @include('sales::admin.partials.order-view-dialog', ['order' => $order])
        @include('sales::admin.partials.invoice-dialog', ['order' => $order])
        @include('sales::admin.partials.thermal-receipt-dialog', ['order' => $order])
        @include('sales::admin.partials.payment-dialog', ['order' => $order])
        @include('sales::admin.partials.return-dialog', ['order' => $order])
        @foreach ($order->payments as $payment)
            @include('sales::admin.partials.payment-receipt-dialog', ['order' => $order, 'payment' => $payment])
        @endforeach
    @endforeach

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        if (window.storebootSalesPosBound) return;
        window.storebootSalesPosBound = true;
        const autoReceiptOrderId = @json(session('receipt_order_id'));
        if (autoReceiptOrderId) {
            window.setTimeout(() => {
                document.getElementById(`sales-receipt-${autoReceiptOrderId}`)?.showModal();
            }, 160);
        }
        const currency = @json($tenant->currency_code);
        const fmt = (value) => `${currency} ${Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        const clean = (value) => Number(String(value || '0').replace(/,/g, '')) || 0;
        const escapeHtml = (value) => String(value || '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
        const cart = [];

        function updateTillVariance(form) {
            if (!form) return;
            let hasVariance = false;
            form.querySelectorAll('[data-till-actual]').forEach((input) => {
                const expected = clean(input.dataset.expected);
                const actual = clean(input.value);
                const variance = actual - expected;
                const output = input.closest('tr')?.querySelector('[data-till-variance]');
                const label = input.closest('tr')?.querySelector('[data-till-variance-label]');
                if (output) output.value = `${variance < 0 ? '-' : ''}${fmt(Math.abs(variance))}`;
                if (label) {
                    label.textContent = String(variance);
                    label.classList.toggle('ok', variance === 0);
                    label.classList.toggle('bad', variance !== 0);
                }
                if (Math.round(variance * 100) !== 0) hasVariance = true;
            });
            const closeButton = form.querySelector('[data-till-close-button]');
            if (closeButton) closeButton.dataset.hasVariance = hasVariance ? '1' : '0';
            const warning = form.querySelector('[data-till-close-warning]');
            if (warning && !hasVariance) warning.hidden = true;
        }

        function render(form) {
            const rows = form.querySelector('[data-sales-cart]');
            const hidden = form.querySelector('[data-sales-items]');
            const summaryItems = form.querySelector('[data-sales-summary-items]');
            rows.innerHTML = '';
            hidden.innerHTML = '';
            if (summaryItems) summaryItems.innerHTML = '';
            let subtotal = 0;
            let tax = 0;
            cart.forEach((item, index) => {
                subtotal += item.quantity * item.price;
                tax += item.quantity * item.price * (item.taxRate / 100);
                rows.insertAdjacentHTML('beforeend', `<div class="cart-row"><strong>${escapeHtml(item.label)}</strong><input type="number" min="1" step="1" value="${item.quantity}" data-cart-qty="${index}"><span>${fmt(item.quantity * item.price)}</span><button class="icon-btn cart-remove-button" type="button" data-cart-remove="${index}">X</button></div>`);
                if (summaryItems) summaryItems.insertAdjacentHTML('beforeend', `<div class="summary-cart-item"><strong>${escapeHtml(item.label)} x ${item.quantity}</strong><span>${fmt(item.quantity * item.price)}</span></div>`);
                hidden.insertAdjacentHTML('beforeend', `<input type="hidden" name="items[${index}][product_variant_id]" value="${item.id}"><input type="hidden" name="items[${index}][quantity]" value="${item.quantity}"><input type="hidden" name="items[${index}][unit_price]" value="${item.price.toFixed(2)}">`);
            });
            const shipping = clean(form.querySelector('[data-sales-shipping]')?.value);
            const couponCode = form.querySelector('[data-sales-coupon-code]')?.value.toUpperCase();
            const coupon = Array.from(form.querySelectorAll('[data-sales-coupon]')).find((item) => item.dataset.code === couponCode);
            const couponDiscount = coupon ? Math.min(subtotal, coupon.dataset.type === 'percentage' ? subtotal * (clean(coupon.dataset.percent) / 100) : clean(coupon.dataset.amount)) : 0;
            const adminType = form.querySelector('[data-sales-admin-discount-type]')?.value;
            const adminValue = clean(form.querySelector('[data-sales-admin-discount]')?.value);
            const adminDiscount = Math.min(subtotal, adminType === 'percentage' ? subtotal * (adminValue / 100) : adminValue);
            const total = Math.max(0, subtotal + tax + shipping - couponDiscount - adminDiscount);
            const paid = clean(form.querySelector('[data-sales-paid]')?.value);
            form.querySelector('[data-sales-subtotal]').textContent = fmt(subtotal);
            form.querySelector('[data-sales-tax]').textContent = fmt(tax);
            form.querySelector('[data-sales-coupon-discount]').textContent = `-${fmt(couponDiscount)}`;
            form.querySelector('[data-sales-admin-discount-label]').textContent = `-${fmt(adminDiscount)}`;
            form.querySelector('[data-sales-total]').textContent = fmt(total);
            form.querySelector('[data-sales-change]').textContent = fmt(Math.max(0, paid - total));
        }

        function addSelectedProduct(form) {
            const search = form.querySelector('[data-sales-product-search]');
            const qty = Math.max(1, Number(form.querySelector('[data-sales-product-qty]')?.value || 1));
            const option = Array.from(search.list?.options || []).find((item) => item.value === search.value);
            if (!option) return false;
            const existing = cart.find((item) => item.id === option.dataset.variantId);
            if (existing) existing.quantity += qty;
            else cart.push({ id: option.dataset.variantId, label: option.value, quantity: qty, price: clean(option.dataset.price), taxRate: clean(option.dataset.taxRate) });
            search.value = '';
            search.focus();
            render(form);

            return true;
        }

        document.addEventListener('input', (event) => {
            const customer = event.target.closest('[data-sales-customer-search]');
            if (customer) {
                const picker = customer.closest('[data-sales-customer-picker]');
                const value = picker?.querySelector('[data-sales-customer-value]');
                const option = Array.from(customer.list?.options || []).find((item) => item.value === customer.value);
                if (value) value.value = option?.dataset.customerId || '';
            }
            const form = event.target.closest('[data-pos-form]');
            if (form) render(form);
            const tillForm = event.target.closest('[data-till-close-form]');
            if (tillForm) updateTillVariance(tillForm);
        });

        document.addEventListener('change', (event) => {
            const delivery = event.target.closest('[data-sales-delivery-method]');
            if (delivery) {
                const form = delivery.closest('form');
                const shipping = form?.querySelector('[data-sales-shipping]');
                if (shipping) shipping.value = Number(delivery.selectedOptions[0]?.dataset.price || 0).toFixed(2);
                if (form) render(form);
            }
        });

        document.addEventListener('keydown', (event) => {
            const search = event.target.closest('[data-sales-product-search]');
            if (!search || event.key !== 'Enter') return;
            const form = search.closest('form');
            if (!form) return;
            event.preventDefault();
            addSelectedProduct(form);
        });

        document.addEventListener('click', (event) => {
            const add = event.target.closest('[data-sales-add-product]');
            if (add) {
                const form = add.closest('form');
                addSelectedProduct(form);
                return;
            }
            const remove = event.target.closest('[data-cart-remove]');
            if (remove) {
                const form = remove.closest('form');
                cart.splice(Number(remove.dataset.cartRemove), 1);
                render(form);
            }
        });

        document.addEventListener('change', (event) => {
            const qty = event.target.closest('[data-cart-qty]');
            if (!qty) return;
            cart[Number(qty.dataset.cartQty)].quantity = Math.max(1, Number(qty.value || 1));
            render(qty.closest('form'));
        });

        document.addEventListener('submit', (event) => {
            const form = event.target.closest('[data-till-close-form]');
            if (!form) return;
            updateTillVariance(form);
            const closeButton = form.querySelector('[data-till-close-button]');
            if (closeButton?.dataset.hasVariance !== '1') return;
            event.preventDefault();
            const warning = form.querySelector('[data-till-close-warning]');
            if (warning) {
                warning.hidden = false;
                warning.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });

        document.querySelectorAll('[data-till-close-form]').forEach(updateTillVariance);
    });
    </script>
</x-layouts.admin>
