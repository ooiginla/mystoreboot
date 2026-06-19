@php
    $money = fn (?int $minor): string => number_format(($minor ?? 0) / 100, 2);
    $statusTag = fn (string $status): string => match ($status) {
        'active', 'completed', 'resolved', 'closed' => 'success',
        'pending', 'open', 'in_progress', 'normal' => 'warning',
        'blocked', 'urgent', 'high' => 'danger',
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
        .link-button { border: 0; background: transparent; padding: 0; color: var(--accent); cursor: pointer; font-weight: 800; text-align: left; }
        .mini-list { display: grid; gap: 8px; }
        .mini-row { border: 1px solid var(--line); border-radius: 8px; padding: 10px; display: flex; justify-content: space-between; gap: 10px; }
        @media (max-width: 960px) { .crm-filter { grid-template-columns: 1fr; } }
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
            <a href="#customers" role="tab" data-tab-target="customers">Customers <span class="badge neutral">{{ $customers->count() }}</span></a>
            <a href="#groups" role="tab" data-tab-target="groups">Groups <span class="badge neutral">{{ $groups->count() }}</span></a>
            <a href="#history" role="tab" data-tab-target="history">Purchase history</a>
            <a href="#follow-ups" role="tab" data-tab-target="follow-ups">Follow-ups <span class="badge neutral">{{ $followUps->count() }}</span></a>
            <a href="#tickets" role="tab" data-tab-target="tickets">Tickets <span class="badge neutral">{{ $tickets->count() }}</span></a>
            <a href="#insights" role="tab" data-tab-target="insights">Insights</a>
        </nav>

        <div class="content-stack">
            <section class="panel tab-panel" id="customers" role="tabpanel" data-tab-panel>
                <div class="panel-header">
                    <div><h2 class="panel-title">Customer database</h2><p class="subtle">Search by phone number, name, or email.</p></div>
                    <button class="btn accent" type="button" data-dialog-open="customer-dialog">Add customer</button>
                </div>
                <div class="panel-body">
                    <form class="crm-filter" method="GET" action="{{ route('admin.customers.index') }}#customers">
                        <input type="hidden" name="tenant" value="{{ $tenant->id }}">
                        <div class="field"><label>Search</label><input name="search" value="{{ $search }}" placeholder="Phone, name, or email"></div>
                        <div class="field"><label>Group</label><select name="group_id"><option value="">All groups</option>@foreach ($groups as $group)<option value="{{ $group->id }}" @selected($filters['group_id'] === (string) $group->id)>{{ $group->name }}</option>@endforeach</select></div>
                        <div class="field"><label>Status</label><select name="status"><option value="">All statuses</option>@foreach ($customerStatuses as $customerStatus)<option value="{{ $customerStatus->value }}" @selected($filters['status'] === $customerStatus->value)>{{ $customerStatus->label() }}</option>@endforeach</select></div>
                        <div class="button-row" style="margin-top: 0; justify-content: flex-start;"><button class="btn secondary" type="submit">Search</button><a class="btn secondary" href="{{ route('admin.customers.index', ['tenant' => $tenant->id]).'#customers' }}">Reset</a></div>
                    </form>
                    <div class="list">
                        @forelse ($customers as $customer)
                            <div class="item">
                                <div>
                                    <button class="link-button item-title" type="button" data-dialog-open="customer-view-{{ $customer->id }}">{{ $customer->name }}</button>
                                    <div class="subtle">{{ $customer->phone }} · {{ $customer->email ?: 'No email' }} · {{ $customer->group?->name ?? 'No group' }}</div>
                                    <div class="subtle">Balance: {{ $tenant->currency_code }} {{ $money($customer->account_balance_minor) }} · Loyalty: {{ $customer->loyalty_points }} pts</div>
                                </div>
                                <div style="display: flex; gap: 8px; align-items: start; flex-wrap: wrap; justify-content: flex-end;">
                                    <span class="tag {{ $statusTag($customer->status->value) }}">{{ $customer->status->label() }}</span>
                                    <button class="btn secondary" type="button" data-dialog-open="customer-edit-{{ $customer->id }}">Edit</button>
                                </div>
                            </div>
                        @empty
                            <div class="empty">No customers match the current filters.</div>
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="panel tab-panel" id="groups" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header"><div><h2 class="panel-title">Customer groups</h2><p class="subtle">Segment customers for offers, follow-ups, and reporting.</p></div><button class="btn accent" type="button" data-dialog-open="group-dialog">Add group</button></div>
                <div class="panel-body">
                    <table class="table">
                        <thead><tr><th>Name</th><th>Code</th><th>Customers</th><th>Description</th></tr></thead>
                        <tbody>
                            @forelse ($groups as $group)
                                <tr><td>{{ $group->name }}</td><td>{{ $group->code ?: 'Not set' }}</td><td>{{ $allCustomers->where('customer_group_id', $group->id)->count() }}</td><td>{{ $group->description ?: 'No description' }}</td></tr>
                            @empty
                                <tr><td colspan="4"><div class="empty">No customer groups yet.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
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
                        <div class="field" style="grid-column: span 2;"><label>Search tickets</label><input name="ticket_search" value="{{ $filters['ticket_search'] }}" placeholder="Customer name, phone, email, ticket number, or subject"></div>
                        <div class="button-row" style="margin-top: 0; justify-content: flex-start;"><button class="btn secondary" type="submit">Search</button><a class="btn secondary" href="{{ route('admin.customers.index', ['tenant' => $tenant->id]).'#tickets' }}">Reset</a></div>
                    </form>
                    <table class="table">
                        <thead><tr><th>Ticket</th><th>Customer</th><th>Type</th><th>Priority</th><th>Status</th><th>Assigned</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($tickets as $ticket)
                                <tr>
                                    <td><button class="link-button" type="button" data-dialog-open="ticket-view-{{ $ticket->id }}">{{ $ticket->ticket_number }}</button><br><span class="subtle">{{ $ticket->subject }}</span></td>
                                    <td>{{ $ticket->customer?->name ?? 'Internal' }}</td>
                                    <td>{{ $ticket->type->label() }}<br><span class="subtle">{{ $ticket->category ?: 'No category' }}</span></td>
                                    <td><span class="tag {{ $statusTag($ticket->priority->value) }}">{{ $ticket->priority->label() }}</span></td>
                                    <td><span class="tag {{ $statusTag($ticket->status->value) }}">{{ $ticket->status->label() }}</span></td>
                                    <td>{{ $ticket->assignee?->name ?? 'Unassigned' }}</td>
                                    <td><button class="btn secondary" type="button" data-dialog-open="ticket-edit-{{ $ticket->id }}">Edit</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="7"><div class="empty">No tickets match the current search.</div></td></tr>
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
    @include('customers::admin.partials.purchase-dialog')
    @include('customers::admin.partials.follow-up-dialog')
    @include('customers::admin.partials.ticket-dialog')

    @foreach ($allCustomers as $customer)
        @include('customers::admin.partials.customer-view-dialog', ['customer' => $customer])
        @include('customers::admin.partials.customer-dialog', ['dialogId' => 'customer-edit-'.$customer->id, 'selectedCustomer' => $customer])
    @endforeach
    @foreach ($allTickets as $ticket)
        @include('customers::admin.partials.ticket-view-dialog', ['ticket' => $ticket])
        @include('customers::admin.partials.ticket-dialog', ['dialogId' => 'ticket-edit-'.$ticket->id, 'selectedTicket' => $ticket])
    @endforeach
</x-layouts.admin>
