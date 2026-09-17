@php
    $money = fn (?int $minor): string => number_format(($minor ?? 0) / 100, 2);
    $qty = fn ($value): string => \Modules\Inventory\Support\Quantity::format($value ?? 0);
    $variantLabel = fn ($v): string => ($v?->product?->name ?? 'Item').' / '.($v?->variant_name ?? '');
    $tenantParam = request('tenant');
@endphp

<x-layouts.admin title="Finished product">
    <div class="topbar">
        <div>
            <div class="eyebrow"><a href="{{ route('admin.inventory.production.index', array_filter(['tenant' => $tenantParam ?: null])) }}">← Finished products</a></div>
            <h1>{{ $product->name }}</h1>
            <p class="subtle">{{ $product->product_type?->label() }} · {{ $variant->sku }}@if ($product->unitCategory) · {{ $product->unitCategory->name }}@endif</p>
        </div>
    </div>

    @if (session('status'))<div class="alert success">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="alert errors">{{ $errors->first() }}</div>@endif

    @if ($inProgress->isNotEmpty())
        {{-- Batches on the go: the first thing to see, so none is forgotten with its
             ingredients still held. --}}
        <section class="panel runs-panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">In progress <span class="run-count">{{ $inProgress->count() }}</span></h2>
                    <p class="subtle">Their ingredients are held at the source store — nothing is deducted until you complete them.</p>
                </div>
            </div>
            <div class="panel-body">
                @foreach ($inProgress as $run)
                    <div class="run-row">
                        <div>
                            <div class="run-title">{{ $qty($run->planned_quantity) }} {{ $recipe?->yieldUnit?->code ?: 'pc' }} planned</div>
                            <div class="subtle">
                                Started {{ $run->started_at?->format('M j, H:i') }} ({{ $run->started_at?->diffForHumans() }}) ·
                                from {{ $run->sourceLocation?->name }}
                                @if ($run->reference_number) · {{ $run->reference_number }} @endif
                            </div>
                            <div class="run-held">
                                Holding:
                                {{ $run->items->map(fn ($i) => ($i->componentVariant?->product?->name ?? 'Item').' '.$qty($i->planned_quantity).($i->unit ? ' '.$i->unit->code : ''))->implode(' · ') ?: 'nothing' }}
                            </div>
                        </div>
                        <div class="run-actions">
                            @permission('inventory.manage')
                            <button class="btn primary" type="button" data-dialog-open="complete-run-{{ $run->id }}">Complete</button>
                            <form method="POST" action="{{ route('admin.inventory.production.runs.cancel', $run) }}"
                                  onsubmit="return confirm('Cancel this batch? The held ingredients become available again.');">
                                @csrf
                                <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                                <button class="btn danger" type="submit">Cancel</button>
                            </form>
                            @endpermission
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <div class="tab-layout">
        <nav class="pill-nav" aria-label="Finished product sections" role="tablist">
            <a href="#recipe" role="tab" data-tab-target="recipe">Recipe</a>
            <a href="#production" role="tab" data-tab-target="production">Recent production &amp; yield</a>
        </nav>

        <div class="content-stack">
            <section class="panel tab-panel" id="recipe" role="tabpanel" data-tab-panel>
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Recipe</h2>
                        @if ($recipe)
                            <h3 class="recipe-name">{{ $recipe->name }}</h3>
                        @endif
                    </div>
                    <div style="display:flex; gap:8px;">
                        @if ($recipe)
                            <button class="btn primary" type="button" data-dialog-open="recipe-dialog">Edit recipe</button>
                            <button class="btn accent" type="button" data-dialog-open="produce-{{ $recipe->id }}">Record production</button>
                            <form method="POST" action="{{ route('admin.inventory.production.recipes.destroy', $recipe->id) }}" onsubmit="return confirm('Remove this recipe?');">
                                @csrf @method('DELETE')
                                <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                                <button class="btn danger recipe-remove-button" type="submit">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M3 6h18"/>
                                        <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                                    </svg>
                                    <span>Remove recipe</span>
                                </button>
                            </form>
                        @else
                            <button class="btn primary" type="button" data-dialog-open="recipe-dialog">Add recipe</button>
                        @endif
                    </div>
                </div>
                <div class="panel-body">
                    @php
                        $policy = $product->stock_policy ?? \Modules\Catalog\Enums\StockPolicy::Tracked;
                        $madeToOrder = $policy === \Modules\Catalog\Enums\StockPolicy::Recipe;
                    @endphp
                    {{-- The single setting that decides when the recipe is consumed. It is
                         phrased as a question about the kitchen, not as a stock policy. --}}
                    <div class="how-made">
                        <div class="how-made-title">How is this made?</div>
                        <div class="how-made-options">
                            <form method="POST" action="{{ route('admin.inventory.production.stock-policy', $product) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                                <input type="hidden" name="stock_policy" value="tracked">
                                <button class="how-made-option {{ ! $madeToOrder ? 'on' : '' }}" type="submit" @disabled(! $madeToOrder)>
                                    <span class="how-made-mark">{{ ! $madeToOrder ? '●' : '○' }}</span>
                                    <span>
                                        <strong>Cooked in batches ahead of time</strong>
                                        <em>You record production, it becomes stock, and selling draws that stock down.</em>
                                    </span>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.inventory.production.stock-policy', $product) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                                <input type="hidden" name="stock_policy" value="recipe">
                                <button class="how-made-option {{ $madeToOrder ? 'on' : '' }}" type="submit" @disabled($madeToOrder)>
                                    <span class="how-made-mark">{{ $madeToOrder ? '●' : '○' }}</span>
                                    <span>
                                        <strong>Made to order</strong>
                                        <em>Nothing is stocked; ordering it takes the ingredients out of the kitchen.</em>
                                    </span>
                                </button>
                            </form>
                        </div>
                        @unless ($recipe)
                            <p class="subtle" style="margin: 10px 0 0;">Add a recipe below so there is something to consume either way.</p>
                        @endunless
                    </div>

                    @if ($recipe)
                        <p class="subtle">Makes {{ $qty($recipe->yield_quantity) }} {{ $recipe->yieldUnit?->code }}@if ($recipe->prepStation) · {{ $recipe->prepStation->name }}@endif · Batch cost ≈ {{ $tenant->currency_code }} {{ $money($recipeCostMinor) }}@if ($recipe->shelf_life_days !== null) · Keeps {{ $recipe->shelf_life_days }} {{ \Illuminate\Support\Str::plural('day', $recipe->shelf_life_days) }}@endif</p>
                        <div style="overflow-x:auto;">
                            <table class="table">
                                <thead><tr><th>Ingredient</th><th>Quantity</th><th>Waste %</th></tr></thead>
                                <tbody>
                                    @foreach ($recipe->items as $item)
                                        <tr>
                                            <td>{{ $variantLabel($item->componentVariant) }}</td>
                                            <td>{{ $qty($item->quantity) }} {{ $item->unit?->code }}</td>
                                            <td>{{ $qty($item->wastage_percent) }}%</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                    @else
                        <p class="subtle">No recipe yet. Add one to define the ingredients a batch consumes.</p>
                    @endif

                    <div class="recipe-cost-header">
                        <h3>Menu margins &amp; food cost</h3>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="table recipe-cost-table">
                            <thead>
                                <tr>
                                    <th>Sell price</th>
                                    <th>Cost / unit</th>
                                    <th>Margin</th>
                                    <th>Margin %</th>
                                    <th>Food cost %</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{{ $margin['sell_minor'] > 0 ? $tenant->currency_code.' '.$money($margin['sell_minor']) : '—' }}</td>
                                    <td>{{ $tenant->currency_code }} {{ $money($margin['unit_cost_minor']) }}</td>
                                    <td>{{ $margin['sell_minor'] > 0 ? $tenant->currency_code.' '.$money($margin['margin_minor']) : '—' }}</td>
                                    <td>{{ $margin['margin_pct'] === null ? '—' : $margin['margin_pct'].'%' }}</td>
                                    <td>{{ $margin['food_cost_pct'] === null ? '—' : $margin['food_cost_pct'].'%' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    @if ($margin['sell_minor'] <= 0)
                        <p class="subtle" style="margin-top:12px;">Set a sell price on this product to compute margin.</p>
                    @endif
                </div>
            </section>

            <section class="panel tab-panel" id="production" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header"><h2 class="panel-title">Recent production &amp; yield</h2></div>
                <div class="panel-body">
                    <div style="overflow-x:auto;">
                        <table class="table">
                            <thead><tr><th>Date</th><th>Status</th><th>Planned</th><th>Actual yield</th><th>Variance</th><th>Unit cost</th><th>Batch cost</th><th>Store</th></tr></thead>
                            <tbody>
                                @forelse ($orders as $order)
                                    @php $planned = (float) $order->planned_quantity; $actual = (float) $order->actual_yield_quantity; $variance = $actual - $planned; $variancePct = $planned > 0 ? round($variance / $planned * 100, 1) : null; @endphp
                                    <tr>
                                        <td>{{ ($order->produced_at ?? $order->cancelled_at ?? $order->started_at)?->format('M j, Y H:i') }}</td>
                                        <td><span class="run-status {{ $order->status }}">{{ $order->statusLabel() }}</span></td>
                                        <td class="subtle">{{ $qty($planned) }}</td>
                                        @if ($order->isCompleted())
                                            <td>{{ $qty($actual) }}</td>
                                            <td class="{{ $variance < 0 ? 'stock-status low' : ($variance > 0 ? 'stock-status ok' : '') }}">{{ $variance > 0 ? '+' : '' }}{{ $qty($variance) }}@if ($variancePct !== null) <span class="subtle">({{ $variance > 0 ? '+' : '' }}{{ $variancePct }}%)</span>@endif</td>
                                            <td>{{ $tenant->currency_code }} {{ $money($order->unit_cost_minor) }}</td>
                                            <td>{{ $tenant->currency_code }} {{ $money($order->total_cost_minor) }}</td>
                                        @else
                                            {{-- Nothing came out yet (or ever), so there is no yield or cost to report. --}}
                                            <td class="subtle">—</td><td class="subtle">—</td><td class="subtle">—</td><td class="subtle">—</td>
                                        @endif
                                        <td class="subtle">{{ $order->sourceLocation?->name }}@if ($order->output_location_id !== $order->source_location_id) → {{ $order->outputLocation?->name }}@endif</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="subtle">No production recorded yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <style>
        .how-made { border: 1px solid var(--line); border-radius: 12px; padding: 14px 16px; background: #fbfcfd; margin-bottom: 16px; }
        .how-made-title { font-size: .74rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: #667085; margin-bottom: 10px; }
        .how-made-options { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 10px; }
        .how-made-options form { margin: 0; }
        .how-made-option { width: 100%; display: flex; gap: 10px; align-items: flex-start; text-align: left;
            border: 1.5px solid var(--line); border-radius: 10px; background: #fff; padding: 12px 13px; cursor: pointer; }
        .how-made-option:hover:not(:disabled) { border-color: #101828; }
        .how-made-option.on { border-color: var(--brand, #12b76a); background: #f4fdf8; cursor: default; opacity: 1; }
        .how-made-option strong { display: block; font-size: .95rem; }
        .how-made-option em { display: block; font-style: normal; font-size: .78rem; color: #667085; margin-top: 3px; line-height: 1.35; }
        .how-made-mark { font-size: 1rem; line-height: 1.3; color: #12b76a; }
        .how-made-option:not(.on) .how-made-mark { color: #cbd2dc; }

        .ing-row { display: grid; grid-template-columns: 1fr 2fr 1fr 1fr 1fr auto; gap: 8px; align-items: end; margin-bottom: 8px; }
        .recipe-name { margin: 5px 0 0; color: var(--ink); font-size: 18px; font-weight: 700; line-height: 1.3; letter-spacing: -.01em; }
        .btn.recipe-remove-button { display: inline-flex; align-items: center; gap: 7px; background: var(--danger); color: #fff; border-color: var(--danger); }
        .btn.recipe-remove-button:hover { background: var(--danger-strong); border-color: var(--danger-strong); }
        .recipe-cost-header { margin: 24px 0 10px; padding-bottom: 10px; border-bottom: 1px solid var(--line); }
        .recipe-cost-header h3 { margin: 0; color: var(--ink); font-size: 18px; font-weight: 700; line-height: 1.3; letter-spacing: -.01em; }
        .recipe-cost-table th, .recipe-cost-table td { white-space: nowrap; }
        @media (max-width: 900px) { .ing-row { grid-template-columns: 1fr 1fr; } }
    </style>

    <style>
        .runs-panel { border: 2px solid #fec84b; margin-bottom: 16px; }
        .run-count { display: inline-block; margin-left: 6px; padding: 1px 9px; border-radius: 999px; background: #fef0c7; color: #b54708; font-size: .8rem; font-weight: 800; vertical-align: 2px; }
        .run-row { display: flex; justify-content: space-between; gap: 14px; align-items: center; padding: 12px 0; border-bottom: 1px solid #f0f1f4; }
        .run-row:last-child { border-bottom: 0; }
        .run-title { font-weight: 800; font-size: 1.05rem; }
        .run-held { font-size: .8rem; color: #475467; margin-top: 3px; }
        .run-actions { display: flex; gap: 8px; align-items: center; }
        .run-actions form { margin: 0; }
        .run-status { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: .74rem; font-weight: 800; background: #dcfae6; color: #067647; white-space: nowrap; }
        .run-status.in_progress { background: #fef0c7; color: #b54708; }
        .run-status.cancelled { background: #f2f4f7; color: #475467; }
        @media (max-width: 720px) { .run-row { flex-direction: column; align-items: flex-start; } }
    </style>

    @include('inventory::admin.production.partials.recipe-dialog')
    @if ($recipe)
        @include('inventory::admin.production.partials.produce-dialog', ['recipe' => $recipe])
    @endif

    @foreach ($inProgress as $run)
        @php $runUnit = $recipe?->yieldUnit?->code ?: 'pc'; @endphp
        <dialog class="dialog" id="complete-run-{{ $run->id }}">
            <div class="dialog-header">
                <div>
                    <h2 class="panel-title">Complete production</h2>
                    <p class="subtle">
                        {{ $qty($run->planned_quantity) }} {{ $runUnit }} planned, started {{ $run->started_at?->format('M j, H:i') }}.
                        Enter what actually came out and what was actually used — the held ingredients are released and those amounts deducted.
                    </p>
                </div>
                <button class="icon-btn" type="button" data-dialog-close aria-label="Close">✕</button>
            </div>
            <div class="dialog-body">
                <form class="mini-form" method="POST" action="{{ route('admin.inventory.production.runs.complete', $run) }}">
                    @csrf
                    <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                    <div class="form-grid">
                        <div class="field">
                            <label>Expected yield ({{ $runUnit }})</label>
                            <input type="text" value="{{ $qty($run->planned_quantity) }}" disabled style="background:#f2f4f7; color:#475467; cursor:not-allowed;">
                        </div>
                        <div class="field">
                            <label for="run-yield-{{ $run->id }}">Actual yield ({{ $runUnit }})</label>
                            <input id="run-yield-{{ $run->id }}" name="actual_yield_quantity" type="number" step="any" min="0.0001" required
                                   value="{{ rtrim(rtrim(number_format((float) $run->planned_quantity, 4, '.', ''), '0'), '.') }}">
                        </div>
                        <div class="field full">
                            <label>Put finished goods in</label>
                            <select name="output_location_id">
                                @foreach ($locations as $location)
                                    <option value="{{ $location->id }}" @selected($location->id === $run->output_location_id)>{{ $location->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <h3 class="panel-title" style="margin: 16px 0 8px;">Ingredients actually used</h3>
                    <div style="overflow-x:auto;">
                        <table class="table">
                            <thead><tr><th>Ingredient</th><th>Planned (held)</th><th>Actual used</th></tr></thead>
                            <tbody>
                                @foreach ($run->items as $item)
                                    <tr>
                                        <td>{{ $variantLabel($item->componentVariant) }}</td>
                                        <td class="subtle">{{ $qty($item->planned_quantity) }} {{ $item->unit?->code }}</td>
                                        <td>
                                            <div style="display:flex; align-items:center; gap:6px;">
                                                <input name="items[{{ $item->id }}][actual_quantity]" type="number" step="any" min="0" required style="max-width:130px;"
                                                       value="{{ rtrim(rtrim(number_format((float) $item->planned_quantity, 4, '.', ''), '0'), '.') }}">
                                                <span class="subtle">{{ $item->unit?->code }}</span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="field full" style="margin-top: 12px;">
                        <label>Notes</label>
                        <input name="notes" placeholder="optional — e.g. 2 burnt">
                    </div>

                    <div class="dialog-actions" style="margin-top: 16px;">
                        <button class="btn" type="button" data-dialog-close>Not yet</button>
                        <button class="btn primary" type="submit">Complete &amp; deduct ingredients</button>
                    </div>
                </form>
            </div>
        </dialog>
    @endforeach

    <script>
        (function () {
            const UNITS = @json($jsUnits);
            const ITEMS = @json($jsItems);
            const VARIANT_CAT = @json($jsVariantCat);

            function applyUnits(select, categoryId) {
                const matches = categoryId ? UNITS.filter((u) => String(u.cat) === String(categoryId)) : [];
                if (matches.length) {
                    select.innerHTML = matches.map((u) => `<option value="${u.id}">${u.code}</option>`).join('');
                } else {
                    select.innerHTML = '<option value="">pc</option>';
                }
            }
            function fillItems(select, type) {
                const current = select.value;
                select.innerHTML = '';
                select.add(new Option('Select…', ''));
                ITEMS.filter((i) => ! type || String(i.type) === String(type)).forEach((i) => select.add(new Option(i.label, i.id)));
                select.value = current;
            }
            function initRow(typeSel) {
                const row = typeSel.closest('.ing-row');
                const itemSel = row.querySelector('[data-recipe-component]');
                const unitSel = row.querySelector('[data-recipe-unit]');
                fillItems(itemSel, typeSel.value);
                if (itemSel.dataset.selected) itemSel.value = itemSel.dataset.selected;
                if (unitSel) {
                    applyUnits(unitSel, VARIANT_CAT[itemSel.value]);
                    if (unitSel.dataset.selected) unitSel.value = unitSel.dataset.selected;
                }
            }
            document.querySelectorAll('[data-recipe-type]').forEach(initRow);

            const container = document.getElementById('recipe-items');
            const addBtn = document.getElementById('recipe-add-item');
            const template = document.getElementById('recipe-item-template');
            if (addBtn && template && container) {
                let index = container.querySelectorAll('.ing-row').length;
                addBtn.addEventListener('click', function () {
                    const wrap = document.createElement('div');
                    wrap.innerHTML = template.innerHTML.replace(/__INDEX__/g, index);
                    const row = wrap.firstElementChild;
                    container.appendChild(row);
                    initRow(row.querySelector('[data-recipe-type]'));
                    index++;
                });
                container.addEventListener('click', function (e) {
                    const remove = e.target.closest('[data-remove-row]');
                    if (remove) remove.closest('.ing-row').remove();
                });
            }

            document.addEventListener('change', function (e) {
                const t = e.target;
                if (! t.matches) return;
                if (t.matches('[data-recipe-type]')) {
                    const row = t.closest('.ing-row');
                    const itemSel = row.querySelector('[data-recipe-component]');
                    itemSel.removeAttribute('data-selected');
                    fillItems(itemSel, t.value);
                    const unitSel = row.querySelector('[data-recipe-unit]');
                    if (unitSel) applyUnits(unitSel, null);
                } else if (t.matches('[data-recipe-component]')) {
                    const row = t.closest('.ing-row');
                    const unitSel = row.querySelector('[data-recipe-unit]');
                    if (unitSel) applyUnits(unitSel, VARIANT_CAT[t.value]);
                }
            });
        })();
    </script>
</x-layouts.admin>
