<dialog class="dialog" id="customer-view-{{ $customer->id }}">
    <div class="dialog-header"><div><h2 class="panel-title">{{ $customer->name }}</h2><p class="subtle">{{ $customer->phone }} · {{ $customer->email ?: 'No email' }}</p></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button></div>
    <div class="dialog-body">
        <div class="summary-grid">
            <div class="summary-item"><span>Group</span><strong>{{ $customer->group?->name ?? 'No group' }}</strong></div>
            <div class="summary-item"><span>Account balance</span><strong>{{ $tenant->currency_code }} {{ $money($customer->account_balance_minor) }}</strong></div>
            <div class="summary-item"><span>Loyalty</span><strong>{{ $customer->loyalty_points }} pts</strong></div>
        </div>
        <nav class="pill-nav" style="position: static; grid-template-columns: repeat(3, minmax(0, 1fr)); margin-top: 16px;" aria-label="Customer sections">
            <a href="#customer-history-{{ $customer->id }}" class="active" data-local-tab-target="customer-history-{{ $customer->id }}">History</a>
            <a href="#customer-followups-{{ $customer->id }}" data-local-tab-target="customer-followups-{{ $customer->id }}">Follow-ups</a>
            <a href="#customer-tickets-{{ $customer->id }}" data-local-tab-target="customer-tickets-{{ $customer->id }}">Tickets</a>
        </nav>
        <section data-local-tab-panel id="customer-history-{{ $customer->id }}" style="margin-top: 16px;">
            <table class="table"><thead><tr><th>Date</th><th>Items</th><th>Amount</th></tr></thead><tbody>@forelse ($customer->purchases as $purchase)<tr><td>{{ $purchase->purchase_date->format('M j, Y') }}</td><td>{{ $purchase->product_summary ?: 'Not specified' }}</td><td>{{ $tenant->currency_code }} {{ $money($purchase->amount_minor) }}</td></tr>@empty<tr><td colspan="3"><div class="empty">No purchases yet.</div></td></tr>@endforelse</tbody></table>
        </section>
        <section data-local-tab-panel id="customer-followups-{{ $customer->id }}" style="margin-top: 16px;" hidden>
            <table class="table"><thead><tr><th>Due</th><th>Subject</th><th>Status</th></tr></thead><tbody>@forelse ($customer->followUps as $followUp)<tr><td>{{ $followUp->due_date->format('M j, Y') }}</td><td>{{ $followUp->subject }}</td><td>{{ $followUp->status->label() }}</td></tr>@empty<tr><td colspan="3"><div class="empty">No follow-ups yet.</div></td></tr>@endforelse</tbody></table>
        </section>
        <section data-local-tab-panel id="customer-tickets-{{ $customer->id }}" style="margin-top: 16px;" hidden>
            <table class="table"><thead><tr><th>Ticket</th><th>Subject</th><th>Status</th></tr></thead><tbody>@forelse ($customer->tickets as $ticket)<tr><td><button class="link-button" type="button" data-dialog-open="ticket-view-{{ $ticket->id }}">{{ $ticket->ticket_number }}</button></td><td>{{ $ticket->subject }}</td><td>{{ $ticket->status->label() }}</td></tr>@empty<tr><td colspan="3"><div class="empty">No tickets yet.</div></td></tr>@endforelse</tbody></table>
        </section>
        <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Close</button><button class="btn primary" type="button" data-dialog-open="customer-edit-{{ $customer->id }}">Edit customer</button></div>
    </div>
</dialog>
