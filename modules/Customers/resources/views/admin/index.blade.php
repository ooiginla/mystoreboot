@php
    $money = fn (?int $minor): string => number_format(($minor ?? 0) / 100, 2);
    $statusTag = fn (string $status): string => match ($status) {
        'active', 'completed', 'resolved', 'closed', 'service_request' => 'success',
        'pending', 'open', 'in_progress', 'normal', 'internal_issue' => 'warning',
        'blocked', 'urgent', 'high', 'complaint' => 'danger',
        default => 'neutral',
    };
@endphp

<x-layouts.admin title="Customers & Support">
    <datalist id="customer-options">
        @foreach ($allCustomers as $customerOption)
            <option value="{{ $customerOption->name }} · {{ $customerOption->phone }}" data-customer-id="{{ $customerOption->id }}"></option>
        @endforeach
    </datalist>

    <style>
        .crm-filter { display: grid; grid-template-columns: 1.5fr repeat(2, minmax(0, 1fr)) auto; gap: 10px; align-items: end; margin-bottom: 16px; }
        .tag { display: inline-flex; border-radius: 6px; padding: 4px 8px; font-size: 12px; font-weight: 800; }
        .tag.neutral { background: #eef2f6; color: #475467; }
        .tag.success { background: #ecfdf3; color: #067647; }
        .tag.warning { background: #fffaeb; color: #b54708; }
        .tag.danger { background: #fef3f2; color: #b42318; }
        .summary-item .tag { display: inline-flex; margin-top: 0; text-transform: none; letter-spacing: 0; }
        .ticket-quick-actions { display: grid; grid-template-columns: minmax(0, 1fr) minmax(280px, 1fr); gap: 12px; margin-top: 16px; }
        .ticket-quick-action { display: flex; align-items: end; justify-content: space-between; gap: 12px; border: 1px solid var(--line); border-radius: 9px; background: var(--panel-soft); padding: 12px; }
        .ticket-assignment { display: grid; gap: 3px; }
        .ticket-assignment span { color: var(--muted); font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
        .ticket-status-form { display: flex; align-items: end; gap: 8px; }
        .ticket-status-form .field { flex: 1; }
        .ticket-status-form select { min-width: 150px; padding-top: 8px; padding-bottom: 8px; }
        .link-button { border: 0; background: transparent; padding: 0; color: var(--accent); cursor: pointer; font-weight: 800; text-align: left; }
        .mini-list { display: grid; gap: 8px; }
        .mini-row { border: 1px solid var(--line); border-radius: 8px; padding: 10px; display: flex; justify-content: space-between; gap: 10px; }
        .pricing-filter-form { display:grid; grid-template-columns:minmax(220px,1fr) minmax(220px,1fr) auto auto; gap:12px; align-items:end; margin-bottom:16px; }
        .pricing-table-wrap { overflow-x:auto; }
        .pricing-list-table { min-width:900px; }
        .pricing-list-name { color:var(--accent); font-weight:800; }
        .pricing-list-name:hover { color:var(--accent-dark); }
        .pricing-actions { display:flex; gap:8px; align-items:center; justify-content:flex-end; white-space:nowrap; }
        .credit-line { display:flex; gap:8px; flex-wrap:wrap; margin-top:5px; }
        .customer-list-heading { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:14px; }
        .customer-list-heading p { margin:0; }
        .customer-filter-panel { padding:16px; margin-bottom:16px; }
        .customer-filter-form { display:grid; grid-template-columns:minmax(280px,1.5fr) minmax(180px,.8fr) minmax(180px,.8fr) auto; gap:12px; align-items:center; }
        .customer-search { position:relative; }
        .customer-search svg { position:absolute; left:14px; top:50%; width:19px; height:19px; transform:translateY(-50%); color:var(--muted); pointer-events:none; }
        .customer-search input { padding-left:43px; }
        .customer-table-panel { overflow:visible; }
        .customer-table-scroll { overflow-x:auto; min-height:310px; }
        .customer-table { min-width:1120px; }
        .customer-table th, .customer-table td { padding:16px 18px; }
        .customer-table th { white-space:nowrap; }
        .customer-identity { display:grid; grid-template-columns:48px minmax(160px,1fr); align-items:center; gap:14px; color:inherit; }
        .customer-avatar { width:48px; height:48px; display:grid; place-items:center; border-radius:50%; background:var(--brand-050); color:var(--brand-strong); font-size:17px; font-weight:800; }
        .customer-identity strong { display:block; color:var(--ink); font-size:14px; }
        .customer-identity:hover strong { color:var(--brand-strong); }
        .customer-contact-line { display:block; color:var(--muted); font-size:12.5px; line-height:1.55; overflow-wrap:anywhere; }
        .customer-group-cell { display:grid; gap:5px; justify-items:start; }
        .customer-status { display:inline-flex; align-items:center; gap:7px; border-radius:7px; padding:6px 10px; font-size:12.5px; font-weight:800; white-space:nowrap; }
        .customer-status::before { content:''; width:8px; height:8px; border-radius:50%; background:currentColor; }
        .customer-status.active { color:#067647; background:#ecfdf3; }
        .customer-status.inactive { color:#475467; background:#eef2f6; }
        .customer-status.blocked { color:#b42318; background:#fef3f2; }
        .customer-row-actions { display:flex; align-items:center; justify-content:flex-end; gap:9px; white-space:nowrap; }
        .customer-menu-trigger { width:42px; height:38px; display:grid; place-items:center; border:1px solid var(--line); border-radius:9px; background:#fff; color:var(--ink-soft); cursor:pointer; font-size:17px; font-weight:800; letter-spacing:2px; }
        .customer-menu-trigger:hover { background:var(--panel-soft); }
        .customer-row-actions .table-action-menu[open] > .customer-menu-trigger { background:var(--panel-soft); color:var(--ink); }
        .customer-table tbody tr:only-child .table-action-dropdown { top:calc(100% + 7px); bottom:auto; }
        .customer-table-footer { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:14px 18px; border-top:1px solid var(--line); flex-wrap:wrap; }
        .customer-pagination-controls { display:flex; align-items:center; gap:10px; }
        .customer-per-page { display:flex; align-items:center; gap:8px; margin:0; }
        .customer-per-page select { width:auto; min-width:118px; padding-top:8px; padding-bottom:8px; }
        .customer-page-button { min-width:38px; height:38px; display:inline-grid; place-items:center; border:1px solid var(--line); border-radius:9px; background:#fff; color:var(--ink-soft); font-weight:750; }
        .customer-page-button.current { border-color:var(--brand); background:var(--brand-050); color:var(--brand-strong); }
        .customer-page-button.disabled { opacity:.45; pointer-events:none; }
        @media (max-width: 960px) { .crm-filter, .ticket-quick-actions, .customer-filter-form, .pricing-filter-form { grid-template-columns: 1fr; } }
        @media (max-width: 640px) { .customer-list-heading, .customer-table-footer { align-items:stretch; flex-direction:column; } .customer-pagination-controls { justify-content:space-between; } }
    </style>

    <div class="topbar">
        <div>
            <div class="eyebrow">Customer relationship management</div>
            <h1>Customers & Support</h1>
            <p class="subtle">Customer records, segmentation, follow-ups, purchase history, enquiries, and support tickets for {{ $tenant->name }}.</p>
        </div>
        @if ($isPlatformAdmin)
            <form method="GET" action="{{ route('admin.customers.index') }}" style="min-width: 260px;">
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
            <strong>Check the CRM details.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="stats-grid" style="margin-bottom: 18px;">
        <div class="stat"><span class="subtle">Customers</span><strong>{{ $stats['customers'] }}</strong></div>
        <div class="stat"><span class="subtle">Buying customers</span><strong>{{ $stats['top_customers'] }}</strong></div>
        <div class="stat"><span class="subtle">Open tickets</span><strong>{{ $stats['open_tickets'] }}</strong></div>
        <div class="stat"><span class="subtle">Due follow-ups</span><strong>{{ $stats['due_follow_ups'] }}</strong></div>
    </div>

    <div class="tab-layout">
        <nav class="pill-nav" aria-label="CRM sections" role="tablist">
            <a href="#customers" role="tab" data-tab-target="customers">Customers <span class="badge neutral">{{ $customers->total() }}</span></a>
            <a href="#groups" role="tab" data-tab-target="groups">Groups <span class="badge neutral">{{ $groups->count() }}</span></a>
            <a href="#price-lists" role="tab" data-tab-target="price-lists">Pricelist <span class="badge neutral">{{ $priceLists->count() }}</span></a>
            <a href="#customer-pricing" role="tab" data-tab-target="customer-pricing">Customer pricing <span class="badge neutral">{{ $specificPrices->count() }}</span></a>
            <a href="#history" role="tab" data-tab-target="history">Purchase history</a>
            <a href="#follow-ups" role="tab" data-tab-target="follow-ups">Follow-ups <span class="badge neutral">{{ $followUps->count() }}</span></a>
            <a href="#tickets" role="tab" data-tab-target="tickets">Tickets <span class="badge neutral">{{ $tickets->count() }}</span></a>
            <a href="#insights" role="tab" data-tab-target="insights">Insights</a>
        </nav>

        <div class="content-stack">
            <section class="tab-panel" id="customers" role="tabpanel" data-tab-panel>
                <div class="customer-list-heading">
                    <div>
                        <h2 class="panel-title">Customer database</h2>
                        <p class="subtle">{{ $stats['customers'] }} {{ \Illuminate\Support\Str::plural('customer', $stats['customers']) }} · {{ $stats['active_customers'] }} active</p>
                    </div>
                    <button class="btn accent" type="button" data-dialog-open="customer-dialog">Add customer</button>
                </div>

                <form class="panel customer-filter-panel customer-filter-form" method="GET" action="{{ route('admin.customers.index') }}#customers">
                    <input type="hidden" name="tenant" value="{{ $tenant->id }}">
                    <input type="hidden" name="per_page" value="{{ $filters['per_page'] }}">
                    <div class="customer-search">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                        <input name="search" value="{{ $search }}" placeholder="Search name, phone or email" aria-label="Search customers">
                    </div>
                    <select name="group_id" aria-label="Filter by customer group" onchange="this.form.submit()">
                        <option value="">All groups</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}" @selected($filters['group_id'] === (string) $group->id)>{{ $group->name }}</option>
                        @endforeach
                    </select>
                    <select name="status" aria-label="Filter by customer status" onchange="this.form.submit()">
                        <option value="">All statuses</option>
                        @foreach ($customerStatuses as $customerStatus)
                            <option value="{{ $customerStatus->value }}" @selected($filters['status'] === $customerStatus->value)>{{ $customerStatus->label() }}</option>
                        @endforeach
                    </select>
                    <a class="btn secondary" href="{{ route('admin.customers.index', ['tenant' => $tenant->id]).'#customers' }}">Reset</a>
                </form>

                <div class="panel customer-table-panel">
                    <div class="customer-table-scroll">
                        <table class="table customer-table">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Group / Type</th>
                                    <th>Balance</th>
                                    <th>Available credit</th>
                                    <th>Loyalty</th>
                                    <th>Status</th>
                                    <th style="text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($customers as $customer)
                                    <tr>
                                        <td>
                                            <a class="customer-identity" href="{{ route('admin.customers.customers.show', ['customer' => $customer, 'tenant' => $tenant->id]) }}">
                                                <span class="customer-avatar">{{ str($customer->first_name)->substr(0, 1)->upper() }}</span>
                                                <span>
                                                    <strong>{{ $customer->name }}</strong>
                                                    <span class="customer-contact-line">{{ $customer->email ?: 'No email' }}</span>
                                                    <span class="customer-contact-line">{{ $customer->phone }}</span>
                                                </span>
                                            </a>
                                        </td>
                                        <td>
                                            <div class="customer-group-cell">
                                                <span>{{ $customer->group?->name ?? 'No group' }}</span>
                                                <span class="tag neutral">{{ $customer->credit_profile['price_list_label'] }}</span>
                                            </div>
                                        </td>
                                        <td><strong>{{ $tenant->currency_code }} {{ $money($customer->account_balance_minor) }}</strong></td>
                                        <td>
                                            @if ($customer->credit_profile['on_hold'])
                                                <span class="tag danger">On hold</span>
                                            @elseif (! $customer->credit_profile['enabled'])
                                                <span class="tag warning">Disabled</span>
                                            @else
                                                {{ $customer->credit_profile['available_minor'] === null ? 'Unlimited' : $tenant->currency_code.' '.$money($customer->credit_profile['available_minor']) }}
                                            @endif
                                        </td>
                                        <td>{{ number_format($customer->loyalty_points) }} pts</td>
                                        <td><span class="customer-status {{ $customer->status->value }}">{{ $customer->status->label() }}</span></td>
                                        <td>
                                            <div class="customer-row-actions">
                                                <button class="btn secondary" type="button" data-dialog-open="customer-edit-{{ $customer->id }}">Edit</button>
                                                <details class="table-action-menu">
                                                    <summary class="customer-menu-trigger" aria-label="More actions for {{ $customer->name }}">•••</summary>
                                                    <div class="table-action-dropdown">
                                                        <div class="table-action-heading">Actions</div>
                                                        <a class="table-action-option open" href="{{ route('admin.customers.customers.show', ['customer' => $customer, 'tenant' => $tenant->id]) }}">View customer</a>
                                                        @permission('customers.credit.manage')
                                                            <button class="table-action-option edit" type="button" data-dialog-open="balance-adjustment-{{ $customer->id }}">Adjust balance</button>
                                                        @endpermission
                                                    </div>
                                                </details>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7"><div class="empty">No customers match the current filters.</div></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="customer-table-footer">
                        <span class="subtle">
                            @if ($customers->total())
                                Showing {{ $customers->firstItem() }}–{{ $customers->lastItem() }} of {{ $customers->total() }} customers
                            @else
                                Showing 0 customers
                            @endif
                        </span>
                        <div class="customer-pagination-controls">
                            <form class="customer-per-page" method="GET" action="{{ route('admin.customers.index') }}#customers">
                                <input type="hidden" name="tenant" value="{{ $tenant->id }}">
                                <input type="hidden" name="search" value="{{ $search }}">
                                <input type="hidden" name="group_id" value="{{ $filters['group_id'] }}">
                                <input type="hidden" name="status" value="{{ $filters['status'] }}">
                                <select name="per_page" aria-label="Customers per page" onchange="this.form.submit()">
                                    @foreach ([10, 20, 50, 100] as $pageSize)
                                        <option value="{{ $pageSize }}" @selected($filters['per_page'] === $pageSize)>{{ $pageSize }} per page</option>
                                    @endforeach
                                </select>
                            </form>
                            <a @class(['customer-page-button', 'disabled' => $customers->onFirstPage()]) href="{{ $customers->previousPageUrl() ?? '#' }}" aria-label="Previous page">‹</a>
                            @php
                                $pageStart = max(1, $customers->currentPage() - 1);
                                $pageEnd = min($customers->lastPage(), $customers->currentPage() + 1);
                            @endphp
                            @if ($pageStart > 1)
                                <a class="customer-page-button" href="{{ $customers->url(1) }}">1</a>
                                @if ($pageStart > 2)<span class="subtle">…</span>@endif
                            @endif
                            @foreach ($customers->getUrlRange($pageStart, $pageEnd) as $page => $url)
                                @if ($page === $customers->currentPage())
                                    <span class="customer-page-button current" aria-current="page">{{ $page }}</span>
                                @else
                                    <a class="customer-page-button" href="{{ $url }}">{{ $page }}</a>
                                @endif
                            @endforeach
                            @if ($pageEnd < $customers->lastPage())
                                @if ($pageEnd < $customers->lastPage() - 1)<span class="subtle">…</span>@endif
                                <a class="customer-page-button" href="{{ $customers->url($customers->lastPage()) }}">{{ $customers->lastPage() }}</a>
                            @endif
                            <a @class(['customer-page-button', 'disabled' => ! $customers->hasMorePages()]) href="{{ $customers->nextPageUrl() ?? '#' }}" aria-label="Next page">›</a>
                        </div>
                    </div>
                </div>
            </section>

            <section class="panel tab-panel" id="groups" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header"><div><h2 class="panel-title">Customer groups</h2><p class="subtle">Segment customers for offers, follow-ups, and reporting.</p></div><button class="btn accent" type="button" data-dialog-open="group-dialog">Add group</button></div>
                <div class="panel-body">
                    <table class="table">
                        <thead><tr><th>Name</th><th>Code</th><th>Price list</th><th>Credit policy</th><th>Customers</th><th>Description</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($groups as $group)
                                <tr><td><a class="link-button" href="{{ route('admin.customers.index', ['tenant' => $tenant->id, 'group_id' => $group->id]).'#customers' }}">{{ $group->name }}</a></td><td>{{ $group->code ?: 'Not set' }}</td><td>{{ $group->priceList?->name ?? 'Retail' }}</td><td>{{ $group->credit_enabled ? ($group->credit_limit_minor === null ? 'Allowed · unlimited' : 'Allowed · '.$tenant->currency_code.' '.$money($group->credit_limit_minor)) : 'Disabled' }}@if($group->credit_terms_days !== null)<br><span class="subtle">{{ $group->credit_terms_days }} day terms</span>@endif</td><td>{{ $allCustomers->where('customer_group_id', $group->id)->count() }}</td><td>{{ $group->description ?: 'No description' }}</td><td><button class="btn secondary" type="button" data-dialog-open="group-edit-{{ $group->id }}">Edit</button></td></tr>
                            @empty
                                <tr><td colspan="7"><div class="empty">No customer groups yet.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel tab-panel" id="price-lists" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header">
                    <div><h2 class="panel-title">Pricelist</h2><p class="subtle">Reusable wholesale, reseller, and other customer-group price lists.</p></div>
                    @permission('customers.pricing.manage')<button class="btn accent" type="button" data-dialog-open="price-list-dialog">Create pricelist</button>@endpermission
                </div>
                <div class="pricing-table-wrap">
                    <table class="table pricing-list-table">
                        <thead><tr><th>Pricelist</th><th>Products</th><th>Assigned groups</th><th>Direct customers</th><th>Validity</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
                        <tbody>
                            @forelse ($priceLists as $priceList)
                                <tr>
                                    <td><a class="pricing-list-name" href="{{ route('admin.customers.price-lists.show', ['priceList' => $priceList, 'tenant' => $tenant->id]) }}">{{ $priceList->name }}</a><br><span class="subtle">{{ $priceList->code }}</span></td>
                                    <td>{{ $priceList->items_count }}</td>
                                    <td>{{ $priceList->groups_count }}</td>
                                    <td>{{ $priceList->customers_count }}</td>
                                    <td>{{ $priceList->starts_at?->format('M j, Y') ?? 'Any date' }} – {{ $priceList->ends_at?->format('M j, Y') ?? 'No expiry' }}</td>
                                    <td><span class="tag {{ $priceList->is_active ? 'success' : 'neutral' }}">{{ $priceList->is_active ? 'Active' : 'Inactive' }}</span></td>
                                    <td>
                                        <div class="pricing-actions">
                                            <a class="btn primary" href="{{ route('admin.customers.price-lists.show', ['priceList' => $priceList, 'tenant' => $tenant->id]) }}">Open</a>
                                            @permission('customers.pricing.manage')<button class="btn secondary" type="button" data-dialog-open="price-list-edit-{{ $priceList->id }}">Edit</button>@endpermission
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7"><div class="empty">No pricelists yet. Create a Wholesale or Reseller pricelist to begin.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel tab-panel" id="customer-pricing" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header">
                    <div><h2 class="panel-title">Customer pricing</h2><p class="subtle">Customer-specific product prices that override pricelist and retail pricing.</p></div>
                    @permission('customers.pricing.manage')<button class="btn accent" type="button" data-dialog-open="specific-price-dialog">Add customer price</button>@endpermission
                </div>
                <div class="panel-body">
                    <form class="pricing-filter-form" method="GET" action="{{ route('admin.customers.index') }}#customer-pricing">
                        <input type="hidden" name="tenant" value="{{ $tenant->id }}">
                        <div class="field">
                            <label>Customer</label>
                            <select name="pricing_customer_id">
                                <option value="">All customers</option>
                                @foreach ($allCustomers as $customerOption)
                                    @continue($customerOption->phone === 'WALK-IN')
                                    <option value="{{ $customerOption->id }}" @selected($filters['pricing_customer_id'] === (string) $customerOption->id)>{{ $customerOption->name }} · {{ $customerOption->phone }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>Product</label>
                            <select name="pricing_variant_id">
                                <option value="">All products</option>
                                @foreach ($pricingVariants as $variant)
                                    <option value="{{ $variant->id }}" @selected($filters['pricing_variant_id'] === (string) $variant->id)>{{ $variant->product?->name }}{{ $variant->variant_name !== 'Default' ? ' · '.$variant->variant_name : '' }} · {{ $variant->sku }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button class="btn primary" type="submit">Apply</button>
                        <a class="btn secondary" href="{{ route('admin.customers.index', ['tenant' => $tenant->id]).'#customer-pricing' }}">Reset</a>
                    </form>

                    <div class="pricing-table-wrap">
                        <table class="table pricing-list-table">
                            <thead><tr><th>Customer</th><th>Product</th><th>Minimum quantity</th><th>Unit price</th><th>Validity</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
                            <tbody>
                                @forelse ($specificPrices as $price)
                                    <tr data-specific-price-row="{{ $price->id }}">
                                        <td><strong>{{ $price->customer?->name }}</strong><br><span class="subtle">{{ $price->customer?->phone }}</span></td>
                                        <td>{{ $price->variant?->product?->name }}@if($price->variant?->variant_name && $price->variant->variant_name !== 'Default')<br><span class="subtle">{{ $price->variant->variant_name }} · {{ $price->variant->sku }}</span>@endif</td>
                                        <td>{{ \Modules\Inventory\Support\Quantity::format($price->minimum_quantity) }}</td>
                                        <td><strong>{{ $tenant->currency_code }} {{ $money($price->unit_price_minor) }}</strong><br><span class="subtle">{{ $price->allow_additional_discounts ? 'Additional discounts allowed' : 'No additional discounts' }}</span></td>
                                        <td>{{ $price->starts_at?->format('M j, Y') ?? 'Any date' }} – {{ $price->ends_at?->format('M j, Y') ?? 'No expiry' }}</td>
                                        <td><span class="tag {{ $price->is_active ? 'success' : 'neutral' }}">{{ $price->is_active ? 'Active' : 'Inactive' }}</span></td>
                                        <td>
                                            @permission('customers.pricing.manage')
                                                <div class="pricing-actions">
                                                    <button class="btn secondary" type="button" data-dialog-open="specific-price-edit-{{ $price->id }}">Edit</button>
                                                    <form method="POST" action="{{ route('admin.customers.specific-prices.destroy', $price) }}" onsubmit="return confirm('Delete this customer-specific price?');">
                                                        @csrf @method('DELETE')
                                                        <button class="btn danger" type="submit">Delete</button>
                                                    </form>
                                                </div>
                                            @endpermission
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7"><div class="empty">No customer-specific prices match the selected filters.</div></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="panel tab-panel" id="history" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header"><div><h2 class="panel-title">Purchase history</h2><p class="subtle">Customer spend, product summaries, and loyalty awards.</p></div><button class="btn accent" type="button" data-dialog-open="purchase-dialog">Record purchase</button></div>
                <div class="panel-body">
                    <table class="table">
                        <thead><tr><th>Date</th><th>Customer</th><th>Items</th><th>Amount</th><th>Loyalty</th><th>Reference</th></tr></thead>
                        <tbody>
                            @forelse ($purchases as $purchase)
                                <tr><td>{{ $purchase->purchase_date->format('M j, Y') }}</td><td>{{ $purchase->customer->name }}</td><td>{{ $purchase->product_summary ?: 'Not specified' }}</td><td>{{ $tenant->currency_code }} {{ $money($purchase->amount_minor) }}</td><td>{{ $purchase->loyalty_points_awarded }} pts</td><td>{{ $purchase->reference_number ?: 'Not set' }}</td></tr>
                            @empty
                                <tr><td colspan="6"><div class="empty">No purchase history yet.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel tab-panel" id="follow-ups" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header"><div><h2 class="panel-title">Customer follow-ups</h2><p class="subtle">Reminders for calls, visits, renewals, birthdays, and anniversaries.</p></div><button class="btn accent" type="button" data-dialog-open="follow-up-dialog">Schedule follow-up</button></div>
                <div class="panel-body">
                    <table class="table">
                        <thead><tr><th>Due</th><th>Customer</th><th>Subject</th><th>Channel</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($followUps as $followUp)
                                <tr>
                                    <td>{{ $followUp->due_date->format('M j, Y') }}</td>
                                    <td>{{ $followUp->customer->name }}</td>
                                    <td>{{ $followUp->subject }}<br><span class="subtle">{{ $followUp->notes }}</span></td>
                                    <td>{{ $followUp->channel ?: 'Not set' }}</td>
                                    <td><span class="tag {{ $statusTag($followUp->status->value) }}">{{ $followUp->status->label() }}</span></td>
                                    <td><form method="POST" action="{{ route('admin.customers.follow-ups.complete', $followUp) }}">@csrf<button class="btn secondary" type="submit">Complete</button></form></td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><div class="empty">No pending follow-ups.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel tab-panel" id="tickets" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header"><div><h2 class="panel-title">Enquiries, support & ticketing</h2><p class="subtle">Complaints, service requests, internal issues, responses, and status tracking.</p></div><button class="btn accent" type="button" data-dialog-open="ticket-dialog">Create ticket</button></div>
                <div class="panel-body">
                    <form class="crm-filter" method="GET" action="{{ route('admin.customers.index') }}#tickets">
                        <input type="hidden" name="tenant" value="{{ $tenant->id }}">
                        <div class="field"><label>Search tickets</label><input name="ticket_search" value="{{ $filters['ticket_search'] }}" placeholder="Customer name, phone, email, ticket number, or subject"></div>
                        <div class="field">
                            <label>Category</label>
                            <select name="ticket_category">
                                <option value="">All categories</option>
                                @foreach ($ticketCategories as $ticketCategory)
                                    <option value="{{ $ticketCategory }}" @selected($filters['ticket_category'] === $ticketCategory)>{{ $ticketCategory }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>Status</label>
                            <select name="ticket_status">
                                <option value="">All statuses</option>
                                @foreach ($ticketStatuses as $ticketStatus)
                                    <option value="{{ $ticketStatus->value }}" @selected($filters['ticket_status'] === $ticketStatus->value)>{{ $ticketStatus->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="button-row" style="margin-top: 0; justify-content: flex-start;"><button class="btn secondary" type="submit">Apply</button><a class="btn secondary" href="{{ route('admin.customers.index', ['tenant' => $tenant->id]).'#tickets' }}">Reset</a></div>
                    </form>
                    <table class="table">
                        <thead><tr><th>Ticket</th><th>Customer</th><th>Type</th><th>Category</th><th>Priority</th><th>Status</th><th>Assigned</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($tickets as $ticket)
                                <tr data-ticket-row="{{ $ticket->id }}">
                                    <td><button class="link-button" type="button" data-dialog-open="ticket-view-{{ $ticket->id }}">{{ $ticket->ticket_number }}</button><br><span class="subtle">{{ $ticket->subject }}</span></td>
                                    <td>{{ $ticket->customer?->name ?? 'Internal' }}</td>
                                    <td>{{ $ticket->type->label() }}</td>
                                    <td>{{ $ticket->category ?: 'No category' }}</td>
                                    <td><span class="tag {{ $statusTag($ticket->priority->value) }}">{{ $ticket->priority->label() }}</span></td>
                                    <td><span class="tag {{ $statusTag($ticket->status->value) }}">{{ $ticket->status->label() }}</span></td>
                                    <td>{{ $ticket->assignee?->name ?? 'Unassigned' }}</td>
                                    <td><button class="btn secondary" type="button" data-dialog-open="ticket-edit-{{ $ticket->id }}">Edit</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="8"><div class="empty">No tickets match the current filters.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel tab-panel" id="insights" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header"><div><h2 class="panel-title">Intelligent CRM insights</h2><p class="subtle">Recommendations generated from customer activity in this module.</p></div></div>
                <div class="panel-body" style="display: grid; gap: 16px;">
                    @include('customers::admin.partials.insight-list', ['title' => 'Top customers', 'rows' => $insights['top_customers'], 'kind' => 'top'])
                    @include('customers::admin.partials.insight-list', ['title' => 'Recommended follow-ups', 'rows' => $insights['follow_up_recommendations'], 'kind' => 'follow'])
                    @include('customers::admin.partials.insight-list', ['title' => 'Inactive customers', 'rows' => $insights['inactive_customers'], 'kind' => 'inactive'])
                    @include('customers::admin.partials.insight-list', ['title' => 'Targeted offer suggestions', 'rows' => $insights['targeted_offers'], 'kind' => 'offer'])
                </div>
            </section>
        </div>
    </div>

    @include('customers::admin.partials.customer-dialog')
    @include('customers::admin.partials.group-dialog')
    @permission('customers.pricing.manage')
        @include('customers::admin.partials.price-list-dialog')
        @foreach ($priceLists as $priceList)
            @include('customers::admin.partials.price-list-dialog', ['selectedPriceList' => $priceList, 'dialogId' => 'price-list-edit-'.$priceList->id])
        @endforeach
        @include('customers::admin.partials.specific-price-dialog')
        @foreach ($specificPrices as $price)
            @include('customers::admin.partials.specific-price-dialog', ['selectedPrice' => $price, 'dialogId' => 'specific-price-edit-'.$price->id])
        @endforeach
    @endpermission
    @include('customers::admin.partials.purchase-dialog')
    @include('customers::admin.partials.follow-up-dialog')
    @include('customers::admin.partials.ticket-dialog')

    @foreach ($customers as $customer)
        @include('customers::admin.partials.customer-dialog', ['dialogId' => 'customer-edit-'.$customer->id, 'selectedCustomer' => $customer])
        @permission('customers.credit.manage')
            @include('customers::admin.partials.balance-adjustment-dialog', ['customer' => $customer])
        @endpermission
    @endforeach
    @foreach ($groups as $group)
        @include('customers::admin.partials.group-dialog', ['selectedGroup' => $group, 'dialogId' => 'group-edit-'.$group->id])
    @endforeach
    @foreach ($allTickets as $ticket)
        @include('customers::admin.partials.ticket-view-dialog', ['ticket' => $ticket])
        @include('customers::admin.partials.ticket-dialog', ['dialogId' => 'ticket-edit-'.$ticket->id, 'selectedTicket' => $ticket])
    @endforeach
</x-layouts.admin>
