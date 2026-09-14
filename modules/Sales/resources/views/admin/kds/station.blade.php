@php
    use Modules\Sales\Enums\TicketStatus;
    $warn = (int) $thresholds['warn'];
    $late = (int) $thresholds['late'];
    $band = function ($ticket) use ($warn, $late): string {
        $age = $ticket->ageMinutes();
        return $age >= $late ? 'late' : ($age >= $warn ? 'warn' : 'fresh');
    };
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $station->name }} — Kitchen</title>
    <style>
        /* A wall screen read from two metres by someone holding a pan. Dark ground with
           bright cards survives kitchen glare and steam far better than a white page.
           Nothing here is under 18px; ticket content is far larger. */
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; height: 100%; }
        body {
            background: #0b1020; color: #f8fafc;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .kds-head {
            display: flex; align-items: center; justify-content: space-between; gap: 20px;
            padding: 16px 26px; background: #111a33; border-bottom: 3px solid #1e2b4d;
            position: sticky; top: 0; z-index: 10;
        }
        .kds-station { font-size: 2.5rem; font-weight: 900; letter-spacing: -.02em; line-height: 1; }
        .kds-sub { font-size: 1rem; color: #94a3b8; margin-top: 4px; font-weight: 600; }
        .kds-right { display: flex; align-items: center; gap: 26px; }
        .kds-count { text-align: center; }
        .kds-count b { display: block; font-size: 2.5rem; font-weight: 900; line-height: 1; }
        .kds-count span { font-size: .8rem; color: #94a3b8; text-transform: uppercase; letter-spacing: .08em; font-weight: 700; }
        .kds-clock { font-size: 2.5rem; font-weight: 800; font-variant-numeric: tabular-nums; }
        .kds-back { color: #94a3b8; text-decoration: none; font-size: .95rem; font-weight: 700; border: 2px solid #24345c; padding: 9px 15px; border-radius: 10px; }
        .kds-back:hover { color: #f8fafc; border-color: #3b5185; }

        .board { padding: 20px 26px 40px; display: grid; grid-template-columns: repeat(auto-fill, minmax(330px, 1fr)); gap: 18px; align-items: start; }

        .ticket { background: #ffffff; color: #0b1020; border-radius: 16px; overflow: hidden; border: 4px solid transparent; box-shadow: 0 10px 26px rgba(0,0,0,.35); }
        .ticket-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; padding: 14px 16px; color: #fff; }
        .ticket-table { font-size: 2rem; font-weight: 900; line-height: 1; }
        .ticket-meta { font-size: .9rem; font-weight: 700; opacity: .95; margin-top: 5px; }
        .ticket-age { font-size: 2rem; font-weight: 900; font-variant-numeric: tabular-nums; line-height: 1; text-align: right; }
        .ticket-age small { display: block; font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; opacity: .9; }

        /* Age drives colour AND a written label — never colour alone, for glare and for
           colour vision deficiency. */
        .fresh { border-color: #12b76a; } .fresh .ticket-top { background: #12b76a; }
        .warn  { border-color: #f79009; } .warn  .ticket-top { background: #f79009; }
        .late  { border-color: #f04438; } .late  .ticket-top { background: #f04438; animation: pulse 1.15s ease-in-out infinite; }
        @keyframes pulse { 0%,100% { box-shadow: 0 0 0 0 rgba(240,68,56,.65); } 50% { box-shadow: 0 0 0 14px rgba(240,68,56,0); } }
        @media (prefers-reduced-motion: reduce) { .late .ticket-top { animation: none; } }

        .ticket-items { padding: 6px 16px 12px; }
        .k-item { padding: 12px 0; border-bottom: 2px dashed #e4e7ec; }
        .k-item:last-child { border-bottom: 0; }
        .k-line { display: flex; gap: 12px; align-items: baseline; }
        .k-qty { font-size: 1.7rem; font-weight: 900; min-width: 2.1ch; }
        .k-name { font-size: 1.45rem; font-weight: 800; line-height: 1.2; }
        /* Modifiers in a contrasting colour: a missed "no peanuts" is a safety incident. */
        .k-mods { margin-top: 5px; margin-left: calc(2.1ch + 12px); font-size: 1.05rem; font-weight: 800; color: #b42318; }

        .ticket-foot { padding: 12px 16px 16px; }
        .bump {
            display: block; width: 100%; padding: 17px; border: 0; border-radius: 12px;
            font-size: 1.2rem; font-weight: 900; letter-spacing: .01em; cursor: pointer;
            background: #101828; color: #fff;
        }
        .bump:hover { background: #1d2939; }
        .bump.ready { background: #067647; }
        .status-tag { display: inline-block; padding: 4px 11px; border-radius: 999px; font-size: .78rem; font-weight: 900; text-transform: uppercase; letter-spacing: .06em; background: #0b1020; color: #fff; }

        .empty-board { grid-column: 1 / -1; text-align: center; padding: 90px 20px; color: #64748b; }
        .empty-board h2 { font-size: 2.1rem; margin: 0 0 8px; color: #94a3b8; }

        @media (max-width: 640px) {
            .kds-station { font-size: 1.7rem; } .kds-clock { font-size: 1.7rem; }
            .board { grid-template-columns: 1fr; padding: 14px; }
        }
    </style>
</head>
<body>
    <header class="kds-head">
        <div>
            <div class="kds-station">{{ $station->name }}</div>
            <div class="kds-sub">{{ $tenant->name }} · kitchen display</div>
        </div>
        <div class="kds-right">
            <div class="kds-count"><b>{{ $tickets->count() }}</b><span>Live</span></div>
            <div class="kds-clock" id="kds-clock">--:--</div>
            <a class="kds-back" href="{{ $indexUrl }}">Exit</a>
        </div>
    </header>

    <style>
        .k-mod { margin: 4px 0 0 44px; padding: 3px 10px; border-radius: 6px; display: inline-block;
            background: #fde047; color: #1c1917; font-weight: 800; font-size: 1.05rem; letter-spacing: .01em; }
        .k-mod.is-removal { background: #ef4444; color: #fff; text-transform: uppercase; }
        .k-void .k-name, .k-void .k-qty { text-decoration: line-through; opacity: .55; }
        .k-void .k-mod { opacity: .45; }
        .k-void-tag { margin-left: 10px; padding: 2px 9px; border-radius: 6px; background: #ef4444; color: #fff;
            font-weight: 900; font-size: .85rem; letter-spacing: .06em; }
    </style>

    <main class="board">
        @forelse ($tickets as $ticket)
            <article class="ticket {{ $band($ticket) }}">
                <div class="ticket-top">
                    <div>
                        <div class="ticket-table">{{ $ticket->order?->table?->name ?? $ticket->order?->order_number }}</div>
                        <div class="ticket-meta">
                            Course {{ $ticket->course }} ·
                            {{ $ticket->order?->cover_count ?? 0 }} cover{{ (int) $ticket->order?->cover_count === 1 ? '' : 's' }} ·
                            {{ $ticket->ticket_number }}
                        </div>
                        <div class="ticket-meta"><span class="status-tag">{{ $ticket->status->label() }}</span></div>
                    </div>
                    <div class="ticket-age">
                        {{ $ticket->ageMinutes() }}<small>{{ $band($ticket) === 'late' ? 'LATE · min' : 'min' }}</small>
                    </div>
                </div>

                <div class="ticket-items">
                    @foreach ($ticket->items as $line)
                        @php $struck = $line->status === 'cancelled'; @endphp
                        <div class="k-item {{ $struck ? 'k-void' : '' }}">
                            <div class="k-line">
                                <span class="k-qty">{{ \Modules\Inventory\Support\Quantity::format($line->quantity) }}×</span>
                                <span class="k-name">{{ $line->orderItem?->item_name }}</span>
                                @if ($struck)
                                    {{-- Loud and labelled: the cook must stop, not just notice a colour. --}}
                                    <span class="k-void-tag">VOID — STOP</span>
                                @endif
                            </div>
                            @foreach ($line->orderItem?->modifiers ?? [] as $modifier)
                                {{-- Directly under the item, in a colour nothing else uses. A missed
                                     "no peanuts" is a safety incident, not a service slip. --}}
                                <div class="k-mod {{ $modifier->isRemoval() ? 'is-removal' : '' }}">
                                    {{ $modifier->isRemoval() ? '✕' : '+' }} {{ $modifier->option_name }}
                                </div>
                            @endforeach
                            @if ($line->orderItem?->seat_number)
                                <div class="k-mods">Seat {{ $line->orderItem->seat_number }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="ticket-foot">
                    @if ($ticket->status->next())
                        <form method="POST" action="{{ route('admin.sales.kds.tickets.advance', $ticket) }}">
                            @csrf
                            <input type="hidden" name="tenant" value="{{ $tenant->id }}">
                            <button class="bump {{ $ticket->status === TicketStatus::Preparing ? 'ready' : '' }}" type="submit">
                                {{ $ticket->status->nextLabel() }}
                            </button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <div class="empty-board">
                <h2>All clear</h2>
                <p style="font-size:1.15rem; margin:0;">No tickets waiting at {{ $station->name }}.</p>
            </div>
        @endforelse
    </main>

    <script>
        // A kitchen screen tolerates a few seconds of staleness; polling is honest for this
        // volume and needs no realtime infrastructure. Refresh pauses while a button has
        // focus so a reload never steals a bump mid-tap.
        (function () {
            const clock = document.getElementById('kds-clock');
            function tick() {
                const now = new Date();
                clock.textContent = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
            }
            tick();
            setInterval(tick, 10000);

            setInterval(function () {
                if (document.activeElement && document.activeElement.tagName === 'BUTTON') return;
                if (document.hidden) return;
                window.location.reload();
            }, 15000);
        })();
    </script>
</body>
</html>
