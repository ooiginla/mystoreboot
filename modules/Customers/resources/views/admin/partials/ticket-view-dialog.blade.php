<dialog class="dialog" id="ticket-view-{{ $ticket->id }}">
    <div class="dialog-header">
        <div>
            <h2 class="panel-title">{{ $ticket->ticket_number }}</h2>
            <p class="subtle">{{ $ticket->subject }} · {{ $ticket->customer?->name ?? 'Internal' }}</p>
        </div>
        <button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button>
    </div>
    <div class="dialog-body">
        <div class="summary-grid">
            <div class="summary-item"><span>Type</span><strong>{{ $ticket->type->label() }}</strong></div>
            <div class="summary-item"><span>Priority</span><strong>{{ $ticket->priority->label() }}</strong></div>
            <div class="summary-item"><span>Status</span><strong>{{ $ticket->status->label() }}</strong></div>
        </div>
        <div style="margin-top: 16px;">
            <strong>Description</strong>
            <p class="subtle">{{ $ticket->description }}</p>
        </div>
        @if ($ticket->internal_notes)
            <div style="margin-top: 12px;"><strong>Internal notes</strong><p class="subtle">{{ $ticket->internal_notes }}</p></div>
        @endif
        <table class="table" style="margin-top: 16px;">
            <thead><tr><th>Date</th><th>Author</th><th>Message</th><th>Visibility</th></tr></thead>
            <tbody>
                @forelse ($ticket->responses as $response)
                    <tr><td>{{ $response->created_at->format('M j, Y g:i A') }}</td><td>{{ $response->user?->name ?? 'System' }}</td><td>{{ $response->message }}</td><td>{{ $response->is_internal ? 'Internal' : 'Customer communication' }}</td></tr>
                @empty
                    <tr><td colspan="4"><div class="empty">No response history yet.</div></td></tr>
                @endforelse
            </tbody>
        </table>
        <form class="mini-form" method="POST" action="{{ route('admin.customers.tickets.responses.store', $ticket) }}" style="margin-top: 16px;">
            @csrf
            <div class="field"><label>Add response or note</label><textarea name="message" required></textarea></div>
            <label><input type="checkbox" name="is_internal" value="1"> Internal note</label>
            <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Close</button><button class="btn primary" type="submit">Add response</button></div>
        </form>
    </div>
</dialog>
