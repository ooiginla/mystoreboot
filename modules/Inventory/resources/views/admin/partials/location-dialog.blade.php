<dialog class="dialog" id="location-dialog">
    <div class="dialog-header">
        <div>
            <h2 class="panel-title">Add inventory location</h2>
            <p class="subtle">Use this for warehouses, store rooms, or special stock areas.</p>
        </div>
        <button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button>
    </div>
    <div class="dialog-body">
        <form class="mini-form" method="POST" action="{{ route('admin.inventory.locations.store') }}">
            @csrf
            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
            <div class="form-grid">
                <div class="field">
                    <label>Name</label>
                    <input name="name" required>
                </div>
                <div class="field">
                    <label>Code</label>
                    <input name="code">
                </div>
                <div class="field">
                    <label>Branch</label>
                    <select name="branch_id">
                        <option value="">Standalone location</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((int) old('branch_id', $activeBranchForView?->id) === $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Location type</label>
                    <select name="location_type" required>
                        @foreach ($locationTypes as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="button-row">
                <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                <button class="btn primary" type="submit">Add location</button>
            </div>
        </form>
    </div>
</dialog>
