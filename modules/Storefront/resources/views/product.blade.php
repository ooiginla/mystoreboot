@extends('storefront::layout', ['title' => $product->name.' | '.$store->store_name])

@php
    $currency = $store->tenant?->currency_code ?? 'NGN';
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
    $priceMinor = (int) ($variant?->selling_price_minor ?? $product->base_price_minor);
    $compareMinor = (int) ($variant?->compare_at_price_minor ?? $product->compare_at_price_minor ?? 0);
    $gallery = collect([$product->image_path])
        ->merge($product->images->pluck('image_path'))
        ->merge($product->variants->pluck('image_path'))
        ->filter()
        ->unique()
        ->map(fn ($path) => '/storage/'.ltrim($path, '/'))
        ->values();
    $primaryImage = $gallery->first();
    $optionGroups = $product->variants
        ->flatMap(fn ($row) => $row->optionValues)
        ->groupBy(fn ($value) => $value->option?->name ?? 'Options');
    $attributeGroups = $product->attributeValues->groupBy(fn ($value) => $value->definition?->name ?? 'Attributes');
    $payload = [
        'id' => 'product-'.$product->id.($variant ? '-variant-'.$variant->id : ''),
        'productVariantId' => $variant?->id,
        'name' => $product->name.($variant && $product->has_variants ? ' - '.$variant->variant_name : ''),
        'priceMinor' => $priceMinor,
        'image' => $primaryImage,
    ];
    $isService = ($catalogType ?? $product->product_type) === \Modules\Catalog\Enums\ProductType::Service;
    $detailRouteName = $isService ? 'storefront.storefront.store.services.show' : 'storefront.storefront.store.products.show';
    $shareUrl = route($detailRouteName, [$store, $product->slug]);
@endphp

