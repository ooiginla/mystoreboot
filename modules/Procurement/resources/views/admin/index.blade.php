@php
    $money = fn (?int $minor): string => number_format(($minor ?? 0) / 100, 2);
    $variantLabel = fn ($variant): string => $variant->product?->name.' / '.$variant->variant_name.' ('.$variant->sku.')';
    $activeBranchForView = app(\App\Support\ActiveBranchManager::class)->stateForRequest(request(), auth()->user())['activeBranch'];
    $activeBranchLocationId = $activeBranchForView ? $locations->firstWhere('branch_id', $activeBranchForView->id)?->id : null;
    $poStatusClass = fn (string $status): string => match ($status) {
        'approved', 'received' => 'success',
        'partially_received' => 'warning',
        'cancelled' => 'danger',
        default => 'neutral',
    };
    $paymentStatusClass = fn (string $status): string => match ($status) {
        'paid' => 'success',
        'partially_paid' => 'warning',
        'overdue' => 'danger',
        default => 'neutral',
    };
@endphp

<x-layouts.admin title="Purchasing & Suppliers">
    <datalist id="variant-options">
        @foreach ($variants as $variant)
            <option value="{{ $variantLabel($variant) }}" data-variant-id="{{ $variant->id }}" data-cost="{{ $money($variant->cost_price_minor ?: $variant->product?->base_cost_price_minor) }}"></option>
        @endforeach
    </datalist>

    <style>
        .po-line-card {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            padding: 14px;
            display: grid;
            gap: 12px;
        }
        .po-line-card + .po-line-card { margin-top: 12px; }
        .po-line-header { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
        .link-button { border: 0; background: transparent; padding: 0; color: var(--accent); cursor: pointer; font-weight: 800; }
        .filter-bar { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)) auto; gap: 10px; align-items: end; margin-bottom: 16px; }
        .tag-row { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
        .status-tag { display: inline-flex; align-items: center; border-radius: 6px; padding: 4px 8px; font-size: 12px; font-weight: 800; }
        .status-tag.neutral { background: #eef2f6; color: #475467; }
        .status-tag.success { background: #ecfdf3; color: #067647; }
        .status-tag.warning { background: #fffaeb; color: #b54708; }
        .status-tag.danger { background: #fef3f2; color: #b42318; }
        .danger-text { color: var(--danger); font-weight: 800; }
        .vendor-dialog-tabs {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--line);
        }
        .vendor-dialog-tabs a {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            border: 1px solid #a6f4c5;
            border-radius: 9px;
            background: var(--brand-050);
            color: var(--brand-strong);
            padding: 7px 11px;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.2;
            transition: background .15s, border-color .15s, color .15s, box-shadow .15s;
        }
        .vendor-dialog-tabs a:hover { border-color: var(--brand); background: var(--brand-100); color: #05603a; }
        .vendor-dialog-tabs a.active { border-color: var(--brand); background: var(--brand); color: #fff; box-shadow: 0 3px 9px -3px rgba(6,193,104,.5); }
        .vendor-dialog-tabs svg { width: 15px; height: 15px; flex: 0 0 auto; }
        .vendor-dialog-tabs .badge { padding: 1px 6px; font-size: 11px; }
        .vendor-dialog-tabs a.active .badge { background: rgba(255,255,255,.22); color: #fff; }
        .vendor-row-actions { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; justify-content: flex-end; }
        .vendor-row-actions .btn { gap: 5px; padding: 6px 9px; border-radius: 8px; font-size: 12.5px; }
        .vendor-row-actions .vendor-view-action { border-color: #a6f4c5; background: var(--brand-050); color: var(--brand-strong); box-shadow: none; }
        .vendor-row-actions .vendor-view-action:hover { border-color: var(--brand); background: var(--brand-100); color: #05603a; }
        .vendor-row-actions .vendor-edit-action { border-color: var(--brand); background: var(--brand); color: #fff; box-shadow: 0 3px 9px -3px rgba(6,193,104,.5); }
        .vendor-row-actions .vendor-edit-action:hover { border-color: var(--brand-strong); background: var(--brand-strong); }
        .po-row-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .po-row-actions .btn { box-shadow: none; }
        .po-row-actions .po-action-approve { border-color: #067647; background: #067647; color: #fff; }
        .po-row-actions .po-action-approve:hover { border-color: #05603a; background: #05603a; }
        .po-row-actions .po-action-view { border-color: #b2ddff; background: #eff8ff; color: #175cd3; }
        .po-row-actions .po-action-view:hover { border-color: #84caff; background: #d1e9ff; color: #1849a9; }
        .po-row-actions .po-action-edit { border-color: #fedf89; background: #fffaeb; color: #b54708; }
        .po-row-actions .po-action-edit:hover { border-color: #fec84b; background: #fef0c7; color: #93370d; }
        .po-row-actions .po-action-cancel { border-color: #fecdca; background: #fef3f2; color: #b42318; }
        .po-row-actions .po-action-cancel:hover { border-color: #f97066; background: #d92d20; color: #fff; }
        .po-row-actions .po-action-receive { border-color: #d9d6fe; background: #f4f3ff; color: #5925dc; }
        .po-row-actions .po-action-receive:hover { border-color: #bdb4fe; background: #ebe9fe; color: #4a1fb8; }
        .po-row-actions .po-action-payment { border-color: #7cd4fd; background: #f0f9ff; color: #026aa2; }
        .po-row-actions .po-action-payment:hover { border-color: #36bffa; background: #e0f2fe; color: #065986; }
        .vendor-lead-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: 1px solid #a6f4c5;
            border-radius: 999px;
            background: var(--brand-050);
            color: var(--brand-strong);
            padding: 4px 9px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }
        .vendor-lead-badge svg { width: 13px; height: 13px; }
        .summary-item strong .vendor-lead-tag {
            display: inline-flex;
            align-items: center;
            width: fit-content;
            margin: 0;
            border: 1px solid #a6f4c5;
            border-radius: 999px;
            background: var(--brand-050);
            color: var(--brand-strong);
            padding: 4px 10px;
            font-size: 12px;
            font-weight: 750;
            line-height: 1.25;
            letter-spacing: 0;
            text-transform: none;
        }
        .printable-receipt { border: 1px solid var(--line); border-radius: 8px; padding: 16px; display: grid; gap: 12px; }
        @media print {
            body:has(dialog[open]) .shell { display: block; }
            body:has(dialog[open]) .sidebar, body:has(dialog[open]) .topbar, body:has(dialog[open]) .tab-layout, body:has(dialog[open]) .stats-grid { display: none; }
            dialog[open] { display: block; position: static; width: 100%; box-shadow: none; }
            dialog[open]::backdrop, dialog[open] .dialog-header .icon-btn, dialog[open] [data-print-dialog], dialog[open] [data-dialog-close] { display: none; }
        }
        @media (max-width: 1100px) { .filter-bar { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    </style>

    <div class="topbar">
        <div>
            <div class="eyebrow">Procurement & supply chain</div>
            <h1>Purchasing & Suppliers</h1>
            <p class="subtle">Supplier database, purchase orders, goods received, payments, and vendor performance for {{ $tenant->name }}.</p>
        </div>
        @if ($isPlatformAdmin)
            <form method="GET" action="{{ route('admin.procurement.index') }}" style="min-width: 260px;">
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
        <div class="alert errors">
            <strong>Check the procurement details.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="stats-grid" style="margin-bottom: 18px;">
        <div class="stat"><span class="subtle">Vendors</span><strong>{{ $stats['vendors'] }}</strong></div>
        <div class="stat"><span class="subtle">Pending POs</span><strong>{{ $stats['pending_pos'] }}</strong></div>
        <div class="stat"><span class="subtle">Outstanding</span><strong>{{ $tenant->currency_code }} {{ $money($stats['outstanding_minor']) }}</strong></div>
        <div class="stat"><span class="subtle">Total spend</span><strong>{{ $tenant->currency_code }} {{ $money($stats['spend_minor']) }}</strong></div>
    </div>

    <div class="tab-layout">
        <nav class="pill-nav" aria-label="Procurement sections" role="tablist">
            <a href="#vendors" role="tab" data-tab-target="vendors">Vendors <span class="badge neutral">{{ $vendors->count() }}</span></a>
            <a href="#purchase-orders" role="tab" data-tab-target="purchase-orders">Purchase orders <span class="badge neutral">{{ $purchaseOrders->count() }}</span></a>
            <a href="#receipts" role="tab" data-tab-target="receipts">Goods received</a>
            <a href="#payments" role="tab" data-tab-target="payments">Payments <span class="badge neutral">{{ $payments->count() }}</span></a>
            <a href="#comparison" role="tab" data-tab-target="comparison">Price comparison</a>
            <a href="#performance" role="tab" data-tab-target="performance">Performance</a>
        </nav>

        <div class="content-stack">
            <section class="panel tab-panel" id="vendors" role="tabpanel" data-tab-panel>
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Vendor/supplier database</h2>
                        <p class="subtle">Contact details, lead time, history, and balances.</p>
                    </div>
                    <button class="btn accent" type="button" data-dialog-open="vendor-dialog">Add vendor</button>
                </div>
                <div class="panel-body">
                    <form class="filter-bar" method="GET" action="{{ route('admin.procurement.index') }}#vendors">
                        <input type="hidden" name="tenant" value="{{ $tenant->id }}">
                        <div class="field" style="grid-column: span 2;"><label>Search vendors</label><input name="vendor_search" value="{{ $vendorSearch }}" placeholder="Name, code, contact, email, or phone"></div>
                        <div class="button-row" style="margin-top: 0; justify-content: flex-start;"><button class="btn secondary" type="submit">Search</button><a class="btn secondary" href="{{ route('admin.procurement.index', ['tenant' => $tenant->id]).'#vendors' }}">Reset</a></div>
                    </form>
                    <div class="list">
                        @forelse ($vendors as $vendor)
                            <div class="item">
                                <div>
                                    <button class="link-button item-title" type="button" data-dialog-open="vendor-view-{{ $vendor->id }}">{{ $vendor->name }}</button>
                                    <div class="subtle">{{ $vendor->contact_name ?: 'No contact' }} · {{ $vendor->phone ?: 'No phone' }} · {{ $vendor->email ?: 'No email' }}</div>
                                    @if ($vendor->bankAccounts->isNotEmpty())
                                        <div class="subtle">{{ $vendor->bankAccounts->count() }} bank account(s) · Primary: {{ $vendor->bankAccounts->firstWhere('is_primary', true)?->bank_name ?? $vendor->bankAccounts->first()->bank_name }}</div>
                                    @endif
                                </div>
                                <div class="vendor-row-actions">
                                    <span class="vendor-lead-badge">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                                        {{ $vendor->lead_time_days }}-day lead
                                    </span>
                                    <button class="btn vendor-view-action" type="button" data-dialog-open="vendor-view-{{ $vendor->id }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                                        <span>View</span>
                                    </button>
                                    <button class="btn vendor-edit-action" type="button" data-dialog-open="vendor-edit-{{ $vendor->id }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/></svg>
                                        <span>Edit</span>
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="empty">No vendors yet. Add suppliers before creating purchase orders.</div>
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="panel tab-panel" id="purchase-orders" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Purchase orders</h2>
                        <p class="subtle">Approval, pending delivery, receiving, and payment state.</p>
                    </div>
                    <button class="btn accent" type="button" data-dialog-open="po-dialog">Create PO</button>
                </div>
                <div class="panel-body">
                    <form class="filter-bar" method="GET" action="{{ route('admin.procurement.index') }}#purchase-orders">
                        <input type="hidden" name="tenant" value="{{ $tenant->id }}">
                        <div class="field"><label>Vendor</label><select name="vendor_id"><option value="">All vendors</option>@foreach ($allVendors as $vendor)<option value="{{ $vendor->id }}" @selected($poFilters['vendor_id'] === (string) $vendor->id)>{{ $vendor->name }}</option>@endforeach</select></div>
                        <div class="field"><label>PO status</label><select name="status"><option value="">All statuses</option>@foreach (['pending_approval' => 'Pending approval', 'approved' => 'Approved', 'partially_received' => 'Partially received', 'received' => 'Received', 'cancelled' => 'Cancelled'] as $value => $label)<option value="{{ $value }}" @selected($poFilters['status'] === $value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="field"><label>Payment status</label><select name="payment_status"><option value="">All payments</option>@foreach (['unpaid' => 'Unpaid', 'partially_paid' => 'Partially paid', 'paid' => 'Paid', 'overdue' => 'Overdue'] as $value => $label)<option value="{{ $value }}" @selected($poFilters['payment_status'] === $value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="field"><label>From</label><input name="date_from" type="date" value="{{ $poFilters['date_from'] }}"></div>
                        <div class="field"><label>To</label><input name="date_to" type="date" value="{{ $poFilters['date_to'] }}"></div>
                        <div class="button-row" style="margin-top: 0; justify-content: flex-start;"><button class="btn secondary" type="submit">Filter</button><a class="btn secondary" href="{{ route('admin.procurement.index', ['tenant' => $tenant->id]).'#purchase-orders' }}">Reset</a></div>
                    </form>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>PO</th>
                                <th>Vendor</th>
                                <th>PO date</th>
                                <th>PO status</th>
                                <th>Payment status</th>
                                <th>Delivery</th>
                                <th>Total</th>
                                <th>Paid</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($purchaseOrders as $po)
                                @php
                                    $canApprove = $po->status->value === 'pending_approval'
                                        && $purchaseOrderApprovalsEnabled
                                        && $canApprovePurchaseOrders;
                                    $canEdit = $po->status->value === 'pending_approval';
                                    $canReceive = in_array($po->status->value, ['approved', 'partially_received'], true) && $po->items->sum('quantity_pending') > 0;
                                    $canPay = in_array($po->status->value, ['approved', 'partially_received', 'received'], true) && $po->balance_minor > 0;
                                @endphp
                                <tr>
                                    <td><strong>{{ $po->po_number }}</strong></td>
                                    <td>{{ $po->vendor->name }}</td>
                                    <td>{{ $po->order_date->format('M j, Y') }}</td>
                                    <td><span class="status-tag {{ $poStatusClass($po->status->value) }}">{{ $po->status->label() }}</span></td>
                                    <td><span class="status-tag {{ $paymentStatusClass($po->payment_status->value) }}">{{ $po->payment_status->label() }}</span></td>
                                    <td>{{ $po->expected_delivery_date?->format('M j, Y') ?? 'Not set' }}</td>
                                    <td>{{ $tenant->currency_code }} {{ $money($po->total_minor) }}</td>
                                    <td>{{ $tenant->currency_code }} {{ $money($po->paid_minor) }}</td>
                                    <td class="po-row-actions">
                                        @if ($canApprove)
                                            <form method="POST" action="{{ route('admin.procurement.purchase-orders.approve', $po) }}">@csrf<button class="btn po-action-approve" type="submit">Approve</button></form>
                                        @endif
                                        <button class="btn po-action-view" type="button" data-dialog-open="view-po-{{ $po->id }}">View</button>
                                        @if ($canEdit)
                                            <button class="btn po-action-edit" type="button" data-dialog-open="edit-po-{{ $po->id }}">Edit</button>
                                            <form method="POST" action="{{ route('admin.procurement.purchase-orders.cancel', $po) }}">@csrf<button class="btn po-action-cancel" type="submit">Cancel</button></form>
                                        @endif
                                        @if ($canReceive)
                                            <button class="btn po-action-receive" type="button" data-dialog-open="receive-po-{{ $po->id }}">Receive</button>
                                        @endif
                                        @if ($canPay)
                                            <button class="btn po-action-payment" type="button" data-dialog-open="payment-po-{{ $po->id }}">Record payment</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9"><div class="empty">No purchase orders match the current filters.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel tab-panel" id="receipts" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Goods received notes</h2>
                        <p class="subtle">Receiving a PO posts stock into Inventory for the selected branch/location.</p>
                    </div>
                </div>
                <div class="panel-body">
                    <div class="list">
                        @forelse ($purchaseOrders->flatMap->receipts as $receipt)
                            <div class="item">
                                <div>
                                    <button class="link-button item-title" type="button" data-dialog-open="view-po-{{ $receipt->purchaseOrder->id }}">{{ $receipt->receipt_number }}</button>
                                    <div class="subtle">{{ $receipt->purchaseOrder->po_number }} · {{ $receipt->received_at->format('M j, Y') }}</div>
                                </div>
                                <span class="badge neutral">{{ $receipt->items->sum('quantity_received') }} units</span>
                            </div>
                        @empty
                            <div class="empty">No goods received notes yet.</div>
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="panel tab-panel" id="payments" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Vendor payment tracking</h2>
                        <p class="subtle">Track payments against suppliers and purchase orders.</p>
                    </div>
                    <button class="btn accent" type="button" data-dialog-open="payment-dialog">Record payment</button>
                </div>
                <div class="panel-body">
                    <table class="table">
                        <thead><tr><th>Date</th><th>Vendor</th><th>PO</th><th>Amount</th><th>Reference</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($payments as $payment)
                                <tr>
                                    <td>{{ $payment->payment_date->format('M j, Y') }}</td>
                                    <td>{{ $payment->vendor->name }}</td>
                                    <td>
                                        @if ($payment->purchaseOrder)
                                            <button class="link-button" type="button" data-dialog-open="view-po-{{ $payment->purchaseOrder->id }}">{{ $payment->purchaseOrder->po_number }}</button>
                                        @else
                                            General
                                        @endif
                                    </td>
                                    <td>{{ $tenant->currency_code }} {{ $money($payment->amount_minor) }}</td>
                                    <td>{{ $payment->reference_number ?: 'Not set' }}</td>
                                    <td><button class="btn secondary" type="button" data-dialog-open="payment-receipt-{{ $payment->id }}">Receipt</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><div class="empty">No vendor payments yet.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel tab-panel" id="comparison" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header"><div><h2 class="panel-title">Supplier pricing comparison</h2><p class="subtle">Recent purchase prices by vendor and variant.</p></div></div>
                <div class="panel-body">
                    <table class="table">
                        <thead><tr><th>Variant</th><th>Vendor</th><th>Unit cost</th><th>PO</th></tr></thead>
                        <tbody>
                            @forelse ($pricingRows as $row)
                                <tr><td>{{ $variantLabel($row->variant) }}</td><td>{{ $row->purchaseOrder->vendor->name }}</td><td>{{ $tenant->currency_code }} {{ $money($row->unit_cost_minor) }}</td><td>{{ $row->purchaseOrder->po_number }}</td></tr>
                            @empty
                                <tr><td colspan="4"><div class="empty">Pricing comparison appears after POs are created.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel tab-panel" id="performance" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header"><div><h2 class="panel-title">Vendor performance</h2><p class="subtle">Order volume, received orders, spend, and outstanding balance.</p></div></div>
                <div class="panel-body">
                    <table class="table">
                        <thead><tr><th>Vendor</th><th>Orders</th><th>Received</th><th>Spend</th><th>Balance</th></tr></thead>
                        <tbody>
                            @forelse ($vendorPerformance as $row)
                                <tr><td>{{ $row['vendor']->name }}</td><td>{{ $row['orders'] }}</td><td>{{ $row['received'] }}</td><td>{{ $tenant->currency_code }} {{ $money($row['spend_minor']) }}</td><td>{{ $tenant->currency_code }} {{ $money($row['balance_minor']) }}</td></tr>
                            @empty
                                <tr><td colspan="5"><div class="empty">Vendor performance appears after purchasing activity.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    @include('procurement::admin.partials.vendor-dialog')
    @include('procurement::admin.partials.po-dialog')
    @include('procurement::admin.partials.payment-dialog')
    @foreach ($allVendors as $vendor)
        @include('procurement::admin.partials.vendor-view-dialog', ['vendor' => $vendor])
        @include('procurement::admin.partials.vendor-dialog', ['dialogId' => 'vendor-edit-'.$vendor->id, 'selectedVendor' => $vendor])
    @endforeach
    @foreach ($allPurchaseOrders as $po)
        @include('procurement::admin.partials.po-view-dialog', ['po' => $po])
        @if ($po->status->value === 'pending_approval')
            @include('procurement::admin.partials.po-dialog', ['dialogId' => 'edit-po-'.$po->id, 'selectedPo' => $po])
        @endif
        @if (in_array($po->status->value, ['approved', 'partially_received'], true) && $po->items->sum('quantity_pending') > 0)
            @include('procurement::admin.partials.receive-dialog', ['po' => $po])
        @endif
        @if (in_array($po->status->value, ['approved', 'partially_received', 'received'], true) && $po->balance_minor > 0)
            @include('procurement::admin.partials.payment-dialog', ['dialogId' => 'payment-po-'.$po->id, 'selectedPo' => $po])
        @endif
    @endforeach
    @foreach ($payments as $payment)
        @include('procurement::admin.partials.payment-receipt-dialog', ['payment' => $payment])
    @endforeach
</x-layouts.admin>
