<dialog class="dialog" id="location-edit-dialog">
    <div class="dialog-header">
        <div>
            <h2 class="panel-title">Edit location</h2>
            <p class="subtle">Update the name, code, type, and whether this location can sell (POS).</p>
        </div>
        <button class="icon-btn" type="button" data-dialog-close aria-label="Close">✕</button>
    </div>
    <div class="dialog-body">
        <form class="mini-form" method="POST" data-action-base="{{ route('admin.inventory.locations.update', ['location' => '__ID__']) }}">
            @csrf
            @method('PUT')
            <div class="form-grid">
                <div class="field">
                    <label>Name</label>
                    <input name="name" required>
                </div>
                <div class="field">
                    <label>Code</label>
                    <input name="code" placeholder="optional">
                </div>
                <div class="field">
                    <label>Location type</label>
                    <select name="location_type" required>
                        @foreach ($locationTypes as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field full">
                    <label style="display:flex; align-items:center; gap:8px;">
                        <input type="hidden" name="is_sellable_point" value="0">
                        <input type="checkbox" name="is_sellable_point" value="1" style="width:auto;">
                        Can sell from here (available as a POS point of sale)
                    </label>
                </div>
                <div class="field full">
                    <label style="display:flex; align-items:center; gap:8px;">
                        <input type="hidden" name="is_prep_station" value="0">
                        <input type="checkbox" name="is_prep_station" value="1" style="width:auto;">
                        Food is made here (a prep station — gets its own kitchen screen)
                    </label>
                </div>
            </div>
            <div class="button-row">
                <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                <button class="btn primary" type="submit">Save changes</button>
            </div>
        </form>
    </div>
</dialog>
