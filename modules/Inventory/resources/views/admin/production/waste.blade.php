@php
    $money = fn ($value) => $tenant->currency_code.' '.number_format($value / 100, 2);
    $qty = fn ($value) => \Modules\Inventory\Support\Quantity::format($value);
    $wasteStockOptions = $wasteStockLevels->map(fn ($level): array => [
        'location_id' => $level->inventory_location_id,
        'variant_id' => $level->product_variant_id,
        'label' => ($level->variant?->product?->name ?? 'Finished product').' / '.($level->variant?->variant_name ?? 'Default').' ('.($level->variant?->sku ?? 'No SKU').')',
        'quantity' => \Modules\Inventory\Support\Quantity::format($level->quantity_on_hand),
    ])->values();
    $productionOptions = $orders->map(fn ($order): array => [
        'id' => $order->id,
        'variant_id' => $order->output_product_variant_id,
        'label' => $order->runNumber().' · '.($order->outputVariant?->product?->name ?? 'Finished product').' · '.($order->produced_at?->format('M j, Y H:i') ?? 'Completed').' · '.$qty($order->actual_yield_quantity).' produced',
    ])->values();
@endphp
<x-layouts.admin title="Production Waste">
    <style>
        .waste-date-filter { display:grid; grid-template-columns:minmax(170px,1fr) minmax(170px,1fr) auto; gap:16px; align-items:end; }
        @media (max-width:640px) { .waste-date-filter { grid-template-columns:1fr; } }
    </style>
    <div class="topbar"><div><div class="eyebrow">F&amp;B production</div><h1>Waste register</h1><p class="subtle">Record production loss, damaged stock, expiry and other waste with accountable value.</p></div><div class="button-row"><a class="btn secondary" href="{{ route('admin.inventory.production.planning',['tenant'=>$tenant->id]) }}">Planning</a><a class="btn secondary" href="{{ route('admin.inventory.production.reports',['tenant'=>$tenant->id]) }}" target="_blank" rel="noopener">Reports</a><button class="btn accent" data-dialog-open="waste-dialog">Record waste</button></div></div>
    @if(session('status'))<div class="alert">{{ session('status') }}</div>@endif @if($errors->any())<div class="alert errors"><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
    <form class="waste-date-filter" method="GET"><input type="hidden" name="tenant" value="{{ $tenant->id }}"><div class="field"><label>From</label><input type="date" name="from" value="{{ $from->toDateString() }}"></div><div class="field"><label>To</label><input type="date" name="to" value="{{ $to->toDateString() }}"></div><div class="field"><button class="btn primary">Apply</button></div></form>
    <div class="stats-grid" style="margin:16px 0"><div class="stat"><span class="subtle">Waste events</span><strong>{{ $records->count() }}</strong></div><div class="stat"><span class="subtle">Quantity</span><strong>{{ $qty($records->sum('quantity')) }}</strong></div><div class="stat"><span class="subtle">Waste value</span><strong>{{ $money($records->sum('value_minor')) }}</strong></div></div>
    <section class="panel"><div class="panel-body" style="overflow-x:auto"><table class="table"><thead><tr><th>Date</th><th>Item</th><th>Production run</th><th>Quantity</th><th>Reason</th><th>Value</th><th>Staff</th><th>Location</th><th>Stock action</th><th>Notes</th></tr></thead><tbody>@forelse($records as $record)<tr><td>{{ $record->waste_date->format('M j, Y') }}</td><td>{{ $record->variant?->product?->name }}</td><td>@if($record->productionOrder)<a href="{{ route('admin.inventory.production.runs.index', ['tenant' => $tenant->id]).'#run-'.$record->production_order_id }}"><strong>{{ $record->productionOrder->runNumber() }}</strong></a>@else—@endif</td><td>{{ $qty($record->quantity) }}</td><td>{{ $record->reason->label() }}</td><td>{{ $money($record->value_minor) }}</td><td>{{ $record->staff?->name ?? '—' }}</td><td>{{ $record->location?->name ?? '—' }}</td><td>{{ $record->deducted_from_stock?'Deducted':'Recorded only' }}</td><td>{{ $record->notes ?: '—' }}</td></tr>@empty<tr><td colspan="10"><div class="empty">No waste recorded in this period.</div></td></tr>@endforelse</tbody></table></div></section>
    <dialog class="dialog" id="waste-dialog"><div class="dialog-header"><h2 class="panel-title">Record waste</h2><button class="icon-btn" data-dialog-close>✕</button></div><div class="dialog-body"><form class="mini-form" method="POST" action="{{ route('admin.inventory.production.waste.store') }}">@csrf<input type="hidden" name="tenant" value="{{ $tenant->id }}"><div class="form-grid"><div class="field full"><label>Stock location</label><select name="inventory_location_id" required data-waste-location><option value="">Select location</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected((string) old('inventory_location_id') === (string) $location->id)>{{ $location->name }}</option>@endforeach</select><small class="subtle">Products are limited to finished goods currently stocked at this location.</small></div><div class="field full"><label>Finished product</label><select name="product_variant_id" required data-waste-product disabled><option value="">Select a stock location first</option></select><small class="subtle" data-waste-product-help>Select a location to see its finished-product stock.</small></div><div class="field"><label>Waste date</label><input type="date" name="waste_date" value="{{ old('waste_date', today()->toDateString()) }}" required></div><div class="field"><label>Quantity</label><input type="number" name="quantity" step="any" min="0.0001" value="{{ old('quantity') }}" required></div><div class="field"><label>Reason</label><select name="reason" required><option value="">Select</option>@foreach($reasons as $value=>$label)<option value="{{ $value }}" @selected(old('reason') === $value)>{{ $label }}</option>@endforeach</select></div><div class="field"><label>Responsible staff</label><select name="hr_staff_id"><option value="">Not assigned</option>@foreach($staff as $employee)<option value="{{ $employee->id }}" @selected((string) old('hr_staff_id') === (string) $employee->id)>{{ $employee->name }}</option>@endforeach</select></div><div class="field full"><label>Related production run <span class="subtle">(optional)</span></label><select name="production_order_id" data-waste-production disabled><option value="">Select a finished product first</option></select><small class="subtle" data-waste-production-help>Choose the matching run number shown on the Production Runs page.</small></div><div class="field full"><label class="inline-check"><input type="checkbox" name="deduct_from_stock" value="1" @checked(old('deduct_from_stock'))> Deduct this quantity from inventory</label><small class="subtle">Enable this when the finished product has already entered stock.</small></div><div class="field full"><label>Notes</label><textarea name="notes">{{ old('notes') }}</textarea></div></div><div class="dialog-actions"><button class="btn" type="button" data-dialog-close>Cancel</button><button class="btn primary">Record waste</button></div></form></div></dialog>
    <script>
        (function () {
            const stock = @json($wasteStockOptions);
            const productions = @json($productionOptions);
            const location = document.querySelector('[data-waste-location]');
            const product = document.querySelector('[data-waste-product]');
            const help = document.querySelector('[data-waste-product-help]');
            const production = document.querySelector('[data-waste-production]');
            const productionHelp = document.querySelector('[data-waste-production-help]');
            const oldProduct = @json((string) old('product_variant_id'));
            const oldProduction = @json((string) old('production_order_id'));

            function refreshProductions() {
                const matches = productions.filter((item) => String(item.variant_id) === String(product.value));
                production.innerHTML = '';

                if (! product.value) {
                    production.add(new Option('Select a finished product first', ''));
                    production.disabled = true;
                    productionHelp.textContent = 'Choose the matching run number shown on the Production Runs page.';
                    return;
                }

                production.add(new Option('None', ''));
                matches.forEach((item) => production.add(new Option(item.label, item.id)));
                production.disabled = false;
                productionHelp.textContent = matches.length
                    ? `${matches.length} recent completed ${matches.length === 1 ? 'run' : 'runs'} found for this product.`
                    : 'No recent completed production run was found for this product.';
                if (matches.some((item) => String(item.id) === oldProduction)) production.value = oldProduction;
            }

            function refreshProducts() {
                const matches = stock.filter((item) => String(item.location_id) === String(location.value));
                product.innerHTML = '';

                if (! location.value) {
                    product.add(new Option('Select a stock location first', ''));
                    product.disabled = true;
                    help.textContent = 'Select a location to see its finished-product stock.';
                    refreshProductions();
                    return;
                }

                product.add(new Option(matches.length ? 'Select finished product' : 'No finished products in stock here', ''));
                matches.forEach((item) => product.add(new Option(`${item.label} · ${item.quantity} on hand`, item.variant_id)));
                product.disabled = matches.length === 0;
                help.textContent = matches.length
                    ? `${matches.length} finished ${matches.length === 1 ? 'product' : 'products'} available at this location.`
                    : 'This location has no finished products currently in stock.';
                if (matches.some((item) => String(item.variant_id) === oldProduct)) product.value = oldProduct;
                refreshProductions();
            }

            location?.addEventListener('change', refreshProducts);
            product?.addEventListener('change', refreshProductions);
            if (location && product && help && production && productionHelp) refreshProducts();
        })();
    </script>
</x-layouts.admin>
