<dialog class="dialog" id="movement-dialog">
    <div class="dialog-header">
        <div>
            <h2 class="panel-title">Post stock movement</h2>
            <p class="subtle">Use this for stock-in, stock-out, adjustments, damaged stock, and returns.</p>
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
                    <select name="movement_type" required>
                        @foreach ($movementTypes as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Location</label>
                    <select name="inventory_location_id" required>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected((int) old('inventory_location_id') === $location->id || (! old('inventory_location_id') && $activeBranchLocationId === $location->id))>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <x-variant-picker label="Product variant" class="full" />
                <div class="field">
                    <label>Quantity</label>
                    <input name="quantity" type="number" min="1" step="1" required>
                </div>
                <div class="field">
                    <label>Unit cost</label>
                    <input name="unit_cost" type="text" inputmode="decimal" data-money-input>
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
                <div class="field">
                    <label>Batch number</label>
                    <input name="batch_number">
                </div>
                <div class="field">
                    <label>Expiry date</label>
                    <input name="expiry_date" type="date">
                </div>
                <div class="field">
                    <label>Reference type</label>
                    <input name="reference_type" placeholder="Purchase, sale, correction">
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
