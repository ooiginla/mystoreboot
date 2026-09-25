@extends('storefront::layout', ['title' => $product->name.' · '.$store->store_name, 'metaDescription' => $metaDescription, 'canonical' => $canonical])

@php
    $currencySymbol = ['NGN' => '₦', 'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'GHS' => '₵', 'KES' => 'KSh', 'ZAR' => 'R'][$product->currency_code] ?? $product->currency_code;
    $payload = ['id' => 'reseller-product-'.$product->id, 'productVariantId' => $product->id, 'productType' => 'product', 'name' => $product->name, 'priceMinor' => $product->selling_price_minor, 'image' => $product->main_image_url];
@endphp

@section('content')
    <section class="store-shell py-12">
        <div class="grid gap-10 lg:grid-cols-2">
            <div class="store-card flex aspect-square items-center justify-center overflow-hidden bg-[var(--store-soft)]">
                @if ($product->main_image_url)<img src="{{ $product->main_image_url }}" alt="{{ $product->name }}" class="h-full w-full object-cover">@else<div class="sf-display-xl text-[var(--store-primary)]">{{ Str::of($product->name)->substr(0, 2)->upper() }}</div>@endif
            </div>
            <div>
                @if ($product->brand)<p class="sf-label-md uppercase text-[var(--store-secondary)]">{{ $product->brand }}</p>@endif
                <h1 class="sf-display-xl mt-2 text-[var(--store-primary)]">{{ $product->name }}</h1>
                <div class="mt-5 flex items-center gap-3"><strong class="sf-headline-lg text-[var(--store-secondary)]">{{ $currencySymbol }}{{ number_format($product->selling_price_minor / 100, 2) }}</strong>@if ($product->selling_previous_price_minor > $product->selling_price_minor)<span class="sf-body-lg text-[var(--store-muted)] line-through">{{ $currencySymbol }}{{ number_format($product->selling_previous_price_minor / 100, 2) }}</span>@endif</div>
                @if ($settings->show_source_store)<p class="sf-body-md mt-3 text-[var(--store-muted)]">Supplied by {{ $product->source_store_name }} · Checked {{ $product->last_checked_at->diffForHumans() }}</p>@endif
                @if ($product->short_description)<div class="sf-body-lg mt-6 text-[var(--store-muted)]">{{ $product->short_description }}</div>@endif
                <dl class="mt-6 grid grid-cols-2 gap-3 text-sm">
                    @if ($product->sku)<div><dt class="font-bold">SKU</dt><dd>{{ $product->sku }}</dd></div>@endif
                    @if ($product->category)<div><dt class="font-bold">Category</dt><dd>{{ $product->category }}</dd></div>@endif
                    <div><dt class="font-bold">Availability</dt><dd>In stock</dd></div>
                </dl>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <button type="button" class="store-btn store-btn-secondary flex-1" data-add-to-cart data-product='@json($payload)'>@include('storefront::partials.icon', ['name' => 'shopping_cart', 'class' => 'h-5 w-5']) Add to Cart</button>
                    <button type="button" class="store-btn store-btn-primary flex-1" data-add-to-cart data-product='@json($payload)'>@include('storefront::partials.icon', ['name' => 'bolt', 'class' => 'h-5 w-5']) Buy It Now</button>
                </div>
            </div>
        </div>
    </section>
    @if ($relatedProducts->isNotEmpty())
        <section class="store-shell border-t border-[var(--store-line)] py-12"><h2 class="sf-headline-lg text-[var(--store-primary)]">You might also like</h2><div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">@foreach ($relatedProducts as $product)@include('reseller::storefront.partials.product-card')@endforeach</div></section>
    @endif
@endsection
