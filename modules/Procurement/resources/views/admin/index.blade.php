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
                                <div style="display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end;">
                                    <span class="badge neutral">{{ $vendor->lead_time_days }} day lead</span>
                                    <button class="btn secondary" type="button" data-dialog-open="vendor-view-{{ $vendor->id }}">View</button>
                                    <button class="btn secondary" type="button" data-dialog-open="vendor-edit-{{ $vendor->id }}">Edit</button>
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
                                    $canApprove = $po->status->value === 'pending_approval';
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
                                    <td style="display: flex; gap: 8px; flex-wrap: wrap;">
                                        @if ($canApprove)
                                            <form method="POST" action="{{ route('admin.procurement.purchase-orders.approve', $po) }}">@csrf<button class="btn secondary" type="submit">Approve</button></form>
                                        @endif
                                        <button class="btn secondary" type="button" data-dialog-open="view-po-{{ $po->id }}">View</button>
                                        @if ($canEdit)
                                            <button class="btn secondary" type="button" data-dialog-open="edit-po-{{ $po->id }}">Edit</button>
                                            <form method="POST" action="{{ route('admin.procurement.purchase-orders.cancel', $po) }}">@csrf<button class="btn secondary" type="submit">Cancel</button></form>
                                        @endif
                                        @if ($canReceive)
                                            <button class="btn secondary" type="button" data-dialog-open="receive-po-{{ $po->id }}">Receive</button>
                                        @endif
                                        @if ($canPay)
                                            <button class="btn secondary" type="button" data-dialog-open="payment-po-{{ $po->id }}">Record payment</button>
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
