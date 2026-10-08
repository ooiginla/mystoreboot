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
            <input type="hidden" name="multi_item" value="1">
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
                    <button class="btn secondary" type="button" data-open-item-editor data-owner-dialog="transfer-dialog" data-item-mode="transfer">Add item</button>
                </div>
                <div class="movement-summary-head" aria-hidden="true"><span></span><span>Type</span><span>Item</span><span>Quantity</span><span>Cost</span></div>
                <div data-movement-lines data-empty-text="No transfer items added yet.">
                    <div class="empty" data-movement-lines-empty>No transfer items added yet.</div>
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
