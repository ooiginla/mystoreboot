@php
    use Modules\Inventory\Enums\StockCountStatus;
    $money = fn (?int $minor): string => number_format(($minor ?? 0) / 100, 2);
    $tenantParam = request('tenant');
    $badge = fn (StockCountStatus $s): string => match ($s) {
        StockCountStatus::Posted => 'success',
        StockCountStatus::Cancelled => 'neutral',
        default => 'warning',
    };
@endphp

<x-layouts.admin title="Stock Takes">
    <div class="topbar">
        <div>
            <div class="eyebrow">Inventory control</div>
            <h1>Stock takes</h1>
            <p class="subtle">Count what is physically on the shelf, compare it against the system, and post the difference for {{ $tenant->name }}.</p>
        </div>
        @if ($isPlatformAdmin)
            <form method="GET" action="{{ route('admin.inventory.stock-counts.index') }}" style="min-width: 260px;">
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

    <div style="margin-bottom: 16px;">
        <button class="btn primary" type="button" data-dialog-open="stock-count-dialog">Start a count</button>
    </div>

    <section class="panel">
        <div class="panel-header"><h2 class="panel-title">Counts</h2></div>
        <div class="panel-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>Count</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Lines</th>
                        <th>Counted</th>
                        <th>Variance lines</th>
                        <th>Variance value</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($counts as $count)
                        @php $value = $count->varianceValueMinor(); @endphp
                        <tr>
                            <td><strong>{{ $count->count_number }}</strong><br><span class="subtle">{{ $count->created_at?->format('d M Y') }}</span></td>
                            <td>{{ $count->location?->name }}</td>
                            <td>
                                <span class="badge {{ $badge($count->status) }}">{{ $count->status->label() }}</span>
                                @if ($count->is_blind && $count->status->isOpen())<span class="subtle"> blind</span>@endif
                            </td>
                            <td>{{ $count->items->count() }}</td>
                            <td>{{ $count->countedLineCount() }}</td>
                            <td>{{ $count->varianceLineCount() }}</td>
                            <td>
                                @if ($count->countedLineCount() === 0)
                                    <span class="subtle">—</span>
                                @else
                                    <span @style(['color: var(--danger, #b42318)' => $value < 0])>{{ $value < 0 ? '-' : '' }}{{ $tenant->currency_code }} {{ $money(abs($value)) }}</span>
                                @endif
                            </td>
                            <td><a class="btn ghost" href="{{ route('admin.inventory.stock-counts.show', array_filter(['stockCount' => $count->id, 'tenant' => $tenantParam])) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="subtle">No stock takes yet. Start one to snapshot a location and begin counting.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <dialog class="dialog" id="stock-count-dialog">
        <div class="dialog-header">
            <div>
                <h2 class="panel-title">Start a stock take</h2>
                <p class="subtle">Snapshots what the system currently holds at the location you pick.</p>
            </div>
            <button class="icon-btn" type="button" data-dialog-close aria-label="Close">✕</button>
        </div>
        <div class="dialog-body">
            <form class="mini-form" method="POST" action="{{ route('admin.inventory.stock-counts.store') }}">
                @csrf
                <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                <div class="form-grid">
                    <div class="field full">
                        <label>Location</label>
                        <select name="inventory_location_id" required>
                            <option value="">Select a location…</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}">{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field full">
                        <label style="display:flex; align-items:center; gap:8px;">
                            <input type="hidden" name="is_blind" value="0">
                            <input type="checkbox" name="is_blind" value="1" checked style="width:auto;">
                            Blind count — hide the system quantity from the counter until review
                        </label>
                    </div>
                    <div class="field full">
                        <label>Notes</label>
                        <input name="notes" placeholder="Monthly count, spot check on the grill store…">
                    </div>
                </div>
                <p class="subtle" style="margin-top: 12px;">Movements that happen while you count are not lost — posting applies only the difference you found, on top of whatever has moved since.</p>
                <div class="button-row" style="margin-top: 16px;">
                    <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                    <button class="btn primary" type="submit">Open count</button>
                </div>
            </form>
        </div>
    </dialog>
</x-layouts.admin>
