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
                <div class="panel-header" style="gap:8px; flex-wrap:wrap;">
                    <h3 class="panel-title">Items</h3>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <select data-datalist-type="variant-options" aria-label="Filter items by type" title="Filter the product list by type">
                            <option value="">All types</option>
                            <option value="raw_material">Raw materials</option>
                            <option value="product">Products</option>
                        </select>
                        <button class="btn secondary" type="button" data-add-po-line>Add line</button>
                    </div>
                </div>
                <div class="panel-body" data-po-lines>
                    @foreach ($poItems as $i => $poItem)
                        @php
                            $lineUnits = $poItem ? ($purchaseOrderUnits[$poItem->product_variant_id] ?? []) : [];
                            $enteredQuantity = $poItem?->entered_quantity ?? $poItem?->quantity_ordered;
                        @endphp
                        <div class="po-line-card" data-po-line>
                            <div class="po-line-header">
                                <strong>Line item</strong>
                                <button class="btn danger" type="button" data-remove-po-line>Remove line</button>
                            </div>
                            <div class="form-grid">
                                <x-variant-picker name="items[{{ $i }}][product_variant_id]" label="Variant" :selected-variant="$poItem?->variant" />
                                <div class="field"><label>Destination</label><select name="items[{{ $i }}][inventory_location_id]" required>@foreach ($locations as $location)<option value="{{ $location->id }}" @selected($poItem?->inventory_location_id === $location->id || (! $poItem?->inventory_location_id && $i === 0 && $activeBranchLocationId === $location->id))>{{ $location->name }}</option>@endforeach</select></div>
                                <div class="field">
                                    <label>Quantity</label>
                                    <div class="qty-unit">
                                        <input name="items[{{ $i }}][quantity_ordered]" type="number" min="0.0001" step="any" value="{{ $enteredQuantity }}" @if ($i === 0) required @endif>
                                        <select name="items[{{ $i }}][unit_id]" data-po-unit data-selected-unit="{{ $poItem?->entered_unit_id }}" aria-label="Measurement unit" @disabled(count($lineUnits) <= 1)>
                                            @forelse ($lineUnits as $unit)
                                                <option value="{{ $unit['id'] }}" @selected($poItem?->entered_unit_id ? (int) $poItem->entered_unit_id === $unit['id'] : $unit['factor'] === 1.0)>{{ $unit['code'] }}</option>
                                            @empty
                                                <option value="">—</option>
                                            @endforelse
                                        </select>
                                    </div>
                                    <small class="subtle" data-po-measurement-hint>{{ $poItem ? 'Stored in inventory as '.$poItem->quantity_ordered.' base units.' : 'Choose an item to see how it is measured.' }}</small>
                                </div>
                                <div class="field"><label>Total line cost</label><input name="items[{{ $i }}][line_total]" type="text" inputmode="decimal" data-money-input value="{{ $poItem ? $money($poItem->line_total_minor) : '' }}" @if ($i === 0) required @endif><small class="subtle">Enter the total cost for this entire line.</small></div>
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
    const units = @json($purchaseOrderUnits ?? []);

    const loadUnits = (line, variantId, selectedUnitId = '') => {
        const select = line?.querySelector('[data-po-unit]');
        const hint = line?.querySelector('[data-po-measurement-hint]');
        if (!select) return;

        const available = units[variantId] || [];
        select.replaceChildren();

        if (!available.length) {
            select.add(new Option('—', ''));
            select.disabled = true;
            if (hint) hint.textContent = 'Choose an item to see how it is measured.';
            return;
        }

        available.forEach((unit) => select.add(new Option(unit.code, unit.id ?? '')));
        const selectedIndex = available.findIndex((unit) => selectedUnitId
            ? String(unit.id) === String(selectedUnitId)
            : Number(unit.factor) === 1);
        select.selectedIndex = selectedIndex >= 0 ? selectedIndex : 0;
        select.disabled = available.length === 1 && available[0].id === null;
        if (hint) hint.textContent = available.length > 1
            ? 'Choose the measurement used by the supplier; inventory will use the base-unit equivalent.'
            : `Quantity is measured in ${available[0].code}.`;
    };

    document.querySelectorAll('[data-po-line]').forEach((line) => {
        const variantId = line.querySelector('[data-variant-value]')?.value || '';
        const selectedUnitId = line.querySelector('[data-po-unit]')?.dataset.selectedUnit || '';
        if (variantId) loadUnits(line, variantId, selectedUnitId);
    });

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
            loadUnits(row, '');
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
        const option = Array.from(search.list?.options || []).find((item) => item.value === search.value);

        if (line && option?.dataset.variantId) loadUnits(line, option.dataset.variantId);
    });
});
</script>
