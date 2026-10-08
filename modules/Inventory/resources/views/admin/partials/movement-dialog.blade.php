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
            <input type="hidden" name="multi_item" value="1">
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
                    <button class="btn secondary" type="button" data-open-item-editor data-owner-dialog="movement-dialog" data-item-mode="movement">Add item</button>
                </div>
                <div class="movement-summary-head" aria-hidden="true"><span></span><span>Type</span><span>Item</span><span>Quantity</span><span>Cost</span></div>
                <div data-movement-lines data-empty-text="No movement items added yet.">
                    <div class="empty" data-movement-lines-empty>No movement items added yet.</div>
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
