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
                    <label>Location</label>
                    <select name="inventory_location_id" required>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected((int) old('inventory_location_id') === $location->id || (! old('inventory_location_id') && $activeBranchLocationId === $location->id))>{{ $location->name }}</option>
                        @endforeach
                    </select>
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
                    <label>Reference number</label>
                    <input name="reference_number">
                </div>
                <div class="field full">
                    <label>Notes</label>
                    <textarea name="notes"></textarea>
                </div>
            </div>
            <div class="movement-lines-panel">
                <div class="movement-lines-header">
                    <div><strong>Movement items</strong><br><span class="subtle">Use Purchasing for supplier deliveries and Sales Returns for customer returns.</span></div>
                    <button class="btn secondary" type="button" data-add-movement-line>Add item</button>
                </div>
                <div data-movement-lines>
                    <div class="movement-line" data-movement-line>
                        <div class="field">
                            <label>Movement type</label>
                            <select name="items[0][movement_type]" required data-movement-type>
                                @foreach ($movementTypes as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
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
                            <label>Total cost</label>
                            <input name="items[0][total_cost]" type="text" inputmode="decimal" data-money-input data-movement-total-cost>
                            <small class="subtle" data-movement-total-cost-help></small>
                        </div>
                        <div class="field"><label>Batch number <span class="subtle">(optional)</span></label><input name="items[0][batch_number]" placeholder="e.g. LOT-2026-001"></div>
                        <div class="field"><label>Expiry date <span class="subtle">(optional)</span></label><input name="items[0][expiry_date]" type="date"></div>
                        <div class="movement-line-actions"><button class="btn danger" type="button" data-remove-movement-line>Remove</button></div>
                    </div>
                </div>
                <div class="movement-grand-total"><span>Grand total</span><strong data-movement-grand-total>{{ $tenant->currency_code }} 0.00</strong></div>
            </div>
            <div class="button-row">
                <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                <button class="btn primary" type="submit">Post movement</button>
            </div>
        </form>
    </div>
</dialog>
