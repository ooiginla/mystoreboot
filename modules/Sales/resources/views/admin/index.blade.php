@php
    $money = fn (?int $minor): string => number_format(($minor ?? 0) / 100, 2);
    $currencySymbols = ['NGN' => '₦', 'USD' => '$', 'GHS' => '₵', 'KES' => 'KSh', 'ZAR' => 'R', 'GBP' => '£', 'EUR' => '€', 'GHc' => '₵'];
    $currencySymbol = $currencySymbols[$tenant->currency_code] ?? $tenant->currency_code;
    $signedMoney = fn (int $minor): string => ($minor < 0 ? '-' : '').$currencySymbol.' '.number_format(abs($minor) / 100, 2);
    $recordSaleLocations = $recordSaleBranch
        ? $locations->filter(fn ($location) => $location->branch_id === null || $location->branch_id === $recordSaleBranch->id)
        : collect();
    $recordSaleLocation = $recordSaleLocations->firstWhere('branch_id', $recordSaleBranch?->id) ?? $recordSaleLocations->first();
    $canRecordOrderPayment = fn ($order): bool => in_array($order->source, ['offline', 'online'], true)
        || ($activeTill && $activeTill->branch_id === $order->branch_id);
    $movementTypes = [
        'cash_in' => 'Cash In',
        'cash_out' => 'Cash Out',
        'petty_cash_withdrawal' => 'Petty Cash Withdrawal',
        'cash_deposit' => 'Move to Vault',
    ];
    $variantLabel = fn ($variant): string => $variant->product?->name.' / '.$variant->variant_name.' ('.$variant->sku.')';
    $statusClass = fn (string $status): string => match ($status) {
        'completed', 'paid', 'delivered' => 'success',
        'partially_paid', 'partially_returned', 'partially_refunded', 'pending', 'processing', 'out_for_delivery', 'customer_credit' => 'warning',
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
                $selectedTaxRate = $variant->product?->taxes?->sum(fn ($tax) => (float) $tax->rate) ?? 0;
                $taxRate = $variant->tax_behavior->value === 'taxable' ? (float) ($selectedTaxRate > 0 ? $selectedTaxRate : ($variant->tax_rate ?? $variant->product?->tax_rate ?? 0)) : 0;
                $priceMinor = $variant->selling_price_minor;
            @endphp
            <option value="{{ $variantLabel($variant) }}" data-variant-id="{{ $variant->id }}" data-price="{{ $priceMinor / 100 }}" data-tax-rate="{{ $taxRate }}" data-sku="{{ $variant->sku }}"></option>
        @endforeach
    </datalist>

    <style>
        .sales-header-context { margin-top: 8px; color: var(--muted); font-size: 13px; display: flex; gap: 14px; flex-wrap: wrap; }
        .sales-header-context strong { color: var(--ink); font-weight: 700; }
        .sales-grid { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(360px, .85fr); gap: 18px; align-items: start; }
        .sales-customer-grid { display: grid; grid-template-columns: 1fr; gap: 14px; }
        .sales-card { border: 1px solid var(--line); border-radius: var(--radius); background: var(--panel); padding: 20px; box-shadow: var(--shadow-sm); }
        .sales-card-title { margin: 0 0 16px; color: var(--ink); font-size: 15px; font-weight: 750; letter-spacing: -.01em; display: flex; align-items: center; gap: 9px; }
        .sales-card-icon { color: var(--brand); font-weight: 800; font-size: 20px; }
        .sales-primary-button { width: 100%; border: 0; border-radius: var(--radius-sm); background: var(--brand); color: #fff; padding: 13px 18px; cursor: pointer; font-size: 15px; font-weight: 700; box-shadow: 0 8px 18px -6px rgba(6,193,104,.4); transition: background .15s, transform .05s; }
        .sales-primary-button:hover { background: var(--brand-strong); }
        .sales-primary-button:active { transform: translateY(.5px); }
        .sales-summary-card { border: 1px solid var(--line); border-radius: var(--radius); background: var(--panel); overflow: hidden; position: sticky; top: 24px; box-shadow: var(--shadow-sm); }
        .sales-summary-header { background: linear-gradient(120deg, var(--brand-050), #f4fbf7); border-bottom: 1px solid var(--line); padding: 18px 22px; }
        .sales-summary-header h3 { margin: 0; color: var(--brand-strong); font-size: 17px; font-weight: 750; letter-spacing: -.01em; }
        .sales-summary-body { padding: 20px 22px; }
        .sales-product-entry-grid { display: grid; grid-template-columns: minmax(0, 1fr) 84px; gap: 14px; }
        .sales-product-quantity input { width: 100%; min-width: 0; padding-inline: 6px; text-align: center; }
        .sales-summary-expander { width: 100%; margin: 10px 0 0; display: flex; align-items: center; justify-content: space-between; gap: 10px; border: 1px solid var(--line); border-radius: var(--radius-sm); background: #fff; color: var(--ink-soft); padding: 10px 12px; cursor: pointer; font-size: 13.5px; font-weight: 700; text-align: left; }
        .sales-summary-expander:hover, .sales-summary-expander[aria-expanded="true"] { border-color: #a6f4c5; background: var(--brand-050); color: var(--brand-strong); }
        .sales-summary-expander svg { width: 16px; height: 16px; transition: transform .15s; }
        .sales-summary-expander[aria-expanded="true"] svg { transform: rotate(45deg); }
        .sales-summary-collapsible { margin: 8px 0 12px; border-radius: var(--radius-sm); background: var(--panel-soft); border: 1px solid var(--line); padding: 16px; display: grid; gap: 14px; }
        .sales-summary-collapsible[hidden] { display: none; }
        .sales-total-band { margin: 16px 0; border-radius: var(--radius); background: linear-gradient(120deg, var(--brand), var(--brand-strong)); color: #eafff5; padding: 18px 22px; display: flex; justify-content: space-between; align-items: center; gap: 16px; box-shadow: 0 10px 22px -10px rgba(6,193,104,.5); }
        .sales-total-band span { font-size: 15px; font-weight: 650; }
        .sales-total-band strong { font-size: 24px; line-height: 1.1; font-weight: 800; font-variant-numeric: tabular-nums; }
        .sales-change-box { border-radius: var(--radius-sm); background: var(--brand-050); color: var(--brand-strong); min-height: 56px; display: grid; place-items: center; font-size: 20px; font-weight: 750; border: 1px solid var(--brand-100); font-variant-numeric: tabular-nums; }
        .sales-change-box.due { background: var(--warn-bg); color: var(--warn); border-color: #fde3a7; }
        .sales-note-card textarea { min-height: 70px; line-height: 22px; resize: vertical; }
        .sales-delivery-note { margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--line); }
        .sales-tag { display: inline-flex; align-items: center; gap: 5px; border-radius: 999px; padding: 3px 10px; font-size: 12px; font-weight: 650; border: 1px solid transparent; white-space: nowrap; }
        .sales-tag.neutral { background: #eef2f6; color: #475467; border-color: #e3e8ef; }
        .sales-tag.success { background: var(--brand-050); color: #067647; border-color: #a6f4c5; }
        .sales-tag.warning { background: var(--warn-bg); color: var(--warn); border-color: #fde3a7; }
        .sales-tag.danger { background: var(--danger-bg); color: var(--danger-strong); border-color: var(--danger-border); }
        .link-button { border: 0; background: transparent; padding: 0; color: var(--brand-strong); cursor: pointer; font-weight: 700; text-align: left; }
        .link-button:hover { color: var(--brand); text-decoration: underline; text-underline-offset: 3px; }
        .order-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .order-actions .btn { padding: 7px 11px; }
        .btn.order-view { border-color: var(--brand-100); background: var(--brand-050); color: var(--brand-strong); }
        .btn.order-view:hover { border-color: #a6f4c5; background: var(--brand-100); color: #067647; }
        .btn.order-receipt { border-color: #bfdbfe; background: #eff6ff; color: #1d4ed8; }
        .btn.order-receipt:hover { border-color: #93c5fd; background: #dbeafe; color: #1e40af; }
        .btn.order-payment { border-color: var(--brand); background: var(--brand); color: #fff; box-shadow: 0 4px 12px -3px rgba(6,193,104,.35); }
        .btn.order-payment:hover { border-color: var(--brand-strong); background: var(--brand-strong); }
        .btn.order-return { border-color: #fed7aa; background: #fff7ed; color: #c2410c; }
        .btn.order-return:hover { border-color: #fdba74; background: #ffedd5; color: #9a3412; }
        .cart-row { border: 1px solid var(--line); border-left: 4px solid var(--brand); border-radius: var(--radius-sm); padding: 9px 12px; display: grid; grid-template-columns: minmax(0, 1fr) 58px 112px 28px; gap: 8px; align-items: center; background: var(--brand-050); }
        .cart-row strong { font-weight: 700; color: var(--ink); font-size: 14px; }
        .cart-row > span { font-weight: 750; color: var(--brand-strong); text-align: right; font-variant-numeric: tabular-nums; }
        .cart-row input[data-cart-qty] { width: 58px; min-width: 0; height: 34px; justify-self: center; border-radius: 7px; padding: 4px 5px; text-align: center; font-size: 13px; font-weight: 700; }
        .cart-row .cart-remove-button { width: 28px; height: 28px; border-radius: 7px; padding: 0; font-size: 12px; }
        .cart-remove-button { border-color: var(--danger-border); background: var(--danger-bg); color: var(--danger); font-weight: 800; }
        .cart-remove-button:hover { border-color: var(--danger); background: #fee4e2; color: var(--danger-strong); }
        .summary-cart-items { display: grid; gap: 8px; margin-bottom: 12px; }
        .summary-cart-item { display: grid; grid-template-columns: 1fr auto; gap: 10px; border: 1px solid var(--line); border-radius: var(--radius-sm); background: var(--panel-soft); padding: 9px 12px; font-size: 13.5px; color: var(--ink-soft); }
        .summary-cart-item strong { color: var(--ink); font-weight: 700; }
        .summary-cart-item span { color: var(--brand-strong); font-weight: 750; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .summary-line { display: flex; justify-content: space-between; gap: 12px; padding: 6px 0; color: var(--muted); font-size: 14px; }
        .summary-line strong { color: var(--ink-soft); font-weight: 700; font-variant-numeric: tabular-nums; }
        .summary-line.discount strong { color: var(--danger); }
        .summary-divider { border-top: 1px solid var(--line); margin: 14px 0; }
        .success-text { color: #067647; font-weight: 750; }
        .danger-text { color: var(--danger); font-weight: 750; }
        .sales-inline-check { margin-top: 14px; display: inline-flex; align-items: center; gap: 10px; color: var(--ink-soft); font-size: 14px; font-weight: 700; cursor: pointer; }
        .sales-input-error { border-color: var(--danger) !important; box-shadow: 0 0 0 3.5px rgba(220,38,38,.15) !important; }
        .sales-search-picker { position: relative; }
        .sales-search-control { display: grid; grid-template-columns: minmax(0, 1fr) 48px; border: 1px solid #d4ddd8; border-radius: var(--radius-sm); background: #fff; overflow: hidden; transition: border-color .15s, box-shadow .15s; }
        .sales-search-control:focus-within { border-color: var(--brand); box-shadow: 0 0 0 3.5px var(--brand-ring); }
        .sales-search-control input { min-width: 0; border: 0; border-radius: 0; box-shadow: none; }
        .sales-search-control input:focus { border: 0; box-shadow: none; }
        .sales-search-button { display: grid; place-items: center; border: 0; border-left: 1px solid var(--brand-strong); background: var(--brand); color: #fff; cursor: pointer; }
        .sales-search-button:hover { background: var(--brand-strong); }
        .sales-search-button svg { width: 22px; height: 22px; }
        .sales-search-options { position: absolute; z-index: 40; top: calc(100% + 6px); left: 0; right: 0; max-height: 280px; overflow-y: auto; border: 1px solid #d4ddd8; border-radius: var(--radius-sm); background: #fff; padding: 6px; box-shadow: 0 14px 30px -10px rgba(16,24,40,.28); }
        .sales-search-options[hidden] { display: none; }
        .sales-search-option { width: 100%; display: block; border: 0; border-radius: 7px; background: #fff; color: var(--ink); padding: 11px 12px; cursor: pointer; text-align: left; font-size: 14px; font-weight: 700; line-height: 1.35; }
        .sales-search-option:hover, .sales-search-option.active { background: var(--brand-050); color: var(--brand-strong); }
        .sales-search-option.add-customer { margin-bottom: 6px; border: 1px solid var(--brand-100); background: var(--brand-050); color: var(--brand-strong); }
        .sales-search-option.add-customer:hover, .sales-search-option.add-customer.active { background: var(--brand-100); }
        .sales-search-empty { padding: 12px; color: var(--muted); font-size: 13.5px; text-align: center; }
        .sales-pos-error { margin-top: 14px; padding: 12px 14px; border-radius: var(--radius-sm); background: var(--danger-bg); border: 1px solid var(--danger-border); color: var(--danger-strong); font-weight: 700; font-size: 13.5px; display: flex; gap: 8px; align-items: flex-start; }
        .sales-pos-error[hidden] { display: none; }
        .sales-confirm-section { display: grid; gap: 10px; }
        .sales-confirm-section + .sales-confirm-section { margin-top: 18px; padding-top: 18px; border-top: 1px solid var(--line); }
        .sales-confirm-heading { margin: 0; color: var(--ink); font-size: 14px; font-weight: 800; }
        .sales-confirm-items { display: grid; gap: 8px; }
        .sales-confirm-item { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 12px; align-items: center; border: 1px solid var(--line); border-radius: var(--radius-sm); background: var(--panel-soft); padding: 10px 12px; }
        .sales-confirm-item strong, .sales-confirm-item small { display: block; }
        .sales-confirm-item strong { color: var(--ink); font-size: 13.5px; }
        .sales-confirm-item small { margin-top: 2px; color: var(--muted); font-size: 12px; }
        .sales-confirm-item > span:last-child { color: var(--brand-strong); font-weight: 800; white-space: nowrap; }
        .sales-confirm-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .sales-confirm-detail { border-radius: var(--radius-sm); background: var(--panel-soft); padding: 10px 12px; }
        .sales-confirm-detail span { display: block; color: var(--muted); font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }
        .sales-confirm-detail strong { display: block; margin-top: 2px; color: var(--ink); font-size: 13.5px; overflow-wrap: anywhere; }
        .sales-confirm-totals { display: grid; gap: 7px; }
        .sales-confirm-total-line { display: flex; justify-content: space-between; gap: 14px; color: var(--muted); font-size: 13.5px; }
        .sales-confirm-total-line strong { color: var(--ink-soft); }
        .sales-confirm-total-line.grand { margin-top: 5px; padding-top: 11px; border-top: 1px solid var(--line); color: var(--ink); font-size: 16px; font-weight: 800; }
        .sales-confirm-total-line.grand strong { color: var(--brand-strong); font-size: 19px; }
        .till-locked-pos { border: 1px dashed var(--line); border-radius: var(--radius); padding: 24px; text-align: center; background: var(--panel-soft); display: grid; gap: 6px; justify-items: center; }
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
            body:has(dialog[open]) .sidebar, body:has(dialog[open]) .topbar, body:has(dialog[open]) .tab-layout { display: none; }
            dialog[open] { display: block; position: static; width: 100%; max-width: none; box-shadow: none; }
            dialog[open]::backdrop, dialog[open] .dialog-header .icon-btn, dialog[open] [data-print-dialog], dialog[open] [data-dialog-close] { display: none; }
            .dialog-body { max-height: none; overflow: visible; }
            dialog[open].thermal-receipt-dialog { width: 80mm; max-width: 80mm; margin: 0 auto; }
            dialog[open].thermal-receipt-dialog .dialog-header, dialog[open].thermal-receipt-dialog .receipt-actions { display: none; }
            dialog[open].thermal-receipt-dialog .dialog-body { padding: 0; background: #fff; }
            dialog[open].thermal-receipt-dialog .thermal-receipt-paper { width: 80mm; max-width: 80mm; padding: 4mm 3mm; box-shadow: none; }
        }
        @media (max-width: 1200px) { .sales-grid { grid-template-columns: 1fr; } .sales-summary-card { position: static; } }
        @media (max-width: 700px) {
            #pos > .panel-body { padding: 14px; }
            .sales-card { padding: 16px; }
            .sales-product-entry-grid { grid-template-columns: 1fr; }
            .sales-product-quantity input { min-height: 44px; padding-inline: 12px; text-align: left; }
            .cart-row {
                grid-template-columns: 88px minmax(0, 1fr) 40px;
                grid-template-areas:
                    "product product product"
                    "quantity price remove";
                gap: 12px 10px;
                padding: 12px;
            }
            .cart-row strong { grid-area: product; overflow-wrap: anywhere; line-height: 1.45; }
            .cart-row input[data-cart-qty] { grid-area: quantity; width: 88px; height: 40px; justify-self: start; }
            .cart-row > span { grid-area: price; align-self: center; white-space: nowrap; }
            .cart-row .cart-remove-button { grid-area: remove; width: 40px; height: 40px; justify-self: end; font-size: 14px; }
            .sales-summary-body { padding: 16px; }
            .sales-total-band { padding: 16px; }
        }
        @media (max-width: 420px) {
            .sales-card { padding: 14px; }
            .cart-row {
                grid-template-columns: 76px minmax(0, 1fr) 40px;
                padding: 11px;
            }
            .cart-row input[data-cart-qty] { width: 76px; }
            .sales-total-band { align-items: flex-start; flex-direction: column; gap: 6px; }
            .sales-total-band strong { font-size: 21px; }
            .sales-confirm-grid { grid-template-columns: 1fr; }
            .sales-confirm-item { align-items: start; }
        }
    </style>

    <div class="topbar">
        <div>
            <div class="eyebrow">Offline sales · invoicing · payments</div>
            <h1>Record Sale</h1>
            <p class="subtle">Record offline and back-office sales at any time, without opening a till. Manage invoices, receipts, credit sales, returns and refunds for {{ $tenant->name }}. For live counter selling with till sessions, use <a href="{{ route('admin.sales.retail-pos', ['tenant' => $tenant->id]) }}" style="color:var(--brand-strong); font-weight:700;">Retail POS</a>.</p>
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
            <a href="#pos" role="tab" data-tab-target="pos">Record sale</a>
            <a href="#orders" role="tab" data-tab-target="orders">Orders <span class="badge neutral">{{ $orders->count() }}</span></a>
            <a href="#returns" role="tab" data-tab-target="returns">Returns</a>
        </nav>

        <div class="content-stack">
            <section class="panel tab-panel" id="pos" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Record Offline Sale</h2>
                        <p class="subtle">Search customers and products, record payment, and update stock without a till session.</p>
                        @if ($recordSaleBranch)
                            <div class="sales-header-context">
                                <span>Branch: <strong>{{ $recordSaleBranch->name }}</strong></span>
                                <span>Inventory location: <strong>{{ $recordSaleLocation?->name ?? 'No location' }}</strong></span>
                                <span>Order date: <strong>{{ now()->toDateString() }}</strong></span>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="panel-body">
                    <form class="mini-form" method="POST" action="{{ route('admin.sales.orders.store') }}" data-pos-form>
                        @csrf
                        <input type="hidden" name="source" value="offline">
                        <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                        <input type="hidden" name="branch_id" value="{{ $recordSaleBranch?->id }}">
                        <input type="hidden" name="inventory_location_id" value="{{ $recordSaleLocation?->id }}">
                        <input type="hidden" name="order_date" value="{{ now()->toDateString() }}">
                        <div class="sales-grid">
                            <div class="stack">
                                <div class="sales-card">
                                    <h3 class="sales-card-title">Sale Information</h3>
                                    <div class="sales-customer-grid">
                                        <div class="field" data-sales-customer-picker>
                                            <label>Customer</label>
                                            <div class="sales-search-picker" data-sales-search-picker>
                                                <div class="sales-search-control">
                                                    <input type="text" data-sales-customer-search data-sales-options-id="sales-customer-options" value="{{ $walkInCustomer->name }} · {{ $walkInCustomer->phone }}" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" required>
                                                    <button class="sales-search-button" type="button" data-sales-search-toggle aria-label="Search customers">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m16 16 5 5"/></svg>
                                                    </button>
                                                </div>
                                                <div class="sales-search-options" data-sales-search-options role="listbox" hidden></div>
                                            </div>
                                            <input type="hidden" name="customer_id" data-sales-customer-value value="{{ $walkInCustomer->id }}">
                                        </div>
                                    </div>
                                </div>
                                <div class="sales-card">
                                    <h3 class="sales-card-title"><span class="sales-card-icon">+</span> Add Product to Cart</h3>
                                    <div style="display: grid; gap: 14px;">
                                        <div class="sales-product-entry-grid">
                                            <div class="field">
                                                <label>Search Product, Variant or SKU</label>
                                                <div class="sales-search-picker" data-sales-search-picker>
                                                    <div class="sales-search-control">
                                                        <input type="text" data-sales-product-search data-sales-options-id="sales-product-options" placeholder="Type to search..." autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false">
                                                        <button class="sales-search-button" type="button" data-sales-search-toggle aria-label="Search products">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m16 16 5 5"/></svg>
                                                        </button>
                                                    </div>
                                                    <div class="sales-search-options" data-sales-search-options role="listbox" hidden></div>
                                                </div>
                                            </div>
                                            <div class="field sales-product-quantity"><label>Quantity</label><input type="number" min="1" step="1" value="1" data-sales-product-qty></div>
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
                                </div>
                            </div>
                            <div class="sales-summary-card">
                                <div class="sales-summary-header"><h3>Order Summary</h3></div>
                                <div class="sales-summary-body">
                                    <div class="summary-cart-items" data-sales-summary-items></div>
                                    <div class="summary-line"><span>Subtotal</span><strong data-sales-subtotal>{{ $currencySymbol }} 0.00</strong></div>
                                    <div class="summary-line"><span>Tax</span><strong data-sales-tax>{{ $currencySymbol }} 0.00</strong></div>
                                    <div class="summary-divider"></div>
                                    <button class="sales-summary-expander" type="button" data-sales-delivery-toggle aria-expanded="false">
                                        <span>Delivery details</span>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                                    </button>
                                    <div class="sales-summary-collapsible" data-sales-delivery-panel hidden>
                                        <div class="form-grid">
                                            <div class="field"><label>Delivery Method</label><select name="delivery_method" data-sales-delivery-method><option value="">No delivery</option>@foreach ($deliveryMethods as $method)<option value="{{ $method['name'] }}" data-price="{{ $method['price'] ?? 0 }}" @selected(old('delivery_method') === $method['name'])>{{ $method['name'] }}</option>@endforeach</select></div>
                                            <div class="field"><label>Shipping Fee</label><input name="shipping" type="text" inputmode="decimal" data-money-input data-sales-shipping value="{{ old('shipping', '0.00') }}"></div>
                                        </div>
                                        <div class="field"><label>Delivery Status</label><select name="delivery_status" required><option value="pending" @selected(old('delivery_status', 'pending') === 'pending')>Pending</option><option value="processing" @selected(old('delivery_status') === 'processing')>Processing</option><option value="out_for_delivery" @selected(old('delivery_status') === 'out_for_delivery')>Out for delivery</option><option value="delivered" @selected(old('delivery_status') === 'delivered')>Delivered</option><option value="failed" @selected(old('delivery_status') === 'failed')>Failed delivery</option><option value="returned" @selected(old('delivery_status') === 'returned')>Returned</option></select></div>
                                    </div>
                                    <button class="sales-summary-expander" type="button" data-sales-discount-toggle aria-expanded="false">
                                        <span>Coupon &amp; admin discount</span>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                                    </button>
                                    <div class="sales-summary-collapsible" data-sales-discount-panel hidden>
                                        <div class="field"><label>Coupon Code</label><input name="coupon_code" data-sales-coupon-code placeholder="Enter code" value="{{ old('coupon_code') }}">@foreach ($coupons as $coupon)<span hidden data-sales-coupon data-code="{{ $coupon->code }}" data-type="{{ $coupon->discount_type->value }}" data-amount="{{ $coupon->discount_value_minor / 100 }}" data-percent="{{ $coupon->discount_percent }}"></span>@endforeach</div>
                                        <div class="form-grid">
                                            <div class="field"><label>Admin Discount</label><select name="admin_discount_type" data-sales-admin-discount-type><option value="amount" @selected(old('admin_discount_type', 'amount') === 'amount')>Amount ({{ $currencySymbol }})</option><option value="percentage" @selected(old('admin_discount_type') === 'percentage')>Percentage (%)</option></select></div>
                                            <div class="field"><label>Value</label><input name="admin_discount_value" type="text" inputmode="decimal" data-money-input data-sales-admin-discount value="{{ old('admin_discount_value', '0') }}"></div>
                                        </div>
                                    </div>
                                    <div class="summary-line discount"><span>Coupon Discount</span><strong data-sales-coupon-discount>-{{ $currencySymbol }} 0.00</strong></div>
                                    <div class="summary-line discount"><span>Admin Discount</span><strong data-sales-admin-discount-label>-{{ $currencySymbol }} 0.00</strong></div>
                                    <div class="sales-total-band"><span>Total</span><strong data-sales-total>{{ $currencySymbol }} 0.00</strong></div>
                                    <div class="summary-divider"></div>
                                    <div class="field"><label>Payment Method</label><select name="payment_method" data-payment-method-selector>@foreach ($paymentMethods as $method)<option value="{{ $method }}">{{ strtoupper($method) }}</option>@endforeach</select></div>
                                    <div class="field" data-payment-account-wrapper hidden>
                                        <label>Receiving account</label>
                                        <select name="business_payment_account_id" data-payment-account-selector disabled>
                                            <option value="">Select receiving account</option>
                                            @foreach ($paymentAccounts as $account)
                                                @foreach ((array) $account->supported_payment_methods as $method)
                                                    <option value="{{ $account->id }}" data-account-method="{{ $method }}">{{ $account->identifier }}</option>
                                                @endforeach
                                            @endforeach
                                        </select>
                                        <span class="subtle" data-payment-account-empty hidden>No active account supports this payment method for this branch.</span>
                                    </div>
                                    <label class="sales-inline-check"><input type="checkbox" name="is_credit_sale" value="1" data-sales-credit> <span>Mark as Credit Sale</span></label>
                                    <p class="subtle" data-sales-credit-hint hidden style="margin: 6px 0 0;">Collect a deposit (part payment) or nothing now — the balance is recorded as the customer's outstanding credit.</p>
                                    <div class="form-grid" style="margin-top: 16px;">
                                        <div class="field"><label>Amount Paid</label><input name="amount_paid" type="text" inputmode="decimal" data-money-input data-sales-paid value="0.00" style="font-size: 22px; font-weight: 900;"></div>
                                        <div class="field"><label data-sales-change-label>Change</label><div class="sales-change-box" data-sales-change>{{ $currencySymbol }} 0.00</div></div>
                                    </div>
                                    <div class="sales-pos-error" data-pos-error hidden></div>
                                    <button class="sales-primary-button sales-submit-action" type="submit">Create sales order</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </section>

            <section class="panel tab-panel" id="orders" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header"><div><h2 class="panel-title">Order listing</h2><p class="subtle">Sales orders, invoices, receipts, payment status, and credit balances.</p></div></div>
                <div class="panel-body">
                    <form class="form-grid" method="GET" action="{{ route('admin.sales.index') }}#orders" style="margin-bottom: 16px;">
                        <input type="hidden" name="tenant" value="{{ $tenant->id }}">
                        <div class="field"><label>Search orders</label><input name="order_search" value="{{ $orderSearch }}" placeholder="Order, invoice, receipt, customer, phone"></div>
                        <div class="button-row" style="justify-content: flex-start;"><button class="btn primary" type="submit">Search</button><a class="btn secondary" href="{{ route('admin.sales.index', ['tenant' => $tenant->id]).'#orders' }}">Reset</a></div>
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
                                    <td>{{ $currencySymbol }} {{ $money($order->total_minor) }}</td>
                                    <td>{{ $currencySymbol }} {{ $money($order->paid_minor) }}</td>
                                    <td><div class="order-actions"><button class="btn order-view" type="button" data-dialog-open="order-view-{{ $order->id }}">View</button><button class="btn order-receipt" type="button" data-dialog-open="sales-receipt-{{ $order->id }}">Receipt</button>@if ($order->balance_minor > 0 && $canRecordOrderPayment($order))<button class="btn order-payment" type="button" data-dialog-open="order-payment-{{ $order->id }}">Add payment</button>@endif<button class="btn order-return" type="button" data-dialog-open="order-return-{{ $order->id }}">Return</button></div></td>
                                </tr>
                            @empty
                                <tr><td colspan="8"><div class="empty">No sales orders yet.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel tab-panel" id="returns" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header"><div><h2 class="panel-title">Sales returns & refunds</h2><p class="subtle">Return history and refunded value from sales orders.</p></div></div>
                <div class="panel-body">
                    <table class="table"><thead><tr><th>Return</th><th>Order</th><th>Date</th><th>Refund</th><th>Reason</th></tr></thead><tbody>@forelse ($allOrders->flatMap->returns as $return)<tr><td>{{ $return->return_number }}</td><td>{{ $return->order->order_number }}</td><td>{{ $return->return_date->format('M j, Y') }}</td><td>{{ $currencySymbol }} {{ $money($return->refund_minor) }}</td><td>{{ $return->reason ?: 'Not set' }}</td></tr>@empty<tr><td colspan="5"><div class="empty">No returns yet.</div></td></tr>@endforelse</tbody></table>
                </div>
            </section>
        </div>
    </div>

    {{-- Till movement & breakdown dialogs now live in Retail POS. --}}
    <dialog class="dialog" id="record-sale-customer-dialog" style="width:min(560px,calc(100vw - 24px));">
        <div class="dialog-header">
            <div>
                <h2 class="panel-title">Add new customer</h2>
                <p class="subtle">Create and select a customer without leaving this sale.</p>
            </div>
            <button class="icon-btn" type="button" data-dialog-close aria-label="Close">✕</button>
        </div>
        <div class="dialog-body">
            <form class="mini-form" method="POST" action="{{ route('admin.sales.customers.quick') }}" data-record-sale-customer-form>
                @csrf
                <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                <div class="form-grid">
                    <div class="field"><label>First name</label><input name="first_name" autocomplete="given-name" required></div>
                    <div class="field"><label>Last name</label><input name="last_name" autocomplete="family-name"></div>
                    <div class="field"><label>Phone</label><input name="phone" type="tel" autocomplete="tel" required></div>
                    <div class="field"><label>Email</label><input name="email" type="email" autocomplete="email"></div>
                </div>
                <p class="sales-pos-error" data-record-sale-customer-error hidden></p>
                <div class="button-row">
                    <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                    <button class="btn primary" type="submit" data-record-sale-customer-submit>Create &amp; select</button>
                </div>
            </form>
        </div>
    </dialog>

    <dialog class="dialog" id="record-sale-confirmation-dialog" style="width:min(680px,calc(100vw - 24px));">
        <div class="dialog-header">
            <div>
                <h2 class="panel-title">Confirm sales order</h2>
                <p class="subtle">Review the details carefully before creating this order.</p>
            </div>
            <button class="icon-btn" type="button" data-dialog-close aria-label="Close">✕</button>
        </div>
        <div class="dialog-body">
            <div class="sales-confirm-section">
                <div class="sales-confirm-grid">
                    <div class="sales-confirm-detail"><span>Customer</span><strong data-confirm-customer></strong></div>
                    <div class="sales-confirm-detail"><span>Payment</span><strong data-confirm-payment></strong></div>
                    <div class="sales-confirm-detail"><span>Delivery</span><strong data-confirm-delivery></strong></div>
                    <div class="sales-confirm-detail"><span data-confirm-balance-label>Amount paid</span><strong data-confirm-balance></strong></div>
                </div>
            </div>
            <div class="sales-confirm-section">
                <h3 class="sales-confirm-heading">Items</h3>
                <div class="sales-confirm-items" data-confirm-items></div>
            </div>
            <div class="sales-confirm-section sales-confirm-totals">
                <div class="sales-confirm-total-line"><span>Subtotal</span><strong data-confirm-subtotal></strong></div>
                <div class="sales-confirm-total-line"><span>Tax</span><strong data-confirm-tax></strong></div>
                <div class="sales-confirm-total-line"><span>Shipping</span><strong data-confirm-shipping></strong></div>
                <div class="sales-confirm-total-line"><span>Coupon discount</span><strong data-confirm-coupon></strong></div>
                <div class="sales-confirm-total-line"><span>Admin discount</span><strong data-confirm-admin-discount></strong></div>
                <div class="sales-confirm-total-line"><span>Amount paid</span><strong data-confirm-amount-paid></strong></div>
                <div class="sales-confirm-total-line grand"><span>Order total</span><strong data-confirm-total></strong></div>
            </div>
            <div class="button-row">
                <button class="btn secondary" type="button" data-dialog-close>Go back and edit</button>
                <button class="btn primary" type="button" data-confirm-sales-order>Confirm &amp; create order</button>
            </div>
        </div>
    </dialog>

    @foreach ($allOrders as $order)
        @include('sales::admin.partials.order-view-dialog', ['order' => $order])
        @include('sales::admin.partials.invoice-dialog', ['order' => $order])
        @include('sales::admin.partials.standard-receipt-dialog', ['order' => $order])
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
        const autoInvoiceOrderId = @json(session('invoice_order_id'));
        if (autoInvoiceOrderId) {
            window.setTimeout(() => {
                document.getElementById(`invoice-${autoInvoiceOrderId}`)?.showModal();
            }, 160);
        }
        const currency = @json($currencySymbol);
        const fmt = (value) => `${currency} ${Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        const clean = (value) => Number(String(value || '0').replace(/,/g, '')) || 0;
        const formatMoneyInput = (value) => clean(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const escapeHtml = (value) => String(value || '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
        const dataOptionsFor = (input) => Array.from(document.getElementById(input?.dataset.salesOptionsId || '')?.options || []);
        const selectedDataOption = (input) => dataOptionsFor(input).find((option) => option.value === input?.value);

        document.querySelectorAll('[data-sales-search-picker]').forEach((picker, pickerIndex) => {
            const input = picker.querySelector('input[role="combobox"]');
            const panel = picker.querySelector('[data-sales-search-options]');
            const toggle = picker.querySelector('[data-sales-search-toggle]');
            if (!input || !panel || !toggle) return;

            const panelId = `sales-search-options-${pickerIndex}`;
            const isCustomerPicker = !!picker.closest('[data-sales-customer-picker]');
            let activeIndex = -1;
            panel.id = panelId;
            input.setAttribute('aria-controls', panelId);

            const close = () => {
                panel.hidden = true;
                input.setAttribute('aria-expanded', 'false');
                activeIndex = -1;
            };
            const activate = (index) => {
                const rows = Array.from(panel.querySelectorAll('[data-sales-search-option]'));
                if (!rows.length) return;
                activeIndex = Math.max(0, Math.min(index, rows.length - 1));
                rows.forEach((row, rowIndex) => row.classList.toggle('active', rowIndex === activeIndex));
                rows[activeIndex].scrollIntoView({ block: 'nearest' });
            };
            const select = (option) => {
                if (!option) return;
                input.value = option.value;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
                close();
                input.focus();
            };
            const render = (showAll = false) => {
                const query = showAll ? '' : input.value.trim().toLowerCase();
                const matches = dataOptionsFor(input)
                    .filter((option) => !query || option.value.toLowerCase().includes(query) || (option.dataset.sku || '').toLowerCase().includes(query))
                    .slice(0, 60);

                panel.innerHTML = '';
                if (isCustomerPicker) {
                    const addCustomer = document.createElement('button');
                    addCustomer.type = 'button';
                    addCustomer.setAttribute('role', 'option');
                    addCustomer.className = 'sales-search-option add-customer';
                    addCustomer.dataset.salesSearchOption = 'add-customer';
                    addCustomer.textContent = '+ Add new customer';
                    addCustomer.addEventListener('mousedown', (event) => event.preventDefault());
                    addCustomer.addEventListener('click', () => {
                        close();
                        const dialog = document.getElementById('record-sale-customer-dialog');
                        dialog?.showModal();
                        window.setTimeout(() => dialog?.querySelector('[name="first_name"]')?.focus(), 0);
                    });
                    panel.append(addCustomer);
                }

                if (!matches.length) {
                    const empty = document.createElement('div');
                    empty.className = 'sales-search-empty';
                    empty.textContent = isCustomerPicker ? 'No matching customers' : 'No matching options';
                    panel.append(empty);
                } else {
                    matches.forEach((option, index) => {
                        const row = document.createElement('button');
                        row.type = 'button';
                        row.setAttribute('role', 'option');
                        row.className = 'sales-search-option';
                        row.dataset.salesSearchOption = String(index);
                        row.textContent = option.value;
                        row.addEventListener('mousedown', (event) => event.preventDefault());
                        row.addEventListener('click', () => select(option));
                        panel.append(row);
                    });
                }
                panel.hidden = false;
                input.setAttribute('aria-expanded', 'true');
                activeIndex = -1;
            };

            input.addEventListener('focus', () => render(selectedDataOption(input) !== undefined));
            input.addEventListener('input', () => render(false));
            input.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    close();
                    return;
                }
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    if (panel.hidden) render(false);
                    activate(activeIndex + (event.key === 'ArrowDown' ? 1 : -1));
                    return;
                }
                if (event.key === 'Enter' && !panel.hidden && activeIndex >= 0) {
                    event.preventDefault();
                    event.stopPropagation();
                    panel.querySelectorAll('[data-sales-search-option]')[activeIndex]?.click();
                }
            });
            toggle.addEventListener('click', () => {
                if (panel.hidden) {
                    render(true);
                    input.focus();
                } else {
                    close();
                }
            });
            picker.addEventListener('focusout', (event) => {
                if (!picker.contains(event.relatedTarget)) close();
            });
        });

        document.addEventListener('click', (event) => {
            document.querySelectorAll('[data-sales-search-picker]').forEach((picker) => {
                if (picker.contains(event.target)) return;
                const input = picker.querySelector('input[role="combobox"]');
                const panel = picker.querySelector('[data-sales-search-options]');
                if (panel) panel.hidden = true;
                if (input) input.setAttribute('aria-expanded', 'false');
            });
        });

        const recordSaleCustomerForm = document.querySelector('[data-record-sale-customer-form]');
        recordSaleCustomerForm?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const submit = recordSaleCustomerForm.querySelector('[data-record-sale-customer-submit]');
            const error = recordSaleCustomerForm.querySelector('[data-record-sale-customer-error]');
            const originalText = submit?.textContent || 'Create & select';

            if (error) error.hidden = true;
            if (submit) {
                submit.disabled = true;
                submit.textContent = 'Creating...';
            }

            try {
                const response = await fetch(recordSaleCustomerForm.action, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(recordSaleCustomerForm),
                });
                const result = await response.json();

                if (!response.ok) {
                    const validationMessage = Object.values(result.errors || {}).flat()[0];
                    throw new Error(validationMessage || result.message || 'Could not create the customer.');
                }

                const label = `${result.name} · ${result.phone}`;
                const options = document.getElementById('sales-customer-options');
                const option = document.createElement('option');
                option.value = label;
                option.dataset.customerId = String(result.id);
                options?.append(option);

                const customerSearch = document.querySelector('[data-sales-customer-search]');
                const customerValue = document.querySelector('[data-sales-customer-value]');
                if (customerSearch) {
                    customerSearch.value = label;
                    customerSearch.setCustomValidity('');
                }
                if (customerValue) customerValue.value = String(result.id);

                savePosState(customerSearch?.closest('form'));
                recordSaleCustomerForm.reset();
                recordSaleCustomerForm.closest('dialog')?.close();
                customerSearch?.focus();
            } catch (failure) {
                if (error) {
                    error.textContent = failure.message || 'Could not create the customer. Check the details and try again.';
                    error.hidden = false;
                }
            } finally {
                if (submit) {
                    submit.disabled = false;
                    submit.textContent = originalText;
                }
            }
        });

        const cart = [];
        function setDiscountPanel(form, open) {
            const panel = form?.querySelector('[data-sales-discount-panel]');
            const toggle = form?.querySelector('[data-sales-discount-toggle]');
            if (!panel || !toggle) return;
            panel.hidden = !open;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        function hasEnteredDiscount(form) {
            return (form?.querySelector('[data-sales-coupon-code]')?.value || '').trim() !== ''
                || clean(form?.querySelector('[data-sales-admin-discount]')?.value) > 0;
        }
        function setDeliveryPanel(form, open) {
            const panel = form?.querySelector('[data-sales-delivery-panel]');
            const toggle = form?.querySelector('[data-sales-delivery-toggle]');
            if (!panel || !toggle) return;
            panel.hidden = !open;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        function hasEnteredDelivery(form) {
            return (form?.querySelector('[data-sales-delivery-method]')?.value || '') !== ''
                || clean(form?.querySelector('[data-sales-shipping]')?.value) > 0
                || (form?.querySelector('[name="delivery_status"]')?.value || 'pending') !== 'pending';
        }
        document.querySelectorAll('[data-sales-discount-toggle]').forEach((toggle) => {
            toggle.addEventListener('click', () => {
                const form = toggle.closest('form');
                setDiscountPanel(form, toggle.getAttribute('aria-expanded') !== 'true');
            });
        });
        document.querySelectorAll('[data-sales-delivery-toggle]').forEach((toggle) => {
            toggle.addEventListener('click', () => {
                const form = toggle.closest('form');
                setDeliveryPanel(form, toggle.getAttribute('aria-expanded') !== 'true');
            });
        });

        // Walk-in id is the server-rendered default customer, captured before any
        // restore overwrites the field — used to block walk-in credit sales.
        const walkInCustomerId = document.querySelector('[data-pos-form] [data-sales-customer-value]')?.value ?? null;

        // The cart is client-side state only, so a server-side validation error that
        // reloads the page would otherwise wipe it. Persist the whole in-progress sale
        // to sessionStorage and restore it on load; clear it once a sale completes.
        function posStateKey(form) {
            const tenant = form.querySelector('[name="tenant_id"]')?.value || '';
            const till = form.querySelector('[name="sales_till_session_id"]')?.value || '';
            return `sb-sales-pos-${tenant}-${till}`;
        }
        function savePosState(form) {
            if (!form || !form.matches('[data-pos-form]')) return;
            try {
                const val = (sel) => form.querySelector(sel)?.value ?? null;
                sessionStorage.setItem(posStateKey(form), JSON.stringify({
                    cart,
                    customerId: val('[data-sales-customer-value]'),
                    customerLabel: val('[data-sales-customer-search]'),
                    coupon: val('[data-sales-coupon-code]'),
                    adminType: val('[data-sales-admin-discount-type]'),
                    adminValue: val('[data-sales-admin-discount]'),
                    shipping: val('[data-sales-shipping]'),
                    paid: val('[data-sales-paid]'),
                    method: val('[data-payment-method-selector]'),
                    account: val('[data-payment-account-selector]'),
                    credit: !!form.querySelector('[name="is_credit_sale"]')?.checked,
                    notes: val('[name="notes"]'),
                    deliveryMethod: val('[data-sales-delivery-method]'),
                    deliveryAddress: val('[name="delivery_address"]'),
                    deliveryStatus: val('[name="delivery_status"]'),
                }));
            } catch (e) { /* storage unavailable — best effort only */ }
        }
        function clearPosState(form) {
            try { sessionStorage.removeItem(posStateKey(form)); } catch (e) {}
        }
        function restorePosState(form) {
            let saved;
            try { saved = JSON.parse(sessionStorage.getItem(posStateKey(form)) || 'null'); } catch (e) { saved = null; }
            if (!saved || !Array.isArray(saved.cart) || !saved.cart.length) return;
            const set = (sel, v) => { const el = form.querySelector(sel); if (el && v != null) el.value = v; };
            cart.length = 0;
            saved.cart.forEach((item) => cart.push(item));
            set('[data-sales-customer-search]', saved.customerLabel);
            set('[data-sales-customer-value]', saved.customerId);
            set('[data-sales-coupon-code]', saved.coupon);
            set('[data-sales-admin-discount-type]', saved.adminType);
            set('[data-sales-admin-discount]', saved.adminValue);
            set('[data-sales-shipping]', saved.shipping);
            set('[data-sales-paid]', saved.paid);
            set('[data-payment-method-selector]', saved.method);
            set('[name="notes"]', saved.notes);
            set('[data-sales-delivery-method]', saved.deliveryMethod);
            set('[name="delivery_address"]', saved.deliveryAddress);
            set('[name="delivery_status"]', saved.deliveryStatus);
            const creditBox = form.querySelector('[name="is_credit_sale"]');
            if (creditBox) creditBox.checked = !!saved.credit;
            const creditHint = form.querySelector('[data-sales-credit-hint]');
            if (creditHint && creditBox) creditHint.hidden = !creditBox.checked;
            renderCart(form);
            syncPaymentAccountSelector(form);
            if (saved.account) { const acc = form.querySelector('[data-payment-account-selector]'); if (acc && !acc.disabled) acc.value = saved.account; }
        }

        function computeTotals(form) {
            let subtotal = 0;
            let tax = 0;
            cart.forEach((item) => {
                subtotal += item.quantity * item.price;
                tax += item.quantity * item.price * (item.taxRate / 100);
            });
            const shipping = clean(form.querySelector('[data-sales-shipping]')?.value);
            const couponCode = (form.querySelector('[data-sales-coupon-code]')?.value || '').trim().toUpperCase();
            const coupon = Array.from(form.querySelectorAll('[data-sales-coupon]')).find((item) => (item.dataset.code || '').toUpperCase() === couponCode);
            const couponDiscount = coupon ? Math.min(subtotal, coupon.dataset.type === 'percentage' ? subtotal * (clean(coupon.dataset.percent) / 100) : clean(coupon.dataset.amount)) : 0;
            const adminType = form.querySelector('[data-sales-admin-discount-type]')?.value;
            const adminValue = clean(form.querySelector('[data-sales-admin-discount]')?.value);
            const adminDiscount = Math.min(subtotal, adminType === 'percentage' ? subtotal * (adminValue / 100) : adminValue);
            const total = Math.max(0, subtotal + tax + shipping - couponDiscount - adminDiscount);
            const paid = clean(form.querySelector('[data-sales-paid]')?.value);
            return { subtotal, tax, couponDiscount, adminDiscount, total, paid };
        }

        // Recompute the summary panel + totals. Never rebuilds the editable cart
        // rows, so it is safe to call while a cart quantity field has focus.
        function renderSummary(form) {
            const summaryItems = form.querySelector('[data-sales-summary-items]');
            if (summaryItems) {
                summaryItems.innerHTML = '';
                cart.forEach((item) => {
                    summaryItems.insertAdjacentHTML('beforeend', `<div class="summary-cart-item"><strong>${escapeHtml(item.label)} x ${item.quantity}</strong><span>${fmt(item.quantity * item.price)}</span></div>`);
                });
            }
            const t = computeTotals(form);
            form.querySelector('[data-sales-subtotal]').textContent = fmt(t.subtotal);
            form.querySelector('[data-sales-tax]').textContent = fmt(t.tax);
            form.querySelector('[data-sales-coupon-discount]').textContent = `-${fmt(t.couponDiscount)}`;
            form.querySelector('[data-sales-admin-discount-label]').textContent = `-${fmt(t.adminDiscount)}`;
            form.querySelector('[data-sales-total]').textContent = fmt(t.total);

            // Reflect a part payment: show the outstanding balance when underpaid,
            // otherwise the change to hand back.
            const changeBox = form.querySelector('[data-sales-change]');
            const changeLabel = form.querySelector('[data-sales-change-label]');
            const shortfall = t.total - t.paid;
            if (shortfall > 0.001) {
                if (changeLabel) changeLabel.textContent = 'Balance due';
                if (changeBox) { changeBox.textContent = fmt(shortfall); changeBox.classList.add('due'); }
            } else {
                if (changeLabel) changeLabel.textContent = 'Change';
                if (changeBox) { changeBox.textContent = fmt(Math.max(0, -shortfall)); changeBox.classList.remove('due'); }
            }
            savePosState(form);
        }

        // Rebuild the editable cart rows + hidden submit inputs, then the summary.
        // Call on add / remove / blur — not on every keystroke.
        function renderCart(form) {
            const rows = form.querySelector('[data-sales-cart]');
            const hidden = form.querySelector('[data-sales-items]');
            rows.innerHTML = '';
            hidden.innerHTML = '';
            cart.forEach((item, index) => {
                rows.insertAdjacentHTML('beforeend', `<div class="cart-row"><strong>${escapeHtml(item.label)}</strong><input type="number" min="1" step="1" value="${item.quantity}" data-cart-qty="${index}"><span data-cart-line="${index}">${fmt(item.quantity * item.price)}</span><button class="icon-btn cart-remove-button" type="button" data-cart-remove="${index}" aria-label="Remove item">X</button></div>`);
                hidden.insertAdjacentHTML('beforeend', `<input type="hidden" name="items[${index}][product_variant_id]" value="${item.id}"><input type="hidden" name="items[${index}][quantity]" data-cart-hidden-qty="${index}" value="${item.quantity}"><input type="hidden" name="items[${index}][unit_price]" value="${item.price.toFixed(2)}">`);
            });
            renderSummary(form);
        }

        function addSelectedProduct(form) {
            const search = form.querySelector('[data-sales-product-search]');
            const qty = Math.max(1, Number(form.querySelector('[data-sales-product-qty]')?.value || 1));
            const option = selectedDataOption(search);
            if (!option) {
                if (search.value.trim() !== '') {
                    search.classList.add('sales-input-error');
                    setTimeout(() => search.classList.remove('sales-input-error'), 1200);
                }
                search.focus();
                return false;
            }
            const existing = cart.find((item) => item.id === option.dataset.variantId);
            if (existing) existing.quantity += qty;
            else cart.push({ id: option.dataset.variantId, label: option.value, quantity: qty, price: clean(option.dataset.price), taxRate: clean(option.dataset.taxRate) });
            search.value = '';
            search.focus();
            renderCart(form);

            return true;
        }

        document.addEventListener('input', (event) => {
            // Editing a cart quantity: update state + this row's figures in place,
            // then only recompute the summary. Rebuilding the rows here would drop
            // focus and reset the field mid-typing.
            const cartQty = event.target.closest('[data-cart-qty]');
            if (cartQty) {
                const cartForm = cartQty.closest('[data-pos-form]');
                const i = Number(cartQty.dataset.cartQty);
                if (cart[i]) {
                    const quantity = Math.max(1, parseInt(cartQty.value, 10) || 1);
                    cart[i].quantity = quantity;
                    const line = cartForm?.querySelector(`[data-cart-line="${i}"]`);
                    if (line) line.textContent = fmt(quantity * cart[i].price);
                    const hiddenQty = cartForm?.querySelector(`[data-cart-hidden-qty="${i}"]`);
                    if (hiddenQty) hiddenQty.value = quantity;
                    if (cartForm) renderSummary(cartForm);
                }
                return;
            }

            const customer = event.target.closest('[data-sales-customer-search]');
            if (customer) {
                const picker = customer.closest('[data-sales-customer-picker]');
                const value = picker?.querySelector('[data-sales-customer-value]');
                const option = selectedDataOption(customer);
                if (value) value.value = option?.dataset.customerId || '';
                // Free text that resolves to no customer would fail server validation
                // with a confusing message — block submit with a clear one instead.
                customer.setCustomValidity(option || customer.value.trim() === '' ? '' : 'Choose a customer from the list.');
            }

            const form = event.target.closest('[data-pos-form]');
            if (form) {
                renderSummary(form);
                // Entering an amount can flip whether a receiving account is needed.
                if (event.target.closest('[data-sales-paid]')) syncPaymentAccountSelector(form);
            }
        });

        document.addEventListener('change', (event) => {
            const delivery = event.target.closest('[data-sales-delivery-method]');
            if (delivery) {
                const form = delivery.closest('form');
                const shipping = form?.querySelector('[data-sales-shipping]');
                if (shipping) shipping.value = formatMoneyInput(delivery.selectedOptions[0]?.dataset.price || 0);
                if (form) renderSummary(form);
            }

            const paymentMethod = event.target.closest('[data-payment-method-selector]');
            if (paymentMethod) {
                syncPaymentAccountSelector(paymentMethod.closest('form'));
            }

            const credit = event.target.closest('[data-sales-credit]');
            if (credit) {
                const form = credit.closest('[data-pos-form]');
                const hint = form?.querySelector('[data-sales-credit-hint]');
                if (hint) hint.hidden = !credit.checked;
                if (form) renderSummary(form);
            }
        });

        function canonicalPaymentMethod(method) {
            method = String(method || '').toLowerCase();
            if (method.includes('card') || method.includes('pos')) return 'card';
            if (method.includes('cheque') || method.includes('check')) return 'cheque';
            if (method.includes('transfer') || method.includes('bank')) return 'transfer';
            return 'cash';
        }

        function syncPaymentAccountSelector(form) {
            if (!form) return;
            const method = canonicalPaymentMethod(form.querySelector('[data-payment-method-selector]')?.value);
            const wrapper = form.querySelector('[data-payment-account-wrapper]');
            const selector = form.querySelector('[data-payment-account-selector]');
            const empty = form.querySelector('[data-payment-account-empty]');
            if (!wrapper || !selector) return;

            // Show the receiving account for any non-cash method — it determines the
            // posting (GL) account for the accounting entry. It's only *required* when
            // money is actually being collected now, so credit / unpaid sales (amount
            // paid 0, which the server posts without an account) aren't blocked. Forms
            // without an amount field (e.g. the add-payment dialog) always collect.
            const paidField = form.querySelector('[data-sales-paid]');
            const collecting = paidField ? clean(paidField.value) > 0 : true;
            const showAccount = method !== 'cash';
            wrapper.hidden = !showAccount;
            selector.disabled = !showAccount;
            selector.required = false;

            let visibleOptions = 0;
            Array.from(selector.options).forEach((option) => {
                if (!option.value) {
                    option.hidden = false;
                    return;
                }

                const matches = canonicalPaymentMethod(option.dataset.accountMethod) === method;
                option.hidden = !matches;
                if (matches) visibleOptions++;
            });

            if (!showAccount) {
                selector.value = '';
            } else {
                const selected = selector.selectedOptions[0];
                if (!selected || selected.hidden) selector.value = '';
                selector.required = collecting && visibleOptions > 0;
            }

            if (empty) empty.hidden = !showAccount || visibleOptions > 0;
        }

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
                renderCart(form);
            }
        });

        document.addEventListener('change', (event) => {
            const qty = event.target.closest('[data-cart-qty]');
            if (!qty) return;
            cart[Number(qty.dataset.cartQty)].quantity = Math.max(1, parseInt(qty.value, 10) || 1);
            renderCart(qty.closest('form'));
        });

        function showSalesConfirmation(form, totals) {
            const dialog = document.getElementById('record-sale-confirmation-dialog');
            if (!(dialog instanceof HTMLDialogElement)) return;

            const setText = (selector, value) => {
                const node = dialog.querySelector(selector);
                if (node) node.textContent = value;
            };
            const itemList = dialog.querySelector('[data-confirm-items]');
            if (itemList) {
                itemList.innerHTML = cart.map((item) => `
                    <div class="sales-confirm-item">
                        <span>
                            <strong>${escapeHtml(item.label)}</strong>
                            <small>${item.quantity.toLocaleString('en-US')} × ${fmt(item.price)}</small>
                        </span>
                        <span>${fmt(item.quantity * item.price)}</span>
                    </div>
                `).join('');
            }

            const paymentMethod = form.querySelector('[data-payment-method-selector]');
            const paymentAccount = form.querySelector('[data-payment-account-selector]');
            const paymentParts = [paymentMethod?.selectedOptions[0]?.textContent?.trim() || 'Not selected'];
            if (paymentAccount && !paymentAccount.disabled && paymentAccount.value) {
                paymentParts.push(paymentAccount.selectedOptions[0]?.textContent?.trim());
            }
            if (form.querySelector('[name="is_credit_sale"]')?.checked) paymentParts.push('Credit sale');

            const deliveryMethod = form.querySelector('[data-sales-delivery-method]');
            const deliveryStatus = form.querySelector('[name="delivery_status"]');
            const deliveryParts = [
                deliveryMethod?.selectedOptions[0]?.textContent?.trim() || 'No delivery',
                deliveryStatus?.selectedOptions[0]?.textContent?.trim() || 'Pending',
            ];
            const shipping = clean(form.querySelector('[data-sales-shipping]')?.value);
            const balance = totals.total - totals.paid;

            setText('[data-confirm-customer]', form.querySelector('[data-sales-customer-search]')?.value || 'Not selected');
            setText('[data-confirm-payment]', paymentParts.filter(Boolean).join(' · '));
            setText('[data-confirm-delivery]', deliveryParts.filter(Boolean).join(' · '));
            const settlementLabel = balance > 0.001 ? 'Balance due' : (balance < -0.001 ? 'Change due' : 'Payment status');
            const settlementValue = balance > 0.001 ? fmt(balance) : (balance < -0.001 ? fmt(-balance) : 'Paid in full');
            setText('[data-confirm-balance-label]', settlementLabel);
            setText('[data-confirm-balance]', settlementValue);
            setText('[data-confirm-subtotal]', fmt(totals.subtotal));
            setText('[data-confirm-tax]', fmt(totals.tax));
            setText('[data-confirm-shipping]', fmt(shipping));
            setText('[data-confirm-coupon]', `-${fmt(totals.couponDiscount)}`);
            setText('[data-confirm-admin-discount]', `-${fmt(totals.adminDiscount)}`);
            setText('[data-confirm-amount-paid]', fmt(totals.paid));
            setText('[data-confirm-total]', fmt(totals.total));
            dialog.showModal();
        }

        document.addEventListener('submit', (event) => {
            const paymentForm = event.target.closest('form');
            if (paymentForm?.querySelector('[data-payment-method-selector]')) {
                syncPaymentAccountSelector(paymentForm);
            }

            // Validate the POS sale client-side so recoverable mistakes are shown inline
            // instead of round-tripping to the server and reloading away the whole cart.
            const posForm = event.target.closest('[data-pos-form]');
            if (!posForm) return;
            if (posForm.dataset.confirmedSubmission === 'true') {
                delete posForm.dataset.confirmedSubmission;
                return;
            }

            const errorBox = posForm.querySelector('[data-pos-error]');
            const fail = (message, focusSel) => {
                event.preventDefault();
                if (errorBox) {
                    errorBox.hidden = false;
                    errorBox.textContent = message;
                    errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                if (focusSel) posForm.querySelector(focusSel)?.focus();
            };

            if (!cart.length) { fail('Add at least one item to the cart before creating the sale.'); return; }

            const totals = computeTotals(posForm);
            const isCredit = !!posForm.querySelector('[name="is_credit_sale"]')?.checked;
            const customerValue = posForm.querySelector('[data-sales-customer-value]');

            if (isCredit && customerValue && walkInCustomerId !== null && customerValue.value === walkInCustomerId) {
                fail('Select or create a real customer before booking a credit sale — the walk-in customer cannot carry a balance.', '[data-sales-customer-search]');
                return;
            }
            if (!isCredit && totals.paid + 0.001 < totals.total) {
                fail(`Amount paid (${fmt(totals.paid)}) is less than the total (${fmt(totals.total)}). Collect the full amount, or tick “Mark as Credit Sale”.`, '[data-sales-paid]');
                return;
            }

            if (errorBox) errorBox.hidden = true;
            event.preventDefault();
            showSalesConfirmation(posForm, totals);
        });

        document.querySelector('[data-confirm-sales-order]')?.addEventListener('click', () => {
            const posForm = document.querySelector('[data-pos-form]');
            if (!posForm) return;

            posForm.dataset.confirmedSubmission = 'true';
            document.getElementById('record-sale-confirmation-dialog')?.close();
            posForm.requestSubmit();
            window.setTimeout(() => delete posForm.dataset.confirmedSubmission, 0);
        });

        document.querySelectorAll('[data-payment-method-selector]').forEach((selector) => syncPaymentAccountSelector(selector.closest('form')));

        // Restore an in-progress sale after a reload; clear it once a sale has completed.
        const posForm = document.querySelector('[data-pos-form]');
        if (posForm) {
            if (autoInvoiceOrderId) clearPosState(posForm);
            else restorePosState(posForm);
            setDeliveryPanel(posForm, hasEnteredDelivery(posForm));
            setDiscountPanel(posForm, hasEnteredDiscount(posForm));
        }
    });
    </script>
</x-layouts.admin>
