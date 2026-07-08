@php
    $dialogId ??= 'po-dialog';
    $selectedPo ??= null;
    $poItems = $selectedPo?->items ?? collect([null]);
    $poFormAction = $selectedPo ? route('admin.procurement.purchase-orders.update', $selectedPo) : route('admin.procurement.purchase-orders.store');
@endphp

<dialog class="dialog" id="{{ $dialogId }}">
    <div class="dialog-header"><div><h2 class="panel-title">{{ $selectedPo ? 'Edit purchase order' : 'Create purchase order' }}</h2><p class="subtle">Order product variants into branch/location inventory.</p></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button></div>
    <div class="dialog-body">
        <form class="mini-form" method="POST" action="{{ $poFormAction }}">
            @csrf
            @if ($selectedPo)
                @method('PUT')
            @endif
            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
            <div class="form-grid">
                <div class="field"><label>Vendor</label><select name="vendor_id" required>@foreach ($allVendors as $vendor)<option value="{{ $vendor->id }}" @selected($selectedPo?->vendor_id === $vendor->id)>{{ $vendor->name }}</option>@endforeach</select></div>
                <div class="field"><label>PO number</label><input name="po_number" placeholder="auto-generated" value="{{ $selectedPo?->po_number }}"></div>
                <div class="field"><label>Order date</label><input name="order_date" type="date" value="{{ $selectedPo?->order_date?->toDateString() ?? now()->toDateString() }}" required></div>
                <div class="field"><label>Expected delivery</label><input name="expected_delivery_date" type="date" value="{{ $selectedPo?->expected_delivery_date?->toDateString() }}"></div>
                <div class="field"><label>Tax</label><input name="tax" type="text" inputmode="decimal" data-money-input value="{{ $selectedPo ? $money($selectedPo->tax_minor) : '' }}"></div>
                <div class="field"><label>Shipping</label><input name="shipping" type="text" inputmode="decimal" data-money-input value="{{ $selectedPo ? $money($selectedPo->shipping_minor) : '' }}"></div>
            </div>
            <div class="panel">
                <div class="panel-header"><h3 class="panel-title">Items</h3><button class="btn secondary" type="button" data-add-po-line>Add line</button></div>
                <div class="panel-body" data-po-lines>
                    @foreach ($poItems as $i => $poItem)
                        <div class="po-line-card" data-po-line>
                            <div class="po-line-header">
                                <strong>Line item</strong>
                                <button class="btn danger" type="button" data-remove-po-line>Remove line</button>
                            </div>
                            <div class="form-grid">
                                <x-variant-picker name="items[{{ $i }}][product_variant_id]" label="Variant" :selected-variant="$poItem?->variant" />
                                <div class="field"><label>Destination</label><select name="items[{{ $i }}][inventory_location_id]" required>@foreach ($locations as $location)<option value="{{ $location->id }}" @selected($poItem?->inventory_location_id === $location->id || (! $poItem?->inventory_location_id && $i === 0 && $activeBranchLocationId === $location->id))>{{ $location->name }}</option>@endforeach</select></div>
                                <div class="field"><label>Quantity</label><input name="items[{{ $i }}][quantity_ordered]" type="number" min="1" step="1" value="{{ $poItem?->quantity_ordered }}" @if ($i === 0) required @endif></div>
                                <div class="field"><label>Unit cost</label><input name="items[{{ $i }}][unit_cost]" type="text" inputmode="decimal" data-money-input value="{{ $poItem ? $money($poItem->unit_cost_minor) : '' }}" @if ($i === 0) required @endif></div>
                                <div class="field"><label>Vendor SKU</label><input name="items[{{ $i }}][vendor_sku]" value="{{ $poItem?->vendor_sku }}"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="field"><label>Notes</label><textarea name="notes">{{ $selectedPo?->notes }}</textarea></div>
            <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Cancel</button><button class="btn primary" type="submit">{{ $selectedPo ? 'Save changes' : 'Create PO' }}</button></div>
        </form>
    </div>
</dialog>

<script>
document.addEventListener('DOMContentLoaded', () => {
    if (window.storebootPoDialogBound) return;
    window.storebootPoDialogBound = true;

    document.querySelectorAll('[data-add-po-line]').forEach((button) => {
        button.addEventListener('click', () => {
            const list = button.closest('form')?.querySelector('[data-po-lines]');
            const first = list?.querySelector('[data-po-line]');
            if (!list || !first) return;
            const index = list.querySelectorAll('[data-po-line]').length;
            const row = first.cloneNode(true);
            row.querySelectorAll('[name]').forEach((field) => {
                field.name = field.name.replace(/items\[\d+\]/, `items[${index}]`);
                if (field.tagName === 'SELECT') field.selectedIndex = 0;
                else field.value = '';
            });
            row.querySelectorAll('[data-variant-search]').forEach((field) => {
                field.value = '';
                field.setCustomValidity('');
            });
            list.appendChild(row);
        });
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-po-line]');
        if (!button) return;

        const line = button.closest('[data-po-line]');
        const list = button.closest('[data-po-lines]');

        if (line && list?.querySelectorAll('[data-po-line]').length > 1) {
            line.remove();
            return;
        }

        line?.querySelectorAll('input').forEach((field) => {
            field.value = '';
            field.setCustomValidity('');
        });
        line?.querySelectorAll('select').forEach((field) => {
            field.selectedIndex = 0;
        });
    });

    document.addEventListener('input', (event) => {
        const search = event.target.closest('[data-variant-search]');
        if (!search) return;

        const line = search.closest('[data-po-line]');
        const unitCost = line?.querySelector('input[name$="[unit_cost]"]');
        const option = Array.from(search.list?.options || []).find((item) => item.value === search.value);

        if (unitCost && option?.dataset.cost && !unitCost.value) {
            unitCost.value = option.dataset.cost;
        }
    });
});
</script>
