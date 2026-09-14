@php
    // Plain number for <input type="number"> — no thousands separators, no trailing zeros.
    $num = fn (float $value): string => rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    $qty = fn ($value): string => \Modules\Inventory\Support\Quantity::format($value ?? 0);
    $tenantParam = request('tenant');
    $statusLabels = ['low' => 'Low', 'ok' => 'OK', 'unset' => 'Not monitored'];
@endphp

<x-layouts.admin title="Reorder levels">
    <style>
        .reorder-filters { display: flex; gap: 10px; flex-wrap: wrap; align-items: end; }
        .reorder-filters .field { display: grid; gap: 4px; min-width: 170px; margin: 0; }
        .reorder-filters input[type="search"] { min-width: 220px; }
        .reorder-table td { vertical-align: middle; }
        .reorder-table input[type="number"] { width: 120px; }
        .reorder-table select { width: 96px; }
        .reorder-table tr.is-changed td { background: #eff8ff; }
        .reorder-item { font-weight: 700; }
        .reorder-chip { display: inline-block; padding: 3px 10px; border-radius: 999px; font-weight: 800; font-size: 12px; white-space: nowrap; }
        .reorder-chip.low { background: #fef0c7; color: #b54708; }
        .reorder-chip.ok { background: #dcfae6; color: #067647; }
        .reorder-chip.unset { background: #f2f4f7; color: #475467; }
        .reorder-savebar { position: sticky; bottom: 0; display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 16px; margin-top: 12px; background: #fff; border: 1px solid var(--line); border-radius: 8px; }
    </style>

    <div class="topbar">
        <div>
            <div class="eyebrow">Inventory &amp; stock</div>
            <h1>Reorder levels</h1>
            <p class="subtle">Set when each item counts as low stock at a location. Type numbers in any unit — they are converted to the item's base unit when saved.</p>
        </div>
        <div style="display:flex; gap:10px; align-items:center;">
            @if ($isPlatformAdmin)
                <form method="GET" action="{{ route('admin.inventory.reorder-levels.index') }}" style="min-width: 240px;">
                    <select name="tenant" onchange="this.form.submit()">
                        @foreach ($tenants as $visibleTenant)
                            <option value="{{ $visibleTenant->id }}" @selected($visibleTenant->id === $tenant->id)>{{ $visibleTenant->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
            <a class="btn ghost" href="{{ route('admin.inventory.index', array_filter(['tenant' => $tenantParam])) }}#alerts">Back to alerts</a>
        </div>
    </div>

    @if (session('status'))<div class="alert success">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="alert errors">{{ $errors->first() }}</div>@endif

    <section class="panel">
        <div class="panel-header">
            <form class="reorder-filters" method="GET" action="{{ route('admin.inventory.reorder-levels.index') }}">
                @if ($tenantParam)<input type="hidden" name="tenant" value="{{ $tenantParam }}">@endif
                <div class="field">
                    <label for="reorder-location">Location</label>
                    <select id="reorder-location" name="location" onchange="this.form.submit()">
                        @foreach ($locations as $option)
                            <option value="{{ $option->id }}" @selected($location?->id === $option->id)>{{ $option->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="reorder-type">Items</label>
                    <select id="reorder-type" name="type" onchange="this.form.submit()">
                        <option value="">All items</option>
                        @foreach ($typeOptions as $value => $label)
                            <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="reorder-status">Status</label>
                    <select id="reorder-status" name="status" onchange="this.form.submit()">
                        <option value="">Any status</option>
                        <option value="low" @selected($status === 'low')>Low only</option>
                        <option value="monitored" @selected($status === 'monitored')>Monitored</option>
                        <option value="unset" @selected($status === 'unset')>Not monitored</option>
                    </select>
                </div>
                <div class="field">
                    <label for="reorder-search">Search</label>
                    <input id="reorder-search" type="search" name="q" value="{{ $search }}" placeholder="Name or SKU">
                </div>
                <button class="btn secondary" type="submit">Filter</button>
            </form>
            @if ($location)
                <span class="reorder-chip {{ $lowCount > 0 ? 'low' : 'ok' }}">{{ $lowCount }} low at {{ $location->name }}</span>
            @endif
        </div>

        <div class="panel-body">
            @if (! $location)
                <div class="empty">Create an inventory location first.</div>
            @else
                <form method="POST" action="{{ route('admin.inventory.reorder.save') }}" data-bulk-reorder>
                    @csrf
                    <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                    <div style="overflow-x:auto;">
                        <table class="table reorder-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Type</th>
                                    <th>Available now</th>
                                    <th>Unit</th>
                                    <th>Reorder at</th>
                                    <th>Reorder quantity</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $i => $row)
                                    @php
                                        $variant = $row['variant'];
                                        $unit = collect($row['units'])->first(fn (array $u): bool => $u['factor'] == 1.0) ?? $row['units'][0];
                                    @endphp
                                    <tr data-reorder-row data-level="{{ $row['level'] }}" data-qty="{{ $row['qty'] }}" data-available="{{ $row['available'] }}" data-factor="{{ $unit['factor'] }}">
                                        <td>
                                            <div class="reorder-item">{{ $variant->product?->name }}</div>
                                            <div class="subtle">{{ $variant->variant_name !== 'Default' ? $variant->variant_name.' · ' : '' }}{{ $variant->sku }}</div>
                                            <input type="hidden" name="rows[{{ $i }}][inventory_location_id]" value="{{ $location->id }}">
                                            <input type="hidden" name="rows[{{ $i }}][product_variant_id]" value="{{ $variant->id }}">
                                        </td>
                                        <td class="subtle">{{ $variant->product?->product_type?->label() }}</td>
                                        <td><strong data-available-display>{{ $qty($row['available'] / $unit['factor']) }}</strong></td>
                                        <td>
                                            @if (count($row['units']) > 1)
                                                <select name="rows[{{ $i }}][unit_id]" data-unit aria-label="Unit for {{ $variant->product?->name }}">
                                                    @foreach ($row['units'] as $option)
                                                        <option value="{{ $option['id'] }}" data-factor="{{ $option['factor'] }}" @selected($option['id'] === $unit['id'])>{{ $option['code'] }}</option>
                                                    @endforeach
                                                </select>
                                            @else
                                                {{ $unit['code'] }}
                                                <input type="hidden" name="rows[{{ $i }}][unit_id]" value="{{ $unit['id'] }}">
                                            @endif
                                        </td>
                                        <td><input type="number" min="0" step="any" name="rows[{{ $i }}][reorder_level]" value="{{ $row['level'] > 0 ? $num($row['level'] / $unit['factor']) : '' }}" placeholder="0" data-level-input aria-label="Reorder at"></td>
                                        <td><input type="number" min="0" step="any" name="rows[{{ $i }}][reorder_quantity]" value="{{ $row['qty'] > 0 ? $num($row['qty'] / $unit['factor']) : '' }}" placeholder="0" data-qty-input aria-label="Reorder quantity"></td>
                                        <td><span class="reorder-chip {{ $row['status'] }}" data-status-chip>{{ $statusLabels[$row['status']] }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7"><div class="empty">No items match these filters.</div></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($rows->isNotEmpty())
                        <div class="reorder-savebar">
                            <span class="subtle" data-changed-count>No changes yet. Leave a level blank or 0 to stop monitoring an item here.</span>
                            <button class="btn primary" type="submit" data-save disabled>Save changes</button>
                        </div>
                    @endif
                </form>
            @endif
        </div>
    </section>

    <script>
        (function () {
            const form = document.querySelector('[data-bulk-reorder]');
            if (! form) return;
            const fmt = (n) => String(Math.round(n * 10000) / 10000);
            const rows = Array.from(form.querySelectorAll('[data-reorder-row]'));
            const counter = form.querySelector('[data-changed-count]');
            const save = form.querySelector('[data-save]');
            const labels = { low: 'Low', ok: 'OK', unset: 'Not monitored' };

            // Compare in base units, so switching g → kg alone is not a change.
            function isChanged(row) {
                const factor = parseFloat(row.dataset.factor) || 1;
                const level = (parseFloat(row.querySelector('[data-level-input]').value) || 0) * factor;
                const qty = (parseFloat(row.querySelector('[data-qty-input]').value) || 0) * factor;
                return Math.abs(level - parseFloat(row.dataset.level)) > 1e-6 || Math.abs(qty - parseFloat(row.dataset.qty)) > 1e-6;
            }

            function refresh(row) {
                const factor = parseFloat(row.dataset.factor) || 1;
                const level = (parseFloat(row.querySelector('[data-level-input]').value) || 0) * factor;
                const status = level <= 0 ? 'unset' : (parseFloat(row.dataset.available) <= level ? 'low' : 'ok');
                const chip = row.querySelector('[data-status-chip]');
                chip.className = 'reorder-chip ' + status;
                chip.textContent = labels[status];
                row.classList.toggle('is-changed', isChanged(row));

                const changed = rows.filter(isChanged).length;
                if (save) save.disabled = changed === 0;
                if (counter) counter.textContent = changed === 0
                    ? 'No changes yet. Leave a level blank or 0 to stop monitoring an item here.'
                    : changed + (changed === 1 ? ' item changed.' : ' items changed.');
            }

            rows.forEach(function (row) {
                row.querySelectorAll('[data-level-input], [data-qty-input]').forEach(function (input) {
                    input.addEventListener('input', function () { refresh(row); });
                });
                const unit = row.querySelector('[data-unit]');
                if (unit) unit.addEventListener('change', function () {
                    const from = parseFloat(row.dataset.factor) || 1;
                    const to = parseFloat(unit.selectedOptions[0].dataset.factor) || 1;
                    row.querySelectorAll('[data-level-input], [data-qty-input]').forEach(function (input) {
                        if (input.value !== '') input.value = fmt(parseFloat(input.value) * from / to);
                    });
                    row.querySelector('[data-available-display]').textContent = fmt(parseFloat(row.dataset.available) / to);
                    row.dataset.factor = to;
                    refresh(row);
                });
            });

            // Only changed rows are posted: keeps big lists under PHP's max_input_vars.
            form.addEventListener('submit', function (event) {
                const changed = rows.filter(isChanged);
                if (changed.length === 0) { event.preventDefault(); return; }
                rows.forEach(function (row) {
                    if (! changed.includes(row)) row.querySelectorAll('input, select').forEach(function (el) { el.disabled = true; });
                });
            });
        })();
    </script>
</x-layouts.admin>
