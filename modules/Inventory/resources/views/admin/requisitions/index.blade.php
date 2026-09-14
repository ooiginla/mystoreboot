@php
    use Modules\Inventory\Enums\RequisitionStatus;
    $qty = fn ($value): string => \Modules\Inventory\Support\Quantity::format($value ?? 0);
    // A variant named after its own product, or called "Default", adds nothing to read.
    $variantLabel = function ($variant): string {
        $product = trim((string) ($variant?->product?->name ?? 'Item'));
        $name = trim((string) ($variant?->variant_name ?? ''));

        return ($name === '' || strcasecmp($name, 'default') === 0 || strcasecmp($name, $product) === 0)
            ? $product
            : $product.' — '.$name;
    };
    $tenantParam = request('tenant');
    $badge = fn (RequisitionStatus $s): string => match ($s) {
        RequisitionStatus::Fulfilled, RequisitionStatus::Approved => 'success',
        RequisitionStatus::Rejected, RequisitionStatus::Cancelled => 'neutral',
        default => 'warning',
    };
@endphp

<x-layouts.admin title="Requisitions">
    <style>
        /* Top-aligned, not bottom-aligned: the availability note under Qty makes that cell
           taller, and aligning to the bottom dragged every other label down with it. */
        .ing-row { display: grid; grid-template-columns: 1fr 2fr 1fr 1fr auto; gap: 8px; align-items: start; margin-bottom: 8px; }
        .ing-row .field { margin: 0; }
        /* Reserve the note's line so the row does not jump as items are picked. */
        .ing-row [data-req-avail] { display: block; min-height: 2.4em; line-height: 1.2; }
        /* Drop the remove button past the label so it sits level with the inputs. */
        .ing-row [data-remove-row] { align-self: start; margin-top: 25px; }
        .req-table td { vertical-align: middle; }
        .req-num { text-align: right; font-variant-numeric: tabular-nums; }
        .req-short { color: #b54708; font-weight: 700; font-size: .8rem; }
        .req-actions { display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end; }
        .req-actions form { margin: 0; }
        .req-actions-col { text-align: right; }
        .req-reference { appearance: none; border: 0; background: transparent; color: var(--ink); padding: 0; cursor: pointer; text-align: left; }
        .req-reference strong { color: var(--brand-strong); text-decoration: underline; text-decoration-color: transparent; text-underline-offset: 3px; transition: text-decoration-color .15s; }
        .req-reference .subtle { display: block; margin-top: 2px; }
        .req-reference:hover strong { text-decoration-color: currentColor; }
        .req-reference:focus-visible { outline: 3px solid var(--brand-ring); outline-offset: 3px; border-radius: 4px; }
        .req-detail-summary { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-bottom: 20px; }
        .req-detail-summary > div { border: 1px solid var(--line); border-radius: 8px; padding: 12px 14px; }
        .req-detail-summary > div > span:first-child { display: block; margin-bottom: 4px; color: var(--muted); font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
        .req-detail-heading { margin: 0 0 10px; font-size: 16px; }
        .req-detail-note { margin-top: 18px; }
        @media (max-width: 700px) { .req-detail-summary { grid-template-columns: 1fr; } }
        @media (max-width: 900px) { .ing-row { grid-template-columns: 1fr 1fr; } }
    </style>

    <div class="topbar">
        <div>
            <div class="eyebrow">Inventory movement</div>
            <h1>Requisitions</h1>
            <p class="subtle">Request stock between stores, approve, and fulfil with a transfer for {{ $tenant->name }}.</p>
        </div>
        @if ($isPlatformAdmin)
            <form method="GET" action="{{ route('admin.inventory.requisitions.index') }}" style="min-width: 260px;">
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
        <button class="btn primary" type="button" data-dialog-open="requisition-dialog">New requisition</button>
    </div>

    <section class="panel">
        <div class="panel-header"><h2 class="panel-title">Requisitions</h2></div>
        <div class="panel-body">
            <table class="table req-table">
                <thead>
                    <tr>
                        <th>Requisition</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Status</th>
                        <th class="req-actions-col"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requisitions as $req)
                        <tr>
                            <td>
                                <button class="req-reference" type="button" data-dialog-open="requisition-detail-{{ $req->id }}" aria-haspopup="dialog">
                                    <strong>{{ $req->requisition_number }}</strong>
                                    <span class="subtle">{{ $req->created_at?->format('d M Y') }}</span>
                                </button>
                            </td>
                            <td>{{ $req->sourceLocation?->name ?? '—' }}</td>
                            <td>
                                {{ $req->destinationLocation?->name ?? '—' }}
                                @if ($req->status === RequisitionStatus::Approved)
                                    <div class="subtle">Stock held at {{ $req->sourceLocation?->name }} until fulfilled or cancelled.</div>
                                @endif
                            </td>
                            <td><span class="badge {{ $badge($req->status) }}">{{ $req->status->label() }}</span></td>
                            <td>
                                <div class="req-actions">
                                    @if ($req->status === RequisitionStatus::Submitted)
                                        <form method="POST" action="{{ route('admin.inventory.requisitions.approve', $req) }}">@csrf<input type="hidden" name="tenant" value="{{ $tenantParam }}"><button class="btn accent" type="submit">Approve</button></form>
                                        <form method="POST" action="{{ route('admin.inventory.requisitions.reject', $req) }}">@csrf<input type="hidden" name="tenant" value="{{ $tenantParam }}"><button class="btn ghost" type="submit">Reject</button></form>
                                    @elseif ($req->status === RequisitionStatus::Approved)
                                        <form method="POST" action="{{ route('admin.inventory.requisitions.fulfil', $req) }}" onsubmit="return confirm('Transfer this stock now?');">@csrf<input type="hidden" name="tenant" value="{{ $tenantParam }}"><button class="btn primary" type="submit">Fulfil &amp; transfer</button></form>
                                        <form method="POST" action="{{ route('admin.inventory.requisitions.cancel', $req) }}">@csrf<input type="hidden" name="tenant" value="{{ $tenantParam }}"><button class="btn ghost" type="submit">Cancel</button></form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty">No requisitions yet.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @foreach ($requisitions as $req)
        <dialog class="dialog" id="requisition-detail-{{ $req->id }}" data-requisition-detail="{{ $req->id }}">
            <div class="dialog-header">
                <div>
                    <h2 class="panel-title">{{ $req->requisition_number }}</h2>
                    <p class="subtle">Created {{ $req->created_at?->format('d M Y') }}</p>
                </div>
                <button class="icon-btn" type="button" data-dialog-close aria-label="Close">✕</button>
            </div>
            <div class="dialog-body">
                <div class="req-detail-summary">
                    <div><span>From</span><strong>{{ $req->sourceLocation?->name ?? '—' }}</strong></div>
                    <div><span>To</span><strong>{{ $req->destinationLocation?->name ?? '—' }}</strong></div>
                    <div><span>Status</span><span class="badge {{ $badge($req->status) }}">{{ $req->status->label() }}</span></div>
                </div>

                <h3 class="req-detail-heading">Item breakdown</h3>
                <div style="overflow-x:auto;">
                    <table class="table req-detail-items">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th class="req-num">Quantity</th>
                                <th>Unit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($req->items as $item)
                                @php
                                    $short = $req->status === RequisitionStatus::Fulfilled
                                        && (float) $item->fulfilled_quantity !== (float) $item->requested_quantity;
                                @endphp
                                <tr>
                                    <td>{{ $variantLabel($item->componentVariant) }}</td>
                                    <td class="req-num">
                                        {{ $qty($item->requested_quantity) }}
                                        @if ($short)
                                            <div class="req-short">sent {{ $qty($item->fulfilled_quantity) }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $item->unit?->code ?: 'each' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3"><div class="empty">No items on this requisition.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($req->notes)
                    <div class="req-detail-note">
                        <h3 class="req-detail-heading">Notes</h3>
                        <p>{{ $req->notes }}</p>
                    </div>
                @endif

                <div class="dialog-actions">
                    <button class="btn secondary" type="button" data-dialog-close>Close</button>
                </div>
            </div>
        </dialog>
    @endforeach

    @include('inventory::admin.requisitions.partials.requisition-dialog')

    <script>
        (function () {
            const container = document.getElementById('req-items');
            const addBtn = document.getElementById('req-add-item');
            if (!container || !addBtn) return;
            let index = container.querySelectorAll('.ing-row').length;
            const template = document.getElementById('req-item-template');
            addBtn.addEventListener('click', function () {
                const wrap = document.createElement('div');
                wrap.innerHTML = template.innerHTML.replace(/__INDEX__/g, index);
                container.appendChild(wrap.firstElementChild);
                index++;
            });
            container.addEventListener('click', function (e) {
                const remove = e.target.closest('[data-remove-row]');
                if (remove) { remove.closest('.ing-row').remove(); }
            });
        })();
    </script>

    <script>
        (function () {
            const UNITS = @json($jsUnits);
            const ITEMS = @json($jsItems);
            const VARIANT_CAT = @json($jsVariantCat);
            const STOCK = @json($jsStock);
            const VARIANT_UNIT = @json($jsVariantUnit);

            // What the chosen source store actually holds, shown as the line is picked so
            // nobody asks for 50kg of rice from a store holding 12.
            function sourceLocationId() {
                const select = document.querySelector('[name="source_location_id"]');
                return select ? select.value : '';
            }

            function availableFor(variantId) {
                const atLocation = STOCK[String(sourceLocationId())];
                if (!atLocation) return null;
                const value = atLocation[String(variantId)];
                return value === undefined ? 0 : value;
            }

            function trimQty(value) {
                return String(Number(value).toFixed(4)).replace(/\.?0+$/, '');
            }

            function refreshAvailability(row) {
                const note = row.querySelector('[data-req-avail]');
                const itemSel = row.querySelector('[data-req-product]');
                const qtyInput = row.querySelector('[data-req-qty]');
                if (!note || !itemSel) return;

                if (!itemSel.value) { note.textContent = ''; note.style.color = ''; return; }

                const available = availableFor(itemSel.value);
                if (available === null) {
                    note.textContent = 'Pick a source store to see availability.';
                    note.style.color = '';
                    return;
                }

                const unit = VARIANT_UNIT[itemSel.value];
                const suffix = (!unit || unit === 'ea') ? '' : ' ' + unit;
                const wanted = qtyInput && qtyInput.value ? Number(qtyInput.value) : 0;

                note.textContent = 'Source has ' + trimQty(available) + suffix + ' available';
                // Warn on over-ask, but never block: the shortfall may be deliberate.
                note.style.color = (wanted > 0 && wanted > available) ? '#b42318' : '';
                if (wanted > available && wanted > 0) {
                    note.textContent += ' — more than you are asking for is not in stock';
                }
            }

            function refreshAllAvailability() {
                document.querySelectorAll('.ing-row').forEach(refreshAvailability);
            }

            document.addEventListener('change', function (e) {
                if (!e.target.matches) return;
                if (e.target.matches('[name="source_location_id"]')) refreshAllAvailability();
            });
            document.addEventListener('input', function (e) {
                if (!e.target.matches) return;
                if (e.target.matches('[data-req-qty]') || e.target.matches('[data-req-product]')) {
                    const row = e.target.closest('.ing-row');
                    if (row) refreshAvailability(row);
                }
            });

            function applyUnits(select, categoryId) {
                const matches = categoryId ? UNITS.filter((u) => String(u.cat) === String(categoryId)) : [];
                const current = select.value;
                if (matches.length) {
                    select.innerHTML = matches.map((u) => `<option value="${u.id}">${u.code}</option>`).join('');
                    if (matches.some((u) => String(u.id) === String(current))) select.value = current;
                } else {
                    select.innerHTML = '<option value="">each</option>';
                }
            }

            function fillItems(select, type) {
                const current = select.value;
                select.innerHTML = '';
                select.add(new Option('Select…', ''));
                ITEMS.filter((i) => ! type || String(i.type) === String(type)).forEach((i) => {
                    select.add(new Option(i.label, i.id));
                });
                select.value = current;
            }

            function fillRow(typeSelect) {
                const row = typeSelect.closest('.ing-row');
                const itemSel = row && row.querySelector('[data-req-product]');
                if (itemSel) fillItems(itemSel, typeSelect.value);
            }

            document.querySelectorAll('[data-req-type]').forEach(fillRow);
            refreshAllAvailability();

            const addBtn = document.getElementById('req-add-item');
            if (addBtn) {
                addBtn.addEventListener('click', function () {
                    document.querySelectorAll('[data-req-product]').forEach(function (sel) {
                        if (! sel.options.length) fillRow(sel.closest('.ing-row').querySelector('[data-req-type]'));
                    });
                    refreshAllAvailability();
                });
            }

            document.addEventListener('change', function (e) {
                const t = e.target;
                if (! t.matches) return;
                if (t.matches('[data-req-type]')) {
                    fillRow(t);
                } else if (t.matches('[data-req-product]')) {
                    const row = t.closest('.ing-row');
                    const unitSel = row && row.querySelector('[data-req-unit]');
                    if (unitSel) applyUnits(unitSel, VARIANT_CAT[t.value]);
                    if (row) refreshAvailability(row);
                }
            });
        })();
    </script>
</x-layouts.admin>
