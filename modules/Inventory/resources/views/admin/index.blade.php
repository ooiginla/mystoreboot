@php
    $money = fn (?int $minor): string => number_format(($minor ?? 0) / 100, 2);
    $variantLabel = fn ($variant): string => $variant->product?->name.' / '.$variant->variant_name.' ('.$variant->sku.')';
@endphp

<x-layouts.admin title="Inventory & Stock">
    <datalist id="variant-options">
        @foreach ($variants as $variant)
            <option value="{{ $variantLabel($variant) }}" data-variant-id="{{ $variant->id }}"></option>
        @endforeach
    </datalist>

    <style>
        .inventory-toolbar { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
        .inventory-actions { display: flex; gap: 10px; flex-wrap: wrap; }
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
                                <th>On hand</th>
                                <th>Available</th>
                                <th>Reorder</th>
                                <th>Avg cost</th>
                                <th>Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($stockLevels as $level)
                                <tr>
                                    <td>
                                        <strong>{{ $level->variant->product?->name }}</strong><br>
                                        <span class="subtle">{{ $level->variant->variant_name }} · {{ $level->variant->sku }}</span>
                                    </td>
                                    <td>{{ $level->location->name }}</td>
                                    <td class="stock-status {{ $level->is_low_stock ? 'low' : 'ok' }}">{{ number_format($level->quantity_on_hand) }}</td>
                                    <td>{{ number_format($level->quantity_available) }}</td>
                                    <td>{{ number_format($level->reorder_level) }}</td>
                                    <td>{{ $tenant->currency_code }} {{ $money($level->average_cost_minor) }}</td>
                                    <td>{{ $tenant->currency_code }} {{ $money($level->stock_value_minor) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7"><div class="empty">No stock levels yet. Post stock-in or opening stock to begin tracking inventory.</div></td></tr>
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
                                    <td>{{ number_format($movement->quantity) }}</td>
                                    <td>{{ $tenant->currency_code }} {{ $money($movement->unit_cost_minor) }}</td>
                                    <td class="movement-note">{{ $movement->reference_number ?: $movement->notes ?: 'Not set' }}</td>
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
                        <p class="subtle">Set reorder levels per branch/location and variant.</p>
                    </div>
                    <button class="btn accent" type="button" data-dialog-open="reorder-dialog">Set reorder level</button>
                </div>
                <div class="panel-body">
                    <div class="list">
                        @forelse ($lowStock as $level)
                            <div class="item">
                                <div>
                                    <div class="item-title">{{ $level->variant->product?->name }} / {{ $level->variant->variant_name }}</div>
                                    <div class="subtle">{{ $level->location->name }} · Available {{ number_format($level->quantity_available) }} · Reorder at {{ number_format($level->reorder_level) }}</div>
                                </div>
                                <span class="badge neutral">Reorder {{ number_format($level->reorder_quantity) }}</span>
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
                </div>
                <div class="panel-body">
                    <div class="report-grid">
                        <div class="report-card">
                            <h3 class="panel-title">Expiring within 30 days</h3>
                            <div class="list" style="margin-top: 12px;">
                                @forelse ($expiringBatches as $batch)
                                    <div class="item">
                                        <div>
                                            <div class="item-title">{{ $batch->variant->product?->name }}</div>
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
                                            <div class="item-title">{{ $batch->variant->product?->name }}</div>
                                            <div class="subtle">{{ $batch->location->name }} · {{ number_format($batch->quantity_remaining) }} units</div>
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
                            @php($location = $locationStock->first()->location)
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
                    <button class="btn accent" type="button" data-dialog-open="location-dialog">Add location</button>
                </div>
                <div class="panel-body">
                    <div class="list">
                        @foreach ($locations as $location)
                            <div class="item">
                                <div>
                                    <div class="item-title">{{ $location->name }}</div>
                                    <div class="subtle">{{ $location->branch?->name ? 'Branch: '.$location->branch->name : 'Standalone location' }}</div>
                                </div>
                                <span class="badge neutral">{{ $location->location_type->label() }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        </div>
    </div>

    @include('inventory::admin.partials.movement-dialog')
    @include('inventory::admin.partials.transfer-dialog')
    @include('inventory::admin.partials.reorder-dialog')
    @include('inventory::admin.partials.location-dialog')
</x-layouts.admin>
