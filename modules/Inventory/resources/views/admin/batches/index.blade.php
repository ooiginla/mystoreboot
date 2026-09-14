@php
    $qty = fn ($value): string => \Modules\Inventory\Support\Quantity::format($value ?? 0);
    $tenantParam = request('tenant');
    $traceUrl = fn ($batch): string => route('admin.inventory.batches.show', array_filter(['batch' => $batch->id, 'tenant' => $tenantParam]));
@endphp

<x-layouts.admin title="Lot Traceability">
    <style>
        .lot-filters { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; margin-bottom: 16px; }
        .lot-filters .field { margin: 0; }
        .lot-filters input[type="search"] { min-width: 240px; }
        .lot-expired { color: #b42318; }
        .lot-soon { color: #b54708; }
    </style>

    <div class="topbar">
        <div>
            <div class="eyebrow">Inventory control</div>
            <h1>Lot traceability</h1>
            <p class="subtle">Find a batch by number, item, or SKU and follow where its stock went — including lots already used up.</p>
        </div>
        @if ($isPlatformAdmin)
            <form method="GET" action="{{ route('admin.inventory.batches.index') }}" style="min-width: 260px;">
                <select name="tenant" onchange="this.form.submit()">
                    @foreach ($tenants as $visibleTenant)
                        <option value="{{ $visibleTenant->id }}" @selected($visibleTenant->id === $tenant->id)>{{ $visibleTenant->name }}</option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    <form class="lot-filters" method="GET" action="{{ route('admin.inventory.batches.index') }}">
        <input type="hidden" name="tenant" value="{{ $tenantParam }}">
        <div class="field">
            <label>Search</label>
            <input type="search" name="q" value="{{ $search }}" placeholder="Batch number, item name, or SKU">
        </div>
        <div class="field">
            <label>Location</label>
            <select name="location">
                <option value="">All locations</option>
                @foreach ($locations as $location)
                    <option value="{{ $location->id }}" @selected($selectedLocationId === $location->id)>{{ $location->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Show</label>
            <select name="show">
                <option value="" @selected($filter === '')>All lots</option>
                <option value="in-stock" @selected($filter === 'in-stock')>Still in stock</option>
                <option value="expiring" @selected($filter === 'expiring')>Expiring within 30 days</option>
                <option value="exhausted" @selected($filter === 'exhausted')>Used up</option>
            </select>
        </div>
        <button class="btn primary" type="submit">Search</button>
        @if ($search !== '' || $selectedLocationId || $filter !== '')
            <a class="btn secondary" href="{{ route('admin.inventory.batches.index', array_filter(['tenant' => $tenantParam])) }}">Clear</a>
        @endif
    </form>

    <section class="panel">
        <div class="panel-header">
            <h2 class="panel-title">Lots</h2>
            <p class="subtle">{{ $batches->count() }} shown{{ $batches->count() >= 200 ? ' (first 200 — narrow your search)' : '' }}</p>
        </div>
        <div class="panel-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>Batch</th>
                        <th>Item</th>
                        <th>Location</th>
                        <th>Expiry</th>
                        <th>Condition</th>
                        <th>Received</th>
                        <th>Used</th>
                        <th>Remaining</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($batches as $batch)
                        <tr>
                            <td><strong>{{ $batch->batch_number ?: 'No batch no.' }}</strong></td>
                            <td>
                                {{ $batch->variant?->product?->name }}<br>
                                <span class="subtle">{{ $batch->variant?->sku }}</span>
                            </td>
                            <td>{{ $batch->location?->name }}</td>
                            <td>
                                @if ($batch->expiry_date)
                                    <span class="{{ $batch->isExpired() ? 'lot-expired' : ($batch->expiry_date->lte(now()->addDays(30)) ? 'lot-soon' : '') }}">
                                        {{ $batch->expiry_date->format('d M Y') }}
                                    </span>
                                    @if ($batch->isExpired() && ! $batch->isExhausted())<br><span class="subtle lot-expired">expired, still in stock</span>@endif
                                @else
                                    <span class="subtle">—</span>
                                @endif
                            </td>
                            <td><span class="badge {{ $batch->stock_condition === \Modules\Inventory\Enums\StockCondition::Sellable ? 'success' : 'neutral' }}">{{ $batch->stock_condition->label() }}</span></td>
                            <td>{{ $qty($batch->receivedQuantity()) }}</td>
                            <td>{{ $qty($batch->consumedQuantity()) }}</td>
                            <td>
                                @if ($batch->isExhausted())
                                    <span class="subtle">used up</span>
                                @else
                                    {{ $qty($batch->quantity_remaining) }}
                                @endif
                            </td>
                            <td><a class="btn ghost" href="{{ $traceUrl($batch) }}">Trace</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9">
                            @if ($search !== '' || $selectedLocationId || $filter !== '')
                                <div class="empty">No lots match this search. <a href="{{ route('admin.inventory.batches.index', array_filter(['tenant' => $tenantParam])) }}">Clear the filters</a> to see every lot.</div>
                            @else
                                <div class="empty" style="text-align: left; padding: 18px 20px;">
                                    <strong>No lots recorded yet.</strong>
                                    <p class="subtle" style="margin: 8px 0 12px;">
                                        A lot is created when stock arrives <em>with a batch number or an expiry date</em>. Stock received
                                        without either is still tracked as a quantity — it just cannot be traced back to a specific batch.
                                        There are three ways to start one:
                                    </p>
                                    <ol class="subtle" style="margin: 0 0 14px; padding-left: 20px; line-height: 1.9;">
                                        <li><strong>Post a stock movement</strong> — fill the <em>Batch number</em> or <em>Expiry date</em> field under “Batch &amp; expiry tracking”.</li>
                                        <li><strong>Receive a purchase order</strong> — each line takes its own batch number and expiry.</li>
                                        <li><strong>Record production</strong> — every batch you cook is lotted automatically, and gets an expiry date if the recipe has a shelf life.</li>
                                    </ol>
                                    <a class="btn primary" href="{{ route('admin.inventory.index', array_filter(['tenant' => $tenantParam])) }}">Go to Inventory &amp; Stock</a>
                                </div>
                            @endif
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.admin>
