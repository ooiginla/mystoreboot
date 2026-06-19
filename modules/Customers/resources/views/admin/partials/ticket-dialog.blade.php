@php
    $dialogId ??= 'ticket-dialog';
    $selectedTicket ??= null;
    $ticketAction = $selectedTicket ? route('admin.customers.tickets.update', $selectedTicket) : route('admin.customers.tickets.store');
@endphp

<dialog class="dialog" id="{{ $dialogId }}">
    <div class="dialog-header"><div><h2 class="panel-title">{{ $selectedTicket ? 'Edit ticket' : 'Create ticket' }}</h2><p class="subtle">Track enquiries, complaints, service requests, internal notes, and assignments.</p></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button></div>
    <div class="dialog-body">
        <form class="mini-form" method="POST" action="{{ $ticketAction }}">
            @csrf
            @if ($selectedTicket)
                @method('PUT')
            @endif
            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
            <div class="form-grid">
                <div class="field" data-customer-picker>
                    <label>Customer</label>
                    <input type="text" list="customer-options" data-customer-search value="{{ $selectedTicket?->customer ? $selectedTicket->customer->name.' · '.$selectedTicket->customer->phone : '' }}" placeholder="Search customer by name or phone">
                    <input type="hidden" name="customer_id" data-customer-value value="{{ $selectedTicket?->customer_id }}">
                </div>
                <div class="field"><label>Assigned to</label><select name="assigned_to"><option value="">Unassigned</option>@foreach ($users as $assignableUser)<option value="{{ $assignableUser->id }}" @selected($selectedTicket?->assigned_to === $assignableUser->id)>{{ $assignableUser->name }}</option>@endforeach</select></div>
                <div class="field"><label>Type</label><select name="type" required>@foreach ($ticketTypes as $ticketType)<option value="{{ $ticketType->value }}" @selected(($selectedTicket?->type?->value ?? 'enquiry') === $ticketType->value)>{{ $ticketType->label() }}</option>@endforeach</select></div>
                <div class="field"><label>Category</label><select name="category"><option value="">Select category</option>@foreach ($ticketCategories as $category)<option value="{{ $category }}" @selected($selectedTicket?->category === $category)>{{ $category }}</option>@endforeach</select></div>
                <div class="field"><label>Priority</label><select name="priority" required>@foreach ($ticketPriorities as $ticketPriority)<option value="{{ $ticketPriority->value }}" @selected(($selectedTicket?->priority?->value ?? 'normal') === $ticketPriority->value)>{{ $ticketPriority->label() }}</option>@endforeach</select></div>
                <div class="field"><label>Status</label><select name="status" required>@foreach ($ticketStatuses as $ticketStatus)<option value="{{ $ticketStatus->value }}" @selected(($selectedTicket?->status?->value ?? 'open') === $ticketStatus->value)>{{ $ticketStatus->label() }}</option>@endforeach</select></div>
                <div class="field full"><label>Subject</label><input name="subject" value="{{ $selectedTicket?->subject }}" required></div>
                <div class="field full"><label>Description</label><textarea name="description" required>{{ $selectedTicket?->description }}</textarea></div>
                <div class="field full"><label>Internal notes</label><textarea name="internal_notes">{{ $selectedTicket?->internal_notes }}</textarea></div>
            </div>
            <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Cancel</button><button class="btn primary" type="submit">{{ $selectedTicket ? 'Save changes' : 'Create ticket' }}</button></div>
        </form>
    </div>
</dialog>
