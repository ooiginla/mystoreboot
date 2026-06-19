@php
    $dialogId ??= 'customer-dialog';
    $selectedCustomer ??= null;
    $customerAction = $selectedCustomer ? route('admin.customers.customers.update', $selectedCustomer) : route('admin.customers.customers.store');
@endphp

<dialog class="dialog" id="{{ $dialogId }}">
    <div class="dialog-header"><div><h2 class="panel-title">{{ $selectedCustomer ? 'Edit customer' : 'Add customer' }}</h2><p class="subtle">Customer contact, segmentation, reminders, loyalty, and account balance.</p></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button></div>
    <div class="dialog-body">
        <form class="mini-form" method="POST" action="{{ $customerAction }}">
            @csrf
            @if ($selectedCustomer)
                @method('PUT')
            @endif
            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
            <div class="form-grid">
                <div class="field"><label>First name</label><input name="first_name" value="{{ $selectedCustomer?->first_name }}" required></div>
                <div class="field"><label>Last name</label><input name="last_name" value="{{ $selectedCustomer?->last_name }}"></div>
                <div class="field"><label>Phone</label><input name="phone" value="{{ $selectedCustomer?->phone }}" required></div>
                <div class="field"><label>Email</label><input name="email" type="email" value="{{ $selectedCustomer?->email }}"></div>
                <div class="field"><label>Group</label><select name="customer_group_id"><option value="">No group</option>@foreach ($groups as $group)<option value="{{ $group->id }}" @selected($selectedCustomer?->customer_group_id === $group->id)>{{ $group->name }}</option>@endforeach</select></div>
                <div class="field"><label>Status</label><select name="status" required>@foreach ($customerStatuses as $customerStatus)<option value="{{ $customerStatus->value }}" @selected(($selectedCustomer?->status?->value ?? 'active') === $customerStatus->value)>{{ $customerStatus->label() }}</option>@endforeach</select></div>
                <div class="field"><label>Birthday</label><input name="birthday" type="date" value="{{ $selectedCustomer?->birthday?->toDateString() }}"></div>
                <div class="field"><label>Anniversary</label><input name="anniversary" type="date" value="{{ $selectedCustomer?->anniversary?->toDateString() }}"></div>
                <div class="field"><label>Loyalty points</label><input name="loyalty_points" type="number" min="0" step="1" value="{{ $selectedCustomer?->loyalty_points ?? 0 }}"></div>
                <div class="field"><label>Account balance</label><input name="account_balance" type="text" inputmode="decimal" data-money-input value="{{ $selectedCustomer ? $money($selectedCustomer->account_balance_minor) : '0.00' }}"></div>
                <div class="field full"><label>Address</label><textarea name="address">{{ $selectedCustomer?->address }}</textarea></div>
                <div class="field full"><label>Notes</label><textarea name="notes">{{ $selectedCustomer?->notes }}</textarea></div>
            </div>
            <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Cancel</button><button class="btn primary" type="submit">{{ $selectedCustomer ? 'Save changes' : 'Add customer' }}</button></div>
        </form>
    </div>
</dialog>
