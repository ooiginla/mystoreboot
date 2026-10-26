@php
    $money = fn (?int $minor): string => number_format(($minor ?? 0) / 100, 2);
    $qty = fn ($value): string => \Modules\Inventory\Support\Quantity::format($value ?? 0);
    $variantLabel = fn ($variant): string => ($variant?->product?->name ?? 'Item').' / '.($variant?->variant_name ?? '');
    $currency = strtoupper($tenant->currency_code) === 'NGN' ? '₦' : strtoupper($tenant->currency_code).' ';
@endphp

<x-layouts.admin title="Production runs">
    <div class="topbar">
        <div>
            <div class="eyebrow">F&amp;B production</div>
            <h1>Production runs</h1>
            <p class="subtle">Finish active production and review recent yield for {{ $branch?->name ?? 'the active branch' }}.</p>
        </div>
        <a class="btn primary" href="{{ route('admin.inventory.production.index', ['tenant' => $tenant->id]) }}">Production &amp; recipes</a>
    </div>

    @if (session('status'))<div class="alert success">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="alert errors">{{ $errors->first() }}</div>@endif

    <section class="panel runs-panel">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Pending completion <span class="run-count">{{ $inProgress->count() }}</span></h2>
                <p class="subtle">These batches have started. Their ingredients remain held until each run is completed or cancelled.</p>
            </div>
        </div>
        <div class="panel-body">
            @forelse ($inProgress as $run)
                @php
                    $runProduct = $run->outputVariant?->product;
                    $runUnit = $run->recipe?->yieldUnit?->code ?: 'pc';
                    $runLead = $run->productionLeadStaff?->name ?? $run->productionLead?->name;
                @endphp
                <div class="run-row" id="run-{{ $run->id }}">
                    <div>
                        <div class="run-title">
                            <span class="run-number">{{ $run->runNumber() }}</span>
                            @if ($runProduct)
                                <a href="{{ route('admin.inventory.production.show', ['product' => $runProduct->id, 'tenant' => $tenant->id]) }}">{{ $runProduct->name }}</a>
                            @else
                                Finished product
                            @endif
                            <span class="subtle">· {{ $qty($run->planned_quantity) }} {{ $runUnit }} planned</span>
                        </div>
                        <div class="subtle">
                            Started {{ $run->started_at?->format('M j, Y H:i') }} ({{ $run->started_at?->diffForHumans() }}) ·
                            from {{ $run->sourceLocation?->name ?? '—' }}
                            @if ($runLead) · Led by {{ $runLead }} @endif
                            @if ($run->reference_number) · {{ $run->reference_number }} @endif
                        </div>
                        <div class="run-held">
                            Holding:
                            {{ $run->items->map(fn ($item) => ($item->componentVariant?->product?->name ?? 'Item').' '.$qty($item->planned_quantity).($item->unit ? ' '.$item->unit->code : ''))->implode(' · ') ?: 'nothing' }}
                        </div>
                    </div>
                    <div class="run-actions">
                        @permission('inventory.manage')
                        <button class="btn primary" type="button" data-dialog-open="complete-run-{{ $run->id }}">Complete</button>
                        <form method="POST" action="{{ route('admin.inventory.production.runs.cancel', $run) }}"
                              onsubmit="return confirm('Cancel this batch? The held ingredients become available again.');">
                            @csrf
                            <input type="hidden" name="tenant" value="{{ $tenant->id }}">
                            <button class="btn danger" type="submit">Cancel</button>
                        </form>
                        @endpermission
                    </div>
                </div>
            @empty
                <div class="empty">No production is waiting to be completed.</div>
            @endforelse
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Recent production &amp; yield</h2>
                <p class="subtle">The latest completed and cancelled runs across all finished products.</p>
            </div>
        </div>
        <div class="panel-body run-history-scroll">
            <table class="table run-history-table">
                <thead>
                    <tr><th>Run</th><th>Date</th><th>Product</th><th>Status</th><th>Production lead</th><th>Planned</th><th>Actual yield</th><th>Variance</th><th>Impact value</th><th>Unit cost</th><th>Batch cost</th><th>Store</th></tr>
                </thead>
                <tbody>
                    @forelse ($recentOrders as $order)
                        @php
                            $planned = (float) $order->planned_quantity;
                            $actual = (float) $order->actual_yield_quantity;
                            $variance = $actual - $planned;
                            $variancePct = $planned > 0 ? round($variance / $planned * 100, 1) : null;
                            $orderProduct = $order->outputVariant?->product;
                            $orderLead = $order->productionLeadStaff?->name ?? $order->productionLead?->name;
                        @endphp
                        <tr id="run-{{ $order->id }}">
                            <td><strong>{{ $order->runNumber() }}</strong></td>
                            <td>{{ ($order->produced_at ?? $order->cancelled_at ?? $order->started_at)?->format('M j, Y H:i') }}</td>
                            <td>
                                @if ($orderProduct)
                                    <a href="{{ route('admin.inventory.production.show', ['product' => $orderProduct->id, 'tenant' => $tenant->id]) }}"><strong>{{ $orderProduct->name }}</strong></a>
                                    @if ($order->outputVariant?->variant_name)<div class="subtle">{{ $order->outputVariant->variant_name }}</div>@endif
                                @else
                                    —
                                @endif
                            </td>
                            <td><span class="run-status {{ $order->status }}">{{ $order->statusLabel() }}</span></td>
                            <td>{{ $orderLead ?? '—' }}</td>
                            <td class="subtle">{{ $qty($planned) }}</td>
                            @if ($order->isCompleted())
                                <td>{{ $qty($actual) }}</td>
                                <td class="{{ $variance < 0 ? 'stock-status low' : ($variance > 0 ? 'stock-status ok' : '') }}">
                                    {{ $variance > 0 ? '+' : '' }}{{ $qty($variance) }}
                                    @if ($variancePct !== null)<span class="subtle">({{ $variance > 0 ? '+' : '' }}{{ $variancePct }}%)</span>@endif
                                    @if ($variance > 0.00001)<div class="subtle">Overproduction</div>@elseif ($order->variance_reason)<div class="subtle">{{ $order->variance_reason->label() }}</div>@endif
                                </td>
                                <td>
                                    @if (abs($variance) > 0.00001)
                                        <span class="{{ $variance > 0 ? 'stock-status ok' : 'stock-status low' }}">{{ $variance > 0 ? 'Surplus' : 'Loss' }}: {{ $currency }}{{ $money($order->variance_value_minor) }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $currency }}{{ $money($order->unit_cost_minor) }}</td>
                                <td>{{ $currency }}{{ $money($order->total_cost_minor) }}</td>
                            @else
                                <td class="subtle">—</td><td class="subtle">—</td><td class="subtle">—</td><td class="subtle">—</td><td class="subtle">—</td>
                            @endif
                            <td class="subtle">{{ $order->sourceLocation?->name ?? '—' }}@if ($order->output_location_id !== $order->source_location_id) → {{ $order->outputLocation?->name }}@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="12"><div class="empty">No finished production runs yet.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <style>
        .runs-panel { border: 2px solid #fec84b; margin-bottom: 16px; }
        .run-count { display: inline-block; margin-left: 6px; padding: 1px 9px; border-radius: 999px; background: #fef0c7; color: #b54708; font-size: .8rem; font-weight: 800; vertical-align: 2px; }
        .run-row { display: flex; justify-content: space-between; gap: 14px; align-items: center; padding: 14px 0; border-bottom: 1px solid #f0f1f4; scroll-margin-top: 24px; }
        .run-row:first-child { padding-top: 0; }
        .run-row:last-child { border-bottom: 0; padding-bottom: 0; }
        .run-title { font-weight: 800; font-size: 1.05rem; }
        .run-title a { color: inherit; }
        .run-number { display:inline-block; margin-right:7px; padding:2px 8px; border-radius:999px; background:#eef2f6; color:#344054; font-size:.72rem; letter-spacing:.02em; vertical-align:2px; }
        .run-held { font-size: .8rem; color: #475467; margin-top: 4px; }
        .run-actions { display: flex; gap: 8px; align-items: center; flex: 0 0 auto; }
        .run-actions form { margin: 0; }
        .run-status { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: .74rem; font-weight: 800; background: #dcfae6; color: #067647; white-space: nowrap; }
        .run-status.cancelled { background: #f2f4f7; color: #475467; }
        .run-history-scroll { overflow-x: auto; }
        .run-history-table { min-width: 1380px; }
        .run-history-table td { vertical-align: top; }
        @media (max-width: 720px) { .run-row { flex-direction: column; align-items: flex-start; } }
    </style>

    @foreach ($inProgress as $run)
        @php
            $runUnit = $run->recipe?->yieldUnit?->code ?: 'pc';
            $runProductName = $run->outputVariant?->product?->name ?? 'finished product';
        @endphp
        <dialog class="dialog" id="complete-run-{{ $run->id }}">
            <div class="dialog-header">
                <div>
                    <h2 class="panel-title">Complete {{ $run->runNumber() }} · {{ $runProductName }}</h2>
                    <p class="subtle">
                        {{ $qty($run->planned_quantity) }} {{ $runUnit }} planned, started {{ $run->started_at?->format('M j, H:i') }}.
                        Enter what actually came out and what was actually used — the held ingredients are released and those amounts deducted.
                    </p>
                </div>
                <button class="icon-btn" type="button" data-dialog-close aria-label="Close">✕</button>
            </div>
            <div class="dialog-body">
                <form class="mini-form" method="POST" action="{{ route('admin.inventory.production.runs.complete', $run) }}"
                      data-complete-production data-planned="{{ (float) $run->planned_quantity }}" data-unit-cost-minor="{{ $run->estimated_unit_cost_minor }}">
                    @csrf
                    <input type="hidden" name="tenant" value="{{ $tenant->id }}">
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
                        <div class="field full" data-completion-variance hidden style="border:1px solid #fec84b; border-radius:10px; background:#fffaeb; padding:12px 14px;">
                            <label data-completion-variance-label>Reason for yield loss</label>
                            <select name="variance_reason" data-completion-variance-reason>
                                <option value="">Select a loss reason</option>
                                @foreach ($varianceReasons as $value => $label)<option value="{{ $value }}" @if($value === 'overproduction') hidden @endif>{{ $label }}</option>@endforeach
                            </select>
                            <div data-completion-variance-surplus hidden style="color:#067647; font-weight:800;">Automatically classified as Overproduction. All actual output will be added to sellable inventory.</div>
                            <small style="color:#b54708; font-weight:800;"><span data-completion-variance-value-label>Estimated loss value:</span> <span data-completion-variance-value>—</span></small>
                        </div>
                    </div>

                    <h3 class="panel-title" style="margin:16px 0 8px;">Ingredients actually used</h3>
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

                    <div class="field full" style="margin-top:12px;">
                        <label>Notes</label>
                        <input name="notes" placeholder="optional — e.g. 2 burnt">
                    </div>

                    <div class="dialog-actions" style="margin-top:16px;">
                        <button class="btn warning" type="button" data-dialog-close>Not yet</button>
                        <button class="btn primary" type="submit">Complete &amp; deduct ingredients</button>
                    </div>
                </form>
            </div>
        </dialog>
    @endforeach

    <script>
        (function () {
            const currency = @json($tenant->currency_code);
            const money = (minor) => new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(minor / 100);

            document.querySelectorAll('[data-complete-production]').forEach(function (form) {
                const actual = form.querySelector('[name="actual_yield_quantity"]');
                const box = form.querySelector('[data-completion-variance]');
                const reason = form.querySelector('[data-completion-variance-reason]');
                const label = form.querySelector('[data-completion-variance-label]');
                const surplus = form.querySelector('[data-completion-variance-surplus]');
                const valueLabel = form.querySelector('[data-completion-variance-value-label]');
                const value = form.querySelector('[data-completion-variance-value]');
                const planned = Number(form.dataset.planned) || 0;
                const unitCostMinor = Number(form.dataset.unitCostMinor) || 0;

                function refresh() {
                    const variance = (Number(actual.value) || 0) - planned;
                    const hasVariance = Math.abs(variance) > 0.00001;
                    const isSurplus = variance > 0.00001;
                    box.hidden = ! hasVariance;
                    reason.hidden = ! hasVariance || isSurplus;
                    reason.required = hasVariance && ! isSurplus;
                    surplus.hidden = ! isSurplus;
                    label.textContent = isSurplus ? 'Yield surplus' : 'Reason for yield loss';
                    valueLabel.textContent = isSurplus ? 'Estimated surplus value:' : 'Estimated loss value:';
                    if (isSurplus) reason.value = 'overproduction';
                    if (hasVariance && ! isSurplus && reason.value === 'overproduction') reason.value = '';
                    if (! hasVariance) reason.value = '';
                    value.textContent = money(Math.round(Math.abs(variance) * unitCostMinor));
                }

                actual.addEventListener('input', refresh);
                refresh();
            });
        })();
    </script>
</x-layouts.admin>
