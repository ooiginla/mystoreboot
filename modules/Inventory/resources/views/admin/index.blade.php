@php
    $money = fn (?int $minor): string => number_format(($minor ?? 0) / 100, 2);
    $variantLabel = fn ($variant): string => $variant->product?->name.' / '.$variant->variant_name.' ('.$variant->sku.')';
    $activeBranchForView = app(\App\Support\ActiveBranchManager::class)->stateForRequest(request(), auth()->user())['activeBranch'];
    $activeBranchLocationId = $activeBranchForView ? $locations->firstWhere('branch_id', $activeBranchForView->id)?->id : null;
@endphp

<x-layouts.admin title="Inventory & Stock">
    <datalist id="variant-options">
        @foreach ($variants as $variant)
            <option value="{{ $variantLabel($variant) }}" data-variant-id="{{ $variant->id }}" data-sku="{{ $variant->sku }}" data-barcode="{{ $variant->barcode }}" data-type="{{ $variant->product?->product_type?->value }}"></option>
        @endforeach
    </datalist>

    <style>
        .inventory-toolbar { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
        .inventory-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .stock-visibility-filters { display: inline-flex; align-items: end; gap: 8px; flex-wrap: wrap; }
        .stock-visibility-filter-field { display: grid; gap: 4px; }
        .stock-visibility-filter-field label { white-space: nowrap; }
        .stock-visibility-filter-field input { min-width: 220px; padding-top: 8px; padding-bottom: 8px; }
        .stock-visibility-filter-field select { min-width: 190px; padding-top: 8px; padding-bottom: 8px; }
        .stock-status { font-weight: 800; }
        .stock-status.low { color: #b54708; }
        .stock-status.ok { color: #067647; }
        .report-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .report-card { border: 1px solid var(--line); border-radius: 8px; padding: 14px; background: #fff; }
        .movement-note { max-width: 260px; }
        @media (max-width: 900px) {
            .report-grid { grid-template-columns: 1fr; }
            .inventory-actions { width: 100%; }
            .inventory-actions .btn { flex: 1; }
            .stock-visibility-filters { width: 100%; }
            .stock-visibility-filter-field { flex: 1; min-width: 180px; }
            .stock-visibility-filter-field input, .stock-visibility-filter-field select { min-width: 0; }
        }
    </style>

    <div class="topbar">
        <div>
            <div class="eyebrow">Inventory & stock management</div>
            <h1>Inventory & Stock</h1>
            <p class="subtle">Real-time branch inventory for {{ $tenant->name }}.</p>
        </div>

        @if ($isPlatformAdmin)
            <form method="GET" action="{{ route('admin.inventory.index') }}" style="min-width: 260px;">
                <select name="tenant" onchange="this.form.submit()">
                    @foreach ($tenants as $visibleTenant)
                        <option value="{{ $visibleTenant->id }}" @selected($visibleTenant->id === $tenant->id)>{{ $visibleTenant->name }}</option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    @if (session('status'))
        <div class="alert">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert errors">
            <strong>Check the highlighted inventory details.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="stats-grid" style="margin-bottom: 18px;">
        <div class="stat"><span class="subtle">On hand</span><strong>{{ number_format($stats['on_hand']) }}</strong></div>
        <div class="stat"><span class="subtle">Available</span><strong>{{ number_format($stats['available']) }}</strong></div>
        <div class="stat"><span class="subtle">Low stock</span><strong>{{ number_format($stats['low_stock']) }}</strong></div>
        <div class="stat"><span class="subtle">Stock value</span><strong>{{ $tenant->currency_code }} {{ $money($stats['valuation_minor']) }}</strong></div>
    </div>

    <div class="tab-layout">
        <nav class="pill-nav" aria-label="Inventory sections" role="tablist">
            <a href="#stock" role="tab" data-tab-target="stock">Stock visibility <span class="badge neutral">{{ $stockLevels->count() }}</span></a>
            <a href="#movements" role="tab" data-tab-target="movements">Movements <span class="badge neutral">{{ $movements->count() }}</span></a>
            <a href="#alerts" role="tab" data-tab-target="alerts">Alerts <span class="badge neutral">{{ $lowStock->count() }}</span></a>
            <a href="#batches" role="tab" data-tab-target="batches">Expiry / condition <span class="badge neutral">{{ $batches->count() }}</span></a>
            <a href="#reports" role="tab" data-tab-target="reports">Reports</a>
            <a href="#locations" role="tab" data-tab-target="locations">Locations <span class="badge neutral">{{ $locations->count() }}</span></a>
        </nav>

        <div class="content-stack">
            <section class="panel tab-panel" id="stock" role="tabpanel" data-tab-panel>
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Multi-branch stock visibility</h2>
                        <p class="subtle">Each row maps a product variant to a branch or inventory location.</p>
                    </div>
                    <div class="inventory-actions">
                        <form class="stock-visibility-filters" method="GET" action="{{ route('admin.inventory.index') }}">
                            <input type="hidden" name="tenant" value="{{ $tenant->id }}">
                            <div class="stock-visibility-filter-field">
                                <label for="stock-product-filter">Product</label>
                                <input id="stock-product-filter" name="stock_product" type="search" list="variant-options" value="{{ $stockProductSearch }}" placeholder="Name, variant, SKU or barcode" autocomplete="off">
                            </div>
                            <div class="stock-visibility-filter-field">
                                <label for="stock-location-filter">Location</label>
                                <select id="stock-location-filter" name="stock_location" onchange="this.form.submit()">
                                    <option value="">All locations</option>
                                    @foreach ($locations as $location)
                                        <option value="{{ $location->id }}" @selected($selectedStockLocationId === $location->id)>{{ $location->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button class="btn secondary" type="submit">Search</button>
                        </form>
                        <button class="btn accent" type="button" data-dialog-open="movement-dialog">Post movement</button>
                        <button class="btn primary" type="button" data-dialog-open="transfer-dialog">Transfer stock</button>
                    </div>
                </div>
                <div class="panel-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Variant</th>
                                <th>Location</th>
                                <th>Unit</th>
                                <th>On hand</th>
                                <th>Available</th>
                                <th>Reorder</th>
                                <th>Avg cost</th>
                                <th>Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($stockLevels as $level)
                                <tr data-stock-location-id="{{ $level->inventory_location_id }}" data-stock-variant-id="{{ $level->product_variant_id }}">
                                    <td>
                                        <strong>{{ $level->variant->product?->name }}</strong><br>
                                        <span class="subtle">{{ $level->variant->variant_name }} · {{ $level->variant->sku }}</span>
                                    </td>
                                    <td>{{ $level->location->name }}</td>
                                    <td>{{ ($level->variant->baseUnit?->code ?? 'ea') === 'ea' ? 'each' : $level->variant->baseUnit->code }}</td>
                                    <td class="stock-status {{ $level->is_low_stock ? 'low' : 'ok' }}">{{ \Modules\Inventory\Support\Quantity::format($level->quantity_on_hand) }}</td>
                                    <td>{{ \Modules\Inventory\Support\Quantity::format($level->quantity_available) }}</td>
                                    <td>{{ \Modules\Inventory\Support\Quantity::format($level->reorder_level) }}</td>
                                    <td>{{ $tenant->currency_code }} {{ $money($level->average_cost_minor) }}</td>
                                    <td>{{ $tenant->currency_code }} {{ $money($level->stock_value_minor) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8"><div class="empty">No stock levels yet. Post stock-in or opening stock to begin tracking inventory.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel tab-panel" id="movements" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Stock movement history</h2>
                        <p class="subtle">Audit trail for stock-in, stock-out, adjustments, transfers, returns, and damaged stock.</p>
                    </div>
                    <button class="btn accent" type="button" data-dialog-open="movement-dialog">Post movement</button>
                </div>
                <div class="panel-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Variant</th>
                                <th>Location</th>
                                <th>Qty</th>
                                <th>Cost</th>
                                <th>Reference</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($movements as $movement)
                                <tr>
                                    <td>{{ $movement->occurred_at->format('M j, Y H:i') }}</td>
                                    <td><span class="badge neutral">{{ $movement->movement_type->label() }}</span></td>
                                    <td>{{ $movement->variant->product?->name }}<br><span class="subtle">{{ $movement->variant->sku }}</span></td>
                                    <td>
                                        {{ $movement->location->name }}
                                        @if ($movement->destinationLocation)
                                            <br><span class="subtle">To {{ $movement->destinationLocation->name }}</span>
                                        @endif
                                    </td>
                                    <td>{{ \Modules\Inventory\Support\Quantity::format($movement->quantity) }}</td>
                                    <td>{{ $tenant->currency_code }} {{ $money($movement->unit_cost_minor) }}</td>
                                    <td class="movement-note">
                                        {{ $movement->reference_number ?: $movement->notes ?: 'Not set' }}
                                        @if ($movement->batchAllocations->isNotEmpty())
                                            <br><span class="subtle">Lots:
                                                {{ $movement->batchAllocations->map(fn ($a) => ($a->batch?->batch_number ?: 'no batch no.')
                                                    .($a->batch?->expiry_date ? ' exp '.$a->batch->expiry_date->format('d M Y') : '')
                                                    .' ('.\Modules\Inventory\Support\Quantity::format($a->quantity).')')->implode(', ') }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7"><div class="empty">No inventory movements yet.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel tab-panel" id="alerts" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Low-stock alerts & reorder settings</h2>
                        <p class="subtle">An item is low when what is available at a location falls to or below its alert level there.</p>
                    </div>
                    <div class="inventory-actions">
                        <a class="btn secondary" href="{{ route('admin.inventory.reorder-levels.index', array_filter(['tenant' => request('tenant')])) }}">Set levels by location</a>
                        <button class="btn accent" type="button" data-dialog-open="reorder-dialog">Set levels for an item</button>
                    </div>
                </div>
                <div class="panel-body">
                    <div class="list">
                        @forelse ($lowStock as $level)
                            @php
                                $alertUnit = ' '.($level->variant->baseUnit?->code ?? '');
                                $alertLabel = $level->variant->product?->name.($level->variant->variant_name !== 'Default' ? ' / '.$level->variant->variant_name : '');
                            @endphp
                            <div class="item">
                                <div>
                                    <div class="item-title">{{ $alertLabel }}</div>
                                    <div class="subtle">{{ $level->location->name }} · Available {{ \Modules\Inventory\Support\Quantity::format($level->quantity_available) }}{{ $alertUnit }} · Alert at {{ \Modules\Inventory\Support\Quantity::format($level->reorder_level) }}{{ $alertUnit }}</div>
                                </div>
                                <div style="display:flex; gap:8px; align-items:center;">
                                    @if ((float) $level->reorder_quantity > 0)
                                        <span class="badge neutral">Reorder {{ \Modules\Inventory\Support\Quantity::format($level->reorder_quantity) }}{{ $alertUnit }}</span>
                                    @endif
                                    <button class="btn ghost" type="button" onclick="window.__reorderOpen && window.__reorderOpen({{ $level->product_variant_id }}, @js($alertLabel))">Edit</button>
                                </div>
                            </div>
                        @empty
                            <div class="empty">No low-stock alerts. Set reorder levels to start monitoring.</div>
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="panel tab-panel" id="batches" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Expiry, damaged, and returned stock</h2>
                        <p class="subtle">Batch and condition tracking for pharmacies, supermarkets, food, and similar businesses.</p>
                    </div>
                    <a class="btn ghost" href="{{ route('admin.inventory.batches.index', array_filter(['tenant' => request('tenant')])) }}">Trace a lot</a>
                </div>
                <div class="panel-body">
                    <div class="report-grid">
                        <div class="report-card">
                            <h3 class="panel-title">Expiring within 30 days</h3>
                            <div class="list" style="margin-top: 12px;">
                                @forelse ($expiringBatches as $batch)
                                    <div class="item">
                                        <div>
                                            <div class="item-title"><a href="{{ route('admin.inventory.batches.show', array_filter(['batch' => $batch->id, 'tenant' => request('tenant')])) }}">{{ $batch->variant->product?->name }}</a></div>
                                            <div class="subtle">{{ $batch->location->name }} · Batch {{ $batch->batch_number ?: 'N/A' }}</div>
                                        </div>
                                        <span class="badge neutral">{{ $batch->expiry_date->format('M j, Y') }}</span>
                                    </div>
                                @empty
                                    <div class="empty">No near-expiry batches.</div>
                                @endforelse
                            </div>
                        </div>
                        <div class="report-card">
                            <h3 class="panel-title">Tracked conditions</h3>
                            <div class="list" style="margin-top: 12px;">
                                @forelse ($conditionBatches as $batch)
                                    <div class="item">
                                        <div>
                                            <div class="item-title"><a href="{{ route('admin.inventory.batches.show', array_filter(['batch' => $batch->id, 'tenant' => request('tenant')])) }}">{{ $batch->variant->product?->name }}</a></div>
                                            <div class="subtle">{{ $batch->location->name }} · {{ \Modules\Inventory\Support\Quantity::format($batch->quantity_remaining) }} units</div>
                                        </div>
                                        <span class="badge neutral">{{ $batch->stock_condition->label() }}</span>
                                    </div>
                                @empty
                                    <div class="empty">No damaged, returned, expired, or quarantined batches tracked yet.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="panel tab-panel" id="reports" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Inventory reports</h2>
                        <p class="subtle">Operational summaries for branch visibility, valuation, and movement analysis.</p>
                    </div>
                </div>
                <div class="panel-body">
                    <div class="report-grid">
                        @foreach ($stockLevels->groupBy('inventory_location_id') as $locationStock)
                            @php $location = $locationStock->first()->location; @endphp
                            <div class="report-card">
                                <h3 class="panel-title">{{ $location->name }}</h3>
                                <p class="subtle">{{ number_format($locationStock->sum('quantity_on_hand')) }} on hand · {{ $tenant->currency_code }} {{ $money($locationStock->sum(fn ($level) => $level->stock_value_minor)) }} value</p>
                            </div>
                        @endforeach
                    </div>
                    @if ($stockLevels->isEmpty())
                        <div class="empty">Reports will appear once stock exists.</div>
                    @endif
                </div>
            </section>

            <section class="panel tab-panel" id="locations" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Inventory locations</h2>
                        <p class="subtle">Branches are automatically mapped as stock locations. Add warehouses or store rooms here.</p>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button class="btn secondary" type="button" data-dialog-open="location-types-dialog">Manage types</button>
                        <button class="btn accent" type="button" data-dialog-open="location-dialog">Add location</button>
                    </div>
                </div>
                <div class="panel-body">
                    <div class="list">
                        @foreach ($locations as $location)
                            <div class="item">
                                <div>
                                    <div class="item-title">{{ $location->name }} @if ($location->is_sellable_point)<span class="badge success">Sells here</span>@endif @if ($location->is_prep_station)<span class="badge neutral">Prep station</span>@endif</div>
                                    <div class="subtle">
                                        {{ $location->code ? $location->code.' · ' : '' }}{{ $location->branch?->name ? 'Branch: '.$location->branch->name : 'Standalone location' }}
                                    </div>
                                </div>
                                <div style="display:flex; align-items:center; gap:12px;">
                                    <span class="badge neutral">{{ $locationTypes[$location->location_type] ?? \Illuminate\Support\Str::headline($location->location_type) }}</span>
                                    <button class="icon-btn" type="button" aria-label="Edit location" title="Edit location"
                                        data-edit-location
                                        data-id="{{ $location->id }}"
                                        data-name="{{ $location->name }}"
                                        data-code="{{ $location->code }}"
                                        data-type="{{ $location->location_type }}"
                                        data-sellable="{{ $location->is_sellable_point ? '1' : '0' }}"
                                        data-prep="{{ $location->is_prep_station ? '1' : '0' }}">
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if ($hasPrepStations)
                    <div class="panel-body" style="border-top: 1px solid var(--line); padding-top: 14px;">
                        <p class="subtle" style="margin:0;">
                            <strong>Prep stations</strong> are locations flagged “Food is made here” — Grill, Main Kitchen,
                            Bar. A station is where food is <em>made</em>; the same row is the store its ingredients come
                            from, so the two can never disagree. Set one on a product and its orders print to that
                            station's kitchen screen.
                            @if ($prepStations->isEmpty())
                                <br><em>None yet — edit a location above and tick “Food is made here”.</em>
                            @else
                                <br>Current stations: {{ $prepStations->pluck('name')->implode(', ') }}.
                            @endif
                        </p>
                    </div>
                @endif
            </section>
        </div>
    </div>

    @include('inventory::admin.partials.movement-dialog')
    @include('inventory::admin.partials.transfer-dialog')
    @include('inventory::admin.partials.reorder-dialog', [
        'reorderLocations' => $locations,
        'reorderPicker' => true,
        'reorderFragment' => 'alerts',
    ])
    @include('inventory::admin.partials.location-dialog')
    @include('inventory::admin.partials.location-edit-dialog')
    @include('inventory::admin.partials.location-types-dialog')

    <script>
        (function () {
            const dialog = document.getElementById('location-edit-dialog');
            if (! dialog) return;
            const form = dialog.querySelector('form');
            const base = form.getAttribute('data-action-base');
            document.querySelectorAll('[data-edit-location]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    form.action = base.replace('__ID__', btn.dataset.id);
                    form.querySelector('[name="name"]').value = btn.dataset.name || '';
                    form.querySelector('[name="code"]').value = btn.dataset.code || '';
                    const typeSelect = form.querySelector('[name="location_type"]');
                    if (typeSelect) typeSelect.value = btn.dataset.type || '';
                    form.querySelector('[name="is_sellable_point"][type="checkbox"]').checked = btn.dataset.sellable === '1';
                    form.querySelector('[name="is_prep_station"][type="checkbox"]').checked = btn.dataset.prep === '1';
                    if (typeof dialog.showModal === 'function') dialog.showModal(); else dialog.setAttribute('open', '');
                });
            });
        })();
    </script>

    <script>
        (function () {
            const VARIANT_UNITS = @json($variantUnits ?? []);
            function refresh(picker) {
                if (! picker) return;
                const dialog = picker.closest('dialog');
                if (! dialog) return;
                const unitSel = dialog.querySelector('[data-movement-unit]');
                const field = dialog.querySelector('[data-measurement-field]');
                const hint = dialog.querySelector('[data-measurement-hint]');
                if (! unitSel || ! field) return;
                const variantId = picker.querySelector('[data-variant-value]')?.value;
                const units = VARIANT_UNITS[variantId] || [];
                if (units.length) {
                    unitSel.innerHTML = units.map((u) => `<option value="${u.id}">${u.code}</option>`).join('');
                    field.hidden = false;
                    if (hint) hint.hidden = false;
                } else {
                    unitSel.innerHTML = '';
                    field.hidden = true;
                    if (hint) hint.hidden = true;
                }
            }
            // Defer on input so the layout's handler has set [data-variant-value] first;
            // also handle change (fired when an item is chosen from the results).
            document.addEventListener('input', function (e) {
                const search = e.target.closest('[data-variant-search]');
                if (search) setTimeout(function () { refresh(search.closest('[data-variant-picker]')); }, 0);
            });
            document.addEventListener('change', function (e) {
                const search = e.target.closest('[data-variant-search]');
                if (search) refresh(search.closest('[data-variant-picker]'));
            });
        })();
    </script>
</x-layouts.admin>
