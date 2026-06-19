@php
    $dialogId ??= 'vendor-dialog';
    $selectedVendor ??= null;
    $vendorAccounts = $selectedVendor?->bankAccounts?->values() ?? collect();
    $vendorAccounts = $vendorAccounts->isNotEmpty() ? $vendorAccounts : collect([null]);
    $vendorFormAction = $selectedVendor ? route('admin.procurement.vendors.update', $selectedVendor) : route('admin.procurement.vendors.store');
@endphp

<dialog class="dialog" id="{{ $dialogId }}">
    <div class="dialog-header"><div><h2 class="panel-title">{{ $selectedVendor ? 'Edit vendor' : 'Add vendor' }}</h2><p class="subtle">Supplier contact and procurement profile.</p></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button></div>
    <div class="dialog-body">
        <form class="mini-form" method="POST" action="{{ $vendorFormAction }}">
            @csrf
            @if ($selectedVendor)
                @method('PUT')
            @endif
            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
            <div class="form-grid">
                <div class="field"><label>Name</label><input name="name" value="{{ $selectedVendor?->name }}" required></div>
                <div class="field"><label>Code</label><input name="code" value="{{ $selectedVendor?->code }}"></div>
                <div class="field"><label>Contact name</label><input name="contact_name" value="{{ $selectedVendor?->contact_name }}"></div>
                <div class="field"><label>Email</label><input name="email" type="email" value="{{ $selectedVendor?->email }}"></div>
                <div class="field"><label>Phone</label><input name="phone" value="{{ $selectedVendor?->phone }}"></div>
                <div class="field"><label>Tax number</label><input name="tax_number" value="{{ $selectedVendor?->tax_number }}"></div>
                <div class="field"><label>Lead time days</label><input name="lead_time_days" type="number" min="0" step="1" value="{{ $selectedVendor?->lead_time_days }}"></div>
                <div class="field full"><label>Address</label><textarea name="address">{{ $selectedVendor?->address }}</textarea></div>
                <div class="field full"><label>Notes</label><textarea name="notes">{{ $selectedVendor?->notes }}</textarea></div>
            </div>
            <div class="panel">
                <div class="panel-header"><h3 class="panel-title">Bank details</h3><button class="btn secondary" type="button" data-add-bank-account>Add bank account</button></div>
                <div class="panel-body" data-bank-accounts>
                    @foreach ($vendorAccounts as $index => $account)
                        <div class="po-line-card" data-bank-account>
                            <div class="po-line-header"><strong>Bank account</strong><button class="btn secondary" type="button" data-remove-bank-account>Remove</button></div>
                            <div class="form-grid">
                                <div class="field"><label>Bank name</label><input name="bank_accounts[{{ $index }}][bank_name]" value="{{ $account?->bank_name }}"></div>
                                <div class="field"><label>Account name</label><input name="bank_accounts[{{ $index }}][account_name]" value="{{ $account?->account_name }}"></div>
                                <div class="field"><label>Account number</label><input name="bank_accounts[{{ $index }}][account_number]" value="{{ $account?->account_number }}"></div>
                                <div class="field"><label>Currency</label><input name="bank_accounts[{{ $index }}][currency_code]" maxlength="3" value="{{ $account?->currency_code ?? $tenant->currency_code }}"></div>
                            </div>
                            <label><input type="checkbox" name="bank_accounts[{{ $index }}][is_primary]" value="1" @checked($account?->is_primary ?? $index === 0)> Primary account</label>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Cancel</button><button class="btn primary" type="submit">{{ $selectedVendor ? 'Save changes' : 'Add vendor' }}</button></div>
        </form>
    </div>
</dialog>

<script>
document.addEventListener('DOMContentLoaded', () => {
    if (window.storebootVendorDialogBound) return;
    window.storebootVendorDialogBound = true;

    document.addEventListener('click', (event) => {
        const addButton = event.target.closest('[data-add-bank-account]');
        if (addButton) {
            const list = addButton.closest('form')?.querySelector('[data-bank-accounts]');
            const first = list?.querySelector('[data-bank-account]');
            if (!list || !first) return;
            const index = list.querySelectorAll('[data-bank-account]').length;
            const row = first.cloneNode(true);
            row.querySelectorAll('[name]').forEach((field) => {
                field.name = field.name.replace(/bank_accounts\[\d+\]/, `bank_accounts[${index}]`);
                if (field.type === 'checkbox') field.checked = false;
                else field.value = field.name.endsWith('[currency_code]') ? '{{ $tenant->currency_code }}' : '';
            });
            list.appendChild(row);
            return;
        }

        const button = event.target.closest('[data-remove-bank-account]');
        if (!button) return;
        const row = button.closest('[data-bank-account]');
        const list = button.closest('[data-bank-accounts]');
        if (row && list?.querySelectorAll('[data-bank-account]').length > 1) {
            row.remove();
            return;
        }
        row?.querySelectorAll('input').forEach((field) => {
            if (field.type === 'checkbox') field.checked = true;
            else field.value = field.name.endsWith('[currency_code]') ? '{{ $tenant->currency_code }}' : '';
        });
    });
});
</script>
