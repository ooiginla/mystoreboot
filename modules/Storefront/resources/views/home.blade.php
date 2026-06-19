@extends('storefront::layout', ['title' => $store->store_name])

@php
    $heroUrl = $store->hero_image_path ? '/storage/'.ltrim($store->hero_image_path, '/') : null;
    $productCategories = $store->categories->filter(fn ($category) => ($category->category_type?->value ?? (string) $category->category_type) === 'product');
@endphp

@section('content')
    @if ($store->maintenance_mode)
        <section class="store-shell py-20">
            <div class="mx-auto max-w-2xl text-center">
                <span class="material-symbols-outlined text-6xl text-[var(--store-secondary)]">construction</span>
                <h1 class="store-display mt-5 text-4xl font-black text-[var(--store-primary)]">We will be back soon</h1>
                <p class="mt-4 text-lg leading-8 text-[var(--store-muted)]">{{ $store->store_name }} is refreshing the online store experience. Please check back shortly.</p>
            </div>
        </section>
    @else
        <section class="relative min-h-[520px] overflow-hidden">
            @if ($heroUrl)
                <img src="{{ $heroUrl }}" alt="{{ $store->store_name }} hero image" class="absolute inset-0 h-full w-full object-cover">
            @else
                <div class="absolute inset-0" style="background: linear-gradient(135deg, color-mix(in srgb, var(--store-primary) 24%, white), color-mix(in srgb, var(--store-secondary) 28%, white));"></div>
            @endif
            <div class="absolute inset-0 bg-gradient-to-r from-white via-white/80 to-white/10"></div>
            <div class="store-shell relative flex min-h-[520px] items-center py-16">
                <div class="max-w-2xl">
                    @if ($store->hero_image_tag)
                        <span class="inline-flex rounded-full px-4 py-2 text-xs font-black uppercase tracking-[.16em] text-white" style="background: var(--store-secondary);">{{ $store->hero_image_tag }}</span>
                    @endif
                    <h1 class="store-display mt-5 text-4xl font-black leading-tight text-[var(--store-primary)] md:text-6xl">{{ $store->hero_image_text ?: 'Shop '.$store->store_name }}</h1>
                    <p class="mt-5 max-w-xl text-lg leading-8 text-[var(--store-muted)]">{{ $store->hero_image_description ?: 'Explore our latest products, curated offers, and customer-first shopping experience.' }}</p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="#products" class="store-btn store-btn-secondary">Shop products <span class="material-symbols-outlined text-[20px]">arrow_downward</span></a>
                        <a href="{{ route('storefront.storefront.store.contact', $store) }}" class="store-btn border border-[var(--store-line)] bg-white text-[var(--store-primary)]">Contact us</a>
                    </div>
                </div>
            </div>
        </section>

        <section id="products" class="store-shell py-14">
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                <div>
                    <h2 class="store-display text-3xl font-black text-[var(--store-primary)]">Our Products</h2>
                    <p class="mt-2 text-[var(--store-muted)]">Browse items available from {{ $store->store_name }}.</p>
                </div>
                @if ($productCategories->isNotEmpty())
                    <div class="flex gap-2 overflow-x-auto pb-1">
                        <a href="{{ route('storefront.storefront.store.home', $store) }}#products" class="whitespace-nowrap rounded-full border border-[var(--store-line)] px-4 py-2 text-sm font-bold {{ $selectedCategory === '' ? 'bg-black text-white' : 'bg-white text-[var(--store-muted)]' }}">All</a>
                        @foreach ($productCategories as $category)
                            <a href="{{ route('storefront.storefront.store.home', [$store, 'category' => $category->slug]) }}#products" class="whitespace-nowrap rounded-full border border-[var(--store-line)] px-4 py-2 text-sm font-bold {{ $selectedCategory === $category->slug ? 'bg-black text-white' : 'bg-white text-[var(--store-muted)]' }}">{{ $category->name }}</a>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @forelse ($products as $product)
                    @include('storefront::partials.product-card', ['product' => $product, 'detailRouteName' => 'storefront.storefront.store.products.show'])
                @empty
                    <div class="store-card col-span-full p-10 text-center">
                        <h3 class="store-display text-2xl font-black">No products available yet</h3>
                        <p class="mt-2 text-[var(--store-muted)]">Please check back soon for new arrivals.</p>
                    </div>
                @endforelse
            </div>

            @if ($products->hasPages())
                <div class="mt-10">
                    {{ $products->fragment('products')->links() }}
                </div>
            @endif
        </section>
    @endif
@endsection
