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
    $primaryPrice = $variant?->selling_price_minor ?? $item->base_price_minor;
    $comparePrice = $variant?->compare_at_price_minor ?? $item->compare_at_price_minor;
    $catalogUser = auth()->user();
    $canDeleteItem = $catalogUser?->is_platform_admin
        || ! app(\Modules\Access\Support\PermissionService::class)->enforcementEnabled($tenant)
        || $catalogUser?->hasPermission($tenant, 'catalog.delete');
    $canManageItem = $catalogUser?->is_platform_admin
        || ! app(\Modules\Access\Support\PermissionService::class)->enforcementEnabled($tenant)
        || $catalogUser?->hasPermission($tenant, 'catalog.update');

    // Total stock available across all variants and locations (products only).
    $totalOnHand = null;
    if (($inventoryEnabled ?? false) && $item->product_type === \Modules\Catalog\Enums\ProductType::Product) {
        $variantStockMap = $variantStock ?? collect();
        $totalOnHand = (int) $item->variants->sum(fn ($cardVariant) => (int) (($variantStockMap[$cardVariant->id] ?? collect())->sum('quantity_on_hand')));
    }
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
            <span class="product-thumb-initials" style="display:block;">{{ strtoupper(substr($item->name, 0, 2)) }}</span>
            @if ($canManageItem)
                <form method="POST" action="{{ route('admin.catalog.products.generate-image', $item) }}" style="margin-top:6px;" onsubmit="const b=this.querySelector('button'); b.disabled=true; b.textContent='Generating…';">
                    @csrf
                    <button type="submit" title="Generate a product image with AI" style="padding:3px 8px; font-size:10px; font-weight:600; border:1px solid rgba(0,0,0,.15); border-radius:999px; background:#fff; color:#027a45; cursor:pointer; white-space:nowrap;">✨ Generate image</button>
                </form>
            @endif
        @endif
    </div>

    <div>
        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
            <button class="product-name-link" type="button" data-dialog-open="view-product-{{ $item->id }}">
                {{ $item->name }}
            </button>
            @if (! is_null($totalOnHand))
                @if (! $item->track_inventory)
                    <span class="badge neutral" title="Made to order — stock isn't tracked">Made to order</span>
                @else
                    <span class="badge {{ $totalOnHand > 0 ? 'success' : 'neutral' }}" title="Total available stock across all locations">{{ number_format($totalOnHand) }} in stock</span>
                @endif
            @endif
        </div>
        <div class="product-meta">
            @if ($item->brand)
                <span>Brand: <strong>{{ $item->brand }}</strong></span>
            @endif
            <span>Category: <strong>{{ $item->category?->name ?? 'Uncategorized' }}</strong></span>
            @if ($item->has_variants)
                <span>Variants: <strong>{{ $item->variants->count() }}</strong></span>
            @endif
            @if ($item->tags->isNotEmpty())
                <span class="product-tags">Tags:
                    @foreach ($item->tags as $tag)
                        <strong class="product-tag-pill">{{ $tag->name }}</strong>
                    @endforeach
                </span>
            @endif
            @if ($item->badges->isNotEmpty())
                <span class="product-tags">Badges:
                    @foreach ($item->badges as $badge)
                        <strong class="product-tag-pill">{{ $badge->name }}</strong>
                    @endforeach
                </span>
            @endif
            @if ($item->product_type === \Modules\Catalog\Enums\ProductType::Product)
                <span>Inventory: <strong>Branch-managed</strong></span>
            @endif
        </div>
    </div>

    <div class="product-price-block">
        <span class="badge {{ $item->status === \Modules\Catalog\Enums\ProductStatus::Active ? 'success' : 'neutral' }}">
            {{ $item->status->label() }}
        </span>
        <div class="product-price">
            @if ($comparePrice && $comparePrice > $primaryPrice)
                <span class="old-price">{{ $tenant->currency_code }} {{ $money($comparePrice) }}</span>
            @endif
            {{ $tenant->currency_code }} {{ $money($primaryPrice) }}
        </div>
        <div class="catalog-product-actions">
            <details class="catalog-status-menu">
                <summary class="btn catalog-status-trigger" aria-label="Change status for {{ $item->name }}" title="Change product status">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/>
                        <circle cx="12" cy="12" r="2.8"/>
                    </svg>
                </summary>
                <div class="catalog-status-dropdown" role="menu" aria-label="Product status">
                    <div class="catalog-status-dropdown-title">Change status</div>
                    @foreach (\Modules\Catalog\Enums\ProductStatus::cases() as $status)
                        <form method="POST" action="{{ route('admin.catalog.products.status.update', $item) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                            <input type="hidden" name="status" value="{{ $status->value }}">
                            <button
                                class="catalog-status-option"
                                type="submit"
                                role="menuitem"
                                @disabled($item->status === $status)
                            >
                                <span class="catalog-status-dot {{ $status === \Modules\Catalog\Enums\ProductStatus::Active ? 'live' : '' }}"></span>
                                <span>{{ $status->label() }}</span>
                                @if ($item->status === $status)
                                    <span class="catalog-status-check" aria-label="Current status">✓</span>
                                @endif
                            </button>
                        </form>
                    @endforeach
                </div>
            </details>
            <button
                class="btn catalog-icon-action catalog-edit-button"
                type="button"
                data-dialog-open="edit-product-{{ $item->id }}"
                aria-label="Edit {{ $item->name }}"
                title="Edit product"
            >
                <svg data-catalog-button-icon="edit" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="m4 20 4.3-1 10-10a2.1 2.1 0 0 0-3-3l-10 10L4 20Zm9.8-12.8 3 3"/>
                </svg>
            </button>
            @if ($canDeleteItem)
                <form
                    method="POST"
                    action="{{ route('admin.catalog.products.destroy', $item) }}"
                    onsubmit="return confirm('Delete this item? It will no longer appear in the catalog or storefront.');"
                >
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                    <button
                        class="btn catalog-icon-action catalog-delete-button"
                        type="submit"
                        aria-label="Delete {{ $item->name }}"
                        title="Delete product"
                    >
                        <svg data-catalog-button-icon="remove" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 7h16M9 7V4h6v3m3 0-1 13H7L6 7m4 4v5m4-5v5"/>
                        </svg>
                    </button>
                </form>
            @endif
        </div>
    </div>
</article>
