@php
    $fmtFactor = fn ($v): string => $v === null ? '' : rtrim(rtrim((string) $v, '0'), '.');
    $tenantParam = request('tenant');
    $saveIcon = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
    $trashIcon = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>';
@endphp

<style>
    .unit-row { display:grid; grid-template-columns:80px 1fr 110px 110px auto auto; gap:8px; align-items:center; margin-bottom:8px; }
    .unit-row input, .unit-row select { width:100%; }
    .unit-head { font-size:12px; color:var(--muted, #667085); }
    .unit-accordion { margin-bottom:16px; }
    .unit-accordion > summary { cursor:pointer; list-style:none; user-select:none; }
    .unit-accordion > summary::-webkit-details-marker { display:none; }
    .unit-accordion-summary { display:flex; align-items:center; gap:10px; width:100%; }
    .unit-accordion-title { display:flex; align-items:center; gap:8px; flex:1; min-width:0; }
    .unit-accordion-chevron { width:18px; height:18px; flex:0 0 auto; transition:transform .18s ease; }
    .unit-accordion[open] .unit-accordion-chevron { transform:rotate(180deg); }
    .unit-category-actions { display:flex; justify-content:space-between; gap:12px; align-items:end; flex-wrap:wrap; margin-bottom:18px; padding-bottom:16px; border-bottom:1px solid var(--line); }
    @media (max-width:760px) { .unit-row { grid-template-columns:1fr 1fr; } }
</style>

<section class="panel" style="margin-bottom:16px;">
    <div class="panel-header"><h2 class="panel-title">New measurement category</h2></div>
    <div class="panel-body">
        <form method="POST" action="{{ route('admin.inventory.unit-categories.store') }}" class="mini-form" style="display:flex; gap:8px; align-items:end; flex-wrap:wrap;">
            @csrf
            <input type="hidden" name="tenant" value="{{ $tenantParam }}">
            <div class="field" style="flex:1; min-width:220px;">
                <label>Category name</label>
                <input name="name" required placeholder="e.g. Okin Biscuit Measurement">
            </div>
            <button class="btn primary" type="submit">Add category</button>
        </form>
    </div>
</section>

<p class="subtle" style="margin-bottom:12px;">
    Group your measurements into categories (e.g. “Okin Biscuit”). Within a category, one unit is the base (factor 1) and the rest are multiples of it. Assign a category to a product so it stocks in that base and can be bought/sold in the others.
</p>

@foreach ($unitCategories as $category)
    <details class="panel unit-accordion" name="unit-categories" data-unit-accordion>
        <summary class="panel-header">
            <span class="unit-accordion-summary">
                <svg class="unit-accordion-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                <span class="unit-accordion-title">
                    <strong>{{ $category->name }}</strong>
                    <span class="badge neutral">{{ $category->units->count() }} {{ \Illuminate\Support\Str::plural('unit', $category->units->count()) }}</span>
                    @if ($category->is_default)<span class="badge neutral">Default</span>@endif
                </span>
            </span>
        </summary>
        <div class="panel-body">
            <div class="unit-category-actions">
                <form method="POST" action="{{ route('admin.inventory.unit-categories.update', $category->id) }}" style="display:flex; gap:8px; align-items:end; flex:1; flex-wrap:wrap;">
                    @csrf @method('PUT')
                    <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                    <div class="field" style="flex:1; min-width:200px;">
                        <label>Category name</label>
                        <input name="name" value="{{ $category->name }}" required>
                    </div>
                    <button class="btn secondary" type="submit">Rename category</button>
                </form>
                @unless ($category->is_default)
                    <form method="POST" action="{{ route('admin.inventory.unit-categories.destroy', $category->id) }}" onsubmit="return confirm('Remove this category?');">
                        @csrf @method('DELETE')
                        <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                        <button class="btn danger" type="submit">Remove category</button>
                    </form>
                @endunless
            </div>

            <div class="unit-row unit-head">
                <span>Code</span><span>Name</span><span>Dimension</span><span>Base units</span><span>Base?</span><span></span>
            </div>
            @forelse ($category->units as $unit)
                <div style="display:flex; gap:6px; align-items:center;">
                    <form method="POST" action="{{ route('admin.inventory.units.update', $unit->id) }}" class="unit-row" style="flex:1; margin:0;">
                        @csrf @method('PUT')
                        <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                        <input type="hidden" name="unit_category_id" value="{{ $category->id }}">
                        <input name="code" value="{{ $unit->code }}" required aria-label="Code">
                        <input name="name" value="{{ $unit->name }}" required aria-label="Name">
                        <select name="dimension" aria-label="Dimension">
                            @foreach ($unitDimensions as $value => $label)
                                <option value="{{ $value }}" @selected($unit->dimension->value === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <input name="to_base_factor" type="number" step="any" min="0" value="{{ $fmtFactor($unit->to_base_factor) }}" placeholder="—" aria-label="Base units per unit">
                        <label style="display:flex; align-items:center; gap:4px;">
                            <input type="hidden" name="is_base_for_dimension" value="0">
                            <input type="checkbox" name="is_base_for_dimension" value="1" @checked($unit->is_base_for_dimension) style="width:auto;">
                        </label>
                        <button class="icon-btn" type="submit" aria-label="Save unit" title="Save">{!! $saveIcon !!}</button>
                    </form>
                    <form method="POST" action="{{ route('admin.inventory.units.destroy', $unit->id) }}" onsubmit="return confirm('Remove this unit?');" style="margin:0;">
                        @csrf @method('DELETE')
                        <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                        <button class="icon-btn" type="submit" aria-label="Remove unit" title="Remove">{!! $trashIcon !!}</button>
                    </form>
                </div>
            @empty
                <p class="subtle">No units in this category yet.</p>
            @endforelse

            <form method="POST" action="{{ route('admin.inventory.units.store') }}" class="unit-row" style="margin-top:12px;">
                @csrf
                <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                <input type="hidden" name="unit_category_id" value="{{ $category->id }}">
                <input name="code" required placeholder="e.g. carton" aria-label="Code">
                <input name="name" required placeholder="e.g. Large carton" aria-label="Name">
                <select name="dimension" aria-label="Dimension">
                    @foreach ($unitDimensions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <input name="to_base_factor" type="number" step="any" min="0" placeholder="base units" aria-label="Base units per unit">
                <label style="display:flex; align-items:center; gap:4px;">
                    <input type="hidden" name="is_base_for_dimension" value="0">
                    <input type="checkbox" name="is_base_for_dimension" value="1" style="width:auto;">
                </label>
                <button class="btn primary" type="submit">Add unit</button>
            </form>
        </div>
    </details>
@endforeach

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-unit-accordion]').forEach((accordion) => {
        accordion.addEventListener('toggle', () => {
            if (!accordion.open) return;
            document.querySelectorAll('[data-unit-accordion][open]').forEach((other) => {
                if (other !== accordion) other.open = false;
            });
        });
    });
});
</script>
