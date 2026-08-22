@php
    $currency = $service->tenant?->currency_code ?? $store->tenant?->currency_code ?? 'NGN';
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
    $activeVariants = $service->variants->sortBy('selling_price_minor')->values();
    $variant = $activeVariants->first();
    $variantPrices = $activeVariants->pluck('selling_price_minor')->map(fn ($price) => (int) $price)->unique();
    $priceMinor = (int) ($variant?->selling_price_minor ?? $service->base_price_minor);
    $showFromPrice = $service->has_variants && $variantPrices->count() > 1;
    $requiresChoices = ($service->has_variants && $activeVariants->count() > 1)
        || (bool) data_get($service->personalization_settings, 'enabled', false);
    $imagePath = $variant?->image_path ?: $service->image_path;
    $image = $imagePath ? '/storage/'.ltrim($imagePath, '/') : null;
    $description = trim(strip_tags((string) $service->description));
    $detailsUrl = $storefrontRoute($store, 'services.show', ['serviceSlug' => $service->slug]);
    $payload = [
        'id' => 'product-'.$service->id.($variant ? '-variant-'.$variant->id : ''),
        'productVariantId' => $variant?->id,
        'productType' => 'service',
        'name' => $service->name.($variant && $service->has_variants ? ' - '.$variant->variant_name : ''),
        'priceMinor' => $priceMinor,
        'image' => $image,
    ];
@endphp

<article class="store-card flex flex-col gap-4 p-4 sm:flex-row sm:items-center md:p-5" data-service-row>
    <div class="flex min-w-0 flex-1 items-start gap-4">
        <a href="{{ $detailsUrl }}" class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-[var(--store-soft)] md:h-20 md:w-20">
            @if ($image)
                <img src="{{ $image }}" alt="{{ $service->name }}" loading="lazy" class="h-full w-full object-cover object-center">
            @else
                <span class="sf-label-md text-[var(--store-primary)]">{{ Str::of($service->name)->substr(0, 2)->upper() }}</span>
            @endif
        </a>
        <div class="min-w-0 flex-1">
            <a href="{{ $detailsUrl }}" class="sf-headline-md block text-[var(--store-ink)] hover:text-[var(--store-primary)]">{{ $service->name }}</a>
            @if ($description !== '')
                <p class="sf-body-md mt-1 text-[var(--store-muted)]">{{ Str::limit($description, 160) }}</p>
            @endif
            <div class="sf-body-md mt-2 flex flex-wrap items-center gap-x-2 text-[var(--store-muted)]">
            <strong class="font-semibold text-[var(--store-secondary)]">{{ $showFromPrice ? 'From ' : '' }}{{ $currencySymbol }}{{ $money($priceMinor) }}</strong>
            </div>
        </div>
    </div>
    <div class="flex w-full shrink-0 gap-2 sm:w-auto">
        <a href="{{ $detailsUrl }}" class="store-btn store-service-action flex-1 border border-[var(--store-line)] bg-white text-[var(--store-primary)] sm:flex-none" data-service-details>
            @include('storefront::partials.icon', ['name' => 'link', 'class' => 'h-5 w-5'])
            Details
        </a>
    @if ($variant && ! $requiresChoices)
        <button type="button" class="store-btn store-btn-secondary store-service-action flex-1 sm:flex-none" data-add-to-cart data-service-add-to-cart data-product='@json($payload)' aria-label="Add {{ $service->name }} to cart">
            @include('storefront::partials.icon', ['name' => 'shopping_cart', 'class' => 'h-5 w-5'])
            Add to cart
        </button>
    @else
        <a href="{{ $detailsUrl }}" class="store-btn store-btn-secondary store-service-action flex-1 sm:flex-none" aria-label="Choose options for {{ $service->name }}">
            @include('storefront::partials.icon', ['name' => 'shopping_cart', 'class' => 'h-5 w-5'])
            Choose options
        </a>
    @endif
    </div>
</article>
