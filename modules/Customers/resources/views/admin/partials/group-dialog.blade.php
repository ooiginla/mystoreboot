@php
    $dialogId ??= 'group-dialog';
    $selectedGroup ??= null;
    $groupAction = $selectedGroup ? route('admin.customers.groups.update', $selectedGroup) : route('admin.customers.groups.store');
@endphp
<dialog class="dialog" id="{{ $dialogId }}">
    <div class="dialog-header"><div><h2 class="panel-title">{{ $selectedGroup ? 'Edit customer group' : 'Add customer group' }}</h2><p class="subtle">Group customers under one price list and default credit policy.</p></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button></div>
    <div class="dialog-body">
        <form class="mini-form" method="POST" action="{{ $groupAction }}">
            @csrf
            @if($selectedGroup) @method('PUT') @endif
            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
            <div class="form-grid">
                <div class="field"><label>Name</label><input name="name" value="{{ $selectedGroup?->name }}" required></div>
                <div class="field"><label>Code</label><input name="code" value="{{ $selectedGroup?->code }}"></div>
                @permission('customers.pricing.manage')
                    <div class="field full"><label>Default price list</label><select name="customer_price_list_id"><option value="">Retail selling prices</option>@foreach($priceLists as $priceList)<option value="{{ $priceList->id }}" @selected($selectedGroup?->customer_price_list_id === $priceList->id)>{{ $priceList->name }}</option>@endforeach</select></div>
                @else
                    <input type="hidden" name="customer_price_list_id" value="{{ $selectedGroup?->customer_price_list_id }}">
                @endpermission
                @permission('customers.credit.manage')
                    <div class="field"><label class="inline-check"><input type="checkbox" name="credit_enabled" value="1" @checked($selectedGroup?->credit_enabled)> Allow credit for this group</label></div>
                    <div class="field"><label>Default credit limit <span class="subtle">(blank is unlimited)</span></label><input name="credit_limit" type="text" inputmode="decimal" data-money-input value="{{ $selectedGroup?->credit_limit_minor !== null ? $money($selectedGroup->credit_limit_minor) : '' }}"></div>
                    <div class="field"><label>Payment terms (days)</label><input name="credit_terms_days" type="number" min="0" max="3650" value="{{ $selectedGroup?->credit_terms_days }}"></div>
                @else
                    <input type="hidden" name="credit_enabled" value="{{ $selectedGroup?->credit_enabled ? 1 : 0 }}">
                    <input type="hidden" name="credit_limit" value="{{ $selectedGroup?->credit_limit_minor !== null ? $money($selectedGroup->credit_limit_minor) : '' }}">
                    <input type="hidden" name="credit_terms_days" value="{{ $selectedGroup?->credit_terms_days }}">
                @endpermission
                <div class="field full"><label>Description</label><textarea name="description">{{ $selectedGroup?->description }}</textarea></div>
            </div>
            <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Cancel</button><button class="btn primary" type="submit">{{ $selectedGroup ? 'Save changes' : 'Add group' }}</button></div>
        </form>
    </div>
</dialog>
