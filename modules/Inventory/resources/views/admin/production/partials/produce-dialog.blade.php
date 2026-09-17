@php
    $qty = fn ($value): string => \Modules\Inventory\Support\Quantity::format($value ?? 0);
    // Plain number for inputs and data attributes — no thousands separators.
    $num = fn ($value): string => rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.') ?: '0';
    $variantLabel = fn ($variant): string => ($variant?->product?->name ?? 'Item').' / '.($variant?->variant_name ?? '');
    // The recipe's own prep station is where this is normally made, so it is the default
    // source — but not the only choice: ingredients often live in a central store.
    $stationLocationId = $recipe->prep_location_id;
    $stations = $locations->filter(fn ($l): bool => (bool) $l->is_prep_station);
    $others = $locations->reject(fn ($l): bool => (bool) $l->is_prep_station);
    $yieldUnit = $recipe->yieldUnit?->code ?: 'pc';
    $yield = (float) $recipe->yield_quantity;
@endphp

<dialog class="dialog" id="produce-{{ $recipe->id }}">
    <style>
        #produce-{{ $recipe->id }} .plan-box { display: grid; grid-template-columns: minmax(180px, 260px) 1fr; gap: 12px; align-items: end; padding: 12px 14px; border: 2px solid #c7d7fe; background: #f5f8ff; border-radius: 12px; margin-bottom: 14px; }
        #produce-{{ $recipe->id }} .plan-box input { font-size: 1.15rem; font-weight: 800; }
        #produce-{{ $recipe->id }} .plan-note { font-size: .88rem; color: #3538cd; font-weight: 650; }
        #produce-{{ $recipe->id }} .is-short { color: #b42318; font-weight: 800; }
        #produce-{{ $recipe->id }} .own-figure { box-shadow: inset 3px 0 0 #f79009; }
        @media (max-width: 640px) { #produce-{{ $recipe->id }} .plan-box { grid-template-columns: 1fr; } }
    </style>
    <div class="dialog-header">
        <div>
            <h2 class="panel-title">Record production</h2>
            <p class="subtle">{{ $recipe->name }} — the recipe makes {{ $qty($yield) }} {{ $yieldUnit }}. Say how many you are making and the ingredients are worked out for you.</p>
        </div>
        <button class="icon-btn" type="button" data-dialog-close aria-label="Close">✕</button>
    </div>
    <div class="dialog-body">
        <form class="mini-form" method="POST" action="{{ route('admin.inventory.production.record') }}" data-produce-form data-yield="{{ $num($yield) }}">
            @csrf
            <input type="hidden" name="tenant" value="{{ $tenantParam }}">
            <input type="hidden" name="recipe_id" value="{{ $recipe->id }}">

            {{-- The plan comes first: everything below is scaled from it. --}}
            <div class="plan-box">
                <div class="field" style="margin:0;">
                    <label for="produce-plan-{{ $recipe->id }}">How many to make ({{ $yieldUnit }})</label>
                    <input id="produce-plan-{{ $recipe->id }}" name="planned_quantity" type="number" step="any" min="0.0001" value="{{ $num($yield) }}" required data-plan>
                </div>
                <div class="plan-note" data-plan-note></div>
            </div>

            <div class="form-grid">
                <div class="field">
                    <label>Take raw materials from</label>
                    <select name="source_location_id" data-produce-source="{{ $recipe->id }}" required>
                        @if ($stations->isNotEmpty())
                            <optgroup label="Prep stations">
                                @foreach ($stations as $location)
                                    <option value="{{ $location->id }}" @selected($stationLocationId === $location->id)>{{ $location->name }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                        @if ($others->isNotEmpty())
                            <optgroup label="Other stores">
                                @foreach ($others as $location)
                                    <option value="{{ $location->id }}" @selected($stationLocationId === $location->id)>{{ $location->name }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                    </select>
                    <small class="subtle">Where the ingredients are drawn from — usually this recipe's prep station, but a central store is fine.</small>
                </div>
                <div class="field">
                    <label>Put finished goods in</label>
                    <select name="output_location_id">
                        <option value="">Same as source</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}">{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Expected yield ({{ $yieldUnit }})</label>
                    {{-- Follows the plan. Shown for comparison only, so the variance against
                         what actually came out is visible while it is typed. --}}
                    <input type="text" value="{{ $qty($yield) }}" disabled data-expected
                           style="background:#f2f4f7; color:#475467; cursor:not-allowed;">
                </div>
                <div class="field">
                    <label>Actual yield ({{ $yieldUnit }})</label>
                    <input name="actual_yield_quantity" type="number" step="any" min="0" value="{{ $num($yield) }}" required data-actual-yield>
                    <small class="subtle">Change it only if the batch came out different — three pies burnt, a tray short.</small>
                </div>
                <div class="field full">
                    <label>Reference</label>
                    <input name="reference_number" placeholder="optional">
                </div>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:baseline; gap:10px; margin: 18px 0 8px;">
                <h3 class="panel-title" style="margin:0;">Raw materials used</h3>
                <button class="btn ghost" type="button" data-reset-plan style="padding:4px 10px;">Reset to plan</button>
            </div>
            <div style="overflow-x:auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Ingredient</th>
                            <th>Available</th>
                            <th data-needed-head>Needed for {{ $qty($yield) }}</th>
                            <th>Actual used</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recipe->items as $i => $item)
                            @php
                                // Per batch, including the recipe's expected waste — the same
                                // amount a sale of this item would deplete.
                                $perBatch = (float) $item->quantity * (1 + (float) $item->wastage_percent / 100);
                                // Stock is held in the base unit; the recipe line may be in kg.
                                $factor = $item->unit && $item->unit->isConvertible() ? (float) $item->unit->to_base_factor : 1.0;
                                $unitCode = $item->unit?->code ?? ($item->componentVariant?->baseUnit?->code ?? 'pc');
                            @endphp
                            <tr data-ingredient
                                data-variant="{{ $item->component_product_variant_id }}"
                                data-per-batch="{{ $num($perBatch) }}"
                                data-factor="{{ $num($factor) }}"
                                data-unit="{{ $unitCode }}">
                                <td>
                                    {{ $variantLabel($item->componentVariant) }}
                                    @if ((float) $item->wastage_percent > 0)
                                        <div class="subtle" style="font-size:.76rem;">incl. {{ $qty($item->wastage_percent) }}% waste</div>
                                    @endif
                                    <input type="hidden" name="items[{{ $i }}][component_product_variant_id]" value="{{ $item->component_product_variant_id }}">
                                    <input type="hidden" name="items[{{ $i }}][unit_id]" value="{{ $item->unit_id }}">
                                    <input type="hidden" name="items[{{ $i }}][planned_quantity]" value="{{ $num($perBatch) }}" data-planned>
                                </td>
                                {{-- Filled in from the chosen source store, in this ingredient's
                                     own unit. Red when the plan needs more than the shelf holds. --}}
                                <td data-available class="subtle">—</td>
                                <td data-needed>{{ $qty($perBatch) }} {{ $unitCode }}</td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:6px;">
                                        <input name="items[{{ $i }}][actual_quantity]" type="number" step="any" min="0"
                                               value="{{ $num($perBatch) }}" required style="max-width: 130px;" data-used>
                                        <span class="subtle">{{ $unitCode }}</span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="subtle" style="font-size:.8rem; margin:6px 0 0;">
                Actual amounts follow the plan until you type your own — rows you changed are marked on the left and keep your figure.
            </p>

            <div class="field full" style="margin-top: 12px;">
                <label>Notes</label>
                <input name="notes" placeholder="optional">
            </div>

            <div class="dialog-actions" style="margin-top: 16px; flex-wrap: wrap;">
                <button class="btn" type="button" data-dialog-close>Cancel</button>
                {{-- Starting only needs the plan and the store; the actual figures come at completion. --}}
                <button class="btn secondary" type="submit" formnovalidate data-produce-start
                        formaction="{{ route('admin.inventory.production.start') }}">Start production</button>
                <button class="btn primary" type="submit" data-produce-submit>Record as finished now</button>
            </div>
            <p class="subtle" style="font-size:.8rem; margin:8px 0 0; text-align:right;">
                <strong>Start production</strong> holds the ingredients now and deducts what was really used when you complete it.
                <strong>Record as finished now</strong> deducts everything straight away.
            </p>
        </form>
    </div>
</dialog>

<script>
    (function () {
        const STOCK = @json($jsStock ?? []);
        const dialog = document.getElementById('produce-{{ $recipe->id }}');
        if (! dialog) return;

        const form = dialog.querySelector('[data-produce-form]');
        const source = dialog.querySelector('[data-produce-source]');
        const plan = dialog.querySelector('[data-plan]');
        const note = dialog.querySelector('[data-plan-note]');
        const expected = dialog.querySelector('[data-expected]');
        const actualYield = dialog.querySelector('[data-actual-yield]');
        const neededHead = dialog.querySelector('[data-needed-head]');
        const submit = dialog.querySelector('[data-produce-submit]');
        const yieldPerBatch = Number(form.dataset.yield) || 1;
        const unit = @json($yieldUnit);

        const round = (n) => Math.round(n * 10000) / 10000;
        const trim = (n) => String(round(n));
        const show = (n) => round(n).toLocaleString(undefined, { maximumFractionDigits: 4 });

        function planned() {
            return Math.max(0, Number(plan.value) || 0);
        }

        // Scale every line from the plan. Lines the cook has typed over keep their figure.
        function applyPlan() {
            const make = planned();
            const scale = make / yieldPerBatch;

            note.textContent = make <= 0
                ? 'Enter how many you are making.'
                : (yieldPerBatch === 1
                    ? `Recipe is for 1 ${unit} — ingredients × ${show(make)}`
                    : `= ${show(scale)} batch${scale === 1 ? '' : 'es'} of ${show(yieldPerBatch)} ${unit}`);
            expected.value = show(make);
            neededHead.textContent = `Needed for ${show(make)}`;
            if (! actualYield.dataset.own) actualYield.value = trim(make);

            dialog.querySelectorAll('[data-ingredient]').forEach(function (row) {
                const need = Number(row.dataset.perBatch) * scale;
                row.querySelector('[data-needed]').textContent = `${show(need)} ${row.dataset.unit}`;
                row.querySelector('[data-planned]').value = trim(need);
                const used = row.querySelector('[data-used]');
                if (! used.dataset.own) used.value = trim(need);
            });

            submit.disabled = make <= 0;
            const startButton = dialog.querySelector('[data-produce-start]');
            if (startButton) startButton.disabled = make <= 0;
            refreshAvailability();
        }

        // What the chosen store holds, in each ingredient's own unit, against what will be used.
        function refreshAvailability() {
            const atLocation = STOCK[String(source ? source.value : '')] || {};

            dialog.querySelectorAll('[data-ingredient]').forEach(function (row) {
                const factor = Number(row.dataset.factor) || 1;
                const have = Number(atLocation[String(row.dataset.variant)] || 0) / factor;
                const used = Number(row.querySelector('[data-used]').value) || 0;
                const cell = row.querySelector('[data-available]');
                const short = used > have + 0.00001;

                cell.textContent = `${show(have)} ${row.dataset.unit}`;
                // Not blocked here — the movement itself refuses it — but seeing it before
                // committing a batch beats a failed save.
                cell.classList.toggle('is-short', short);
                cell.classList.toggle('subtle', ! short);
            });
        }

        plan.addEventListener('input', applyPlan);
        if (source) source.addEventListener('change', refreshAvailability);

        dialog.addEventListener('input', function (e) {
            if (e.target.matches('[data-used]')) {
                e.target.dataset.own = '1';
                e.target.closest('td').classList.add('own-figure');
                refreshAvailability();
            } else if (e.target.matches('[data-actual-yield]')) {
                actualYield.dataset.own = '1';
            }
        });

        dialog.querySelector('[data-reset-plan]').addEventListener('click', function () {
            dialog.querySelectorAll('[data-used]').forEach(function (input) {
                delete input.dataset.own;
                input.closest('td').classList.remove('own-figure');
            });
            delete actualYield.dataset.own;
            applyPlan();
        });

        applyPlan();
    })();
</script>
