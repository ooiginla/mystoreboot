@php
    $currency = $product->tenant?->currency_code ?? $store->tenant?->currency_code ?? 'NGN';
    $currencySymbol = [
        'NGN' => '₦',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'GHS' => '₵',
        'KES' => 'KSh',
        'ZAR' => 'R',
        'CAD' => '$',
        'AUD' => '$',
    ][strtoupper($currency)] ?? strtoupper($currency);
    $money = fn (int|float|null $minor): string => number_format(((int) $minor) / 100, 2);
    $variant = $product->variants->first();
    $priceMinor = (int) ($variant?->discount_price_minor ?? $variant?->selling_price_minor ?? $product->discount_price_minor ?? $product->base_price_minor);
    $compareMinor = $variant?->discount_price_minor ? (int) $variant->selling_price_minor : ((int) ($product->discount_price_minor ?? 0) > 0 ? (int) $product->base_price_minor : null);
    $imagePath = $variant?->image_path ?: $product->image_path;
    $image = $imagePath ? '/storage/'.ltrim($imagePath, '/') : null;
    $payload = [
        'id' => 'product-'.$product->id.($variant ? '-variant-'.$variant->id : ''),
        'name' => $product->name.($variant && $product->has_variants ? ' - '.$variant->variant_name : ''),
        'priceMinor' => $priceMinor,
        'image' => $image,
    ];
    $detailRouteName = $detailRouteName ?? 'storefront.storefront.store.products.show';
    $detailsUrl = route($detailRouteName, [$store, $product->slug]);
@endphp

<article class="store-card group cursor-pointer overflow-hidden p-2 transition-all duration-300 hover:shadow-2xl">
    <a href="{{ $detailsUrl }}" class="relative mb-4 flex aspect-square items-center justify-center overflow-hidden rounded-lg bg-[var(--store-soft)]">
        @if ($image)
            <img src="{{ $image }}" alt="{{ $product->name }}" class="h-4/5 w-4/5 object-contain transition-transform duration-500 group-hover:scale-110">
        @else
            <div class="flex h-full w-full items-center justify-center text-3xl font-black text-[var(--store-primary)]">{{ Str::of($product->name)->substr(0, 2)->upper() }}</div>
        @endif
        @if ($compareMinor && $compareMinor > $priceMinor)
            <span class="absolute left-3 top-3 rounded-full bg-black px-3 py-1 text-xs font-black text-white">Sale</span>
        @endif
    </a>
    <div class="px-2 pb-2">
        <a href="{{ $detailsUrl }}" class="store-display mt-2 block line-clamp-2 min-h-14 text-xl font-black text-[var(--store-ink)] hover:text-[var(--store-primary)]">{{ $product->name }}</a>
        <div class="mt-2 flex items-center gap-3">
            <strong class="text-lg text-[var(--store-secondary)]">{{ $currencySymbol }}{{ $money($priceMinor) }}</strong>
            @if ($compareMinor && $compareMinor > $priceMinor)
                <span class="text-sm text-[var(--store-muted)] line-through">{{ $currencySymbol }}{{ $money($compareMinor) }}</span>
            @endif
        </div>
        <button type="button" class="mt-4 flex w-full items-center justify-center gap-2 rounded-lg bg-[var(--store-secondary)] py-3 text-sm font-black uppercase tracking-[.05em] text-white transition-colors hover:brightness-90" data-add-to-cart data-product='@json($payload)'>
            <span class="material-symbols-outlined text-[20px]">shopping_cart</span>
            Add to Cart
        </button>
    </div>
</article>
