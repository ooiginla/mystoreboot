@php $tenantParam = request('tenant'); @endphp

<x-layouts.admin title="Kitchen Screens">
    <style>
        .station-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px; }
        .station-card {
            display: block; text-decoration: none; color: inherit;
            border: 2px solid var(--line); border-radius: 14px; padding: 20px; background: #fff;
            transition: transform .12s ease, box-shadow .12s ease, border-color .12s ease;
        }
        .station-card:hover { transform: translateY(-2px); border-color: #101828; box-shadow: 0 8px 20px rgba(16,24,40,.10); }
        .station-card .s-name { font-size: 1.45rem; font-weight: 800; line-height: 1.15; }
        .station-card .s-sub { font-size: .84rem; color: #667085; margin-top: 3px; }
        .station-card .s-queue { margin-top: 14px; display: flex; align-items: baseline; gap: 8px; }
        .station-card .s-queue b { font-size: 2.1rem; font-weight: 900; line-height: 1; }
        .station-card.busy { border-color: #f79009; background: #fffaf0; }
        .station-card.busy .s-queue b { color: #b54708; }
    </style>

    <div class="topbar">
        <div>
            <div class="eyebrow">Restaurant</div>
            <h1>Kitchen screens</h1>
            <p class="subtle">Open a station's display on the screen in that section of the kitchen. Each board shows only that station's work.</p>
        </div>
        <a class="btn secondary" href="{{ route('admin.sales.restaurant.floor', array_filter(['tenant' => $tenantParam])) }}">Back to floor</a>
    </div>

    @if ($errors->any())<div class="alert errors">{{ $errors->first() }}</div>@endif

    @if ($unassigned > 0)
        <div class="alert">
            {{ $unassigned }} live ticket(s) are not routed to any station, because the products on them have no prep station set.
            Set a prep station on those products (Catalog → edit a product) so the right screen picks them up.
        </div>
    @endif

    @if ($stations->isEmpty())
        <section class="panel">
            <div class="panel-body" style="text-align:center; padding: 40px 20px;">
                <h2 style="margin:0 0 8px;">No prep stations yet</h2>
                <p class="subtle" style="margin:0 0 16px;">
                    A prep station is an arm of the kitchen — Grill, Main Kitchen, Bar, Pastry.
                    Products are routed to a screen by the prep station set on them.
                </p>
                <a class="btn primary" href="{{ route('admin.inventory.index', array_filter(['tenant' => $tenantParam])) }}#locations">Set up prep stations</a>
            </div>
        </section>
    @else
        <div class="station-grid">
            @foreach ($stations as $station)
                @php $live = (int) ($queues[$station->id] ?? 0); @endphp
                <a class="station-card {{ $live > 0 ? 'busy' : '' }}"
                   href="{{ route('admin.sales.kds.station', array_filter(['station' => $station->id, 'tenant' => $tenantParam])) }}">
                    <div class="s-name">{{ $station->name }}</div>
                    <div class="s-sub">{{ $station->status === 'active' ? 'Active' : ucfirst((string) $station->status) }}</div>
                    <div class="s-queue">
                        <b>{{ $live }}</b>
                        <span class="subtle">ticket{{ $live === 1 ? '' : 's' }} waiting</span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.admin>
