<dialog class="dialog" id="follow-up-dialog">
    <div class="dialog-header"><div><h2 class="panel-title">Schedule follow-up</h2><p class="subtle">Create reminders for customer calls, birthdays, renewals, or visits.</p></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button></div>
    <div class="dialog-body">
        <form class="mini-form" method="POST" action="{{ route('admin.customers.follow-ups.store') }}">
            @csrf
            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
            <div class="form-grid">
                <div class="field"><label>Customer</label><select name="customer_id" required>@foreach ($allCustomers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }} · {{ $customer->phone }}</option>@endforeach</select></div>
                <div class="field"><label>Due date</label><input name="due_date" type="date" required></div>
                <div class="field"><label>Subject</label><input name="subject" required></div>
                <div class="field"><label>Channel</label><select name="channel"><option value="">Select channel</option>@foreach ($followUpChannels as $channel)<option value="{{ $channel }}">{{ $channel }}</option>@endforeach</select></div>
                <div class="field"><label>Status</label><select name="status" required>@foreach ($followUpStatuses as $followUpStatus)<option value="{{ $followUpStatus->value }}">{{ $followUpStatus->label() }}</option>@endforeach</select></div>
                <div class="field full"><label>Notes</label><textarea name="notes"></textarea></div>
            </div>
            <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Cancel</button><button class="btn primary" type="submit">Schedule</button></div>
        </form>
    </div>
</dialog>
