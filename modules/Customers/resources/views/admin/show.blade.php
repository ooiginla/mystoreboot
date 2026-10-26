@php
    $money = fn (?int $minor): string => number_format(($minor ?? 0) / 100, 2);
    $statusClass = fn (string $status): string => match ($status) {
        'active', 'completed', 'resolved', 'closed' => 'success',
        'pending', 'open', 'in_progress', 'normal' => 'warning',
        'blocked', 'urgent', 'high' => 'danger',
        default => 'neutral',
    };
@endphp

<x-layouts.admin :title="$customer->name">
    <style>
        .customer-back-link { display:inline-flex; align-items:center; gap:7px; color:var(--muted); font-size:13px; font-weight:700; margin-bottom:12px; }
        .customer-back-link:hover { color:var(--brand-strong); }
        .customer-profile-header { display:flex; align-items:center; justify-content:space-between; gap:20px; padding:22px; margin-bottom:18px; }
        .customer-profile-main { display:flex; align-items:center; gap:16px; min-width:0; }
        .customer-profile-avatar { width:64px; height:64px; flex:0 0 64px; display:grid; place-items:center; border-radius:50%; background:var(--brand-050); color:var(--brand-strong); font-size:24px; font-weight:800; }
        .customer-profile-main h1 { margin:0 0 5px; }
        .customer-profile-contact { color:var(--muted); font-size:13.5px; overflow-wrap:anywhere; }
        .customer-profile-actions { display:flex; align-items:center; gap:9px; flex-wrap:wrap; justify-content:flex-end; }
        .customer-detail-status, .customer-detail-tag { display:inline-flex; align-items:center; gap:7px; border-radius:7px; padding:6px 10px; font-size:12.5px; font-weight:800; }
        .customer-detail-status::before { content:''; width:8px; height:8px; border-radius:50%; background:currentColor; }
        .customer-detail-status.active, .customer-detail-tag.success { color:#067647; background:#ecfdf3; }
        .customer-detail-status.inactive, .customer-detail-tag.neutral { color:#475467; background:#eef2f6; }
        .customer-detail-status.blocked, .customer-detail-tag.danger { color:#b42318; background:#fef3f2; }
        .customer-detail-tag.warning { color:#b54708; background:#fffaeb; }
        .customer-metric-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; margin-bottom:18px; }
        .customer-metric { padding:17px 18px; }
        .customer-metric span { display:block; color:var(--muted); font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
        .customer-metric strong { display:block; margin-top:6px; font-size:19px; line-height:1.25; overflow-wrap:anywhere; }
        .customer-sales-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; }
        .customer-overview-grid { display:grid; grid-template-columns:minmax(0,1.2fr) minmax(300px,.8fr); gap:18px; margin-bottom:18px; }
        .customer-info-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:0; }
        .customer-info-item { padding:14px 0; border-bottom:1px solid var(--line-soft); }
        .customer-info-item:nth-child(odd) { padding-right:18px; }
        .customer-info-item:nth-child(even) { padding-left:18px; border-left:1px solid var(--line-soft); }
        .customer-info-item span { display:block; color:var(--muted); font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
        .customer-info-item strong { display:block; margin-top:4px; font-size:13.5px; overflow-wrap:anywhere; }
        .customer-notes { white-space:pre-wrap; overflow-wrap:anywhere; }
        .customer-activity-tabs { position:static; margin-bottom:14px; }
        .customer-detail-table-scroll { overflow-x:auto; }
        .customer-detail-table { min-width:760px; }
        .customer-credit-description { display:grid; gap:9px; }
        .customer-credit-row { display:flex; justify-content:space-between; gap:14px; padding-bottom:9px; border-bottom:1px solid var(--line-soft); }
        .customer-credit-row:last-child { border-bottom:0; padding-bottom:0; }
        @media (max-width:1000px) { .customer-metric-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } .customer-overview-grid { grid-template-columns:1fr; } }
        @media (max-width:680px) { .customer-profile-header { align-items:flex-start; flex-direction:column; } .customer-profile-actions { width:100%; justify-content:flex-start; } .customer-metric-grid, .customer-sales-grid, .customer-info-grid { grid-template-columns:1fr; } .customer-info-item:nth-child(odd), .customer-info-item:nth-child(even) { padding-left:0; padding-right:0; border-left:0; } }
    </style>

    <a class="customer-back-link" href="{{ route('admin.customers.index', ['tenant' => $tenant->id]).'#customers' }}">
        <span aria-hidden="true">←</span> Back to customers
    </a>

    @if (session('status'))
        <div class="alert">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert errors">{{ $errors->first() }}</div>
    @endif

    <section class="panel customer-profile-header">
        <div class="customer-profile-main">
            <span class="customer-profile-avatar">{{ str($customer->first_name)->substr(0, 1)->upper() }}</span>
            <div>
                <h1>{{ $customer->name }}</h1>
                <div class="customer-profile-contact">{{ $customer->email ?: 'No email address' }} · {{ $customer->phone }}</div>
            </div>
        </div>
        <div class="customer-profile-actions">
            <span class="customer-detail-status {{ $customer->status->value }}">{{ $customer->status->label() }}</span>
            @permission('customers.credit.manage')
                <button class="btn secondary" type="button" data-dialog-open="balance-adjustment-{{ $customer->id }}">Adjust balance</button>
            @endpermission
            <button class="btn primary" type="button" data-dialog-open="customer-edit-{{ $customer->id }}">Edit customer</button>
        </div>
    </section>

    <div class="customer-metric-grid">
        <div class="panel customer-metric">
            <span>Outstanding balance</span>
            <strong>{{ $tenant->currency_code }} {{ $money($customer->account_balance_minor) }}</strong>
        </div>
        <div class="panel customer-metric">
            <span>Available credit</span>
            <strong>
                @if ($creditProfile['on_hold'])
                    <span class="customer-detail-tag danger">On hold</span>
                @elseif (! $creditProfile['enabled'])
                    <span class="customer-detail-tag warning">Disabled</span>
                @else
                    {{ $creditProfile['available_minor'] === null ? 'Unlimited' : $tenant->currency_code.' '.$money($creditProfile['available_minor']) }}
                @endif
            </strong>
        </div>
        <div class="panel customer-metric">
            <span>Loyalty</span>
            <strong>{{ number_format($customer->loyalty_points) }} pts</strong>
        </div>
        <div class="panel customer-metric">
            <span>Group / type</span>
            <strong>{{ $customer->group?->name ?? 'No group' }}</strong>
            <span class="customer-detail-tag neutral" style="display:inline-flex; margin-top:7px;">{{ $creditProfile['price_list_label'] }}</span>
        </div>
    </div>

    <div class="customer-overview-grid">
        <section class="panel">
            <div class="panel-header"><h2 class="panel-title">Customer information</h2></div>
            <div class="panel-body">
                <div class="customer-info-grid">
                    <div class="customer-info-item"><span>Phone</span><strong>{{ $customer->phone }}</strong></div>
                    <div class="customer-info-item"><span>Email</span><strong>{{ $customer->email ?: 'Not provided' }}</strong></div>
                    <div class="customer-info-item"><span>Birthday</span><strong>{{ $customer->birthday?->format('M j, Y') ?? 'Not provided' }}</strong></div>
                    <div class="customer-info-item"><span>Anniversary</span><strong>{{ $customer->anniversary?->format('M j, Y') ?? 'Not provided' }}</strong></div>
                    <div class="customer-info-item"><span>Last purchase</span><strong>{{ $customer->last_purchase_at?->format('M j, Y') ?? 'No purchase yet' }}</strong></div>
                    <div class="customer-info-item"><span>Customer since</span><strong>{{ $customer->created_at?->format('M j, Y') }}</strong></div>
                    <div class="customer-info-item"><span>Address</span><strong class="customer-notes">{{ $customer->address ?: 'Not provided' }}</strong></div>
                    <div class="customer-info-item"><span>Notes</span><strong class="customer-notes">{{ $customer->notes ?: 'No notes' }}</strong></div>
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header"><h2 class="panel-title">Credit & pricing</h2></div>
            <div class="panel-body customer-credit-description">
                <div class="customer-credit-row"><span class="subtle">Pricing</span><strong>{{ $creditProfile['price_list_label'] }}</strong></div>
                <div class="customer-credit-row"><span class="subtle">Credit access</span><strong>{{ $creditProfile['enabled'] ? 'Enabled' : 'Disabled' }}</strong></div>
                <div class="customer-credit-row"><span class="subtle">Credit limit</span><strong>{{ $creditProfile['limit_minor'] === null ? 'Unlimited' : $tenant->currency_code.' '.$money($creditProfile['limit_minor']) }}</strong></div>
                <div class="customer-credit-row"><span class="subtle">Payment terms</span><strong>{{ $creditProfile['terms_days'] === null ? 'Not set' : $creditProfile['terms_days'].' days' }}</strong></div>
                <div class="customer-credit-row"><span class="subtle">Credit hold</span><strong>{{ $creditProfile['on_hold'] ? 'Yes' : 'No' }}</strong></div>
            </div>
        </section>
    </div>

    <section class="panel" aria-label="Customer sales values" style="margin-bottom:18px;">
        <div class="panel-header"><h2 class="panel-title">Sales summary</h2></div>
        <div class="panel-body">
            <div class="customer-sales-grid">
                <div class="summary-item"><span>Overall sales</span><strong>{{ $tenant->currency_code }} {{ $money($salesTotals['overall']) }}</strong></div>
                <div class="summary-item"><span>Current year sales</span><strong>{{ $tenant->currency_code }} {{ $money($salesTotals['year']) }}</strong></div>
                <div class="summary-item"><span>Current month sales</span><strong>{{ $tenant->currency_code }} {{ $money($salesTotals['month']) }}</strong></div>
            </div>
        </div>
    </section>

    <nav class="pill-nav customer-activity-tabs" aria-label="Customer activity">
        <a class="active" href="#customer-purchases" data-local-tab-target="customer-purchases">Purchase history <span class="badge neutral">{{ $customer->purchases->count() }}</span></a>
        <a href="#customer-follow-ups" data-local-tab-target="customer-follow-ups">Follow-ups <span class="badge neutral">{{ $customer->followUps->count() }}</span></a>
        <a href="#customer-tickets" data-local-tab-target="customer-tickets">Tickets <span class="badge neutral">{{ $customer->tickets->count() }}</span></a>
        <a href="#customer-adjustments" data-local-tab-target="customer-adjustments">Balance adjustments <span class="badge neutral">{{ $customer->balanceAdjustments->count() }}</span></a>
        <a href="#customer-prices" data-local-tab-target="customer-prices">Customer prices <span class="badge neutral">{{ $customer->specificPrices->count() }}</span></a>
    </nav>

    <section class="panel" data-local-tab-panel id="customer-purchases">
        <div class="panel-header"><div><h2 class="panel-title">Purchase history</h2><p class="subtle">Recorded purchases and loyalty awards for this customer.</p></div></div>
        <div class="customer-detail-table-scroll">
            <table class="table customer-detail-table">
                <thead><tr><th>Date</th><th>Items</th><th>Amount</th><th>Loyalty</th><th>Reference</th></tr></thead>
                <tbody>
                    @forelse ($customer->purchases as $purchase)
                        <tr><td>{{ $purchase->purchase_date->format('M j, Y') }}</td><td>{{ $purchase->product_summary ?: 'Not specified' }}</td><td>{{ $tenant->currency_code }} {{ $money($purchase->amount_minor) }}</td><td>{{ number_format($purchase->loyalty_points_awarded) }} pts</td><td>{{ $purchase->reference_number ?: '—' }}</td></tr>
                    @empty
                        <tr><td colspan="5"><div class="empty">No purchases recorded for this customer.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel" data-local-tab-panel id="customer-follow-ups" hidden>
        <div class="panel-header"><div><h2 class="panel-title">Follow-ups</h2><p class="subtle">Scheduled and completed customer follow-ups.</p></div></div>
        <div class="customer-detail-table-scroll">
            <table class="table customer-detail-table">
                <thead><tr><th>Due</th><th>Subject</th><th>Channel</th><th>Status</th><th>Notes</th></tr></thead>
                <tbody>
                    @forelse ($customer->followUps as $followUp)
                        <tr><td>{{ $followUp->due_date->format('M j, Y') }}</td><td>{{ $followUp->subject }}</td><td>{{ $followUp->channel ?: '—' }}</td><td><span class="customer-detail-tag {{ $statusClass($followUp->status->value) }}">{{ $followUp->status->label() }}</span></td><td>{{ $followUp->notes ?: '—' }}</td></tr>
                    @empty
                        <tr><td colspan="5"><div class="empty">No follow-ups recorded for this customer.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel" data-local-tab-panel id="customer-tickets" hidden>
        <div class="panel-header"><div><h2 class="panel-title">Support tickets</h2><p class="subtle">Enquiries, complaints, and service requests tied to this customer.</p></div></div>
        <div class="customer-detail-table-scroll">
            <table class="table customer-detail-table">
                <thead><tr><th>Ticket</th><th>Subject</th><th>Type</th><th>Priority</th><th>Status</th><th>Assigned</th></tr></thead>
                <tbody>
                    @forelse ($customer->tickets as $ticket)
                        <tr><td><strong>{{ $ticket->ticket_number }}</strong></td><td>{{ $ticket->subject }}</td><td>{{ $ticket->type->label() }}</td><td><span class="customer-detail-tag {{ $statusClass($ticket->priority->value) }}">{{ $ticket->priority->label() }}</span></td><td><span class="customer-detail-tag {{ $statusClass($ticket->status->value) }}">{{ $ticket->status->label() }}</span></td><td>{{ $ticket->assignee?->name ?? 'Unassigned' }}</td></tr>
                    @empty
                        <tr><td colspan="6"><div class="empty">No support tickets recorded for this customer.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel" data-local-tab-panel id="customer-adjustments" hidden>
        <div class="panel-header"><div><h2 class="panel-title">Balance adjustments</h2><p class="subtle">Controlled changes made to this customer’s outstanding balance.</p></div></div>
        <div class="customer-detail-table-scroll">
            <table class="table customer-detail-table">
                <thead><tr><th>Date</th><th>Direction</th><th>Amount</th><th>Balance before</th><th>Balance after</th><th>Reason</th><th>Recorded by</th></tr></thead>
                <tbody>
                    @forelse ($customer->balanceAdjustments as $adjustment)
                        <tr><td>{{ $adjustment->adjustment_date->format('M j, Y') }}</td><td>{{ str($adjustment->direction)->headline() }}</td><td>{{ $tenant->currency_code }} {{ $money($adjustment->amount_minor) }}</td><td>{{ $tenant->currency_code }} {{ $money($adjustment->balance_before_minor) }}</td><td>{{ $tenant->currency_code }} {{ $money($adjustment->balance_after_minor) }}</td><td>{{ $adjustment->reason }}@if($adjustment->notes)<br><span class="subtle">{{ $adjustment->notes }}</span>@endif</td><td>{{ $adjustment->recordedBy?->name ?? 'System' }}</td></tr>
                    @empty
                        <tr><td colspan="7"><div class="empty">No manual balance adjustments recorded.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel" data-local-tab-panel id="customer-prices" hidden>
        <div class="panel-header"><div><h2 class="panel-title">Customer-specific prices</h2><p class="subtle">Prices that take priority over group and standard retail pricing.</p></div></div>
        <div class="customer-detail-table-scroll">
            <table class="table customer-detail-table">
                <thead><tr><th>Product</th><th>Minimum quantity</th><th>Unit price</th><th>Validity</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($customer->specificPrices as $price)
                        <tr><td>{{ $price->variant?->product?->name ?? 'Unknown product' }}@if($price->variant?->variant_name && $price->variant->variant_name !== 'Default')<br><span class="subtle">{{ $price->variant->variant_name }}</span>@endif</td><td>{{ \Modules\Inventory\Support\Quantity::format($price->minimum_quantity) }}</td><td>{{ $tenant->currency_code }} {{ $money($price->unit_price_minor) }}</td><td>{{ $price->starts_at?->format('M j, Y') ?? 'Any date' }} – {{ $price->ends_at?->format('M j, Y') ?? 'No expiry' }}</td><td><span class="customer-detail-tag {{ $price->is_active ? 'success' : 'neutral' }}">{{ $price->is_active ? 'Active' : 'Inactive' }}</span></td></tr>
                    @empty
                        <tr><td colspan="5"><div class="empty">No customer-specific prices have been set.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @include('customers::admin.partials.customer-dialog', [
        'dialogId' => 'customer-edit-'.$customer->id,
        'selectedCustomer' => $customer,
        'returnToCustomer' => true,
    ])
    @permission('customers.credit.manage')
        @include('customers::admin.partials.balance-adjustment-dialog', [
            'customer' => $customer,
            'returnToCustomer' => true,
        ])
    @endpermission
</x-layouts.admin>
