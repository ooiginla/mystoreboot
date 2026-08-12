@php
    $variant = $product->variants->first();
    $imagePath = $variant?->image_path ?: $product->image_path;
    $image = $imagePath ? '/storage/'.ltrim($imagePath, '/') : null;
    $detailRouteName = $detailRouteName ?? 'products.show';
    $detailsUrl = $storefrontRoute($store, $detailRouteName, [
        $detailRouteName === 'services.show' ? 'serviceSlug' : 'productSlug' => $product->slug,
    ]);
@endphp

<a href="{{ $detailsUrl }}" class="group block overflow-hidden rounded-lg border border-[var(--store-line)] bg-white p-2 transition hover:shadow-xl">
    <div class="relative flex aspect-square items-center justify-center overflow-hidden rounded-lg bg-[var(--store-soft)]">
        @include('storefront::partials.product-badges', ['product' => $product])
        @if ($image)
            <img src="{{ $image }}" alt="{{ $product->name }}" loading="lazy" class="h-full w-full object-cover object-center transition-transform duration-500 group-hover:scale-105">
        @else
            <div class="sf-headline-lg flex h-full w-full items-center justify-center text-[var(--store-primary)]">{{ Str::of($product->name)->substr(0, 2)->upper() }}</div>
        @endif
    </div>
    <h3 class="sf-body-md mt-4 line-clamp-2 px-2 pb-3 font-bold text-[var(--store-ink)] group-hover:text-[var(--store-primary)]">{{ $product->name }}</h3>
</a>
