<dialog class="dialog" id="transfer-dialog">
    <div class="dialog-header">
        <div>
            <h2 class="panel-title">Transfer stock</h2>
            <p class="subtle">Move stock from one branch or location to another.</p>
        </div>
        <button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button>
    </div>
    <div class="dialog-body">
        <form class="mini-form" method="POST" action="{{ route('admin.inventory.movements.store') }}">
            @csrf
            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
            <input type="hidden" name="movement_type" value="{{ \Modules\Inventory\Enums\InventoryMovementType::TransferOut->value }}">
            <input type="hidden" name="stock_condition" value="{{ \Modules\Inventory\Enums\StockCondition::Sellable->value }}">

            <div class="form-grid">
                <div class="field">
                    <label>From location</label>
                    <select name="inventory_location_id" required>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected((int) old('inventory_location_id') === $location->id || (! old('inventory_location_id') && $activeBranchLocationId === $location->id))>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>To location</label>
                    <select name="destination_inventory_location_id" required>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected((int) old('destination_inventory_location_id') === $location->id)>{{ $location->name }}</option>
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
                    <label>Reference number</label>
                    <input name="reference_number">
                </div>
                <div class="field">
                    <label>Occurred at</label>
                    <input name="occurred_at" type="datetime-local">
                </div>
                <div class="field full">
                    <label>Notes</label>
                    <textarea name="notes"></textarea>
                </div>
            </div>

            <div class="button-row">
                <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                <button class="btn primary" type="submit">Transfer stock</button>
            </div>
        </form>
    </div>
</dialog>
