@php
    use Modules\Tenancy\Enums\CommerceMode;

    $money = fn (?int $minor): string => number_format(($minor ?? 0) / 100, 2);
    $formattedMoney = fn (string $currency, ?int $minor): string => strtoupper($currency) === 'NGN'
        ? '₦'.$money($minor)
        : strtoupper($currency).' '.$money($minor);
    $percentage = number_format($settings->percentage_markup_basis_points / 100, 2, '.', '');
    $titles = ['overview' => 'Reseller Store', 'suppliers' => 'Supplier Websites', 'products' => 'Sourced Products', 'orders' => 'Reseller Orders', 'payments' => 'Reseller Payments', 'settings' => 'Reseller Settings'];
    $pageTitle = $titles[$section] ?? 'Reseller Store';
    $tenantRoute = ['tenant' => $tenant->id];
@endphp

<x-layouts.admin :title="$pageTitle">
    <style>
        .reseller-stack { display: grid; gap: 20px; }
        .reseller-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
        .reseller-stat, .reseller-shortcut { border: 1px solid var(--line); border-radius: 10px; padding: 16px; background: var(--panel); }
        .reseller-stat strong { display: block; margin-top: 5px; font-size: 24px; }
        .reseller-shortcuts { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; }
        .reseller-shortcut { display: grid; gap: 5px; }
        .reseller-shortcut:hover { border-color: var(--brand); box-shadow: var(--shadow-sm); }
        .reseller-list { display: grid; gap: 10px; }
        .reseller-row { display: flex; align-items: center; justify-content: space-between; gap: 15px; padding: 14px; border: 1px solid var(--line); border-radius: 10px; }
        .reseller-row-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .reseller-row details > summary { cursor: pointer; list-style: none; }
        .reseller-row details[open] { width: min(100%, 560px); }
        .reseller-mode { border: 1px solid var(--line); border-radius: 14px; background: linear-gradient(135deg, var(--brand-050), #fff); padding: 28px; }
        .reseller-mode h1 { margin: 0 0 8px; }
        .reseller-help { color: var(--muted); font-size: 13px; }
        .reseller-supplier-copy { display: grid; gap: 10px; min-width: 0; }
        .reseller-scan-times { display: flex; flex-wrap: wrap; gap: 10px 22px; }
        .reseller-scan-time { display: grid; gap: 2px; }
        .reseller-scan-time span { color: var(--muted); font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
        .reseller-scan-time strong { font-size: 12.5px; font-weight: 650; }
        .reseller-scan-running { background: #eff8ff; color: #175cd3; }
        .reseller-scan-failed { background: var(--danger-bg); color: var(--danger-strong); }
        .reseller-scan-overlay { width: 100vw; max-width: none; height: 100vh; max-height: none; margin: 0; padding: 0; border: 0; background: transparent; }
        .reseller-scan-overlay::backdrop { background: rgba(8, 20, 14, .66); backdrop-filter: blur(3px); }
        .reseller-scan-overlay[open] { display: grid; place-items: center; }
        .reseller-scan-overlay-card { width: min(430px, calc(100vw - 32px)); display: grid; justify-items: center; gap: 12px; padding: 30px; border: 1px solid rgba(255,255,255,.5); border-radius: 16px; background: rgba(255,255,255,.9); box-shadow: 0 28px 80px rgba(0,0,0,.28); text-align: center; }
        .reseller-scan-overlay-card h2 { margin: 0; font-size: 20px; }
        .reseller-scan-overlay-card p { margin: 0; color: var(--muted); }
        .reseller-scan-spinner { width: 42px; height: 42px; border: 4px solid var(--brand-100); border-top-color: var(--brand); border-radius: 50%; animation: reseller-scan-spin .8s linear infinite; }
        @keyframes reseller-scan-spin { to { transform: rotate(360deg); } }
        @media (prefers-reduced-motion: reduce) { .reseller-scan-spinner { animation-duration: 1.8s; } }
        .reseller-table-wrap { width: 100%; overflow-x: auto; }
        .reseller-table-wrap .table { min-width: 1050px; }
        .reseller-product-search { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .reseller-product-search input { width: min(320px, 70vw); }
        .reseller-product-action { width: 36px; height: 36px; padding: 0; }
        .reseller-product-action svg { width: 18px; height: 18px; }
        .reseller-product-action.exclude { color: var(--danger); border-color: var(--danger-border); }
        .reseller-product-action.exclude:hover { color: #fff; border-color: var(--danger); background: var(--danger); }
        @media (max-width: 1000px) { .reseller-shortcuts { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 900px) { .reseller-stats { grid-template-columns: 1fr; } .reseller-row { align-items: flex-start; flex-direction: column; } }
        @media (max-width: 600px) { .reseller-shortcuts { grid-template-columns: 1fr; } }
    </style>

    <div class="reseller-stack">
        @if (session('status'))<div class="notice success">{{ session('status') }}</div>@endif
        @if ($errors->any())
            <div class="notice danger"><strong>Please check the following:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        @if ($tenant->commerce_mode === CommerceMode::Standard)
            <section class="reseller-mode">
                <span class="badge">Optional sales model</span>
                <h1>Build a store from supplier websites</h1>
                <p>Reseller mode replaces your native product catalogue with products recovered from websites you approve. Your current products and historical orders are retained, but they will not be sold while reseller mode is active.</p>
                <form method="POST" action="{{ route('admin.reseller.mode.update') }}" onsubmit="return confirm('Switch this tenant to reseller mode? Native product management will be unavailable until you switch back.');">
                    @csrf @method('PUT')
                    <input type="hidden" name="tenant_id" value="{{ $tenant->id }}"><input type="hidden" name="commerce_mode" value="reseller">
                    <button class="btn accent" type="submit">Activate reseller mode</button>
                </form>
            </section>
        @else
            <div class="page-heading">
                <div>
                    <h1>{{ $pageTitle }}</h1>
                    <p>@switch($section)
                        @case('suppliers') Configure the websites Storeboot scans for products. @break
                        @case('products') Review and control products recovered from supplier websites. @break
                        @case('orders') Manage customer orders from the reseller storefront. @break
                        @case('payments') Review payments recorded against reseller orders. @break
                        @case('settings') Configure recovery, pricing, and storefront behaviour. @break
                        @default Source products from supplier websites and manage reseller sales independently.
                    @endswitch</p>
                </div>
                @if ($section === 'settings')
                    <form method="POST" action="{{ route('admin.reseller.mode.update') }}" onsubmit="return confirm('Switch back to a standard product store? Reseller products will stop appearing on your storefront.');">
                        @csrf @method('PUT')
                        <input type="hidden" name="tenant_id" value="{{ $tenant->id }}"><input type="hidden" name="commerce_mode" value="standard">
                        <button class="btn secondary" type="submit">Switch to standard store</button>
                    </form>
                @endif
            </div>

            @if ($section === 'overview')
                <div class="reseller-stats">
                    <div class="reseller-stat"><span class="subtle">Suppliers</span><strong>{{ $suppliers->count() }}</strong></div>
                    <div class="reseller-stat"><span class="subtle">Recovered products</span><strong>{{ $products->total() }}</strong></div>
                    <div class="reseller-stat"><span class="subtle">Visible products</span><strong>{{ $products->getCollection()->where('is_visible', true)->count() }}</strong></div>
                    <div class="reseller-stat"><span class="subtle">Recent orders</span><strong>{{ $orders->count() }}</strong></div>
                </div>
                <div class="reseller-shortcuts">
                    <a class="reseller-shortcut" href="{{ route('admin.reseller.suppliers.index', $tenantRoute) }}"><strong>Supplier Websites</strong><span class="subtle">Add and scan sources</span></a>
                    <a class="reseller-shortcut" href="{{ route('admin.reseller.products.index', $tenantRoute) }}"><strong>Sourced Products</strong><span class="subtle">Review recovered items</span></a>
                    <a class="reseller-shortcut" href="{{ route('admin.reseller.orders.index', $tenantRoute) }}"><strong>Orders</strong><span class="subtle">Manage fulfilment</span></a>
                    <a class="reseller-shortcut" href="{{ route('admin.reseller.payments.index', $tenantRoute) }}"><strong>Payments</strong><span class="subtle">Review transactions</span></a>
                    <a class="reseller-shortcut" href="{{ route('admin.reseller.settings.index', $tenantRoute) }}"><strong>Settings</strong><span class="subtle">Pricing and recovery</span></a>
                </div>
            @elseif ($section === 'suppliers')
                <section class="panel">
                    <div class="panel-header">
                        <div><h2 class="panel-title">Supplier websites</h2><p class="subtle">{{ $suppliers->count() }} configured.</p></div>
                        <button class="btn accent" type="button" data-dialog-open="add-supplier-website-dialog">Add supplier website</button>
                    </div>
                    <div class="panel-body reseller-list">
                        @forelse ($suppliers as $supplier)
                            @php
                                $scan = $supplierScanSchedules[$supplier->id];
                                $scanRunning = $supplier->last_scan_status === \Modules\Reseller\Enums\ScanStatus::Running;
                                $scanFailed = $supplier->last_scan_status === \Modules\Reseller\Enums\ScanStatus::Failed;
                                $scanFrequency = $scan['frequency'] === 'twice_daily' ? 'Twice daily (every 12 hours)' : 'Daily (every 24 hours)';
                                $nextRun = $scan['next_run_at']?->copy()->timezone($tenant->timezone)->format('M j, Y · g:i A T');
                                $lastRun = $supplier->last_scanned_at?->copy()->timezone($tenant->timezone)->format('M j, Y · g:i A T');
                            @endphp
                            <div class="reseller-row">
                                <div class="reseller-supplier-copy">
                                    <div><strong>{{ $supplier->name }}</strong><div class="reseller-help">{{ $supplier->website_url }} · {{ $supplier->products_count }} products</div></div>
                                    <div class="reseller-scan-times">
                                        <div class="reseller-scan-time"><span>Schedule</span><strong>{{ $scanFrequency }}</strong></div>
                                        <div class="reseller-scan-time"><span>Next run</span><strong>{{ $scanRunning ? 'Running now' : ($nextRun ?? 'Paused') }}</strong></div>
                                        <div class="reseller-scan-time"><span>Last run</span><strong>{{ $lastRun ?? 'Never run' }}</strong></div>
                                    </div>
                                </div>
                                <div class="reseller-row-actions">
                                    <span class="badge {{ $supplier->is_active ? 'success' : 'neutral' }}">{{ $supplier->is_active ? 'Active' : 'Paused' }}</span>
                                    <span class="badge {{ $scanRunning ? 'reseller-scan-running' : ($scanFailed ? 'reseller-scan-failed' : 'neutral') }}" @if($supplier->last_scan_message) title="{{ $supplier->last_scan_message }}" @endif>{{ $scanRunning ? 'Running' : str($supplier->last_scan_status->value)->headline() }}</span>
                                    <form method="POST" action="{{ route('admin.reseller.suppliers.scan.schedule', $supplier) }}">@csrf<button class="btn secondary" type="submit" @disabled(! $supplier->is_active || $scanRunning)>Schedule scan</button></form>
                                    <form method="POST" action="{{ route('admin.reseller.suppliers.scan', $supplier) }}" data-scan-now-form data-supplier-name="{{ $supplier->name }}">@csrf<button class="btn accent" type="submit" @disabled(! $supplier->is_active || $scanRunning)>Scan now</button></form>
                                    <details>
                                        <summary class="btn secondary">Edit</summary>
                                        <form method="POST" action="{{ route('admin.reseller.suppliers.update', $supplier) }}" class="form-grid" style="margin-top:12px;">
                                            @csrf @method('PUT')
                                            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                                            <label>Name<input name="name" value="{{ $supplier->name }}" required></label>
                                            <label>Website<input type="url" name="website_url" value="{{ $supplier->website_url }}" required></label>
                                            <label>Email<input type="email" name="contact_email" value="{{ $supplier->contact_email }}"></label>
                                            <label>WhatsApp<input name="whatsapp" value="{{ $supplier->whatsapp }}"></label>
                                            <label>Schedule<select name="scan_frequency"><option value="">Use store setting</option><option value="daily" @selected($supplier->scan_frequency === 'daily')>Daily</option><option value="twice_daily" @selected($supplier->scan_frequency === 'twice_daily')>Twice daily</option></select></label>
                                            <label class="check"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($supplier->is_active)> Active</label>
                                            <div class="full"><button class="btn primary" type="submit">Save supplier</button></div>
                                        </form>
                                    </details>
                                    <form method="POST" action="{{ route('admin.reseller.suppliers.destroy', $supplier) }}" onsubmit="return confirm('Delete this supplier and its recovered products? Historical order snapshots will remain.');">@csrf @method('DELETE')<button class="btn danger" type="submit">Delete</button></form>
                                </div>
                            </div>
                        @empty
                            <div class="empty">No supplier websites yet. Use “Add supplier website” to add your first source.</div>
                        @endforelse
                    </div>
                </section>

                <dialog class="dialog" id="add-supplier-website-dialog">
                    <div class="dialog-header"><div><h2 class="panel-title">Add supplier website</h2><p class="subtle">The recovery service will discover products from this website.</p></div><button class="btn secondary" type="button" data-dialog-close aria-label="Close supplier website dialog">Close</button></div>
                    <div class="dialog-body">
                        <form method="POST" action="{{ route('admin.reseller.suppliers.store') }}" class="form-grid">
                            @csrf
                            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                            <label>Supplier name<input name="name" value="{{ old('name') }}" required maxlength="160"></label>
                            <label>Website URL<input type="url" name="website_url" value="{{ old('website_url') }}" placeholder="https://supplier.example" required></label>
                            <label>Email<input type="email" name="contact_email" value="{{ old('contact_email') }}"></label>
                            <label>WhatsApp<input name="whatsapp" value="{{ old('whatsapp') }}"></label>
                            <label>Schedule<select name="scan_frequency"><option value="">Use store setting</option><option value="daily" @selected(old('scan_frequency') === 'daily')>Daily</option><option value="twice_daily" @selected(old('scan_frequency') === 'twice_daily')>Twice daily</option></select></label>
                            <input type="hidden" name="is_active" value="1">
                            <div class="full button-row"><button class="btn secondary" type="button" data-dialog-close>Cancel</button><button class="btn accent" type="submit">Add supplier</button></div>
                        </form>
                    </div>
                </dialog>

                <dialog class="reseller-scan-overlay" id="reseller-scan-overlay" aria-labelledby="reseller-scan-overlay-title" aria-describedby="reseller-scan-overlay-message">
                    <div class="reseller-scan-overlay-card" role="status" aria-live="assertive">
                        <span class="reseller-scan-spinner" aria-hidden="true"></span>
                        <h2 id="reseller-scan-overlay-title">Scanning supplier website</h2>
                        <p id="reseller-scan-overlay-message">Recovering products now. Please keep this page open until the scan completes.</p>
                    </div>
                </dialog>

                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        const overlay = document.getElementById('reseller-scan-overlay');
                        const title = document.getElementById('reseller-scan-overlay-title');
                        let scanSubmitted = false;

                        overlay?.addEventListener('cancel', (event) => event.preventDefault());

                        document.querySelectorAll('[data-scan-now-form]').forEach((form) => {
                            form.addEventListener('submit', (event) => {
                                if (scanSubmitted) {
                                    event.preventDefault();
                                    return;
                                }

                                const supplierName = form.dataset.supplierName || 'supplier website';
                                if (!window.confirm(`Run the scan for ${supplierName} now? This page will wait until it finishes.`)) {
                                    event.preventDefault();
                                    return;
                                }

                                scanSubmitted = true;
                                form.querySelectorAll('button').forEach((control) => control.disabled = true);
                                if (title) title.textContent = `Scanning ${supplierName}`;

                                if (overlay?.showModal) {
                                    overlay.showModal();
                                } else if (overlay) {
                                    overlay.setAttribute('open', '');
                                }
                            });
                        });
                    });
                </script>
            @elseif ($section === 'products')
                <section class="panel">
                    <div class="panel-header">
                        <div><h2 class="panel-title">Sourced products</h2><p class="subtle">Products recovered from your supplier websites.</p></div>
                        <form class="reseller-product-search" method="GET" action="{{ route('admin.reseller.products.index') }}" role="search">
                            <input type="hidden" name="tenant" value="{{ $tenant->id }}">
                            <input type="search" name="product_search" value="{{ $productSearch }}" placeholder="Search product or supplier" aria-label="Search by product name or supplier">
                            <button class="btn secondary" type="submit">Search</button>
                            @if ($productSearch !== '')<a class="btn secondary" href="{{ route('admin.reseller.products.index', $tenantRoute) }}">Clear</a>@endif
                        </form>
                    </div>
                    <div class="panel-body"><div class="reseller-table-wrap"><table class="table"><thead><tr><th>Product</th><th>Supplier</th><th>Source price</th><th>Selling price</th><th>Availability</th><th>Checked</th><th>Actions</th></tr></thead><tbody>
                        @forelse ($products as $product)
                            <tr><td><strong>{{ $product->name }}</strong><div class="subtle">{{ $product->sku ?: 'No SKU' }}</div></td><td>{{ $product->supplier->name }}</td><td>{{ $formattedMoney($product->currency_code, $product->source_price_minor) }}</td><td><strong>{{ $formattedMoney($product->currency_code, $product->selling_price_minor) }}</strong></td><td>{{ str($product->availability->value)->headline() }}</td><td>{{ $product->last_checked_at->diffForHumans() }}</td><td><div class="reseller-row-actions">
                                <form method="POST" action="{{ route('admin.reseller.products.update', $product) }}">@csrf @method('PATCH')<input type="hidden" name="is_visible" value="{{ $product->is_visible ? 0 : 1 }}"><input type="hidden" name="is_excluded" value="0"><button class="icon-btn reseller-product-action" type="submit" aria-label="{{ $product->is_visible ? 'Hide' : 'Publish' }} {{ $product->name }}" title="{{ $product->is_visible ? 'Hide product' : 'Publish product' }}">@if ($product->is_visible)<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 3 18 18M10.6 10.7a2 2 0 0 0 2.7 2.7M9.9 4.2A10.7 10.7 0 0 1 12 4c5.5 0 9 6 9 6a16.8 16.8 0 0 1-2.1 2.8M6.6 6.6C4.3 8.1 3 10 3 10s3.5 6 9 6c1 0 2-.2 2.9-.5"/></svg>@else<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12s3.5-6 9-6 9 6 9 6-3.5 6-9 6-9-6-9-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>@endif</button></form>
                                <form method="POST" action="{{ route('admin.reseller.products.update', $product) }}" onsubmit="return confirm('Exclude this product from future automatic publishing?');">@csrf @method('PATCH')<input type="hidden" name="is_visible" value="0"><input type="hidden" name="is_excluded" value="1"><button class="icon-btn reseller-product-action exclude" type="submit" aria-label="Exclude {{ $product->name }}" title="Exclude product"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m6 6 12 12"/></svg></button></form>
                            </div></td></tr>
                        @empty
                            <tr><td colspan="7"><div class="empty">{{ $productSearch !== '' ? 'No sourced products match your search.' : 'No products recovered yet.' }}</div></td></tr>
                        @endforelse
                    </tbody></table></div>{{ $products->links() }}</div>
                </section>
            @elseif ($section === 'orders')
                <section class="panel">
                    <div class="panel-header"><div><h2 class="panel-title">Reseller orders</h2><p class="subtle">Customer orders from the reseller storefront.</p></div></div>
                    <div class="panel-body"><div class="reseller-table-wrap"><table class="table"><thead><tr><th>Order</th><th>Reseller</th><th>Customer</th><th>Items</th><th>Total</th><th>Payment</th><th>Fulfilment</th></tr></thead><tbody>
                        @forelse ($orders as $order)
                            <tr>
                                <td><a href="{{ route('admin.reseller.orders.show', $order) }}"><strong>{{ $order->order_reference }}</strong></a></td>
                                <td><strong>{{ $tenant->name }}</strong>@if ($resellerStoreName && $resellerStoreName !== $tenant->name)<div class="subtle">{{ $resellerStoreName }}</div>@endif</td>
                                <td>{{ $order->customer_name }}</td>
                                <td>{{ $order->items_count }}</td>
                                <td>{{ $order->currency_code }} {{ $money($order->total_minor) }}</td>
                                <td><span class="badge neutral">{{ str($order->payment_status)->headline() }}</span></td>
                                <td>{{ str($order->fulfilment_status)->headline() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="empty">No reseller orders yet.</div></td></tr>
                        @endforelse
                    </tbody></table></div></div>
                </section>
            @elseif ($section === 'payments')
                <section class="panel">
                    <div class="panel-header"><div><h2 class="panel-title">Reseller payments</h2><p class="subtle">Payments recorded against reseller orders.</p></div></div>
                    <div class="panel-body"><div class="reseller-table-wrap"><table class="table"><thead><tr><th>Reference</th><th>Order</th><th>Provider</th><th>Amount</th><th>Type</th><th>Status</th></tr></thead><tbody>
                        @forelse ($payments as $payment)<tr><td>{{ $payment->provider_reference }}</td><td>{{ $payment->order->order_reference }}</td><td>{{ str($payment->provider)->headline() }}</td><td>{{ $payment->currency_code }} {{ $money($payment->amount_minor) }}</td><td>{{ str($payment->type)->headline() }}</td><td>{{ str($payment->status)->headline() }}</td></tr>@empty<tr><td colspan="6"><div class="empty">No reseller payments yet.</div></td></tr>@endforelse
                    </tbody></table></div></div>
                </section>
            @elseif ($section === 'settings')
                <section class="panel">
                    <div class="panel-header"><div><h2 class="panel-title">Additional pricing and recovery</h2><p class="subtle">Applied to every recovered source price.</p></div></div>
                    <div class="panel-body">
                        <form method="POST" action="{{ route('admin.reseller.settings.update') }}" class="form-grid">
                            @csrf @method('PUT')
                            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                            <label>Pricing method<select name="pricing_mode" required>@foreach ($pricingModes as $mode)<option value="{{ $mode->value }}" @selected(old('pricing_mode', $settings->pricing_mode->value) === $mode->value)>{{ str($mode->value)->headline() }}</option>@endforeach</select></label>
                            <label>Percentage addition<div class="input-suffix"><input type="number" name="percentage_markup" value="{{ old('percentage_markup', $percentage) }}" min="0" max="500" step="0.01" required><span>%</span></div></label>
                            <label>Fixed addition ({{ $tenant->currency_code }})<input type="number" name="fixed_markup" value="{{ old('fixed_markup', $money($settings->fixed_markup_minor)) }}" min="0" step="0.01" required></label>
                            <label>Recovery schedule<select name="scan_frequency" required><option value="daily" @selected($settings->scan_frequency === 'daily')>Daily</option><option value="twice_daily" @selected($settings->scan_frequency === 'twice_daily')>Twice daily</option></select></label>
                            <label>Stale after (hours)<input type="number" name="stale_after_hours" value="{{ old('stale_after_hours', $settings->stale_after_hours) }}" min="1" max="720" required></label>
                            <label>Hide after missed scans<input type="number" name="hide_after_missing_scans" value="{{ old('hide_after_missing_scans', $settings->hide_after_missing_scans) }}" min="1" max="20" required></label>
                            <label class="check"><input type="checkbox" name="auto_publish_products" value="1" @checked(old('auto_publish_products', $settings->auto_publish_products))> Automatically publish valid products</label>
                            <label class="check"><input type="checkbox" name="show_source_store" value="1" @checked(old('show_source_store', $settings->show_source_store))> Show source store to customers</label>
                            <div class="full"><button class="btn primary" type="submit">Save and reprice products</button></div>
                        </form>
                    </div>
                </section>
            @endif
        @endif
    </div>
</x-layouts.admin>
