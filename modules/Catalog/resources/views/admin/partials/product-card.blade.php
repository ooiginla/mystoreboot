@php
    $variant = $item->variants->first();
    $searchText = strtolower(collect([
        $item->name,
        $item->brand,
        $item->category?->name,
        $variant?->sku,
        $variant?->barcode,
        $item->variants->pluck('sku')->implode(' '),
        $item->variants->pluck('barcode')->implode(' '),
        $item->tags->pluck('name')->implode(' '),
        $item->attributeValues->pluck('value')->implode(' '),
    ])->filter()->implode(' '));
    $primaryPrice = $variant?->discount_price_minor ?? $variant?->selling_price_minor ?? $item->discount_price_minor ?? $item->base_price_minor;
    $comparePrice = $variant?->discount_price_minor ? $variant?->selling_price_minor : null;
@endphp

<article
    class="product-card"
    data-catalog-card
    data-search="{{ $searchText }}"
    data-category="{{ $item->category_id }}"
    data-status="{{ $item->status->value }}"
>
    <div class="product-thumb">
        @if ($imageUrl($item->image_path))
            <img src="{{ $imageUrl($item->image_path) }}" alt="{{ $item->name }}">
        @else
            {{ strtoupper(substr($item->name, 0, 2)) }}
        @endif
    </div>

    <div>
        <button class="product-name-link" type="button" data-dialog-open="view-product-{{ $item->id }}">
            {{ $item->name }}
        </button>
        <div class="product-meta">
            <span>SKU: <strong>{{ $variant?->sku ?? 'Pending' }}</strong></span>
            @if ($item->brand)
                <span>Brand: <strong>{{ $item->brand }}</strong></span>
            @endif
            <span>Category: <strong>{{ $item->category?->name ?? 'Uncategorized' }}</strong></span>
            @if ($item->has_variants)
                <span>Variants: <strong>{{ $item->variants->count() }}</strong></span>
            @endif
            @if ($item->tags->isNotEmpty())
                <span>Tags: <strong>{{ $item->tags->pluck('name')->join(', ') }}</strong></span>
            @endif
            @if ($item->product_type === \Modules\Catalog\Enums\ProductType::Product)
                <span>Inventory: <strong>Branch-managed</strong></span>
            @endif
        </div>
    </div>

    <div class="product-price-block">
        <span class="badge neutral">{{ $item->status->label() }}</span>
        <div class="product-price">
            @if ($comparePrice)
                <span class="old-price">{{ $tenant->currency_code }} {{ $money($comparePrice) }}</span>
            @endif
            {{ $tenant->currency_code }} {{ $money($primaryPrice) }}
        </div>
        <button class="btn secondary" type="button" data-dialog-open="edit-product-{{ $item->id }}">Edit</button>
    </div>
</article>
