@extends('storefront::layout', [
    'title' => ($selectedCollection?->name ?? $selectedCategoryName) ? ($selectedCollection?->name ?? $selectedCategoryName).' · '.$store->store_name : $store->store_name,
    'metaDescription' => $metaDescription ?? null,
    'metaKeywords' => $metaKeywords ?? null,
    'canonical' => $canonical ?? null,
    'robots' => $robots ?? null,
])

@php
    $heroUrl = $store->hero_image_path ? '/storage/'.ltrim($store->hero_image_path, '/') : null;
    $heroSlides = collect($store->slides ?? [])
        ->filter(fn ($slide) => is_array($slide))
        ->map(fn (array $slide) => [
            'image' => filled($slide['image_path'] ?? null) ? '/storage/'.ltrim((string) $slide['image_path'], '/') : null,
            'tag' => $slide['hero_image_tag'] ?? null,
            'text' => $slide['hero_image_text'] ?? null,
            'description' => $slide['hero_image_description'] ?? null,
        ])
        ->filter(fn (array $slide) => $slide['image'] || $slide['tag'] || $slide['text'] || $slide['description'])
        ->values();

    if ($heroSlides->isEmpty()) {
        $heroSlides = collect([[
            'image' => $heroUrl,
            'tag' => $store->hero_image_tag,
            'text' => $store->hero_image_text,
            'description' => $store->hero_image_description,
        ]]);
    }

    $productCategories = $store->categories->filter(fn ($category) => ($category->category_type?->value ?? (string) $category->category_type) === 'product');
@endphp

