@extends('storefront::layout', ['title' => 'Services | '.$store->store_name])

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
        <section id="services" class="store-shell py-14">
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                <div>
                    <p class="text-sm font-black uppercase tracking-[.18em] text-[var(--store-secondary)]">{{ $store->store_name }}</p>
                    <h1 class="store-display mt-2 text-4xl font-black text-[var(--store-primary)]">Our Services</h1>
                    <p class="mt-2 text-[var(--store-muted)]">Browse services available from {{ $store->store_name }}.</p>
                </div>
            </div>

            <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @forelse ($services as $product)
                    @include('storefront::partials.product-card', ['product' => $product, 'detailRouteName' => 'storefront.storefront.store.services.show'])
                @empty
                    <div class="store-card col-span-full p-10 text-center">
                        <h3 class="store-display text-2xl font-black">No services available yet</h3>
                        <p class="mt-2 text-[var(--store-muted)]">Please check back soon.</p>
                    </div>
                @endforelse
            </div>

            @if ($services->hasPages())
                <div class="mt-10">
                    {{ $services->fragment('services')->links() }}
                </div>
            @endif
        </section>
    @endif
@endsection
