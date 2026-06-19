<dialog class="dialog" id="reorder-dialog">
    <div class="dialog-header">
        <div>
            <h2 class="panel-title">Set reorder level</h2>
            <p class="subtle">Reorder levels are tracked per variant and branch/location.</p>
        </div>
        <button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button>
    </div>
    <div class="dialog-body">
        <form class="mini-form" method="POST" action="{{ route('admin.inventory.reorder.save') }}">
            @csrf
            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
            <div class="form-grid">
                <div class="field">
                    <label>Location</label>
                    <select name="inventory_location_id" required>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}">{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <x-variant-picker label="Product variant" />
                <div class="field">
                    <label>Reorder level</label>
                    <input name="reorder_level" type="number" min="0" step="1" required>
                </div>
                <div class="field">
                    <label>Reorder quantity</label>
                    <input name="reorder_quantity" type="number" min="0" step="1">
                </div>
            </div>
            <div class="button-row">
                <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                <button class="btn primary" type="submit">Save reorder level</button>
            </div>
        </form>
    </div>
</dialog>
