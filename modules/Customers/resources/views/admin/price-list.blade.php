@php
    $money = fn (?int $minor): string => number_format(($minor ?? 0) / 100, 2);
@endphp

<x-layouts.admin :title="$priceList->name">
    <style>
        .price-list-back { display:inline-flex; align-items:center; gap:7px; color:var(--muted); font-size:13px; font-weight:700; margin-bottom:12px; }
        .price-list-back:hover { color:var(--brand-strong); }
        .price-list-header { display:flex; align-items:flex-start; justify-content:space-between; gap:18px; margin-bottom:18px; }
        .price-list-header h1 { margin-bottom:6px; }
        .price-list-header-actions { display:flex; align-items:center; gap:9px; flex-wrap:wrap; justify-content:flex-end; }
        .price-list-status { display:inline-flex; align-items:center; gap:7px; border-radius:7px; padding:6px 10px; font-size:12.5px; font-weight:800; }
        .price-list-status::before { content:''; width:8px; height:8px; border-radius:50%; background:currentColor; }
        .price-list-status.active { color:#067647; background:#ecfdf3; }
        .price-list-status.inactive { color:#475467; background:#eef2f6; }
        .price-list-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; margin-bottom:18px; }
        .price-list-stat { padding:17px 18px; }
        .price-list-stat span { display:block; color:var(--muted); font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
        .price-list-stat strong { display:block; margin-top:6px; font-size:18px; line-height:1.3; overflow-wrap:anywhere; }
        .price-list-table-wrap { overflow-x:auto; min-height:320px; }
        .price-list-items-table { min-width:880px; }
        .price-list-actions { display:flex; align-items:center; justify-content:flex-end; gap:8px; white-space:nowrap; }
        .price-list-assignment-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:18px; margin-top:18px; }
        .price-list-chip-list { display:flex; flex-wrap:wrap; gap:8px; }
        .price-list-chip { display:inline-flex; border-radius:999px; padding:6px 10px; background:var(--panel-soft); color:var(--ink-soft); font-size:12px; font-weight:700; }
        @media (max-width:850px) { .price-list-stats, .price-list-assignment-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media (max-width:620px) { .price-list-header { flex-direction:column; } .price-list-header-actions { justify-content:flex-start; } .price-list-stats, .price-list-assignment-grid { grid-template-columns:1fr; } }
    </style>

    <a class="price-list-back" href="{{ route('admin.customers.index', ['tenant' => $tenant->id]).'#price-lists' }}"><span aria-hidden="true">←</span> Back to pricelists</a>

    @if (session('status'))<div class="alert">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="alert errors">{{ $errors->first() }}</div>@endif

    <div class="price-list-header">
        <div>
            <div class="eyebrow">Pricelist</div>
            <h1>{{ $priceList->name }}</h1>
            <p class="subtle">{{ $priceList->description ?: 'Reusable customer pricing and quantity breaks.' }}</p>
        </div>
        <div class="price-list-header-actions">
            <span class="price-list-status {{ $priceList->is_active ? 'active' : 'inactive' }}">{{ $priceList->is_active ? 'Active' : 'Inactive' }}</span>
            @permission('customers.pricing.manage')
                <button class="btn secondary" type="button" data-dialog-open="price-list-edit-{{ $priceList->id }}">Edit pricelist</button>
                <button class="btn primary" type="button" data-dialog-open="price-list-item-dialog">Add product</button>
            @endpermission
        </div>
    </div>

    <div class="price-list-stats">
        <div class="panel price-list-stat"><span>Products / tiers</span><strong>{{ $priceList->items_count }}</strong></div>
        <div class="panel price-list-stat"><span>Assigned groups</span><strong>{{ $priceList->groups_count }}</strong></div>
        <div class="panel price-list-stat"><span>Direct customers</span><strong>{{ $priceList->customers_count }}</strong></div>
        <div class="panel price-list-stat"><span>Validity</span><strong>{{ $priceList->starts_at?->format('M j, Y') ?? 'Any date' }} – {{ $priceList->ends_at?->format('M j, Y') ?? 'No expiry' }}</strong></div>
    </div>

    <section class="panel">
        <div class="panel-header">
            <div><h2 class="panel-title">Products and prices</h2><p class="subtle">Use a fixed amount or percentage off retail for each quantity tier. The largest qualifying minimum quantity is used.</p></div>
            @permission('customers.pricing.manage')<button class="btn accent" type="button" data-dialog-open="price-list-item-dialog">Add product</button>@endpermission
        </div>
        <div class="price-list-table-wrap">
            <table class="table price-list-items-table">
                <thead><tr><th>Product</th><th>SKU</th><th>Minimum quantity</th><th>Price rule</th><th style="text-align:right;">Actions</th></tr></thead>
                <tbody>
                    @forelse ($priceList->items as $item)
                        <tr>
                            <td><strong>{{ $item->variant?->product?->name ?? 'Unknown product' }}</strong>@if($item->variant?->variant_name && $item->variant->variant_name !== 'Default')<br><span class="subtle">{{ $item->variant->variant_name }}</span>@endif</td>
                            <td>{{ $item->variant?->sku ?? '—' }}</td>
                            <td>{{ \Modules\Inventory\Support\Quantity::format($item->minimum_quantity) }}</td>
                            <td>
                                @if ($item->price_type === 'percentage')
                                    @php
                                        $calculatedPrice = (int) round(($item->variant?->selling_price_minor ?? 0) * (1 - ((float) $item->discount_percent / 100)));
                                    @endphp
                                    <strong>{{ rtrim(rtrim(number_format((float) $item->discount_percent, 2), '0'), '.') }}% off retail</strong><br>
                                    <span class="subtle">Currently {{ $tenant->currency_code }} {{ $money(max(0, $calculatedPrice)) }}</span>
                                @else
                                    <strong>{{ $tenant->currency_code }} {{ $money($item->unit_price_minor) }}</strong>
                                @endif
                            </td>
                            <td>
                                @permission('customers.pricing.manage')
                                    <div class="price-list-actions">
                                        <button class="btn secondary" type="button" data-dialog-open="price-list-item-edit-{{ $item->id }}">Edit</button>
                                        <form method="POST" action="{{ route('admin.customers.price-list-items.destroy', $item) }}" onsubmit="return confirm('Delete this product price from the pricelist?');">
                                            @csrf @method('DELETE')
                                            <button class="btn danger" type="submit">Delete</button>
                                        </form>
                                    </div>
                                @endpermission
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty">No product prices have been added to this pricelist.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="price-list-assignment-grid">
        <section class="panel">
            <div class="panel-header"><h2 class="panel-title">Assigned customer groups</h2></div>
            <div class="panel-body">
                <div class="price-list-chip-list">
                    @forelse ($priceList->groups as $group)
                        <span class="price-list-chip">{{ $group->name }}</span>
                    @empty
                        <span class="subtle">No customer groups use this pricelist.</span>
                    @endforelse
                </div>
            </div>
        </section>
        <section class="panel">
            <div class="panel-header"><h2 class="panel-title">Direct customer assignments</h2></div>
            <div class="panel-body">
                <div class="price-list-chip-list">
                    @forelse ($priceList->customers as $assignedCustomer)
                        <a class="price-list-chip" href="{{ route('admin.customers.customers.show', ['customer' => $assignedCustomer, 'tenant' => $tenant->id]) }}">{{ $assignedCustomer->name }}</a>
                    @empty
                        <span class="subtle">No customers are assigned directly to this pricelist.</span>
                    @endforelse
                </div>
            </div>
        </section>
    </div>

    @permission('customers.pricing.manage')
        @include('customers::admin.partials.price-list-dialog', [
            'selectedPriceList' => $priceList,
            'dialogId' => 'price-list-edit-'.$priceList->id,
            'returnToPriceList' => true,
        ])
        @include('customers::admin.partials.price-list-item-dialog')
        @foreach ($priceList->items as $item)
            @include('customers::admin.partials.price-list-item-dialog', [
                'selectedItem' => $item,
                'dialogId' => 'price-list-item-edit-'.$item->id,
            ])
        @endforeach
    @endpermission

    <script>
        document.querySelectorAll('[data-price-rule-form]').forEach((form) => {
            const type = form.querySelector('[data-price-rule-type]');
            const sync = () => {
                form.querySelectorAll('[data-price-rule-panel]').forEach((panel) => {
                    const active = panel.dataset.priceRulePanel === type.value;
                    panel.hidden = !active;
                    panel.querySelectorAll('input').forEach((input) => {
                        input.required = active;
                    });
                });
            };

            type.addEventListener('change', sync);
            sync();
        });
    </script>
</x-layouts.admin>
