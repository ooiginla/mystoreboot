@php
    $variant = $item->variants->first();
    $primaryPrice = $variant?->discount_price_minor ?? $variant?->selling_price_minor ?? $item->discount_price_minor ?? $item->base_price_minor;
    $comparePrice = $variant?->discount_price_minor ? $variant?->selling_price_minor : null;
    $availability = $item->product_type === \Modules\Catalog\Enums\ProductType::Service
        ? 'Available'
        : 'Managed in Inventory';
@endphp

<dialog class="drawer" id="view-product-{{ $item->id }}">
    <div class="drawer-header">
        <h2 class="panel-title">{{ $item->product_type->label() }} details</h2>
        <button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button>
    </div>
    <div class="drawer-body">
        <div class="drawer-hero">
            @if ($imageUrl($item->image_path))
                <img src="{{ $imageUrl($item->image_path) }}" alt="{{ $item->name }}">
            @else
                <div class="empty">No image uploaded</div>
            @endif
        </div>

        <h3 class="drawer-title">{{ $item->name }}</h3>
        <p class="subtle">{{ $item->description ?: 'No description has been added yet.' }}</p>

        <dl class="detail-grid">
            <dt>Availability</dt>
            <dd><span class="badge">{{ $availability }}</span></dd>
            <dt>SKU</dt>
            <dd>{{ $variant?->sku ?? 'Pending' }}</dd>
            <dt>Barcode</dt>
            <dd>{{ $variant?->barcode ?? 'Not set' }}</dd>
            <dt>Category</dt>
            <dd>{{ $item->category?->name ?? 'Uncategorized' }}</dd>
            <dt>Brand</dt>
            <dd>{{ $item->brand ?: 'Not set' }}</dd>
            <dt>Tags</dt>
            <dd>{{ $item->tags->pluck('name')->join(', ') ?: 'Not set' }}</dd>
            <dt>Attributes</dt>
            <dd>
                @php
                    $groupedAttributes = $item->attributeValues->groupBy(fn ($value) => $value->definition?->name ?? 'Attribute');
                @endphp
                @forelse ($groupedAttributes as $name => $values)
                    <div><strong>{{ $name }}:</strong> {{ $values->pluck('value')->join(', ') }}</div>
                @empty
                    Not set
                @endforelse
            </dd>
            <dt>Tax</dt>
            <dd>{{ $item->tax_behavior->label() }}{{ $item->tax_rate ? ' at '.$item->tax_rate.'%' : '' }}</dd>
            <dt>Status</dt>
            <dd>{{ $item->status->label() }}</dd>
        </dl>

        <div class="variant-table">
            <h3 class="panel-title">Sellable variants</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Variant</th>
                        <th>SKU</th>
                        <th>Price</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($item->variants as $row)
                        <tr>
                            <td>{{ $row->variant_name }}</td>
                            <td>{{ $row->sku }}</td>
                            <td>{{ $tenant->currency_code }} {{ $money($row->discount_price_minor ?? $row->selling_price_minor) }}</td>
                            <td>{{ $row->status->label() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="button-row">
            <div class="product-price">
                @if ($comparePrice)
                    <span class="old-price">{{ $tenant->currency_code }} {{ $money($comparePrice) }}</span>
                @endif
                {{ $tenant->currency_code }} {{ $money($primaryPrice) }}
            </div>
            <button class="btn primary" type="button" data-drawer-edit="edit-product-{{ $item->id }}">Edit item</button>
        </div>
    </div>
</dialog>
