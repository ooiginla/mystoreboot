<dialog class="dialog" id="location-types-dialog">
    <div class="dialog-header">
        <div>
            <h2 class="panel-title">Location types</h2>
            <p class="subtle">Add your own store categories (e.g. kitchen store, cold room). Built-in types can't be removed.</p>
        </div>
        <button class="icon-btn" type="button" data-dialog-close aria-label="Close">✕</button>
    </div>
    <div class="dialog-body">
        <div class="list" style="margin-bottom: 16px;">
            @foreach ($locationTypeRows as $type)
                <div class="item" style="gap: 8px; flex-wrap: wrap;">
                    <form method="POST" action="{{ route('admin.inventory.location-types.update', $type->id) }}" style="display:flex; gap:8px; align-items:center; flex:1; min-width: 220px;">
                        @csrf @method('PUT')
                        <input type="hidden" name="tenant" value="{{ request('tenant') }}">
                        <input name="label" value="{{ $type->label }}" required style="flex:1;">
                        @if ($type->is_system)<span class="badge neutral">Built-in</span>@endif
                        <button class="icon-btn" type="submit" aria-label="Rename" title="Rename">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.inventory.location-types.destroy', $type->id) }}" onsubmit="return confirm('Remove this location type?');">
                        @csrf @method('DELETE')
                        <input type="hidden" name="tenant" value="{{ request('tenant') }}">
                        <button class="icon-btn" type="submit" aria-label="Remove" title="Remove">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                        </button>
                    </form>
                </div>
            @endforeach
        </div>

        <form class="mini-form" method="POST" action="{{ route('admin.inventory.location-types.store') }}">
            @csrf
            <input type="hidden" name="tenant" value="{{ request('tenant') }}">
            <div class="form-grid">
                <div class="field full">
                    <label>New type name</label>
                    <input name="label" required placeholder="e.g. Kitchen store">
                </div>
            </div>
            <div class="button-row">
                <button class="btn secondary" type="button" data-dialog-close>Close</button>
                <button class="btn primary" type="submit">Add type</button>
            </div>
        </form>
    </div>
</dialog>
