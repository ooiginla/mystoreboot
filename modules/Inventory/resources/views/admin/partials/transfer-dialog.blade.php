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

            <div class="movement-lines-panel">
                <div class="movement-lines-header">
                    <div><strong>Transfer items</strong><br><span class="subtle">Costs use the current weighted average at the source location.</span></div>
                    <button class="btn secondary" type="button" data-add-movement-line>Add item</button>
                </div>
                <div data-movement-lines>
                    <div class="movement-line transfer-line" data-movement-line>
                        <x-variant-picker name="items[0][product_variant_id]" label="Product variant" />
                        <div class="field">
                            <label>Quantity</label>
                            <div class="qty-unit">
                                <input name="items[0][quantity]" type="number" min="0" step="any" required data-qty-input>
                                <select name="items[0][unit_id]" data-movement-unit aria-label="Measurement unit" disabled><option value="">—</option></select>
                            </div>
                            <small class="subtle" data-measurement-hint>Choose an item to see how it is measured.</small>
                        </div>
                        <div class="field">
                            <label>Cost</label>
                            <div class="movement-line-cost"><strong data-line-total>{{ $tenant->currency_code }} 0.00</strong><span class="subtle" data-line-unit-cost>Choose an item</span></div>
                        </div>
                        <div class="movement-line-actions"><button class="btn danger" type="button" data-remove-movement-line>Remove</button></div>
                    </div>
                </div>
                <div class="movement-grand-total"><span>Total transfer value</span><strong data-movement-grand-total>{{ $tenant->currency_code }} 0.00</strong></div>
            </div>

            <div class="button-row">
                <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                <button class="btn primary" type="submit">Transfer stock</button>
            </div>
        </form>
    </div>
</dialog>
