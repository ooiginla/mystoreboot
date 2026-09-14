@php
    use Modules\Sales\Enums\TableStatus;
    $tenantParam = request('tenant');
    $areaId = $area?->id;
@endphp

<x-layouts.admin title="Floor Plan">
    <style>
        .plan-toolbar { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; margin-bottom: 18px; }
        .plan-picker { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; }
        .plan-picker .field { margin: 0; }
        .plan-picker select { min-width: 240px; }
        .plan-actions { display: flex; gap: 10px; flex-wrap: wrap; }

        .area-summary { display: flex; gap: 22px; flex-wrap: wrap; align-items: center; padding: 14px 16px; border: 1px solid var(--line); border-radius: 12px; background: #fff; margin-bottom: 16px; }
        .area-summary .bit span { display: block; font-size: .74rem; color: #667085; text-transform: uppercase; letter-spacing: .05em; font-weight: 800; }
        .area-summary .bit strong { font-size: 1.02rem; }

        /* Status column: the shade IS the signal, and it carries its own label. */
        .status-cell { display: flex; align-items: center; gap: 10px; }
        .status-menu { position: relative; display: inline-block; }
        .status-menu > summary { list-style: none; cursor: pointer; }
        .status-menu > summary::-webkit-details-marker { display: none; }

        .status-pill {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 7px 12px; border-radius: 999px; border: 1.5px solid transparent;
            font-size: .84rem; font-weight: 800; letter-spacing: .01em; white-space: nowrap;
            transition: box-shadow .12s ease, border-color .12s ease;
        }
        .status-menu[open] > .status-pill { box-shadow: 0 0 0 3px rgba(16,24,40,.08); }
        .status-pill .caret { opacity: .6; font-size: .7rem; }

        .tone-free  { background: #ecfdf3; border-color: #a6e6c3; color: #05603a; }
        .tone-busy  { background: #fef3f2; border-color: #f3b7ab; color: #b42318; }
        .tone-held  { background: #fffaeb; border-color: #f6d68a; color: #b54708; }
        .tone-dirty { background: #f2f4f7; border-color: #cbd2dc; color: #344054; }

        .status-dot { width: 9px; height: 9px; border-radius: 999px; background: #98a2b3; flex: none; }
        .dot-free  { background: #12b76a; box-shadow: 0 0 0 3px #d1fadf; }
        .dot-busy  { background: #f04438; box-shadow: 0 0 0 3px #fee4e2; }
        .dot-held  { background: #f79009; box-shadow: 0 0 0 3px #fef0c7; }
        .dot-dirty { background: #98a2b3; box-shadow: 0 0 0 3px #eaecf0; }

        .status-dropdown { position: absolute; z-index: 30; top: calc(100% + 7px); left: 0; width: 232px; border: 1px solid var(--line); border-radius: 10px; background: #fff; padding: 6px; box-shadow: 0 16px 36px rgba(16,24,40,.16); }
        .status-dropdown-title { padding: 8px 9px; color: #667085; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; }
        .status-dropdown form { margin: 0; }
        .status-option { width: 100%; display: grid; grid-template-columns: auto minmax(0,1fr) auto; gap: 10px; align-items: center; border: 0; border-radius: 7px; background: transparent; color: #344054; padding: 10px 9px; text-align: left; cursor: pointer; font-weight: 750; font-size: .95rem; }
        .status-option:hover:not(:disabled) { background: #f8fafc; color: #101828; }
        .status-option:disabled { color: #101828; cursor: default; opacity: 1; }
        .status-check { color: #067647; font-weight: 900; }

        .locked-note { font-size: .74rem; color: #b42318; font-weight: 700; }
        .row-actions { display: flex; gap: 8px; justify-content: flex-end; }
        .icon-action { width: 36px; height: 36px; display: grid; place-items: center; padding: 0; }
        .icon-action svg { width: 17px; height: 17px; }
        .plan-empty { border: 2px dashed var(--line); border-radius: 14px; padding: 38px 24px; text-align: center; }
        @media (max-width: 640px) { .plan-picker select { min-width: 0; width: 100%; } }
    </style>

    <div class="topbar">
        <div>
            <div class="eyebrow"><a href="{{ route('admin.sales.restaurant.floor', array_filter(['tenant' => $tenantParam])) }}">← Floor</a></div>
            <h1>Floor plan</h1>
            <p class="subtle">Service areas group your tables. Each area sells from one store — a location you have ticked “Can sell from here”.</p>
        </div>
    </div>

    @if (session('status'))<div class="alert success">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="alert errors">{{ $errors->first() }}</div>@endif

    <div class="plan-toolbar">
        <form class="plan-picker" method="GET" action="{{ route('admin.sales.restaurant.areas.index') }}">
            <input type="hidden" name="tenant" value="{{ $tenantParam }}">
            <div class="field">
                <label for="area-select">Service area</label>
                <select id="area-select" name="area" onchange="this.form.submit()">
                    @forelse ($areas as $option)
                        <option value="{{ $option->id }}" @selected($areaId === $option->id)>
                            {{ $option->name }} ({{ $option->tables_count }} table{{ $option->tables_count === 1 ? '' : 's' }})
                        </option>
                    @empty
                        <option value="">No areas yet</option>
                    @endforelse
                </select>
            </div>
        </form>
        <div class="plan-actions">
            <button class="btn secondary" type="button" data-dialog-open="area-dialog">New service area</button>
            @if ($area)
                <button class="btn ghost" type="button" data-dialog-open="area-edit-dialog">Edit area</button>
                <button class="btn primary" type="button" data-dialog-open="table-add-dialog">Add table</button>
            @endif
        </div>
    </div>

    @if (! $area)
        <div class="plan-empty">
            <h2 style="margin:0 0 8px;">No service areas yet</h2>
            <p class="subtle" style="margin:0 0 16px;">
                A service area is a part of your floor — “Main Restaurant”, “Pool Bar”, “Terrace”, “Room Service”.
                Tables live inside an area, and the area decides which store its stock comes from.
            </p>
            <button class="btn primary" type="button" data-dialog-open="area-dialog">Create the first area</button>
        </div>
    @else
        <div class="area-summary">
            <div class="bit"><span>Area</span><strong>{{ $area->name }}</strong></div>
            <div class="bit"><span>Branch</span><strong>{{ $area->branch?->name ?? '—' }}</strong></div>
            <div class="bit"><span>Sells from</span><strong>{{ $area->sellableLocation?->name ?? 'Not set' }}</strong></div>
            <div class="bit"><span>Tables</span><strong>{{ $area->tables->count() }}</strong></div>
        </div>

        @if (! $area->sellableLocation)
            <div class="alert errors">
                This area has no store set, so a check cannot be opened here — there would be nowhere to take the drinks and food from.
                <button class="btn ghost" type="button" data-dialog-open="area-edit-dialog" style="margin-left:8px;">Set one now</button>
            </div>
        @endif

        <section class="panel">
            <div class="panel-header">
                <h2 class="panel-title">Tables</h2>
                <p class="subtle">Click a status to change it — it saves straight away.</p>
            </div>
            <div class="panel-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Seats</th>
                            <th>Status</th>
                            <th style="text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($area->tables as $table)
                            @php $isBusy = in_array($table->id, $busyTableIds, true); @endphp
                            <tr>
                                <td><strong>{{ $table->name }}</strong></td>
                                <td>{{ $table->seats }}</td>
                                <td>
                                    <div class="status-cell">
                                        <details class="status-menu">
                                            <summary class="status-pill tone-{{ $table->status->tone() }}" aria-label="Change status for table {{ $table->name }}">
                                                <span class="status-dot dot-{{ $table->status->tone() }}"></span>
                                                <span>{{ $table->status->label() }}</span>
                                                <span class="caret" aria-hidden="true">▾</span>
                                            </summary>
                                            <div class="status-dropdown" role="menu" aria-label="Table status">
                                                <div class="status-dropdown-title">Change status</div>
                                                @foreach (TableStatus::cases() as $status)
                                                    <form method="POST" action="{{ route('admin.sales.restaurant.tables.status', $table) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                                                        <input type="hidden" name="area" value="{{ $areaId }}">
                                                        <input type="hidden" name="status" value="{{ $status->value }}">
                                                        <button class="status-option" type="submit" role="menuitem" @disabled($table->status === $status)>
                                                            <span class="status-dot dot-{{ $status->tone() }}"></span>
                                                            <span>{{ $status->label() }}</span>
                                                            @if ($table->status === $status)
                                                                <span class="status-check" aria-label="Current status">✓</span>
                                                            @endif
                                                        </button>
                                                    </form>
                                                @endforeach
                                            </div>
                                        </details>
                                        @if ($isBusy)
                                            <span class="locked-note">guests seated</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <button class="btn secondary icon-action" type="button"
                                                data-dialog-open="table-edit-{{ $table->id }}"
                                                aria-label="Edit table {{ $table->name }}" title="Edit table">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <div class="empty">
                                        No tables in {{ $area->name }} yet.
                                        <button class="btn primary" type="button" data-dialog-open="table-add-dialog" style="margin-left:10px;">Add the first table</button>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Edit dialogs, one per table --}}
        @foreach ($area->tables as $table)
            <dialog class="dialog" id="table-edit-{{ $table->id }}">
                <div class="dialog-header">
                    <div>
                        <h2 class="panel-title">Edit table {{ $table->name }}</h2>
                        <p class="subtle">Status is changed from the list — this is for the name and seating.</p>
                    </div>
                    <button class="icon-btn" type="button" data-dialog-close aria-label="Close">✕</button>
                </div>
                <div class="dialog-body">
                    <form class="mini-form" method="POST" action="{{ route('admin.sales.restaurant.tables.update', $table) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                        <input type="hidden" name="area" value="{{ $areaId }}">
                        <input type="hidden" name="status" value="{{ $table->status->value }}">
                        <div class="form-grid">
                            <div class="field"><label>Name</label><input name="name" value="{{ $table->name }}" required></div>
                            <div class="field"><label>Seats</label><input name="seats" type="number" min="1" max="100" value="{{ $table->seats }}" required></div>
                        </div>
                        <div class="button-row" style="margin-top: 16px;">
                            <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                            <button class="btn primary" type="submit">Save changes</button>
                        </div>
                    </form>
                </div>
            </dialog>
        @endforeach

        <dialog class="dialog" id="table-add-dialog">
            <div class="dialog-header">
                <div>
                    <h2 class="panel-title">Add a table</h2>
                    <p class="subtle">Added to {{ $area->name }}. New tables start as available.</p>
                </div>
                <button class="icon-btn" type="button" data-dialog-close aria-label="Close">✕</button>
            </div>
            <div class="dialog-body">
                <form class="mini-form" method="POST" action="{{ route('admin.sales.restaurant.tables.store') }}">
                    @csrf
                    <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                    <input type="hidden" name="area" value="{{ $areaId }}">
                    <input type="hidden" name="service_area_id" value="{{ $areaId }}">
                    <div class="form-grid">
                        <div class="field"><label>Name</label><input name="name" placeholder="e.g. T12" required></div>
                        <div class="field"><label>Seats</label><input name="seats" type="number" min="1" max="100" value="4" required></div>
                    </div>
                    <div class="button-row" style="margin-top: 16px;">
                        <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                        <button class="btn primary" type="submit">Add table</button>
                    </div>
                </form>
            </div>
        </dialog>

        <dialog class="dialog" id="area-edit-dialog">
            <div class="dialog-header">
                <div>
                    <h2 class="panel-title">Edit {{ $area->name }}</h2>
                    <p class="subtle">The sales point decides which store this area's stock comes from.</p>
                </div>
                <button class="icon-btn" type="button" data-dialog-close aria-label="Close">✕</button>
            </div>
            <div class="dialog-body">
                <form class="mini-form" method="POST" action="{{ route('admin.sales.restaurant.areas.update', $area) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                    <div class="form-grid">
                        <div class="field full"><label>Name</label><input name="name" value="{{ $area->name }}" required></div>
                        <div class="field full">
                            <label>Sells from</label>
                            <select name="sellable_location_id">
                                <option value="">—</option>
                                @foreach ($sellableLocations as $location)
                                    <option value="{{ $location->id }}" @selected($area->sellable_location_id === $location->id)>{{ $location->name }}</option>
                                @endforeach
                            </select>
                            <small class="subtle">Only locations ticked “Can sell from here” appear.</small>
                        </div>
                    </div>
                    <div class="button-row" style="margin-top: 16px;">
                        <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                        <button class="btn primary" type="submit">Save changes</button>
                    </div>
                </form>
            </div>
        </dialog>
    @endif

    <dialog class="dialog" id="area-dialog">
        <div class="dialog-header">
            <div>
                <h2 class="panel-title">New service area</h2>
                <p class="subtle">A named part of the floor — “Main Restaurant”, “Pool Bar”, “Terrace”, “Room Service”.</p>
            </div>
            <button class="icon-btn" type="button" data-dialog-close aria-label="Close">✕</button>
        </div>
        <div class="dialog-body">
            <form class="mini-form" method="POST" action="{{ route('admin.sales.restaurant.areas.store') }}">
                @csrf
                <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                <div class="form-grid">
                    <div class="field full"><label>Name</label><input name="name" required placeholder="Main Restaurant"></div>
                    <div class="field">
                        <label>Branch</label>
                        <select name="branch_id" required>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>Sells from</label>
                        <select name="sellable_location_id" required>
                            <option value="">Select a store…</option>
                            @foreach ($sellableLocations as $location)
                                <option value="{{ $location->id }}">{{ $location->name }}</option>
                            @endforeach
                        </select>
                        <small class="subtle">The store this area's food and drink comes out of. Only locations ticked “Can sell from here” appear.</small>
                    </div>
                </div>
                <div class="button-row" style="margin-top: 16px;">
                    <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                    <button class="btn primary" type="submit">Create area</button>
                </div>
            </form>
        </div>
    </dialog>

    <script>
        // Only one status menu open at a time, and clicking anywhere else closes it.
        (function () {
            document.addEventListener('click', function (e) {
                document.querySelectorAll('details.status-menu[open]').forEach(function (menu) {
                    if (!menu.contains(e.target)) menu.removeAttribute('open');
                });
            });
            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Escape') return;
                document.querySelectorAll('details.status-menu[open]').forEach(function (menu) {
                    menu.removeAttribute('open');
                });
            });
        })();
    </script>
</x-layouts.admin>
