@php
    $currencySymbol = ['NGN' => '₦', 'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'GHS' => '₵', 'KES' => 'KSh', 'ZAR' => 'R'][$product->currency_code] ?? $product->currency_code;
    $payload = [
        'id' => 'reseller-product-'.$product->id,
        'productVariantId' => $product->id,
        'productType' => 'product',
        'name' => $product->name,
        'priceMinor' => $product->selling_price_minor,
        'image' => $product->main_image_url,
    ];
@endphp

<article class="store-card store-product-card group overflow-hidden p-2 transition-all duration-300 hover:shadow-2xl">
    <a href="{{ $storefrontRoute($store, 'products.show', ['productSlug' => $product->id]) }}" class="relative mb-4 flex aspect-square items-center justify-center overflow-hidden rounded-lg bg-[var(--store-soft)]">
        @if ($product->main_image_url)
            <img src="{{ $product->main_image_url }}" alt="{{ $product->name }}" loading="lazy" class="h-full w-full object-cover object-center transition-transform duration-500 group-hover:scale-105">
        @else
            <div class="sf-headline-lg text-[var(--store-primary)]">{{ Str::of($product->name)->substr(0, 2)->upper() }}</div>
        @endif
    </a>
    <div class="store-product-card-body px-2 pb-2">
        <a href="{{ $storefrontRoute($store, 'products.show', ['productSlug' => $product->id]) }}" class="sf-body-md store-product-card-title mt-2 block line-clamp-2 font-bold text-[var(--store-ink)]">{{ $product->name }}</a>
        @if ($settings->show_source_store)<p class="sf-caption mt-1 text-[var(--store-muted)]">From {{ $product->source_store_name }}</p>@endif
        <div class="store-product-card-price mt-2 flex items-center">
            <strong class="sf-body-md font-bold text-[var(--store-secondary)]">{{ $currencySymbol }}{{ number_format($product->selling_price_minor / 100, 2) }}</strong>
            @if ($product->selling_previous_price_minor > $product->selling_price_minor)
                <span class="sf-body-md text-[var(--store-muted)] line-through">{{ $currencySymbol }}{{ number_format($product->selling_previous_price_minor / 100, 2) }}</span>
            @endif
        </div>
        <button type="button" class="sf-label-md store-product-card-action flex w-full items-center justify-center gap-2 rounded-lg bg-[var(--store-secondary)] py-3 uppercase text-white" data-add-to-cart data-product='@json($payload)'>
            @include('storefront::partials.icon', ['name' => 'shopping_cart', 'class' => 'h-5 w-5']) Add to Cart
        </button>
    </div>
</article>
