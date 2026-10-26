@php
    $dialogId ??= 'customer-dialog';
    $selectedCustomer ??= null;
    $customerAction = $selectedCustomer ? route('admin.customers.customers.update', $selectedCustomer) : route('admin.customers.customers.store');
@endphp

<dialog class="dialog" id="{{ $dialogId }}">
    <div class="dialog-header"><div><h2 class="panel-title">{{ $selectedCustomer ? 'Edit customer' : 'Add customer' }}</h2><p class="subtle">Customer contact, group pricing, loyalty, and controlled credit settings.</p></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button></div>
    <div class="dialog-body">
        <form class="mini-form" method="POST" action="{{ $customerAction }}">
            @csrf
            @if ($selectedCustomer)
                @method('PUT')
            @endif
            @if (!empty($returnToCustomer))
                <input type="hidden" name="return_to" value="customer">
            @endif
            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
            <div class="form-grid">
                <div class="field"><label>First name</label><input name="first_name" value="{{ $selectedCustomer?->first_name }}" required></div>
                <div class="field"><label>Last name</label><input name="last_name" value="{{ $selectedCustomer?->last_name }}"></div>
                <div class="field"><label>Phone</label><input name="phone" value="{{ $selectedCustomer?->phone }}" required></div>
                <div class="field"><label>Email</label><input name="email" type="email" value="{{ $selectedCustomer?->email }}"></div>
                <div class="field"><label>Group</label><select name="customer_group_id"><option value="">No group</option>@foreach ($groups as $group)<option value="{{ $group->id }}" @selected($selectedCustomer?->customer_group_id === $group->id)>{{ $group->name }}</option>@endforeach</select></div>
                @permission('customers.pricing.manage')
                    <div class="field"><label>Direct price list <span class="subtle">(optional override)</span></label><select name="customer_price_list_id"><option value="">Use customer group price list</option>@foreach ($priceLists as $priceList)<option value="{{ $priceList->id }}" @selected($selectedCustomer?->customer_price_list_id === $priceList->id)>{{ $priceList->name }}</option>@endforeach</select></div>
                @else
                    <input type="hidden" name="customer_price_list_id" value="{{ $selectedCustomer?->customer_price_list_id }}">
                @endpermission
                <div class="field"><label>Status</label><select name="status" required>@foreach ($customerStatuses as $customerStatus)<option value="{{ $customerStatus->value }}" @selected(($selectedCustomer?->status?->value ?? 'active') === $customerStatus->value)>{{ $customerStatus->label() }}</option>@endforeach</select></div>
                <div class="field"><label>Birthday</label><input name="birthday" type="date" value="{{ $selectedCustomer?->birthday?->toDateString() }}"></div>
                <div class="field"><label>Anniversary</label><input name="anniversary" type="date" value="{{ $selectedCustomer?->anniversary?->toDateString() }}"></div>
                <div class="field"><label>Loyalty points</label><input name="loyalty_points" type="number" min="0" step="1" value="{{ $selectedCustomer?->loyalty_points ?? 0 }}"></div>
                <div class="field"><label>Outstanding balance</label><input type="text" value="{{ $tenant->currency_code }} {{ $selectedCustomer ? $money($selectedCustomer->account_balance_minor) : '0.00' }}" readonly style="background:#f2f4f7;"><small class="subtle">Changed by sales, payments, returns, or controlled adjustments.</small></div>
                @permission('customers.credit.manage')
                    <div class="field"><label>Credit policy</label><select name="credit_policy" required>@foreach($creditPolicies as $policy)<option value="{{ $policy->value }}" @selected(($selectedCustomer?->credit_policy?->value ?? 'inherit') === $policy->value)>{{ $policy->label() }}</option>@endforeach</select></div>
                    <div class="field"><label>Credit limit <span class="subtle">(blank inherits group/unlimited)</span></label><input name="credit_limit" type="text" inputmode="decimal" data-money-input value="{{ $selectedCustomer?->credit_limit_minor !== null ? $money($selectedCustomer->credit_limit_minor) : '' }}" placeholder="Use group limit"></div>
                    <div class="field"><label>Payment terms (days)</label><input name="credit_terms_days" type="number" min="0" max="3650" value="{{ $selectedCustomer?->credit_terms_days }}" placeholder="Use group terms"></div>
                    <div class="field" style="align-self:end;"><label class="inline-check"><input name="credit_hold" type="checkbox" value="1" @checked($selectedCustomer?->credit_hold)> Put customer on credit hold</label></div>
                @else
                    <input type="hidden" name="credit_policy" value="{{ $selectedCustomer?->credit_policy?->value ?? 'inherit' }}">
                    <input type="hidden" name="credit_limit" value="{{ $selectedCustomer?->credit_limit_minor !== null ? $money($selectedCustomer->credit_limit_minor) : '' }}">
                    <input type="hidden" name="credit_terms_days" value="{{ $selectedCustomer?->credit_terms_days }}">
                    <input type="hidden" name="credit_hold" value="{{ $selectedCustomer?->credit_hold ? 1 : 0 }}">
                @endpermission
                <div class="field full"><label>Address</label><textarea name="address">{{ $selectedCustomer?->address }}</textarea></div>
                <div class="field full"><label>Notes</label><textarea name="notes">{{ $selectedCustomer?->notes }}</textarea></div>
            </div>
            <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Cancel</button><button class="btn primary" type="submit">{{ $selectedCustomer ? 'Save changes' : 'Add customer' }}</button></div>
        </form>
    </div>
</dialog>
