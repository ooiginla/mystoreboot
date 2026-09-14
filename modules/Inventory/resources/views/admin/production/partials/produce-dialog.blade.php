@php
    $qty = fn ($value): string => \Modules\Inventory\Support\Quantity::format($value ?? 0);
    $variantLabel = fn ($variant): string => ($variant?->product?->name ?? 'Item').' / '.($variant?->variant_name ?? '');
    // The recipe's own prep station is where this is normally made, so it is the default
    // source — but not the only choice: ingredients often live in a central store.
    $stationLocationId = $recipe->prep_location_id;
    $stations = $locations->filter(fn ($l): bool => (bool) $l->is_prep_station);
    $others = $locations->reject(fn ($l): bool => (bool) $l->is_prep_station);
@endphp

<dialog class="dialog" id="produce-{{ $recipe->id }}">
    <div class="dialog-header">
        <div>
            <h2 class="panel-title">Record production</h2>
            <p class="subtle">{{ $recipe->name }} — makes {{ $qty($recipe->yield_quantity) }} {{ $recipe->yieldUnit?->code }}. Adjust the actual amounts used and produced.</p>
        </div>
        <button class="icon-btn" type="button" data-dialog-close aria-label="Close">✕</button>
    </div>
    <div class="dialog-body">
        <form class="mini-form" method="POST" action="{{ route('admin.inventory.production.record') }}">
            @csrf
            <input type="hidden" name="tenant" value="{{ $tenantParam }}">
            <input type="hidden" name="recipe_id" value="{{ $recipe->id }}">
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
                    <label>Expected yield ({{ $recipe->yieldUnit?->code ?: 'units' }})</label>
                    {{-- What the recipe says a batch makes. Shown for comparison only, so
                         the variance against what was actually produced is visible while
                         the number is being typed. --}}
                    <input type="text" value="{{ $qty($recipe->yield_quantity) }}" disabled
                           style="background:#f2f4f7; color:#475467; cursor:not-allowed;">
                </div>
                <div class="field">
                    <label>Actual yield ({{ $recipe->yieldUnit?->code ?: 'units' }})</label>
                    <input name="actual_yield_quantity" type="number" step="0.0001" min="0" value="{{ $qty($recipe->yield_quantity) }}" required>
                </div>
                <div class="field full">
                    <label>Reference</label>
                    <input name="reference_number" placeholder="optional">
                </div>
            </div>

            <h3 class="panel-title" style="margin: 18px 0 8px;">Raw materials used</h3>
            <div style="overflow-x:auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Ingredient</th>
                            <th>Available</th>
                            <th>Recipe qty</th>
                            <th>Actual used</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recipe->items as $i => $item)
                            <tr>
                                <td>
                                    {{ $variantLabel($item->componentVariant) }} <span class="subtle">{{ $item->unit?->code }}</span>
                                    <input type="hidden" name="items[{{ $i }}][component_product_variant_id]" value="{{ $item->component_product_variant_id }}">
                                    <input type="hidden" name="items[{{ $i }}][unit_id]" value="{{ $item->unit_id }}">
                                    <input type="hidden" name="items[{{ $i }}][planned_quantity]" value="{{ $qty($item->quantity) }}">
                                </td>
                                {{-- Filled in by JS from the chosen source store, and re-read
                                     whenever that changes. Turns red when the batch would
                                     take more than the shelf holds. --}}
                                <td data-produce-available
                                    data-variant="{{ $item->component_product_variant_id }}"
                                    data-needed="{{ $qty($item->quantity) }}"
                                    class="subtle">—</td>
                                <td class="subtle">{{ $qty($item->quantity) }}</td>
                                <td>
                                    <input name="items[{{ $i }}][actual_quantity]" type="number" step="0.0001" min="0"
                                           value="{{ $qty($item->quantity) }}" required style="max-width: 140px;"
                                           data-produce-used data-variant="{{ $item->component_product_variant_id }}">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="field full" style="margin-top: 12px;">
                <label>Notes</label>
                <input name="notes" placeholder="optional">
            </div>

            <div class="dialog-actions" style="margin-top: 16px;">
                <button class="btn" type="button" data-dialog-close>Cancel</button>
                <button class="btn primary" type="submit">Record production</button>
            </div>
        </form>
    </div>
</dialog>

<script>
    (function () {
        const STOCK = @json($jsStock ?? []);
        const dialog = document.getElementById('produce-{{ $recipe->id }}');
        if (! dialog) return;

        const source = dialog.querySelector('[data-produce-source]');

        function trim(value) {
            return String(Number(value).toFixed(4)).replace(/\.?0+$/, '');
        }

        function refresh() {
            const atLocation = STOCK[String(source ? source.value : '')] || {};

            dialog.querySelectorAll('[data-produce-available]').forEach(function (cell) {
                const have = Number(atLocation[String(cell.dataset.variant)] || 0);
                const usedInput = dialog.querySelector('[data-produce-used][data-variant="' + cell.dataset.variant + '"]');
                const need = usedInput && usedInput.value ? Number(usedInput.value) : Number(cell.dataset.needed || 0);

                cell.textContent = trim(have);
                // Short is not blocked here — the movement itself refuses it — but seeing
                // it before committing a batch beats a failed save.
                const short = need > have;
                cell.style.color = short ? '#b42318' : '';
                cell.style.fontWeight = short ? '800' : '';
                cell.classList.toggle('subtle', ! short);
            });
        }

        if (source) source.addEventListener('change', refresh);
        dialog.addEventListener('input', function (e) {
            if (e.target.matches && e.target.matches('[data-produce-used]')) refresh();
        });
        refresh();
    })();
</script>
