@php
    $variant = $product->variants->first();
    $imagePath = $variant?->image_path ?: $product->image_path;
    $image = $imagePath ? '/storage/'.ltrim($imagePath, '/') : null;
    $detailRouteName = $detailRouteName ?? 'storefront.storefront.store.products.show';
    $detailsUrl = route($detailRouteName, [$store, $product->slug]);
@endphp

<a href="{{ $detailsUrl }}" class="group block overflow-hidden rounded-lg border border-[var(--store-line)] bg-white p-2 transition hover:shadow-xl">
    <div class="flex aspect-square items-center justify-center overflow-hidden rounded-lg bg-[var(--store-soft)]">
        @if ($image)
            <img src="{{ $image }}" alt="{{ $product->name }}" class="h-4/5 w-4/5 object-contain transition-transform duration-500 group-hover:scale-110">
        @else
            <div class="sf-headline-lg flex h-full w-full items-center justify-center text-[var(--store-primary)]">{{ Str::of($product->name)->substr(0, 2)->upper() }}</div>
        @endif
    </div>
    <h3 class="sf-headline-md mt-4 line-clamp-2 px-2 pb-3 text-[var(--store-ink)] group-hover:text-[var(--store-primary)]">{{ $product->name }}</h3>
</a>
