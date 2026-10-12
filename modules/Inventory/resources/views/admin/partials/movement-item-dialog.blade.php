<dialog class="dialog movement-item-dialog" id="movement-item-dialog">
    <div class="dialog-header">
        <div>
            <h2 class="panel-title" data-item-editor-title>Add movement item</h2>
            <p class="subtle">Select the item and enter its movement details.</p>
        </div>
        <button class="icon-btn" type="button" data-item-editor-cancel aria-label="Close">x</button>
    </div>
    <div class="dialog-body">
        <form class="mini-form movement-item-editor" data-item-editor-form>
            <div class="movement-item-row movement-item-context-row">
                <div class="field" data-item-movement-type-field>
                    <label>Movement type</label>
                    <select data-movement-type required>
                        @foreach ($movementTypes as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Item type</label>
                    <select data-item-type-filter>
                        <option value="">All items</option>
                        <option value="product">Products</option>
                        <option value="raw_material">Raw materials</option>
                    </select>
                </div>
                <div class="field" data-item-batch-field><label>Batch number <span class="subtle">(optional)</span></label><input data-item-batch-number placeholder="e.g. LOT-2026-001"></div>
                <div class="field" data-item-expiry-field><label>Expiry date <span class="subtle">(optional)</span></label><input data-item-expiry-date type="date"></div>
            </div>
            <div class="movement-item-row movement-item-details-row">
                <x-variant-picker name="editor_product_variant_id" label="Variant" class="movement-item-variant" options-id="movement-item-variant-options" />
                <div class="field">
                    <label>Quantity</label>
                    <div class="movement-quantity-parts" data-quantity-parts>
                        <div class="movement-quantity-part" data-quantity-part>
                            <input type="number" min="0" step="any" required data-qty-input aria-label="Quantity">
                            <select data-movement-unit aria-label="Measurement unit" disabled><option value="">—</option></select>
                            <button class="movement-quantity-part-remove" type="button" data-remove-quantity-part aria-label="Remove measurement" hidden>×</button>
                        </div>
                    </div>
                    <button class="btn secondary movement-add-quantity-part" type="button" data-add-quantity-part>+ Add measurement</button>
                    <small class="subtle" data-measurement-hint>Choose an item to see how it is measured.</small>
                </div>
                <div class="field" data-item-total-cost-field>
                    <label>Total cost</label>
                    <input type="text" inputmode="decimal" data-money-input data-movement-total-cost>
                    <small class="subtle" data-movement-total-cost-help></small>
                </div>
                <div class="field" data-item-transfer-cost-field hidden>
                    <label>Transfer value</label>
                    <div class="movement-line-cost"><strong data-line-total>{{ $tenant->currency_code }} 0.00</strong><span class="subtle" data-line-unit-cost>Choose an item</span></div>
                </div>
            </div>
            <div class="button-row">
                <button class="btn secondary" type="button" data-item-editor-cancel>Cancel</button>
                <button class="btn primary" type="submit">Add item</button>
            </div>
        </form>
    </div>
</dialog>

<datalist id="movement-item-variant-options">
    @foreach ($variants as $variant)
        <option value="{{ $variantLabel($variant) }}" data-variant-id="{{ $variant->id }}" data-sku="{{ $variant->sku }}" data-barcode="{{ $variant->barcode }}" data-type="{{ $variant->product?->product_type?->value }}"></option>
    @endforeach
</datalist>
