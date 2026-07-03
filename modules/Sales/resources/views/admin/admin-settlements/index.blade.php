<x-layouts.admin title="Admin Settlements">
    @php
        $currency = $selectedTenant?->currency_code ?? 'NGN';
        $money = fn (int $minor): string => $currency.' '.number_format($minor / 100, 2);
    @endphp

    <div class="topbar">
        <div>
            <div class="eyebrow">Superadmin only</div>
            <h1>Admin Settlements</h1>
            <p class="subtle">Create settlement batches across organizations and review completed settlements.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="alert">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert errors">{{ $errors->first() }}</div>
    @endif

    <div class="stats-grid" style="margin-bottom: 18px;">
        <div class="stat"><span class="subtle">Unsettled payments</span><strong>{{ $stats['unsettled_count'] }}</strong></div>
        <div class="stat"><span class="subtle">Unsettled amount</span><strong>{{ $money($stats['unsettled_minor']) }}</strong></div>
        <div class="stat"><span class="subtle">Total settled</span><strong>{{ $money($stats['settled_minor']) }}</strong></div>
        <div class="stat"><span class="subtle">Gateway charges</span><strong>{{ $money($stats['total_gateway_charge_minor']) }}</strong></div>
        <div class="stat"><span class="subtle">Storeboot charges</span><strong>{{ $money($stats['storeboot_charges_minor']) }}</strong></div>
    </div>

    <div class="tab-layout">
        <nav class="pill-nav" aria-label="Admin settlement sections" role="tablist">
            <a href="#list" role="tab" data-tab-target="list">List Settlements <span>{{ $settlements->count() }}</span></a>
            <a href="#online-payments" role="tab" data-tab-target="online-payments">List Online Payments <span>{{ $onlinePayments->count() }}</span></a>
            <a href="#create" role="tab" data-tab-target="create">Create <span>{{ $unsettledPayments->count() }}</span></a>
        </nav>

        <div class="content-stack">
            <section class="panel tab-panel" id="list" data-tab-panel>
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Settlement list</h2>
                        <p class="subtle">Filter settlements, then open a batch to see included payments.</p>
                    </div>
                    <button class="btn secondary" type="button" data-filter-toggle="settlement-list-filters" aria-expanded="false">Show filters</button>
                </div>
                <div class="panel-body">
                    <form class="form-grid" id="settlement-list-filters" method="GET" action="{{ route('admin.sales.admin-settlements.index') }}" style="display: none; margin-bottom: 16px;" data-admin-settlement-list-filter>
                        <input type="hidden" name="_tab" value="list">
                        <div class="field">
                            <label>Organization</label>
                            <select name="tenant" data-admin-settlement-filter-tenant>
                                @foreach ($tenants as $tenant)
                                    <option value="{{ $tenant->id }}" @selected($selectedTenant?->id === $tenant->id)>{{ $tenant->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field"><label>Reference</label><input name="reference" value="{{ $filters['reference'] }}" placeholder="SETT-..."></div>
                        <div class="field"><label>Provider</label><input name="provider" value="{{ $filters['provider'] }}" placeholder="paystack"></div>
                        <div class="field"><label>Status</label><input name="status" value="{{ $filters['status'] }}" placeholder="settled"></div>
                        <div class="field"><label>Currency</label><input name="currency" value="{{ $filters['currency'] }}" maxlength="3" placeholder="NGN"></div>
                        <div class="field"><label>Settlement from</label><input type="date" name="settlement_date_from" value="{{ $filters['settlement_date_from'] }}"></div>
                        <div class="field"><label>Settlement to</label><input type="date" name="settlement_date_to" value="{{ $filters['settlement_date_to'] }}"></div>
                        <div class="field"><label>Created from</label><input type="date" name="created_from" value="{{ $filters['created_from'] }}"></div>
                        <div class="field"><label>Created to</label><input type="date" name="created_to" value="{{ $filters['created_to'] }}"></div>
                        <div class="field"><label>Settled from</label><input type="date" name="settled_from" value="{{ $filters['settled_from'] }}"></div>
                        <div class="field"><label>Settled to</label><input type="date" name="settled_to" value="{{ $filters['settled_to'] }}"></div>
                        <div class="field full"><label>Notes</label><input name="notes" value="{{ $filters['notes'] }}" placeholder="Search notes"></div>
                        <div class="button-row" style="grid-column: 1 / -1; justify-content: flex-start; margin-top: 0;">
                            <button class="btn secondary" type="submit">Filter</button>
                            <a class="btn secondary" href="{{ route('admin.sales.admin-settlements.index') }}#list">Reset</a>
                        </div>
                    </form>

                    <table class="table">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Organization</th>
                                <th>Date</th>
                                <th>Payments</th>
                                <th>Gateway charges</th>
                                <th>Net amount</th>
                                <th>Storeboot charges</th>
                                <th>Settled</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($settlements as $settlement)
                                <tr>
                                    <td><strong>{{ $settlement->reference }}</strong><br><span class="badge neutral">{{ $settlement->status }}</span></td>
                                    <td>{{ $settlement->tenant?->name }}</td>
                                    <td>{{ $settlement->settlement_date?->format('M j, Y') ?? 'Not set' }}</td>
                                    <td>{{ $settlement->payment_count }}</td>
                                    <td>{{ $money((int) $settlement->total_gateway_charge_minor) }}</td>
                                    <td>{{ $money((int) $settlement->total_net_amount_minor) }}</td>
                                    <td>{{ $money((int) $settlement->storeboot_charges_minor) }}</td>
                                    <td>{{ $money((int) $settlement->total_settled_minor) }}</td>
                                    <td><a class="btn secondary" href="{{ route('admin.sales.admin-settlements.show', $settlement) }}">View</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="9"><div class="empty">No settlements have been created for this organization.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel tab-panel" id="online-payments" data-tab-panel hidden>
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Online payments</h2>
                        <p class="subtle">Successful and tracked online collections before or after settlement.</p>
                    </div>
                    <button class="btn secondary" type="button" data-filter-toggle="online-payment-filters" aria-expanded="false">Show filters</button>
                </div>
                <div class="panel-body">
                    <form class="form-grid" id="online-payment-filters" method="GET" action="{{ route('admin.sales.admin-settlements.index') }}#online-payments" style="display: none; margin-bottom: 16px;" data-admin-payment-list-filter>
                        <div class="field">
                            <label>Organization</label>
                            <select name="tenant" data-admin-payment-filter-tenant>
                                @foreach ($tenants as $tenant)
                                    <option value="{{ $tenant->id }}" @selected($selectedTenant?->id === $tenant->id)>{{ $tenant->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field"><label>Order reference</label><input name="payment_order" value="{{ $paymentFilters['order'] }}" placeholder="SO-..."></div>
                        <div class="field"><label>Payment reference</label><input name="payment_provider_reference" value="{{ $paymentFilters['provider_reference'] }}" placeholder="PSK-..."></div>
                        <div class="field"><label>Provider</label><input name="payment_provider" value="{{ $paymentFilters['provider'] }}" placeholder="paystack"></div>
                        <div class="field"><label>Payment method</label><input name="payment_method" value="{{ $paymentFilters['payment_method'] }}" placeholder="storeboot_paystack"></div>
                        <div class="field"><label>Status</label><input name="payment_status" value="{{ $paymentFilters['status'] }}" placeholder="successful"></div>
                        <div class="field">
                            <label>Settlement status</label>
                            <select name="payment_settlement_status">
                                <option value="">All</option>
                                <option value="settled" @selected($paymentFilters['settlement_status'] === 'settled')>Settled</option>
                                <option value="unsettled" @selected($paymentFilters['settlement_status'] === 'unsettled')>Unsettled</option>
                            </select>
                        </div>
                        <div class="field"><label>Currency</label><input name="payment_currency" value="{{ $paymentFilters['currency'] }}" maxlength="3" placeholder="NGN"></div>
                        <div class="field"><label>Customer</label><input name="payment_customer" value="{{ $paymentFilters['customer'] }}" placeholder="Name, phone, or email"></div>
                        <div class="field"><label>Collected from</label><input type="date" name="payment_collected_from" value="{{ $paymentFilters['collected_from'] }}"></div>
                        <div class="field"><label>Collected to</label><input type="date" name="payment_collected_to" value="{{ $paymentFilters['collected_to'] }}"></div>
                        <div class="field"><label>Verified from</label><input type="date" name="payment_verified_from" value="{{ $paymentFilters['verified_from'] }}"></div>
                        <div class="field"><label>Verified to</label><input type="date" name="payment_verified_to" value="{{ $paymentFilters['verified_to'] }}"></div>
                        <div class="button-row" style="grid-column: 1 / -1; justify-content: flex-start; margin-top: 0;">
                            <button class="btn secondary" type="submit">Filter</button>
                            <a class="btn secondary" href="{{ route('admin.sales.admin-settlements.index') }}#online-payments">Reset</a>
                        </div>
                    </form>

                    <table class="table">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Customer</th>
                                <th>Collected</th>
                                <th>Product</th>
                                <th>Shipping</th>
                                <th>Gateway</th>
                                <th>Amount</th>
                                <th>Fees</th>
                                <th>Net</th>
                                <th>Status</th>
                                <th>Settlement</th>
                                <th>Reference</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($onlinePayments as $payment)
                                <tr>
                                    <td><strong>{{ $payment->order?->order_number }}</strong><br><span class="subtle">{{ $payment->payment_method }}</span></td>
                                    <td>{{ $payment->order?->customer?->name ?? 'Customer' }}<br><span class="subtle">{{ $payment->customer_email }}</span></td>
                                    <td>{{ $payment->collected_at?->format('M j, Y g:ia') ?? 'Not set' }}</td>
                                    <td>{{ $money((int) $payment->product_amount_minor) }}</td>
                                    <td>{{ $money((int) $payment->shipping_amount_minor) }}</td>
                                    <td>{{ $money((int) $payment->gateway_charge_minor) }}</td>
                                    <td>{{ $money((int) $payment->amount_minor) }}</td>
                                    <td>{{ $money((int) $payment->fees_minor) }}</td>
                                    <td>{{ $money((int) $payment->net_amount_minor) }}</td>
                                    <td><span class="badge neutral">{{ $payment->status }}</span></td>
                                    <td>
                                        @if ($payment->settlement)
                                            <a href="{{ route('admin.sales.admin-settlements.show', $payment->settlement) }}">{{ $payment->settlement->reference }}</a>
                                        @else
                                            <span class="badge neutral">Unsettled</span>
                                        @endif
                                    </td>
                                    <td>{{ $payment->provider_reference }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="12"><div class="empty">No online payments matched these filters.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel tab-panel" id="create" data-tab-panel hidden>
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Create settlement</h2>
                        <p class="subtle">Upload a CSV or XLSX settlement sheet, validate it, then post settlements per organization.</p>
                    </div>
                </div>
                <div class="panel-body">
                    <form class="mini-form" method="POST" action="{{ route('admin.sales.admin-settlements.store') }}" enctype="multipart/form-data" data-admin-settlement-form>
                        @csrf
                        <div class="field">
                            <label>Settlement spreadsheet</label>
                            <input type="file" name="settlement_file" accept=".csv,.xlsx" required>
                            <span class="subtle">Required columns: online_collected_payment_id, tenant_id, gateway_reference.</span>
                        </div>
                        <div class="button-row"><button class="btn primary" type="submit">Validate spreadsheet</button></div>
                    </form>

                    @if ($settlementPreview)
                        <div style="margin-top: 18px;">
                            <div class="panel-header">
                                <div>
                                    <h3 class="panel-title">Settlement preview</h3>
                                    <p class="subtle">{{ $settlementPreview['overall']['tenant_count'] }} organization(s), {{ $settlementPreview['overall']['payment_count'] }} payment(s), gateway charges {{ $currency }} {{ number_format(($settlementPreview['overall']['total_gateway_charge_minor'] ?? 0) / 100, 2) }}, total net {{ $currency }} {{ number_format($settlementPreview['overall']['total_net_amount_minor'] / 100, 2) }}.</p>
                                </div>
                            </div>
                            <div class="panel-body">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Organization</th>
                                            <th>Payments</th>
                                            <th>Gateway charges</th>
                                            <th>Total net amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($settlementPreview['tenants'] as $tenantPreview)
                                            <tr>
                                                <td><strong>{{ $tenantPreview['tenant_name'] }}</strong><br><span class="subtle">{{ $tenantPreview['tenant_id'] }}</span></td>
                                                <td>{{ $tenantPreview['payment_count'] }}</td>
                                                <td>{{ $tenantPreview['currency_code'] }} {{ number_format(($tenantPreview['total_gateway_charge_minor'] ?? 0) / 100, 2) }}</td>
                                                <td>{{ $tenantPreview['currency_code'] }} {{ number_format($tenantPreview['total_net_amount_minor'] / 100, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>

                                <form method="POST" action="{{ route('admin.sales.admin-settlements.post') }}" style="margin-top: 16px;">
                                    @csrf
                                    <div class="button-row" style="justify-content: flex-start;">
                                        <button class="btn primary" type="submit">Post settlement</button>
                                        <button class="btn danger" type="submit" form="cancel-settlement-preview">Cancel</button>
                                    </div>
                                </form>
                                <form id="cancel-settlement-preview" method="POST" action="{{ route('admin.sales.admin-settlements.cancel-preview') }}">
                                    @csrf
                                </form>
                            </div>
                        </div>
                    @endif
                </div>
            </section>
        </div>
    </div>

    <script>
        document.querySelectorAll('[data-filter-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                const target = document.getElementById(button.dataset.filterToggle);

                if (target) {
                    const willOpen = target.style.display === 'none';

                    target.style.display = willOpen ? '' : 'none';
                    button.setAttribute('aria-expanded', String(willOpen));
                    button.textContent = willOpen ? 'Hide filters' : 'Show filters';
                }
            });
        });
    </script>
</x-layouts.admin>
