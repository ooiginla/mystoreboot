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
        /* Quantity and its unit read as one control: [ 25 | kg ▾ ]. */
        .qty-unit { display: flex; align-items: stretch; }
        .qty-unit input { flex: 1; min-width: 0; border-top-right-radius: 0; border-bottom-right-radius: 0; }
        .qty-unit select { flex: 0 0 auto; width: auto; min-width: 88px; margin-left: -1px; border-top-left-radius: 0; border-bottom-left-radius: 0; background-color: #f9fafb; font-weight: 700; }
        .qty-unit select:disabled { color: #344054; opacity: 1; cursor: default; }
        .movement-lines-panel { margin-top: 18px; border: 1px solid var(--line); border-radius: 10px; overflow: hidden; }
        .movement-lines-header, .movement-grand-total { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 14px; background: #f8fafc; }
        .movement-summary-head, .movement-summary-row { display: grid; grid-template-columns: 38px minmax(110px, .75fr) minmax(220px, 1.5fr) minmax(130px, .8fr) minmax(150px, .8fr); gap: 12px; align-items: center; padding: 11px 14px; }
        .movement-summary-head { border-top: 1px solid var(--line); background: #fbfcfd; color: var(--muted); font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }
        .movement-summary-row { border-top: 1px solid var(--line); }
        .movement-summary-row:first-child { border-top: 0; }
        .movement-summary-remove { width: 32px; height: 32px; display: grid; place-items: center; padding: 0; border: 1px solid var(--danger-border); border-radius: 8px; background: #fff; color: var(--danger); cursor: pointer; font-size: 20px; line-height: 1; }
        .movement-summary-remove:hover { color: #fff; background: var(--danger); }
        .movement-summary-cell { min-width: 0; overflow-wrap: anywhere; }
        .movement-summary-cell strong, .movement-summary-cell span { display: block; }
        .movement-line-cost { min-height: 42px; display: flex; flex-direction: column; justify-content: center; }
        .movement-grand-total { border-top: 1px solid var(--line); font-size: 1.05rem; }
        .movement-item-dialog { width: min(1040px, calc(100vw - 32px)); max-width: 1040px; }
        @media (max-width: 900px) {
            .report-grid { grid-template-columns: 1fr; }
            .inventory-actions { width: 100%; }
            .inventory-actions .btn { flex: 1; }
            .stock-visibility-filters { width: 100%; }
            .stock-visibility-filter-field { flex: 1; min-width: 180px; }
            .stock-visibility-filter-field input, .stock-visibility-filter-field select { min-width: 0; }
            .movement-summary-head { display: none; }
            .movement-summary-row { grid-template-columns: 36px 1fr; align-items: start; }
            .movement-summary-cell { grid-column: 2; }
            .movement-summary-remove { grid-row: 1 / span 4; }
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
                                    <td>{{ ($level->variant->baseUnit?->code ?? 'ea') === 'ea' ? 'pc' : $level->variant->baseUnit->code }}</td>
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
                                <th>Quantity</th>
                                <th>Cost</th>
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
                                    @php
                                        $enteredQuantity = $movement->entered_quantity !== null
                                            ? abs((float) $movement->entered_quantity)
                                            : abs((float) $movement->quantity);
                                        $enteredUnitCode = $movement->entered_unit_code
                                            ?: $movement->enteredUnit?->code
                                            ?: $movement->variant?->baseUnit?->code
                                            ?: 'pc';
                                        $baseUnitCode = $movement->variant?->baseUnit?->code ?? 'pc';
                                    @endphp
                                    <td class="movement-note">
                                        <strong>{{ \Modules\Inventory\Support\Quantity::format($enteredQuantity) }} {{ $enteredUnitCode }}</strong>
                                        <br><span class="subtle">
                                            {{ \Modules\Inventory\Support\Quantity::format(abs((float) $movement->quantity)) }} {{ $baseUnitCode }} in base unit
                                        </span>
                                        @if ($movement->batchAllocations->isNotEmpty())
                                            <br><span class="subtle">Lots:
                                                {{ $movement->batchAllocations->map(fn ($a) => ($a->batch?->batch_number ?: 'no batch no.')
                                                    .($a->batch?->expiry_date ? ' exp '.$a->batch->expiry_date->format('d M Y') : '')
                                                    .' ('.\Modules\Inventory\Support\Quantity::format($a->quantity).')')->implode(', ') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="movement-note">
                                        <strong>{{ $tenant->currency_code }} {{ $money($movement->movement_value_minor) }}</strong>
                                        <br><span class="subtle">{{ $tenant->currency_code }} {{ $money($movement->unit_cost_minor) }}/{{ $baseUnitCode }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><div class="empty">No inventory movements yet.</div></td></tr>
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
    @include('inventory::admin.partials.movement-item-dialog')
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
            const UNITS = @json($reorderUnits ?? []);
            const LEVELS = @json($reorderLevels ?? []);
            const CURRENCY = @json($tenant->currency_code);
            const editor = document.getElementById('movement-item-dialog');
            const editorForm = editor?.querySelector('[data-item-editor-form]');
            const editorOptions = document.getElementById('movement-item-variant-options');
            const allEditorOptions = Array.from(editorOptions?.options || []).map((option) => option.cloneNode(true));
            const fmt = (n) => (Math.round(n * 10000) / 10000).toLocaleString(undefined, { maximumFractionDigits: 4 });
            const money = (n) => `${CURRENCY} ${Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            const numberValue = (value) => parseFloat(String(value || '').replace(/,/g, '')) || 0;

            if (!editor || !editorForm || !editorOptions) return;

            function ownerDialog() {
                return document.getElementById(editor.dataset.ownerDialog || '');
            }

            function parts() {
                const owner = ownerDialog();
                const picker = editor.querySelector('[data-variant-picker]');
                const variantId = picker?.querySelector('[data-variant-value]')?.value || '';

                return {
                    owner,
                    variantId,
                    units: variantId ? (UNITS[variantId] || []) : [],
                    picker,
                    search: picker?.querySelector('[data-variant-search]'),
                    variantValue: picker?.querySelector('[data-variant-value]'),
                    unit: editor.querySelector('[data-movement-unit]'),
                    qty: editor.querySelector('[data-qty-input]'),
                    hint: editor.querySelector('[data-measurement-hint]'),
                    type: editor.querySelector('[data-movement-type]'),
                    totalCost: editor.querySelector('[data-movement-total-cost]'),
                    costHelp: editor.querySelector('[data-movement-total-cost-help]'),
                    lineTotal: editor.querySelector('[data-line-total]'),
                    lineUnitCost: editor.querySelector('[data-line-unit-cost]'),
                    location: owner?.querySelector('select[name="inventory_location_id"]'),
                };
            }

            function loadUnits() {
                const p = parts();
                if (!p.unit) return;

                if (!p.units.length) {
                    p.unit.innerHTML = '<option value="">—</option>';
                    p.unit.disabled = true;
                } else {
                    p.unit.innerHTML = p.units.map((u) =>
                        `<option value="${u.id ?? ''}" data-factor="${u.factor}" data-code="${u.code}">${u.code}</option>`
                    ).join('');
                    const base = p.units.findIndex((u) => u.factor === 1);
                    p.unit.selectedIndex = base >= 0 ? base : 0;
                    // A plain count has nothing to choose and nothing to convert.
                    p.unit.disabled = p.units.length === 1 && p.units[0].id === null;
                }

                updateEditor();
            }

            function updateEditor() {
                const p = parts();
                if (!p.hint || !p.unit) return;

                const mode = editor.dataset.itemMode || 'movement';
                const movementType = mode === 'transfer' ? 'transfer_out' : p.type?.value;
                const acceptsTotal = mode === 'movement' && ['opening_stock', 'stock_in'].includes(movementType);
                p.totalCost.disabled = !acceptsTotal;
                p.totalCost.required = acceptsTotal;
                if (!acceptsTotal) p.totalCost.value = '';

                if (!p.variantId || !p.units.length) {
                    p.hint.textContent = 'Choose an item to see how it is measured.';
                    p.costHelp.textContent = acceptsTotal ? 'Enter the total paid for this entire line.' : 'Choose an item to calculate its value.';
                    p.lineTotal.textContent = money(0);
                    p.lineUnitCost.textContent = 'Choose an item';
                    return;
                }

                const option = p.unit.selectedOptions[0];
                const factor = parseFloat(option?.dataset.factor) || 1;
                const code = option?.dataset.code || '';
                const base = p.units.find((u) => u.factor === 1) || p.units[0];
                const qty = parseFloat(p.qty?.value);
                const bits = [];

                if (p.units.length === 1 && p.units[0].id === null) {
                    bits.push(`Counted in ${code}`);
                } else if (factor !== 1) {
                    bits.push(qty > 0
                        ? `${fmt(qty)} ${code} = ${fmt(qty * factor)} ${base.code} in stock`
                        : `Stock is kept in ${base.code} — ${code} is converted automatically`);
                } else {
                    bits.push(`Stock is kept in ${code}`);
                }

                if (p.location?.value) {
                    const level = (LEVELS[p.variantId] || {})[p.location.value];
                    const place = p.location.selectedOptions[0]?.textContent.trim() || 'this location';
                    bits.push(`${fmt((level ? level.available : 0) / factor)} ${code} available at ${place}`);
                }

                p.hint.textContent = bits.join(' · ');
                const level = p.location?.value ? (LEVELS[p.variantId] || {})[p.location.value] : null;
                const averageMinor = level?.average_cost_minor || 0;
                const calculatedTotal = (qty || 0) * factor * averageMinor / 100;
                p.costHelp.textContent = acceptsTotal
                    ? 'Enter the total paid for this entire line.'
                    : `${money(averageMinor / 100)}/${base.code} · ${money(calculatedTotal)} line value`;
                p.lineTotal.textContent = money(calculatedTotal);
                p.lineUnitCost.textContent = `${money(averageMinor / 100)}/${base.code}`;
            }

            function updateGrandTotal(dialog) {
                if (!dialog) return;
                const target = dialog.querySelector('[data-movement-grand-total]');
                if (!target) return;
                const total = Array.from(dialog.querySelectorAll('[data-movement-summary-row]'))
                    .reduce((sum, row) => sum + numberValue(row.dataset.lineValue), 0);
                target.textContent = money(total);
            }

            function reindex(dialog) {
                dialog.querySelectorAll('[data-movement-summary-row]').forEach((row, index) => {
                    row.querySelectorAll('[data-item-field]').forEach((field) => {
                        field.name = `items[${index}][${field.dataset.itemField}]`;
                    });
                });
            }

            function syncEmptyState(dialog) {
                const list = dialog.querySelector('[data-movement-lines]');
                const empty = list?.querySelector('[data-movement-lines-empty]');
                if (empty) empty.hidden = Boolean(list.querySelector('[data-movement-summary-row]'));
            }

            function hiddenField(key, value) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.dataset.itemField = key;
                input.value = value ?? '';
                return input;
            }

            function summaryCell(primary, secondary = '') {
                const cell = document.createElement('div');
                cell.className = 'movement-summary-cell';
                const strong = document.createElement('strong');
                strong.textContent = primary;
                cell.appendChild(strong);
                if (secondary) {
                    const subtle = document.createElement('span');
                    subtle.className = 'subtle';
                    subtle.textContent = secondary;
                    cell.appendChild(subtle);
                }

                return cell;
            }

            function appendSummary(dialog, item) {
                const list = dialog.querySelector('[data-movement-lines]');
                const row = document.createElement('div');
                row.className = 'movement-summary-row';
                row.dataset.movementSummaryRow = '';
                Object.entries(item.dataset).forEach(([key, value]) => { row.dataset[key] = String(value ?? ''); });

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'movement-summary-remove';
                remove.dataset.removeMovementLine = '';
                remove.setAttribute('aria-label', `Remove ${item.itemLabel}`);
                remove.textContent = '×';
                row.append(
                    remove,
                    summaryCell(item.typeLabel),
                    summaryCell(item.itemLabel, item.lotLabel),
                    summaryCell(`${fmt(item.enteredQuantity)} ${item.unitCode}`, item.baseQuantity !== item.enteredQuantity || item.unitCode !== item.baseCode ? `${fmt(item.baseQuantity)} ${item.baseCode} in base unit` : ''),
                    summaryCell(money(item.lineValue), `${money(item.unitCostMinor / 100)}/${item.baseCode}`),
                );

                Object.entries(item.fields).forEach(([key, value]) => {
                    if (value !== null && value !== '') row.appendChild(hiddenField(key, value));
                });
                list.appendChild(row);
                reindex(dialog);
                syncEmptyState(dialog);
                updateGrandTotal(dialog);
            }

            function refreshSummaries(dialog) {
                const locationId = dialog.querySelector('select[name="inventory_location_id"]')?.value;
                dialog.querySelectorAll('[data-movement-summary-row]').forEach((row) => {
                    if (row.dataset.usesAverage !== '1') return;
                    const level = (LEVELS[row.dataset.variantId] || {})[locationId] || null;
                    const averageMinor = level?.average_cost_minor || 0;
                    const value = numberValue(row.dataset.baseQuantity) * averageMinor / 100;
                    row.dataset.lineValue = String(value);
                    row.dataset.unitCostMinor = String(averageMinor);
                    const cost = row.querySelectorAll('.movement-summary-cell')[3];
                    if (cost) {
                        cost.querySelector('strong').textContent = money(value);
                        cost.querySelector('.subtle').textContent = `${money(averageMinor / 100)}/${row.dataset.baseCode}`;
                    }
                });
                updateGrandTotal(dialog);
            }

            function applyItemFilter() {
                const filter = editor.querySelector('[data-item-type-filter]').value;
                editorOptions.replaceChildren(...allEditorOptions
                    .filter((option) => !filter || option.dataset.type === filter)
                    .map((option) => option.cloneNode(true)));
            }

            function clearEditor() {
                editorForm.reset();
                const p = parts();
                p.search.value = '';
                p.search.setCustomValidity('');
                p.variantValue.value = '';
                p.unit.innerHTML = '<option value="">—</option>';
                p.unit.disabled = true;
                applyItemFilter();
                updateEditor();
            }

            function openDialog(dialog) {
                if (window.sbOpenDialog) window.sbOpenDialog(dialog);
                else if (typeof dialog.showModal === 'function') dialog.showModal();
                else dialog.setAttribute('open', '');
            }

            function closeDialog(dialog) {
                if (window.sbCloseDialog) window.sbCloseDialog(dialog);
                else if (dialog.open && typeof dialog.close === 'function') dialog.close();
                else dialog.removeAttribute('open');
            }

            function returnToOwner() {
                const owner = ownerDialog();
                closeDialog(editor);
                if (owner) setTimeout(() => openDialog(owner), 50);
            }

            document.addEventListener('click', (event) => {
                const opener = event.target.closest('[data-open-item-editor]');
                if (opener) {
                    const owner = document.getElementById(opener.dataset.ownerDialog);
                    editor.dataset.ownerDialog = opener.dataset.ownerDialog;
                    editor.dataset.itemMode = opener.dataset.itemMode;
                    editor.querySelector('[data-item-editor-title]').textContent = opener.dataset.itemMode === 'transfer' ? 'Add transfer item' : 'Add movement item';
                    editor.querySelector('[data-item-movement-type-field]').hidden = opener.dataset.itemMode === 'transfer';
                    editor.querySelector('[data-item-total-cost-field]').hidden = opener.dataset.itemMode === 'transfer';
                    editor.querySelector('[data-item-transfer-cost-field]').hidden = opener.dataset.itemMode !== 'transfer';
                    editor.querySelector('[data-item-batch-field]').hidden = opener.dataset.itemMode === 'transfer';
                    editor.querySelector('[data-item-expiry-field]').hidden = opener.dataset.itemMode === 'transfer';
                    clearEditor();
                    closeDialog(owner);
                    setTimeout(() => openDialog(editor), 50);
                    return;
                }

                if (event.target.closest('[data-item-editor-cancel]')) {
                    returnToOwner();
                    return;
                }

                const remove = event.target.closest('[data-remove-movement-line]');
                if (remove) {
                    const row = remove.closest('[data-movement-summary-row]');
                    const dialog = row.closest('#movement-dialog, #transfer-dialog');
                    row.remove();
                    reindex(dialog);
                    syncEmptyState(dialog);
                    updateGrandTotal(dialog);
                }
            });

            editor.querySelector('[data-item-type-filter]').addEventListener('change', () => {
                const p = parts();
                applyItemFilter();
                p.search.value = '';
                p.variantValue.value = '';
                loadUnits();
            });

            editor.addEventListener('input', (event) => {
                if (event.target.closest('[data-variant-search]')) setTimeout(loadUnits, 0);
                else if (event.target.matches('[data-qty-input], [data-movement-total-cost]')) updateEditor();
            });
            editor.addEventListener('change', (event) => {
                if (event.target.closest('[data-variant-search]')) loadUnits();
                else if (event.target.matches('[data-movement-unit], [data-movement-type]')) updateEditor();
            });

            document.querySelectorAll('#movement-dialog select[name="inventory_location_id"], #transfer-dialog select[name="inventory_location_id"]').forEach((select) => {
                select.addEventListener('change', () => refreshSummaries(select.closest('dialog')));
            });

            document.querySelectorAll('#movement-dialog form, #transfer-dialog form').forEach((form) => {
                form.addEventListener('submit', (event) => {
                    const dialog = form.closest('dialog');
                    if (dialog.querySelector('[data-movement-summary-row]')) return;
                    event.preventDefault();
                    const empty = dialog.querySelector('[data-movement-lines-empty]');
                    empty.textContent = 'Add at least one item before continuing.';
                    dialog.querySelector('[data-open-item-editor]')?.focus();
                });
            });

            editorForm.addEventListener('submit', (event) => {
                event.preventDefault();
                const p = parts();
                const mode = editor.dataset.itemMode;
                const movementType = mode === 'transfer' ? 'transfer_out' : p.type.value;
                const acceptsTotal = mode === 'movement' && ['opening_stock', 'stock_in'].includes(movementType);
                const enteredQuantity = numberValue(p.qty.value);
                const selectedUnit = p.unit.selectedOptions[0];
                const factor = numberValue(selectedUnit?.dataset.factor) || 1;
                const base = p.units.find((unit) => unit.factor === 1) || p.units[0];
                const locationId = p.location?.value;
                const level = (LEVELS[p.variantId] || {})[locationId] || null;
                const averageMinor = level?.average_cost_minor || 0;

                if (!editorForm.reportValidity() || !p.variantId || !base) return;

                const baseQuantity = enteredQuantity * factor;
                const enteredTotal = acceptsTotal ? numberValue(p.totalCost.value) : null;
                const unitCostMinor = acceptsTotal ? Math.round(enteredTotal * 100 / baseQuantity) : averageMinor;
                const lineValue = acceptsTotal ? enteredTotal : baseQuantity * averageMinor / 100;
                const batch = editor.querySelector('[data-item-batch-number]').value.trim();
                const expiry = editor.querySelector('[data-item-expiry-date]').value;
                const lotLabel = [batch ? `Batch ${batch}` : '', expiry ? `Expires ${expiry}` : ''].filter(Boolean).join(' · ');

                appendSummary(p.owner, {
                    typeLabel: mode === 'transfer' ? 'Transfer' : p.type.selectedOptions[0].textContent.trim(),
                    itemLabel: p.search.value,
                    lotLabel,
                    enteredQuantity,
                    unitCode: selectedUnit?.dataset.code || base.code,
                    baseQuantity,
                    baseCode: base.code,
                    lineValue,
                    unitCostMinor,
                    dataset: {
                        variantId: p.variantId,
                        baseQuantity,
                        baseCode: base.code,
                        lineValue,
                        unitCostMinor,
                        usesAverage: acceptsTotal ? 0 : 1,
                    },
                    fields: {
                        movement_type: movementType,
                        product_variant_id: p.variantId,
                        quantity: enteredQuantity,
                        unit_id: selectedUnit?.value || null,
                        total_cost: acceptsTotal ? enteredTotal : null,
                        batch_number: mode === 'movement' ? batch : null,
                        expiry_date: mode === 'movement' ? expiry : null,
                    },
                });
                returnToOwner();
            });
        })();
    </script>
</x-layouts.admin>
