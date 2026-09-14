@php
    use Modules\Sales\Enums\CheckStatus;
    use Modules\Sales\Enums\TableStatus;
    use Modules\Sales\Support\DineInSettings;
    $money = fn (?int $minor): string => number_format(($minor ?? 0) / 100, 2);
    $tenantParam = request('tenant');
    $tableUrl = fn ($check): string => route('admin.sales.restaurant.check', array_filter(['order' => $check->id, 'tenant' => $tenantParam]));
@endphp

<x-layouts.admin title="Restaurant Floor">
    <style>
        /* Floor map: read the whole room at a glance. Every state carries a colour, a
           label and an icon — colour is never the only signal. */
        .floor-legend { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 18px; }
        .legend-item { display: inline-flex; align-items: center; gap: 7px; font-size: 0.85rem; font-weight: 600; }
        .legend-dot { width: 12px; height: 12px; border-radius: 4px; display: inline-block; }

        .area-block { margin-bottom: 28px; }
        .area-head { display: flex; align-items: baseline; gap: 12px; margin-bottom: 12px; }
        .area-head h2 { margin: 0; font-size: 1.15rem; }

        .table-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(178px, 1fr)); gap: 14px; }

        .table-card {
            position: relative; display: block; text-align: left; width: 100%;
            border: 2px solid var(--line); border-radius: 14px; padding: 15px 15px 13px;
            background: #fff; cursor: pointer; text-decoration: none; color: inherit;
            transition: transform .12s ease, box-shadow .12s ease;
        }
        .table-card:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(16,24,40,.10); }
        .table-card .t-name { font-size: 1.5rem; font-weight: 800; line-height: 1.1; }
        .table-card .t-seats { font-size: 0.8rem; color: #667085; margin-top: 2px; }
        .table-card .t-state { display: inline-flex; align-items: center; gap: 5px; margin-top: 11px; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }
        .table-card .t-meta { margin-top: 10px; padding-top: 10px; border-top: 1px dashed var(--line); font-size: 0.82rem; }
        .table-card .t-total { font-size: 1.15rem; font-weight: 800; }
        .t-bills { display: inline-block; margin-left: 6px; padding: 1px 7px; border-radius: 999px; background: #101828; color: #fff; font-size: .68rem; font-weight: 800; }

        /* State tones. Left bar gives a second, non-colour cue at a distance. */
        .t-free   { border-color: #a6e6c3; background: #f4fdf8; }
        .t-busy   { border-color: #f3b7ab; background: #fff6f4; }
        .t-bill   { border-color: #a4bcfd; background: #f5f8ff; }
        .t-held   { border-color: #f6d68a; background: #fffaf0; }
        .t-dirty  { border-color: #cbd2dc; background: #f7f8fa; }
        .t-free .t-state   { color: #067647; }
        .t-busy .t-state   { color: #b42318; }
        .t-bill .t-state   { color: #3538cd; }
        .t-held .t-state   { color: #b54708; }
        .t-dirty .t-state  { color: #475467; }
        .dot-free { background: #12b76a; } .dot-busy { background: #f04438; } .dot-bill { background: #444ce7; }
        .dot-held { background: #f79009; } .dot-dirty { background: #98a2b3; }

        /* Stretched link so the whole card is clickable while real controls sit above it. */
        /* The link covers the whole card and sits ABOVE the text, so a tap anywhere on the
           card opens the check. Only the real controls are lifted over it — text above the
           link would swallow the click, which is what made the card feel dead. */
        .card-link { position: absolute; inset: 0; z-index: 1; border-radius: 14px; }
        .card-link:focus-visible { outline: 2px solid #101828; outline-offset: 2px; }
        .table-card form { position: relative; z-index: 2; }
        .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
        .card-free { margin-top: 11px; }
        .card-free button { width: 100%; padding: 8px 10px; font-size: .85rem; }

        .seat-form { display: flex; gap: 8px; align-items: center; margin-top: 11px; }
        .seat-form input { width: 62px; padding: 7px 8px; font-size: 0.95rem; text-align: center; }
        .seat-form button { flex: 1; padding: 8px 10px; font-size: 0.88rem; }

        .floor-empty { border: 2px dashed var(--line); border-radius: 14px; padding: 34px; text-align: center; }
        .timing-choice { display: grid; gap: 8px; }
        .timing-choice label { display: flex; gap: 10px; align-items: flex-start; padding: 10px 12px; border: 1px solid var(--line); border-radius: 10px; cursor: pointer; }
        .timing-choice label:has(input:checked) { border-color: #101828; background: #f9fafb; }
        @media (max-width: 560px) { .table-grid { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); } }
    </style>

    <div class="topbar">
        <div>
            <div class="eyebrow">Restaurant</div>
            <h1>Floor</h1>
            <p class="subtle">Every table in {{ $tenant->name }}, live. Tap a free table to seat guests, or an occupied one to open its check.</p>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a class="btn secondary" href="{{ route('admin.sales.kds.index', array_filter(['tenant' => $tenantParam])) }}">Kitchen screens</a>
            @permission('pos.restaurant.modifiers.manage')
            <a class="btn ghost" href="{{ route('admin.sales.restaurant.modifiers.index', array_filter(['tenant' => $tenantParam])) }}">Menu modifiers</a>
            @endpermission
            @permission('pos.restaurant.tables.manage')
            <a class="btn ghost" href="{{ route('admin.sales.restaurant.areas.index', array_filter(['tenant' => $tenantParam])) }}">Floor plan</a>
            @endpermission
            @permission('sales.service-charge.manage')
            <button class="btn ghost" type="button" data-dialog-open="restaurant-settings">Settings</button>
            @endpermission
        </div>
    </div>

    @if (session('status'))<div class="alert success">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="alert errors">{{ $errors->first() }}</div>@endif

    <div class="floor-legend">
        <span class="legend-item"><span class="legend-dot dot-free"></span> Available</span>
        <span class="legend-item"><span class="legend-dot dot-busy"></span> Occupied</span>
        <span class="legend-item"><span class="legend-dot dot-bill"></span> Bill printed</span>
        <span class="legend-item"><span class="legend-dot dot-held"></span> Reserved</span>
        <span class="legend-item"><span class="legend-dot dot-dirty"></span> Needs cleaning</span>
    </div>

    @forelse ($areas as $area)
        <section class="area-block">
            <div class="area-head">
                <h2>{{ $area->name }}</h2>
                <span class="subtle">{{ $area->tables->count() }} table{{ $area->tables->count() === 1 ? '' : 's' }}</span>
            </div>

            @if ($area->tables->isEmpty())
                <div class="floor-empty">
                    <p class="subtle" style="margin:0 0 10px;">No tables in this area yet.</p>
                    @permission('pos.restaurant.tables.manage')
                    <a class="btn primary" href="{{ route('admin.sales.restaurant.areas.index', array_filter(['tenant' => $tenantParam])) }}">Add tables</a>
                    @endpermission
                </div>
            @else
                <div class="table-grid">
                    @foreach ($area->tables as $table)
                        @php $checks = $openChecks->get($table->id); @endphp
                        @if ($checks && $checks->isNotEmpty())
                            @php
                                $check = $checks->first();
                                $isEmpty = $checks->count() === 1 && (int) $check->items_count === 0;
                                // Every bill at the table printed means the table is waiting to pay.
                                $allBilled = $checks->every(fn ($c) => $c->check_status === CheckStatus::BillPrinted);
                            @endphp
                            <div class="table-card {{ $allBilled ? 't-bill' : 't-busy' }}">
                                {{-- Stretched link: the whole card opens the check, but the
                                     Free button below still gets its own clicks. --}}
                                <a class="card-link" href="{{ $tableUrl($check) }}"><span class="sr-only">Open check for table {{ $table->name }}</span></a>
                                <div class="t-name">
                                    {{ $table->name }}
                                    @if ($checks->count() > 1)<span class="t-bills">{{ $checks->count() }} bills</span>@endif
                                </div>
                                <div class="t-seats">{{ $checks->sum('cover_count') }} of {{ $table->seats }} seats</div>
                                <div class="t-state">
                                    <span class="legend-dot {{ $allBilled ? 'dot-bill' : 'dot-busy' }}"></span>
                                    {{ $allBilled ? 'Bill printed' : 'Occupied' }}
                                </div>
                                <div class="t-meta">
                                    <div class="t-total">{{ $tenant->currency_code }} {{ $money($checks->sum('total_minor')) }}</div>
                                    <div class="subtle">
                                        {{ $check->openMinutes() }} min
                                        @if ($check->server) · {{ $check->server->name }} @endif
                                    </div>
                                </div>
                                @if ($isEmpty)
                                    {{-- Seated by mistake: nothing ordered, so it can be undone here
                                         rather than making someone open an empty check to escape. --}}
                                    @permission('pos.restaurant.operate')
                                    <form class="card-free" method="POST" action="{{ route('admin.sales.restaurant.checks.cancel', $check) }}"
                                          onsubmit="return confirm('Nothing has been ordered. Free table {{ $table->name }}?');">
                                        @csrf
                                        <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                                        <button class="btn danger" type="submit">Free table</button>
                                    </form>
                                    @endpermission
                                @endif
                            </div>
                        @elseif ($table->status === TableStatus::Dirty)
                            <div class="table-card t-dirty">
                                <div class="t-name">{{ $table->name }}</div>
                                <div class="t-seats">{{ $table->seats }} seats</div>
                                <div class="t-state"><span class="legend-dot dot-dirty"></span> Needs cleaning</div>
                                @permission('pos.restaurant.operate')
                                <form class="card-free" method="POST" action="{{ route('admin.sales.restaurant.tables.clean', $table) }}">
                                    @csrf
                                    <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                                    <button class="btn secondary" type="submit">Cleared — ready for guests</button>
                                </form>
                                @endpermission
                            </div>
                        @else
                            <div class="table-card t-{{ $table->status->tone() }}">
                                <div class="t-name">{{ $table->name }}</div>
                                <div class="t-seats">{{ $table->seats }} seats</div>
                                <div class="t-state"><span class="legend-dot dot-{{ $table->status->tone() }}"></span> {{ $table->status->label() }}</div>
                                @permission('pos.restaurant.operate')
                                <form class="seat-form" method="POST" action="{{ route('admin.sales.restaurant.checks.open', $table) }}">
                                    @csrf
                                    <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                                    <input type="number" name="cover_count" min="1" max="{{ max(20, $table->seats) }}" value="{{ $table->seats }}" aria-label="Guests at {{ $table->name }}">
                                    <button class="btn primary" type="submit">Seat</button>
                                </form>
                                @endpermission
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        </section>
    @empty
        <div class="floor-empty">
            <h2 style="margin:0 0 8px;">No service areas yet</h2>
            <p class="subtle" style="margin:0 0 16px;">
                A service area is a part of your floor — “Main Restaurant”, “Pool Bar”, “Terrace”.
                Tables live inside an area, and the area decides which store its stock comes from.
            </p>
            @permission('pos.restaurant.tables.manage')
            <a class="btn primary" href="{{ route('admin.sales.restaurant.areas.index', array_filter(['tenant' => $tenantParam])) }}">Set up the floor plan</a>
            @endpermission
        </div>
    @endforelse

    @permission('sales.service-charge.manage')
    <dialog class="dialog" id="restaurant-settings">
        <div class="dialog-header">
            <div><h2 class="panel-title">Restaurant settings</h2></div>
            <button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button>
        </div>
        <div class="dialog-body">
            <form class="mini-form" method="POST" action="{{ route('admin.sales.restaurant.settings.update') }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                <div class="form-grid">
                    <div class="field">
                        <label for="sc-rate">Service charge (%)</label>
                        <input id="sc-rate" name="service_charge_rate" type="number" min="0" max="50" step="0.01" value="{{ rtrim(rtrim(number_format($serviceChargeRate, 2, '.', ''), '0'), '.') ?: '0' }}">
                        <small class="subtle">Added on food and drink — never on tax. 0 turns it off. Applies to tables seated from now on.</small>
                    </div>
                </div>
                <div class="field" style="margin-top:10px;">
                    <label>When do ingredients leave the store?</label>
                    <div class="timing-choice">
                        <label>
                            <input type="radio" name="depletion" value="{{ DineInSettings::DEPLETE_AT_FIRE }}" @checked($depletionTiming === DineInSettings::DEPLETE_AT_FIRE)>
                            <span><strong>When the kitchen is sent the order</strong> (recommended)<br><span class="subtle">Stock is true during service, and a table that walks out still shows what was cooked.</span></span>
                        </label>
                        <label>
                            <input type="radio" name="depletion" value="{{ DineInSettings::DEPLETE_AT_SETTLE }}" @checked($depletionTiming === DineInSettings::DEPLETE_AT_SETTLE)>
                            <span><strong>When the bill is paid</strong><br><span class="subtle">Matches counter sales. Stock looks higher than it is until tables pay.</span></span>
                        </label>
                    </div>
                </div>
                <div class="button-row">
                    <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                    <button class="btn primary" type="submit">Save settings</button>
                </div>
            </form>
        </div>
    </dialog>
    @endpermission
</x-layouts.admin>