@section('content')
    <section class="store-shell py-10 md:py-14">
        <div class="grid grid-cols-1 gap-10 lg:grid-cols-12">
            <div class="lg:col-span-7">
                <div class="relative aspect-[4/5] overflow-hidden rounded-lg bg-[var(--store-soft)]">
                    @if ($primaryImage)
                        <img src="{{ $primaryImage }}" alt="{{ $product->name }}" class="h-full w-full object-contain mix-blend-multiply" data-product-main-image>
                    @else
                        <div class="sf-display-xl flex h-full w-full items-center justify-center text-[var(--store-primary)]">{{ Str::of($product->name)->substr(0, 2)->upper() }}</div>
                    @endif
                    @if ($gallery->count() > 1)
                        <div class="absolute inset-x-4 top-1/2 flex -translate-y-1/2 justify-between">
                            <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full bg-white shadow-xl" data-gallery-prev aria-label="Previous image">@include('storefront::partials.icon', ['name' => 'chevron_left', 'class' => 'h-5 w-5'])</button>
                            <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full bg-white shadow-xl" data-gallery-next aria-label="Next image">@include('storefront::partials.icon', ['name' => 'chevron_right', 'class' => 'h-5 w-5'])</button>
                        </div>
                    @endif
                </div>
                <div class="mt-4 grid grid-cols-4 gap-4" data-gallery-thumbnails>
                    @forelse ($gallery as $image)
                        <button type="button" class="aspect-square overflow-hidden rounded-lg border border-[var(--store-line)] bg-[var(--store-soft)] p-2 ring-[var(--store-primary)] first:ring-2" data-gallery-image="{{ $image }}" aria-label="View product image">
                            <img src="{{ $image }}" alt="" class="h-full w-full object-contain">
                        </button>
                    @empty
                        @for ($i = 0; $i < 4; $i++)
                            <div class="aspect-square rounded-lg bg-[var(--store-soft)]"></div>
                        @endfor
                    @endforelse
                </div>
            </div>

            <div class="lg:col-span-5">
                <p class="sf-label-md uppercase text-[var(--store-secondary)]">{{ $product->category?->name ?? 'Product' }}</p>
                <h1 class="sf-headline-lg mt-2 text-[var(--store-ink)]">{{ $product->name }}</h1>
                <div class="mt-4 flex items-center gap-3">
                    <strong class="sf-headline-lg text-[var(--store-primary)]">{{ $currencySymbol }}{{ $money($priceMinor) }}</strong>
                    @if ($compareMinor && $compareMinor > $priceMinor)
                        <span class="sf-body-lg text-[var(--store-muted)] line-through">{{ $currencySymbol }}{{ $money($compareMinor) }}</span>
                    @endif
                </div>

                <div class="mt-6">
                    <span class="sf-body-md font-bold">Quantity</span>
                    <div class="mt-2 flex w-fit items-center overflow-hidden rounded-full border border-[var(--store-line)] bg-white">
                        <button type="button" class="px-4 py-2 hover:bg-[var(--store-soft)]" data-detail-qty="-1">@include('storefront::partials.icon', ['name' => 'remove', 'class' => 'h-5 w-5'])</button>
                        <span class="sf-body-md min-w-12 text-center font-bold" data-detail-quantity>1</span>
                        <button type="button" class="px-4 py-2 hover:bg-[var(--store-soft)]" data-detail-qty="1">@include('storefront::partials.icon', ['name' => 'add', 'class' => 'h-5 w-5'])</button>
                    </div>
                </div>

                <div class="mt-5 grid gap-4">
                    @foreach ($optionGroups as $name => $values)
                        <label class="grid gap-2">
                            <span class="sf-body-md font-bold">{{ $name }}</span>
                            <select class="store-input">
                                @foreach ($values->unique('value') as $value)
                                    <option>{{ $value->value }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endforeach
                    @foreach ($attributeGroups as $name => $values)
                        <label class="grid gap-2">
                            <span class="sf-body-md font-bold">{{ $name }}</span>
                            <select class="store-input">
                                @foreach ($values->unique('value') as $value)
                                    <option>{{ $value->value }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endforeach
                </div>

                <div class="mt-6">
                    <p class="sf-body-md font-bold">Share this product</p>
                    <div class="mt-3 flex gap-3">
                        <a class="flex h-10 w-10 items-center justify-center rounded-full border border-[var(--store-line)] text-[var(--store-primary)] hover:bg-[var(--store-soft)]" href="https://facebook.com/sharer/sharer.php?u={{ urlencode($shareUrl) }}" aria-label="Share on Facebook">@include('storefront::partials.social-icon', ['network' => 'facebook'])</a>
                        <a class="flex h-10 w-10 items-center justify-center rounded-full border border-[var(--store-line)] text-[var(--store-primary)] hover:bg-[var(--store-soft)]" href="https://wa.me/?text={{ urlencode($product->name.' '.$shareUrl) }}" aria-label="Share on WhatsApp">@include('storefront::partials.social-icon', ['network' => 'whatsapp'])</a>
                        <a class="flex h-10 w-10 items-center justify-center rounded-full border border-[var(--store-line)] text-[var(--store-primary)] hover:bg-[var(--store-soft)]" href="https://twitter.com/intent/tweet?text={{ urlencode($product->name) }}&url={{ urlencode($shareUrl) }}" aria-label="Share on X"><span class="sf-label-md">X</span></a>
                    </div>
                </div>

                <div class="mt-7 border-t border-[var(--store-line)] pt-5">
                    <div class="flex gap-2 border-b border-[var(--store-line)]" role="tablist">
                        <button type="button" class="sf-label-md border-b-2 border-[var(--store-primary)] px-4 py-3 text-[var(--store-primary)]" data-tab-button="description">Product Description</button>
                        <button type="button" class="sf-label-md border-b-2 border-transparent px-4 py-3 text-[var(--store-muted)]" data-tab-button="reviews">Reviews</button>
                    </div>
                    <div class="sf-body-md py-5 text-[var(--store-muted)]" data-tab-panel="description">{{ $product->description ?: 'No product description has been added yet.' }}</div>
                    <div class="sf-body-md hidden py-5 text-[var(--store-muted)]" data-tab-panel="reviews">No customer reviews yet.</div>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    @forelse ($product->tags as $tag)
                        <span class="sf-caption rounded-full bg-[var(--store-soft)] px-3 py-1 font-bold uppercase text-[var(--store-muted)]">{{ $tag->name }}</span>
                    @empty
                        <span class="sf-caption rounded-full bg-[var(--store-soft)] px-3 py-1 font-bold uppercase text-[var(--store-muted)]">No tags</span>
                    @endforelse
                </div>

                <div class="mt-7 flex flex-col gap-3 sm:flex-row">
                    <button type="button" class="store-btn store-btn-secondary flex-1" data-add-to-cart data-use-detail-quantity="true" data-product='@json($payload)'>Add to Cart</button>
                    <button type="button" class="store-btn store-btn-primary flex-1" data-add-to-cart data-use-detail-quantity="true" data-product='@json($payload)'>Buy It Now</button>
                </div>
            </div>
        </div>
    </section>

    <section class="store-shell border-t border-[var(--store-line)] py-12">
        <h2 class="sf-headline-lg text-[var(--store-primary)]">YOU MIGHT ALSO LIKE</h2>
        <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @forelse ($relatedProducts as $product)
                @include('storefront::partials.related-product-card', ['product' => $product, 'detailRouteName' => $detailRouteName])
            @empty
                <div class="sf-body-md store-card col-span-full p-8 text-center text-[var(--store-muted)]">No related products yet.</div>
            @endforelse
        </div>
    </section>
@endsection
