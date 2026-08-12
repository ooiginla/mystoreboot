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
    $activeVariants = $product->variants
        ->sortBy('selling_price_minor')
        ->values();
    $variant = $activeVariants->first();
    $variantPrices = $activeVariants->pluck('selling_price_minor')->map(fn ($price) => (int) $price)->unique();
    $priceMinor = (int) ($variant?->selling_price_minor ?? $product->base_price_minor);
    $compareMinor = (int) ($variant?->compare_at_price_minor ?? $product->compare_at_price_minor ?? 0);
    $showFromPrice = $product->has_variants && $variantPrices->count() > 1;
    $requiresVariantSelection = $product->has_variants && $activeVariants->count() > 1;
    $requiresPersonalizationChoice = (bool) data_get($product->personalization_settings, 'enabled', false);
    $imagePath = $variant?->image_path ?: $product->image_path;
    $image = $imagePath ? '/storage/'.ltrim($imagePath, '/') : null;
    $payload = [
        'id' => 'product-'.$product->id.($variant ? '-variant-'.$variant->id : ''),
        'productVariantId' => $variant?->id,
        'name' => $product->name.($variant && $product->has_variants ? ' - '.$variant->variant_name : ''),
        'priceMinor' => $priceMinor,
        'image' => $image,
    ];
    $detailRouteName = $detailRouteName ?? 'products.show';
    $detailsUrl = $storefrontRoute($store, $detailRouteName, [
        $detailRouteName === 'services.show' ? 'serviceSlug' : 'productSlug' => $product->slug,
    ]);
@endphp

<article class="store-card store-product-card group cursor-pointer overflow-hidden p-2 transition-all duration-300 hover:shadow-2xl">
    <a href="{{ $detailsUrl }}" class="relative mb-4 flex aspect-square items-center justify-center overflow-hidden rounded-lg bg-[var(--store-soft)]">
        @include('storefront::partials.product-badges', ['product' => $product])
        @if ($image)
            <img src="{{ $image }}" alt="{{ $product->name }}" loading="lazy" class="h-full w-full object-cover object-center transition-transform duration-500 group-hover:scale-105">
        @else
            <div class="sf-headline-lg flex h-full w-full items-center justify-center text-[var(--store-primary)]">{{ Str::of($product->name)->substr(0, 2)->upper() }}</div>
        @endif
    </a>
    <div class="store-product-card-body px-2 pb-2">
        <a href="{{ $detailsUrl }}" class="sf-body-md store-product-card-title mt-2 block line-clamp-2 font-bold text-[var(--store-ink)] hover:text-[var(--store-primary)]">{{ $product->name }}</a>
        <div class="store-product-card-price mt-2 flex items-center">
            <strong class="sf-body-md font-bold text-[var(--store-secondary)]" @if ($showFromPrice) data-variant-price-mode="from" @endif>{{ $showFromPrice ? 'From ' : '' }}{{ $currencySymbol }}{{ $money($priceMinor) }}</strong>
            @if ($compareMinor && $compareMinor > $priceMinor)
                <span class="sf-body-md text-[var(--store-muted)] line-through">{{ $currencySymbol }}{{ $money($compareMinor) }}</span>
            @endif
        </div>
        @if ($requiresVariantSelection || $requiresPersonalizationChoice)
            <a href="{{ $detailsUrl }}" class="sf-label-md store-product-card-action flex w-full items-center justify-center gap-2 rounded-lg bg-[var(--store-secondary)] py-3 uppercase text-white transition-colors hover:brightness-90">
                {{ $requiresPersonalizationChoice ? 'Personalise item' : 'Choose options' }}
                @include('storefront::partials.icon', ['name' => 'chevron_right', 'class' => 'h-5 w-5'])
            </a>
        @elseif ($variant)
            <button type="button" class="sf-label-md store-product-card-action flex w-full items-center justify-center gap-2 rounded-lg bg-[var(--store-secondary)] py-3 uppercase text-white transition-colors hover:brightness-90" data-add-to-cart data-product='@json($payload)'>
                @include('storefront::partials.icon', ['name' => 'shopping_cart', 'class' => 'h-5 w-5'])
                Add to Cart
            </button>
        @else
            <a href="{{ $detailsUrl }}" class="sf-label-md store-product-card-action flex w-full items-center justify-center gap-2 rounded-lg border border-[var(--store-line)] py-3 uppercase text-[var(--store-muted)]">
                View details
                @include('storefront::partials.icon', ['name' => 'chevron_right', 'class' => 'h-5 w-5'])
            </a>
        @endif
    </div>
</article>
