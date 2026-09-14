@php
    use Modules\Inventory\Enums\InventoryMovementType;
    $qty = fn ($value): string => \Modules\Inventory\Support\Quantity::format($value ?? 0);
    $money = fn (?int $minor): string => number_format(($minor ?? 0) / 100, 2);
    $tenantParam = request('tenant');
    $traceUrl = fn ($b): string => route('admin.inventory.batches.show', array_filter(['batch' => $b->id, 'tenant' => $tenantParam]));

    // Split the lot's history: the inbound allocation is what put stock here, the
    // negative-delta ones are every draw against it.
    $allocations = $batch->allocations->sortBy(fn ($a) => $a->movement?->occurred_at);
    $inbound = $allocations->filter(fn ($a): bool => (float) ($a->movement?->quantity ?? 0) > 0);
    $outbound = $allocations->filter(fn ($a): bool => (float) ($a->movement?->quantity ?? 0) < 0);

    $unit = $batch->variant?->baseUnit?->code;
    $unitLabel = (! $unit || $unit === 'ea') ? '' : ' '.$unit;
@endphp

<x-layouts.admin :title="$batch->batch_number ?: 'Lot trace'">
    <style>
        .lot-meta { display: flex; gap: 22px; flex-wrap: wrap; margin-bottom: 18px; }
        .lot-meta .stat span { display: block; }
        .lot-expired { color: #b42318; }
        .chain-row td:first-child { padding-left: calc(12px + var(--depth, 0) * 22px); }
        .chain-arrow { color: var(--muted, #667085); margin-right: 6px; }
    </style>

    <div class="topbar">
        <div>
            <div class="eyebrow"><a href="{{ $indexUrl }}">Lot traceability</a></div>
            <h1>{{ $batch->batch_number ?: 'Lot without a batch number' }}</h1>
            <p class="subtle">
                {{ $batch->variant?->product?->name }} · {{ $batch->variant?->sku }} · currently at {{ $batch->location?->name }}
            </p>
        </div>
    </div>

    @if ($batch->isExpired() && ! $batch->isExhausted())
        <div class="alert errors">This lot expired on {{ $batch->expiry_date->format('d M Y') }} and still shows {{ $qty($batch->quantity_remaining) }}{{ $unitLabel }} in stock.</div>
    @endif

    <div class="lot-meta">
        <div class="stat"><span class="subtle">Received</span><strong>{{ $qty($batch->receivedQuantity()) }}{{ $unitLabel }}</strong></div>
        <div class="stat"><span class="subtle">Used</span><strong>{{ $qty($batch->consumedQuantity()) }}{{ $unitLabel }}</strong></div>
        <div class="stat"><span class="subtle">Remaining</span><strong>{{ $batch->isExhausted() ? 'used up' : $qty($batch->quantity_remaining).$unitLabel }}</strong></div>
        <div class="stat">
            <span class="subtle">Expiry</span>
            <strong class="{{ $batch->isExpired() ? 'lot-expired' : '' }}">{{ $batch->expiry_date?->format('d M Y') ?: 'Not set' }}</strong>
        </div>
        <div class="stat"><span class="subtle">Condition</span><strong>{{ $batch->stock_condition->label() }}</strong></div>
        <div class="stat"><span class="subtle">Unit cost</span><strong>{{ $tenant->currency_code }} {{ $money($batch->unit_cost_minor) }}</strong></div>
    </div>

    @if ($batch->sourceBatch)
        <div class="alert success">
            Transferred in from <strong>{{ $batch->sourceBatch->location?->name }}</strong> —
            <a href="{{ $traceUrl($batch->sourceBatch) }}">trace the lot it came from</a>.
        </div>
    @endif

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Where this lot went</h2>
                <p class="subtle">Every movement that drew stock from this lot, oldest first.</p>
            </div>
        </div>
        <div class="panel-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Movement</th>
                        <th>Quantity</th>
                        <th>Destination</th>
                        <th>Reference</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($outbound as $allocation)
                        @php $movement = $allocation->movement; @endphp
                        <tr>
                            <td>{{ $movement?->occurred_at?->format('d M Y H:i') }}</td>
                            <td><span class="badge neutral">{{ $movement?->movement_type?->label() }}</span></td>
                            <td>{{ $qty($allocation->quantity) }}{{ $unitLabel }}</td>
                            <td>
                                @if ($movement?->destinationLocation)
                                    {{ $movement->destinationLocation->name }}
                                @elseif ($movement?->movement_type === InventoryMovementType::StockOut)
                                    <span class="subtle">Written off</span>
                                @else
                                    <span class="subtle">—</span>
                                @endif
                            </td>
                            <td>{{ $movement?->reference_number ?: ($movement?->reference_type ? str($movement->reference_type)->headline() : ($movement?->notes ?: '—')) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty">Nothing has been drawn from this lot yet.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel" style="margin-top: 16px;">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Downstream lots</h2>
                <p class="subtle">Stores this lot reached through transfers. In a recall, every line here holds affected stock.</p>
            </div>
        </div>
        <div class="panel-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>Location</th>
                        <th>Received</th>
                        <th>Used</th>
                        <th>Remaining</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($downstream as $node)
                        @php $child = $node['batch']; @endphp
                        <tr class="chain-row" style="--depth: {{ $node['depth'] }};">
                            <td>
                                @if ($node['depth'] > 1)<span class="chain-arrow">↳</span>@endif
                                {{ $child->location?->name }}
                            </td>
                            <td>{{ $qty($child->receivedQuantity()) }}{{ $unitLabel }}</td>
                            <td>{{ $qty($child->consumedQuantity()) }}{{ $unitLabel }}</td>
                            <td>{{ $child->isExhausted() ? 'used up' : $qty($child->quantity_remaining).$unitLabel }}</td>
                            <td><a class="btn ghost" href="{{ $traceUrl($child) }}">Trace</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty">This lot was never transferred to another store.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel" style="margin-top: 16px;">
        <div class="panel-header"><h2 class="panel-title">How this lot arrived</h2></div>
        <div class="panel-body">
            @forelse ($inbound as $allocation)
                @php $movement = $allocation->movement; @endphp
                <div class="item">
                    <div>
                        <div class="item-title">{{ $movement?->movement_type?->label() }} · {{ $qty($allocation->quantity) }}{{ $unitLabel }}</div>
                        <div class="subtle">
                            {{ $movement?->occurred_at?->format('d M Y H:i') }}
                            @if ($movement?->reference_number) · {{ $movement->reference_number }} @endif
                        </div>
                    </div>
                </div>
            @empty
                <p class="subtle">Recorded on receipt at {{ $batch->created_at?->format('d M Y H:i') }}. Goods receipts and stock movements create the lot directly rather than through an allocation.</p>
            @endforelse
        </div>
    </section>
</x-layouts.admin>
