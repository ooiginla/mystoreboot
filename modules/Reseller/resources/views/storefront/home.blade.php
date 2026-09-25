@extends('storefront::layout', [
    'title' => $selectedCategory ? $selectedCategory.' · '.$store->store_name : $store->store_name,
    'metaDescription' => $metaDescription,
    'canonical' => $canonical,
    'robots' => $search !== '' ? 'noindex, follow' : null,
])

@section('content')
    @if ($store->maintenance_mode)
        <section class="store-shell py-20 text-center"><h1 class="sf-headline-lg">We will be back soon</h1></section>
    @else
        @if ($selectedCategory === '' && $search === '')
            <section class="bg-[var(--store-soft)]">
                <div class="store-shell flex min-h-[420px] items-center py-16">
                    <div class="max-w-2xl">
                        @if ($store->hero_image_tag)<span class="sf-label-md rounded-full bg-[var(--store-secondary)] px-4 py-2 uppercase text-white">{{ $store->hero_image_tag }}</span>@endif
                        <h1 class="sf-display-xl mt-5 text-[var(--store-primary)]">{{ $store->hero_image_text ?: 'Shop '.$store->store_name }}</h1>
                        <p class="sf-body-lg mt-5 text-[var(--store-muted)]">{{ $store->hero_image_description ?: 'Products selected from trusted suppliers, available in one convenient store.' }}</p>
                        <a href="#products" class="store-btn store-btn-secondary mt-8">Shop products @include('storefront::partials.icon', ['name' => 'arrow_downward', 'class' => 'h-5 w-5'])</a>
                    </div>
                </div>
            </section>
        @endif

        <section id="products" class="store-shell py-14">
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                <div><h2 class="sf-headline-lg text-[var(--store-primary)]">{{ $search ? 'Search results for “'.$search.'”' : ($selectedCategory ?: 'Our Products') }}</h2><p class="sf-body-md mt-2 text-[var(--store-muted)]">{{ $products->total() }} {{ Str::plural('product', $products->total()) }} available.</p></div>
                @if ($categories->isNotEmpty())
                    <div class="flex max-w-full gap-2 overflow-x-auto pb-1">
                        <a href="{{ $storefrontRoute($store) }}#products" class="sf-label-md whitespace-nowrap rounded-full border px-4 py-2">All</a>
                        @foreach ($categories as $category)
                            <a href="{{ $storefrontRoute($store, 'categories.show', ['categorySlug' => Str::slug($category)]) }}" class="sf-label-md whitespace-nowrap rounded-full border px-4 py-2">{{ $category }}</a>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @forelse ($products as $product)
                    @include('reseller::storefront.partials.product-card')
                @empty
                    <div class="store-card col-span-full p-10 text-center"><h3 class="sf-headline-md">No products available</h3><p class="sf-body-md mt-2 text-[var(--store-muted)]">Please check back after our next supplier update.</p></div>
                @endforelse
            </div>
            @if ($products->hasPages())<div class="mt-10">{{ $products->fragment('products')->links() }}</div>@endif
        </section>
    @endif
@endsection
