@php
    use Modules\Inventory\Enums\StockCountStatus;
    $money = fn (?int $minor): string => number_format(($minor ?? 0) / 100, 2);
    $qty = fn ($value): string => \Modules\Inventory\Support\Quantity::format($value ?? 0);
    $tenantParam = request('tenant');
    $isOpen = $count->status->isOpen();
    // A blind count keeps the system figure off the sheet until the counter has entered
    // their numbers, so the sheet cannot anchor them to what the system expects.
    $showSystem = ! $count->is_blind || $count->status !== StockCountStatus::Counting;
    $varianceValue = $count->varianceValueMinor();
@endphp

<x-layouts.admin :title="$count->count_number">
    <style>
        .count-meta { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 16px; }
        .count-meta .stat { min-width: 130px; }
        .count-meta .stat span { display: block; }
        .count-input { width: 110px; }
        .variance-up { color: #067647; }
        .variance-down { color: #b42318; }
    </style>

    <div class="topbar">
        <div>
            <div class="eyebrow"><a href="{{ $indexUrl }}">Stock takes</a></div>
            <h1>{{ $count->count_number }}</h1>
            <p class="subtle">
                {{ $count->location?->name }} ·
                <span class="badge {{ $count->status === StockCountStatus::Posted ? 'success' : ($count->status === StockCountStatus::Cancelled ? 'neutral' : 'warning') }}">{{ $count->status->label() }}</span>
                @if ($count->is_blind) · blind count @endif
                @if ($count->notes) · {{ $count->notes }} @endif
            </p>
        </div>
        <div class="inventory-actions" style="display:flex; gap:10px; flex-wrap:wrap;">
            @if ($isOpen)
                <form method="POST" action="{{ route('admin.inventory.stock-counts.cancel', $count) }}" onsubmit="return confirm('Cancel this count? Nothing will be posted.');">
                    @csrf
                    <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                    <button class="btn secondary" type="submit">Cancel count</button>
                </form>
            @endif
        </div>
    </div>

    @if (session('status'))<div class="alert success">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="alert errors">{{ $errors->first() }}</div>@endif

    <div class="count-meta">
        <div class="stat"><span class="subtle">Lines</span><strong>{{ $count->items->count() }}</strong></div>
        <div class="stat"><span class="subtle">Counted</span><strong>{{ $count->countedLineCount() }}</strong></div>
        <div class="stat"><span class="subtle">Lines with variance</span><strong>{{ $count->varianceLineCount() }}</strong></div>
        <div class="stat">
            <span class="subtle">Variance value</span>
            <strong class="{{ $varianceValue < 0 ? 'variance-down' : ($varianceValue > 0 ? 'variance-up' : '') }}">
                {{ $varianceValue < 0 ? '-' : '' }}{{ $tenant->currency_code }} {{ $money(abs($varianceValue)) }}
            </strong>
        </div>
        @if ($count->posted_at)
            <div class="stat"><span class="subtle">Posted</span><strong>{{ $count->posted_at->format('d M Y H:i') }}</strong></div>
        @endif
    </div>

    <form method="POST" action="{{ route('admin.inventory.stock-counts.items.update', $count) }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="tenant" value="{{ $tenantParam }}">

        <section class="panel">
            <div class="panel-header">
                <h2 class="panel-title">Count sheet</h2>
                @if ($isOpen)
                    <p class="subtle">Leave a line blank if it has not been counted yet. Entering <strong>0</strong> means you looked and found none.</p>
                @endif
            </div>
            <div class="panel-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Unit</th>
                            @if ($showSystem)<th>System</th>@endif
                            <th>Counted</th>
                            @if ($showSystem)<th>Variance</th><th>Variance value</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($count->items as $item)
                            @php
                                $variance = $item->variance();
                                $unit = $item->componentVariant?->baseUnit?->code;
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $item->componentVariant?->product?->name }}</strong><br>
                                    <span class="subtle">{{ $item->componentVariant?->variant_name }} · {{ $item->componentVariant?->sku }}</span>
                                </td>
                                <td>{{ (! $unit || $unit === 'ea') ? 'pc' : $unit }}</td>
                                @if ($showSystem)<td>{{ $qty($item->system_quantity) }}</td>@endif
                                <td>
                                    @if ($isOpen)
                                        <input class="count-input" type="number" step="any" min="0" name="counted[{{ $item->id }}]" value="{{ $item->isCounted() ? $qty($item->counted_quantity) : '' }}" placeholder="—">
                                    @else
                                        {{ $item->isCounted() ? $qty($item->counted_quantity) : '—' }}
                                    @endif
                                </td>
                                @if ($showSystem)
                                    <td class="{{ $variance < 0 ? 'variance-down' : ($variance > 0 ? 'variance-up' : '') }}">
                                        {{ $item->isCounted() ? ($variance > 0 ? '+' : '').$qty($variance) : '—' }}
                                    </td>
                                    <td class="{{ $item->varianceValueMinor() < 0 ? 'variance-down' : ($item->varianceValueMinor() > 0 ? 'variance-up' : '') }}">
                                        @if ($item->isCounted())
                                            {{ $item->varianceValueMinor() < 0 ? '-' : '' }}{{ $money(abs($item->varianceValueMinor())) }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="6" class="subtle">This count has no lines.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if ($isOpen)
            <div class="button-row" style="margin-top: 16px;">
                <button class="btn primary" type="submit">Save counted quantities</button>
            </div>
        @endif
    </form>

    @if ($isOpen)
        <section class="panel" style="margin-top: 16px;">
            <div class="panel-header"><h2 class="panel-title">Post the count</h2></div>
            <div class="panel-body">
                <p class="subtle" style="margin-bottom: 12px;">
                    Posting writes an adjustment for every line that differs, so stock matches what you counted and the loss or gain lands in the accounts.
                    Lines you never counted are left alone.
                </p>
                <form method="POST" action="{{ route('admin.inventory.stock-counts.post', $count) }}" onsubmit="return confirm('Post this count? Stock and the ledger will be adjusted.');">
                    @csrf
                    <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                    <button class="btn accent" type="submit" @disabled($count->countedLineCount() === 0)>Post count &amp; adjust stock</button>
                </form>
            </div>
        </section>
    @endif
</x-layouts.admin>
