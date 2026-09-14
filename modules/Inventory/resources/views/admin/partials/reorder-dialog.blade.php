{{--
    Reorder levels for one item at every location. Open with
    window.__reorderOpen(variantId, label), or pick the item inside the dialog when
    $reorderPicker is true. Expects $tenant, $reorderLocations, $reorderUnits and
    $reorderLevels (see Modules\Inventory\Support\ReorderLevels).
--}}
@php
    $reorderPicker = $reorderPicker ?? false;
    $reorderFragment = $reorderFragment ?? null;
@endphp

<dialog class="dialog" id="reorder-dialog">
    <style>
        #reorder-dialog { width: min(760px, 96vw); }
        #reorder-dialog .reorder-item-name { font-weight: 800; color: #101828; }
        #reorder-dialog table input[type="number"] { width: 110px; }
        #reorder-dialog .reorder-chip { display: inline-block; padding: 3px 10px; border-radius: 999px; font-weight: 800; font-size: 12px; white-space: nowrap; }
        #reorder-dialog .reorder-chip.low { background: #fef0c7; color: #b54708; }
        #reorder-dialog .reorder-chip.ok { background: #dcfae6; color: #067647; }
        #reorder-dialog .reorder-chip.unset { background: #f2f4f7; color: #475467; }
    </style>
    <div class="dialog-header">
        <div>
            <h2 class="panel-title">Low-stock alert levels</h2>
            <p class="subtle" data-reorder-item>Choose an item to see its levels at every location.</p>
        </div>
        <button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button>
    </div>
    <div class="dialog-body">
        <form class="mini-form" method="POST" action="{{ route('admin.inventory.reorder.save') }}" data-reorder-form>
            @csrf
            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
            @if ($reorderFragment)
                <input type="hidden" name="fragment" value="{{ $reorderFragment }}">
            @endif

            <div class="form-grid">
                @if ($reorderPicker)
                    <x-variant-picker label="Item" name="reorder_variant" class="full" enhanced />
                @endif
                <div class="field" data-reorder-unit-field hidden>
                    <label for="reorder-unit">Enter quantities in</label>
                    <select id="reorder-unit" data-reorder-unit></select>
                </div>
            </div>

            <div style="overflow-x:auto;" data-reorder-table hidden>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Location</th>
                            <th>Available now</th>
                            <th>Alert when at or below</th>
                            <th>Reorder quantity</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reorderLocations as $reorderLocation)
                            <tr data-reorder-location="{{ $reorderLocation->id }}">
                                <td>
                                    {{ $reorderLocation->name }}
                                    <input type="hidden" name="rows[{{ $loop->index }}][inventory_location_id]" value="{{ $reorderLocation->id }}">
                                    <input type="hidden" name="rows[{{ $loop->index }}][product_variant_id]" data-reorder-variant>
                                    <input type="hidden" name="rows[{{ $loop->index }}][unit_id]" data-reorder-unit-id>
                                </td>
                                <td><strong data-reorder-available>—</strong></td>
                                <td><input type="number" min="0" step="any" name="rows[{{ $loop->index }}][reorder_level]" placeholder="0" data-reorder-level aria-label="Alert level at {{ $reorderLocation->name }}"></td>
                                <td><input type="number" min="0" step="any" name="rows[{{ $loop->index }}][reorder_quantity]" placeholder="0" data-reorder-qty aria-label="Reorder quantity at {{ $reorderLocation->name }}"></td>
                                <td><span class="reorder-chip unset" data-reorder-status>Not monitored</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="subtle" style="margin: 10px 0 0;">
                Leave a level blank or 0 to stop monitoring at that location.
                <a href="{{ route('admin.inventory.reorder-levels.index', array_filter(['tenant' => request('tenant')])) }}">Set many items at once for one location →</a>
            </p>

            <div class="button-row">
                <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                <button class="btn primary" type="submit" data-reorder-save disabled>Save levels</button>
            </div>
        </form>
    </div>

    <script>
        (function () {
            const dialog = document.getElementById('reorder-dialog');
            const form = dialog && dialog.querySelector('[data-reorder-form]');
            if (! form) return;

            const UNITS = @json($reorderUnits ?? []);
            const LEVELS = @json($reorderLevels ?? []);
            const LABELS = { low: 'Low', ok: 'OK', unset: 'Not monitored' };
            const fmt = (n) => String(Math.round(n * 10000) / 10000);
            const rows = Array.from(form.querySelectorAll('[data-reorder-location]'));
            const unitSel = form.querySelector('[data-reorder-unit]');
            const unitField = form.querySelector('[data-reorder-unit-field]');
            const table = form.querySelector('[data-reorder-table]');
            const save = form.querySelector('[data-reorder-save]');
            const itemLabel = dialog.querySelector('[data-reorder-item]');
            let unit = { id: null, code: '', factor: 1 };

            function paint(row) {
                const available = parseFloat(row.dataset.available) || 0;
                const level = (parseFloat(row.querySelector('[data-reorder-level]').value) || 0) * unit.factor;
                const status = level <= 0 ? 'unset' : (available <= level ? 'low' : 'ok');
                row.querySelector('[data-reorder-available]').textContent = fmt(available / unit.factor) + ' ' + unit.code;
                const chip = row.querySelector('[data-reorder-status]');
                chip.className = 'reorder-chip ' + status;
                chip.textContent = LABELS[status];
            }

            function load(variantId, label) {
                const units = UNITS[variantId];
                if (! units) {
                    table.hidden = true;
                    unitField.hidden = true;
                    save.disabled = true;
                    return;
                }
                unit = units.find((u) => u.factor === 1) || units[0];
                unitSel.innerHTML = units.map((u, i) => `<option value="${i}">${u.code}</option>`).join('');
                unitSel.value = String(units.indexOf(unit));
                unitSel.dataset.variant = variantId;
                unitField.hidden = units.length < 2;

                const levels = LEVELS[variantId] || {};
                rows.forEach(function (row) {
                    const current = levels[row.dataset.reorderLocation] || { level: 0, qty: 0, available: 0 };
                    row.dataset.available = current.available;
                    row.querySelector('[data-reorder-variant]').value = variantId;
                    row.querySelector('[data-reorder-unit-id]').value = unit.id ?? '';
                    row.querySelector('[data-reorder-level]').value = current.level > 0 ? fmt(current.level / unit.factor) : '';
                    row.querySelector('[data-reorder-qty]').value = current.qty > 0 ? fmt(current.qty / unit.factor) : '';
                    paint(row);
                });

                if (label) itemLabel.innerHTML = '<span class="reorder-item-name"></span>';
                if (label) itemLabel.firstChild.textContent = label;
                table.hidden = false;
                save.disabled = false;
            }

            // Switching unit re-expresses what is typed, so 2500 g becomes 2.5 kg.
            unitSel.addEventListener('change', function () {
                const next = (UNITS[unitSel.dataset.variant] || [])[parseInt(unitSel.value, 10)];
                if (! next) return;
                rows.forEach(function (row) {
                    row.querySelectorAll('[data-reorder-level], [data-reorder-qty]').forEach(function (input) {
                        if (input.value !== '') input.value = fmt(parseFloat(input.value) * unit.factor / next.factor);
                    });
                    row.querySelector('[data-reorder-unit-id]').value = next.id ?? '';
                });
                unit = next;
                rows.forEach(paint);
            });

            rows.forEach(function (row) {
                row.querySelector('[data-reorder-level]').addEventListener('input', function () { paint(row); });
            });

            const picker = form.querySelector('[data-variant-picker]');
            function fromPicker() {
                const id = picker.querySelector('[data-variant-value]').value;
                if (id) load(id, picker.querySelector('[data-variant-search]').value);
            }
            if (picker) {
                form.addEventListener('input', function (e) { if (e.target.closest('[data-variant-search]')) setTimeout(fromPicker, 0); });
                form.addEventListener('change', function (e) { if (e.target.closest('[data-variant-search]')) fromPicker(); });
            }

            window.__reorderOpen = function (variantId, label) {
                if (picker) {
                    picker.querySelector('[data-variant-value]').value = variantId;
                    picker.querySelector('[data-variant-search]').value = label || '';
                }
                load(String(variantId), label);
                if (typeof dialog.showModal === 'function') dialog.showModal(); else dialog.setAttribute('open', '');
            };
        })();
    </script>
</dialog>
