@php
    $qty = fn ($value): string => \Modules\Inventory\Support\Quantity::format($value ?? 0);
    $money = fn (?int $minor): string => number_format(($minor ?? 0) / 100, 2);
    $tenantParam = request('tenant');
@endphp

<x-layouts.admin title="Raw Materials">
    <div class="topbar">
        <div>
            <div class="eyebrow">Ingredients &amp; inputs</div>
            <h1>Raw Materials</h1>
            <p class="subtle">Stocked inputs used in recipes and production — never sold. For {{ $tenant->name }}.</p>
        </div>
        @if ($isPlatformAdmin)
            <form method="GET" action="{{ route('admin.catalog.raw-materials.index') }}" style="min-width: 260px;">
                <select name="tenant" onchange="this.form.submit()">
                    @foreach ($tenants as $visibleTenant)
                        <option value="{{ $visibleTenant->id }}" @selected($visibleTenant->id === $tenant->id)>{{ $visibleTenant->name }}</option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    @if (session('status'))<div class="alert success">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="alert errors">{{ $errors->first() }}</div>@endif

    <div style="margin-bottom:16px;">
        <button class="btn primary" type="button" data-dialog-open="raw-material-dialog"
            onclick="window.__rmEdit && window.__rmEdit(null)">New raw material</button>
    </div>

    <section class="panel">
        <div class="panel-header"><h2 class="panel-title">Raw materials</h2></div>
        <div class="panel-body">
            <div style="overflow-x:auto;">
                <table class="table">
                    <thead><tr><th>Name</th><th>SKU</th><th>On hand</th><th>Unit</th><th>Low-stock alert</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($materials as $material)
                            @php $variant = $material->variants->first(); @endphp
                            <tr>
                                <td>{{ $material->name }}</td>
                                <td class="subtle">{{ $variant?->sku }}</td>
                                @php $baseOnHand = (float) ($onHand[$variant?->id] ?? 0); $rmUnits = ($material->unitCategory?->units ?? collect())->filter(fn ($u) => $u->to_base_factor !== null)->values(); @endphp
                                <td><span data-onhand-display data-base="{{ $baseOnHand }}">{{ $qty($baseOnHand) }}</span></td>
                                <td>
                                    <select data-onhand-unit>
                                        @forelse ($rmUnits as $u)
                                            <option value="{{ $u->to_base_factor }}">{{ $u->code }}</option>
                                        @empty
                                            <option value="1">each</option>
                                        @endforelse
                                    </select>
                                </td>
                                @php
                                    $watched = collect($reorderLevels[$variant?->id] ?? [])->filter(fn (array $l): bool => $l['level'] > 0);
                                    $lowAt = $watched->filter(fn (array $l): bool => $l['available'] <= $l['level'])->count();
                                @endphp
                                <td>
                                    @if ($watched->isEmpty())
                                        <span class="subtle">Not set</span>
                                    @elseif ($lowAt > 0)
                                        <span class="badge" style="background:#fef0c7; color:#b54708; font-weight:800;">Low at {{ $lowAt }} of {{ $watched->count() }}</span>
                                    @else
                                        <span class="badge" style="background:#dcfae6; color:#067647; font-weight:800;">OK · {{ $watched->count() }} {{ \Illuminate\Support\Str::plural('location', $watched->count()) }}</span>
                                    @endif
                                </td>
                                <td style="text-align:right;">
                                    @if ($canManageReorder && $variant)
                                        <button class="icon-btn" type="button" aria-label="Low-stock alert levels" title="Low-stock alert levels"
                                            onclick="window.__reorderOpen && window.__reorderOpen({{ $variant->id }}, @js($material->name))">
                                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                                        </button>
                                    @endif
                                    <button class="icon-btn" type="button" aria-label="Edit" title="Edit"
                                        data-dialog-open="raw-material-dialog"
                                        onclick="window.__rmEdit && window.__rmEdit({id: {{ $material->id }}, name: @js($material->name), sku: @js($variant?->sku), unit_category_id: '{{ $material->unit_category_id }}'})">
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                    </button>
                                    <form method="POST" action="{{ route('admin.catalog.raw-materials.destroy', $material->id) }}" onsubmit="return confirm('Remove this raw material?');" style="display:inline;">
                                        @csrf @method('DELETE')
                                        <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                                        <button class="icon-btn" type="submit" aria-label="Remove" title="Remove">
                                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="subtle">No raw materials yet. Add rice, oil, flour, packaging, etc.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <dialog class="dialog" id="raw-material-dialog">
        <div class="dialog-header">
            <div><h2 class="panel-title" data-rm-title>New raw material</h2></div>
            <button class="icon-btn" type="button" data-dialog-close aria-label="Close">✕</button>
        </div>
        <div class="dialog-body">
            <form class="mini-form" method="POST" action="{{ route('admin.catalog.raw-materials.store') }}" data-rm-form
                data-store-action="{{ route('admin.catalog.raw-materials.store') }}"
                data-update-base="{{ route('admin.catalog.raw-materials.update', ['product' => '__ID__']) }}">
                @csrf
                <input type="hidden" name="_method" value="POST" data-rm-method>
                <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                <div class="form-grid">
                    <div class="field">
                        <label>Name</label>
                        <input name="name" required data-rm-name>
                    </div>
                    <div class="field">
                        <label>SKU</label>
                        <input name="sku" placeholder="optional" data-rm-sku>
                    </div>
                    <div class="field">
                        <label>Measurement</label>
                        <select name="unit_category_id" data-rm-unit-category>
                            <option value="">Default (each) — plain count</option>
                            @foreach ($unitCategories as $unitCategory)
                                <option value="{{ $unitCategory->id }}">{{ $unitCategory->name }}</option>
                            @endforeach
                        </select>
                        @if ($unitCategories->isEmpty())
                            <small class="subtle">Counted in plain units. Create a measurement category in Production → Units of measure to stock this in kg, cartons, etc.</small>
                        @endif
                    </div>
                </div>
                <div class="button-row">
                    <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                    <button class="btn primary" type="submit">Save raw material</button>
                </div>
            </form>
        </div>
    </dialog>

    @if ($canManageReorder)
        @include('inventory::admin.partials.reorder-dialog')
    @endif

    <script>
        (function () {
            const form = document.querySelector('[data-rm-form]');
            if (! form) return;
            // On-hand column: show the stored base quantity in the selected unit.
            const fmtQty = (n) => (Math.round(n * 10000) / 10000).toString();
            document.querySelectorAll('[data-onhand-unit]').forEach(function (sel) {
                const row = sel.closest('tr');
                const display = row && row.querySelector('[data-onhand-display]');
                if (! display) return;
                const base = parseFloat(display.dataset.base) || 0;
                sel.addEventListener('change', function () {
                    const factor = parseFloat(sel.value) || 1;
                    display.textContent = fmtQty(base / factor);
                });
            });

            window.__rmEdit = function (rm) {
                const title = form.closest('dialog').querySelector('[data-rm-title]');
                if (rm && rm.id) {
                    form.action = form.dataset.updateBase.replace('__ID__', rm.id);
                    form.querySelector('[data-rm-method]').value = 'PUT';
                    title.textContent = 'Edit raw material';
                    form.querySelector('[data-rm-name]').value = rm.name || '';
                    form.querySelector('[data-rm-sku]').value = rm.sku || '';
                    form.querySelector('[data-rm-unit-category]').value = rm.unit_category_id || '';
                } else {
                    form.action = form.dataset.storeAction;
                    form.querySelector('[data-rm-method]').value = 'POST';
                    title.textContent = 'New raw material';
                    form.reset();
                }
            };
        })();
    </script>
</x-layouts.admin>