@push('styles')
    <style>
        .store-hero { background: var(--store-soft); }
        .store-hero-slide { position: absolute; inset: 0; opacity: 0; pointer-events: none; transition: opacity .7s ease; }
        .store-hero-slide.is-active { opacity: 1; pointer-events: auto; z-index: 1; }
        .store-hero-image { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
        .store-hero-fallback { position: absolute; inset: 0; background: linear-gradient(135deg, color-mix(in srgb, var(--store-primary) 24%, white), color-mix(in srgb, var(--store-secondary) 28%, white)); }
        .store-hero-overlay { position: absolute; inset: 0; background: linear-gradient(90deg, rgba(255,255,255,.96), rgba(255,255,255,.78), rgba(255,255,255,.1)); }
        .store-hero-control { position: absolute; top: 50%; z-index: 5; display: inline-flex; width: 44px; height: 44px; transform: translateY(-50%); align-items: center; justify-content: center; border-radius: 999px; border: 1px solid rgba(17,24,39,.14); background: rgba(255,255,255,.86); color: var(--store-primary); box-shadow: 0 14px 32px rgba(15,23,42,.16); }
        .store-hero-control:hover { background: #fff; }
        .store-hero-control-prev { left: 18px; }
        .store-hero-control-next { right: 18px; }
        .store-hero-dots { position: absolute; bottom: 22px; left: 50%; z-index: 5; display: flex; transform: translateX(-50%); gap: 8px; }
        .store-hero-dot { width: 10px; height: 10px; border-radius: 999px; border: 1px solid rgba(17,24,39,.25); background: rgba(255,255,255,.72); transition: width .18s ease, background .18s ease; }
        .store-hero-dot.is-active { width: 28px; background: var(--store-secondary); border-color: var(--store-secondary); }
        .store-collection-viewport { overflow-x: auto; scroll-behavior: smooth; scroll-snap-type: x mandatory; scrollbar-width: none; }
        .store-collection-viewport::-webkit-scrollbar { display: none; }
        .store-collection-track { display: grid; grid-auto-columns: 100%; grid-auto-flow: column; gap: 1.5rem; }
        .store-collection-item { height: 100%; min-width: 0; scroll-snap-align: start; }
        .store-collection-control { position: absolute; top: 50%; z-index: 5; display: inline-flex; width: 44px; height: 44px; transform: translateY(-50%); align-items: center; justify-content: center; border-radius: 999px; border: 1px solid rgba(17,24,39,.14); background: rgba(255,255,255,.94); color: var(--store-primary); box-shadow: 0 14px 32px rgba(15,23,42,.18); transition: opacity .18s ease, background .18s ease; }
        .store-collection-control.hidden { display: none; }
        .store-collection-control:hover { background: #fff; }
        .store-collection-control:disabled { cursor: default; opacity: .35; }
        .store-collection-control-prev { left: 8px; }
        .store-collection-control-next { right: 8px; }
        @media (min-width: 640px) {
            .store-collection-track { grid-auto-columns: calc((100% - 1.5rem) / 2); }
        }
        @media (min-width: 1024px) {
            .store-collection-track { grid-auto-columns: calc((100% - 6rem) / 5); }
        }
        @media (max-width: 767px) {
            .store-hero-overlay { background: linear-gradient(90deg, rgba(255,255,255,.94), rgba(255,255,255,.8)); }
            .store-hero-control { display: none; }
        }
    </style>
@endpush

@section('content')
    @if ($store->maintenance_mode)
        <section class="store-shell py-20">
            <div class="mx-auto max-w-2xl text-center">
                @include('storefront::partials.icon', ['name' => 'construction', 'class' => 'mx-auto h-16 w-16 text-[var(--store-secondary)]'])
                <h1 class="sf-headline-lg mt-5 text-[var(--store-primary)]">We will be back soon</h1>
                <p class="sf-body-lg mt-4 text-[var(--store-muted)]">{{ $store->store_name }} is refreshing the online store experience. Please check back shortly.</p>
            </div>
        </section>
    @else
        @if (! $selectedCollection && $selectedCategory === '')
            <section class="store-hero relative min-h-[520px] overflow-hidden" data-store-hero-slider>
                @foreach ($heroSlides as $index => $slide)
                    <div class="store-hero-slide {{ $index === 0 ? 'is-active' : '' }}" data-store-hero-slide>
                        @if ($slide['image'])
                            <img src="{{ $slide['image'] }}" alt="{{ $store->store_name }} hero slide {{ $index + 1 }}" @if ($index === 0) fetchpriority="high" @else loading="lazy" @endif class="store-hero-image">
                        @else
                            <div class="store-hero-fallback"></div>
                        @endif
                        <div class="store-hero-overlay"></div>
                        <div class="store-shell relative flex min-h-[520px] items-center py-16">
                            <div class="max-w-2xl">
                                @if ($slide['tag'])
                                    <span class="sf-label-md inline-flex rounded-full px-4 py-2 uppercase text-white" style="background: var(--store-secondary);">{{ $slide['tag'] }}</span>
                                @endif
                                <h1 class="sf-display-xl mt-5 text-[var(--store-primary)]">{{ $slide['text'] ?: 'Shop '.$store->store_name }}</h1>
                                <p class="sf-body-lg mt-5 max-w-xl text-[var(--store-muted)]">{{ $slide['description'] ?: 'Explore our latest products, curated offers, and customer-first shopping experience.' }}</p>
                                <div class="mt-8 flex flex-wrap gap-3">
                                    <a href="#products" class="store-btn store-btn-secondary">Shop products @include('storefront::partials.icon', ['name' => 'arrow_downward', 'class' => 'h-5 w-5'])</a>
                                    <a href="{{ $storefrontRoute($store, 'contact') }}" class="store-btn border border-[var(--store-line)] bg-white text-[var(--store-primary)]">Contact us</a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach

                @if ($heroSlides->count() > 1)
                    <button type="button" class="store-hero-control store-hero-control-prev" data-store-hero-prev aria-label="Previous slide">@include('storefront::partials.icon', ['name' => 'chevron_left', 'class' => 'h-5 w-5'])</button>
                    <button type="button" class="store-hero-control store-hero-control-next" data-store-hero-next aria-label="Next slide">@include('storefront::partials.icon', ['name' => 'chevron_right', 'class' => 'h-5 w-5'])</button>
                    <div class="store-hero-dots" aria-label="Hero slides">
                        @foreach ($heroSlides as $index => $slide)
                            <button type="button" class="store-hero-dot {{ $index === 0 ? 'is-active' : '' }}" data-store-hero-dot aria-label="Show slide {{ $index + 1 }}"></button>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif

        @if ($selectedCategory === '' && ! $selectedCollection)
            @foreach ($productCollections as $collection)
                <section id="collection-{{ $collection->slug ?: $collection->id }}" class="store-shell py-12">
                    <div>
                        <h2 class="sf-headline-lg text-[var(--store-primary)]">{{ $collection->name }}</h2>
                        <p class="sf-body-md mt-2 text-[var(--store-muted)]">Explore products from this collection.</p>
                    </div>
                    <div class="relative mt-8" data-collection-carousel>
                        <div class="store-collection-viewport" data-collection-viewport>
                            <div class="store-collection-track">
                                @foreach ($collection->products as $product)
                                    <div class="store-collection-item">
                                        @include('storefront::partials.product-card', ['product' => $product, 'detailRouteName' => 'products.show'])
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <button type="button" class="store-collection-control store-collection-control-prev hidden" data-collection-prev aria-label="Previous products in {{ $collection->name }}">
                            @include('storefront::partials.icon', ['name' => 'chevron_left', 'class' => 'h-5 w-5'])
                        </button>
                        <button type="button" class="store-collection-control store-collection-control-next hidden" data-collection-next aria-label="Next products in {{ $collection->name }}">
                            @include('storefront::partials.icon', ['name' => 'chevron_right', 'class' => 'h-5 w-5'])
                        </button>
                    </div>
                </section>
            @endforeach
        @endif

        <section id="products" class="store-shell py-14">
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                <div>
                    <h2 class="sf-headline-lg text-[var(--store-primary)]">{{ $selectedCollection?->name ?? $selectedCategoryName ?? 'Our Products' }}</h2>
                    <p class="sf-body-md mt-2 text-[var(--store-muted)]">
                        {{ $selectedCollection ? 'Browse all products in this collection.' : ($selectedCategoryName ? 'Browse all products in this category.' : 'Browse items available from '.$store->store_name.'.') }}
                    </p>
                </div>
                @if ($productCategories->isNotEmpty())
                    <div class="flex gap-2 overflow-x-auto pb-1">
                        <a href="{{ $storefrontRoute($store) }}#products" class="sf-label-md whitespace-nowrap rounded-full border border-[var(--store-line)] px-4 py-2 {{ $selectedCategory === '' && ! $selectedCollection ? 'bg-black text-white' : 'bg-white text-[var(--store-muted)]' }}">All</a>
                        @foreach ($productCategories as $category)
                            <a href="{{ $storefrontRoute($store, 'categories.show', ['categorySlug' => $category->slug]) }}" class="sf-label-md whitespace-nowrap rounded-full border border-[var(--store-line)] px-4 py-2 {{ $selectedCategory === $category->slug ? 'bg-black text-white' : 'bg-white text-[var(--store-muted)]' }}">{{ $category->name }}</a>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @forelse ($products as $product)
                    @include('storefront::partials.product-card', ['product' => $product, 'detailRouteName' => 'products.show'])
                @empty
                    <div class="store-card col-span-full p-10 text-center">
                        <h3 class="sf-headline-lg-mobile">No products available yet</h3>
                        <p class="sf-body-md mt-2 text-[var(--store-muted)]">Please check back soon for new arrivals.</p>
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

@push('scripts')
    <script>
        (() => {
            const slider = document.querySelector('[data-store-hero-slider]');
            if (!slider) return;

            const slides = Array.from(slider.querySelectorAll('[data-store-hero-slide]'));
            const dots = Array.from(slider.querySelectorAll('[data-store-hero-dot]'));
            const previous = slider.querySelector('[data-store-hero-prev]');
            const next = slider.querySelector('[data-store-hero-next]');
            if (slides.length < 2) return;

            let active = 0;
            let timer = null;

            const show = (index) => {
                active = (index + slides.length) % slides.length;
                slides.forEach((slide, slideIndex) => {
                    slide.classList.toggle('is-active', slideIndex === active);
                });
                dots.forEach((dot, dotIndex) => {
                    dot.classList.toggle('is-active', dotIndex === active);
                    dot.setAttribute('aria-current', dotIndex === active ? 'true' : 'false');
                });
            };

            const start = () => {
                timer = window.setInterval(() => show(active + 1), 6500);
            };

            const restart = () => {
                if (timer) window.clearInterval(timer);
                start();
            };

            previous?.addEventListener('click', () => {
                show(active - 1);
                restart();
            });

            next?.addEventListener('click', () => {
                show(active + 1);
                restart();
            });

            dots.forEach((dot, index) => {
                dot.addEventListener('click', () => {
                    show(index);
                    restart();
                });
            });

            show(0);
            start();
        })();

        (() => {
            const carousels = Array.from(document.querySelectorAll('[data-collection-carousel]'));
            if (carousels.length === 0) return;

            carousels.forEach((carousel) => {
                const viewport = carousel.querySelector('[data-collection-viewport]');
                const previous = carousel.querySelector('[data-collection-prev]');
                const next = carousel.querySelector('[data-collection-next]');
                if (!viewport || !previous || !next) return;

                const update = () => {
                    const hasOverflow = viewport.scrollWidth > viewport.clientWidth + 1;
                    const atStart = viewport.scrollLeft <= 1;
                    const atEnd = viewport.scrollLeft + viewport.clientWidth >= viewport.scrollWidth - 1;

                    previous.classList.toggle('hidden', !hasOverflow);
                    next.classList.toggle('hidden', !hasOverflow);
                    previous.disabled = atStart;
                    next.disabled = atEnd;
                };

                const move = (direction) => {
                    viewport.scrollBy({
                        left: direction * viewport.clientWidth,
                        behavior: 'smooth',
                    });
                };

                previous.addEventListener('click', () => move(-1));
                next.addEventListener('click', () => move(1));
                viewport.addEventListener('scroll', update, { passive: true });
                window.addEventListener('resize', update);
                update();
            });
        })();
    </script>
@endpush
