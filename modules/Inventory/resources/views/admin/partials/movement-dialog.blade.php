<dialog class="dialog" id="movement-dialog">
    <div class="dialog-header">
        <div>
            <h2 class="panel-title">Post stock movement</h2>
            <p class="subtle">Use this for opening stock, non-purchase stock-in, write-offs, adjustments, and damaged stock with no recoverable value.</p>
        </div>
        <button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button>
    </div>
    <div class="dialog-body">
        <form class="mini-form" method="POST" action="{{ route('admin.inventory.movements.store') }}">
            @csrf
            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
            <div class="form-grid">
                <div class="field">
                    <label>Movement type</label>
                    <select name="movement_type" required data-movement-type>
                        @foreach ($movementTypes as $value => $label)
                            <option value="{{ $value }}" @selected(old('movement_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <small class="subtle">Use Purchasing for supplier deliveries and Sales Returns for customer returns.</small>
                </div>
                <div class="field">
                    <label>Location</label>
                    <select name="inventory_location_id" required>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected((int) old('inventory_location_id') === $location->id || (! old('inventory_location_id') && $activeBranchLocationId === $location->id))>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Type</label>
                    <select data-datalist-type="variant-options">
                        <option value="">All</option>
                        <option value="product">Product</option>
                        <option value="raw_material">Raw material</option>
                    </select>
                </div>
                <x-variant-picker label="Product variant" class="full" enhanced />
                <div class="field">
                    <label for="movement-quantity">Quantity</label>
                    {{-- The unit sits on the quantity itself, so "25" never goes in without saying 25 of what. --}}
                    <div class="qty-unit">
                        <input id="movement-quantity" name="quantity" type="number" min="0" step="any" required data-qty-input>
                        <select name="unit_id" data-movement-unit aria-label="Measurement unit" disabled>
                            <option value="">—</option>
                        </select>
                    </div>
                    <small class="subtle" data-measurement-hint>Choose an item to see how it is measured.</small>
                </div>
                <div class="field">
                    <label for="movement-unit-cost">Unit cost</label>
                    <input id="movement-unit-cost" name="unit_cost" type="text" inputmode="decimal" value="{{ old('unit_cost') }}" data-money-input data-movement-unit-cost aria-describedby="movement-unit-cost-help">
                    <small class="subtle" id="movement-unit-cost-help" data-movement-unit-cost-help></small>
                </div>
                <div class="field">
                    <label>Stock condition</label>
                    <select name="stock_condition" required>
                        @foreach ($stockConditions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Occurred at</label>
                    <input name="occurred_at" type="datetime-local">
                </div>
                <div class="field full">
                    <h3 style="margin: 8px 0 2px; font-size: 0.95rem;">Batch &amp; expiry tracking <span class="subtle" style="font-weight: 400;">(optional)</span></h3>
                    <p class="subtle" style="margin: 0;">
                        Fill either field and this stock is recorded as its own <strong>lot</strong>. Lots are depleted
                        earliest-expiry-first, and you can trace where each one went from
                        <a href="{{ route('admin.inventory.batches.index', array_filter(['tenant' => request('tenant')])) }}">Lot traceability</a>.
                        Leave both blank for stock you do not need to track by batch.
                    </p>
                </div>
                <div class="field">
                    <label>Batch number</label>
                    <input name="batch_number" placeholder="e.g. LOT-2026-001">
                </div>
                <div class="field">
                    <label>Expiry date</label>
                    <input name="expiry_date" type="date">
                </div>
                <div class="field">
                    <label>Reference number</label>
                    <input name="reference_number">
                </div>
                <div class="field full">
                    <label>Notes</label>
                    <textarea name="notes"></textarea>
                </div>
            </div>
            <div class="button-row">
                <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                <button class="btn primary" type="submit">Post movement</button>
            </div>
        </form>
    </div>
</dialog>

<script>
    (() => {
        const dialog = document.getElementById('movement-dialog');
        const movementType = dialog?.querySelector('[data-movement-type]');
        const unitCost = dialog?.querySelector('[data-movement-unit-cost]');
        const help = dialog?.querySelector('[data-movement-unit-cost-help]');

        if (!movementType || !unitCost || !help) return;

        const syncUnitCost = () => {
            const acceptsUnitCost = ['opening_stock', 'stock_in'].includes(movementType.value);
            unitCost.disabled = !acceptsUnitCost;
            unitCost.required = acceptsUnitCost;

            if (!acceptsUnitCost) unitCost.value = '';

            help.textContent = acceptsUnitCost
                ? 'Required for opening stock and stock-in.'
                : 'Uses the product’s current weighted-average cost.';
        };

        movementType.addEventListener('change', syncUnitCost);
        syncUnitCost();
    })();
</script>
