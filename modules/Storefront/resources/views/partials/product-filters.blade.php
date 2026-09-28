@php
    $currencyCode = strtoupper($store->tenant?->currency_code ?? 'NGN');
    $currencyMark = [
        'NGN' => '₦', 'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'GHS' => '₵',
        'KES' => 'KSh', 'ZAR' => 'R', 'CAD' => '$', 'AUD' => '$',
    ][$currencyCode] ?? $currencyCode;
    $activeFilterQuery = array_filter([
        'min_price' => $filters['min_price'],
        'max_price' => $filters['max_price'],
        'in_stock' => $filters['in_stock'] ? 1 : null,
        'on_sale' => $filters['on_sale'] ? 1 : null,
    ], fn ($value) => $value !== null && $value !== false && $value !== '');
    $filterUrl = fn (string $url): string => $activeFilterQuery === [] ? $url : $url.'?'.http_build_query($activeFilterQuery);
@endphp

<form method="GET" action="{{ url()->current() }}" data-storefront-filters>
    @if ($search !== '')
        <input type="hidden" name="search" value="{{ $search }}">
    @endif

    <section class="p-5" data-filter-section="price">
        <div class="flex items-center justify-between gap-3">
            <h3 class="sf-label-md uppercase">Price ({{ $currencyMark }})</h3>
            <button type="submit" class="sf-label-md text-[var(--store-secondary)] hover:underline">Apply</button>
        </div>
        <div class="mt-4 grid grid-cols-2 gap-3">
            <label class="sf-caption text-[var(--store-muted)]">
                Min
                <input class="store-input mt-1 px-3 py-2" type="number" name="min_price" min="0" step="0.01" value="{{ $filters['min_price'] }}" placeholder="{{ number_format($priceBounds['min'], 0, '.', '') }}">
            </label>
            <label class="sf-caption text-[var(--store-muted)]">
                Max
                <input class="store-input mt-1 px-3 py-2" type="number" name="max_price" min="0" step="0.01" value="{{ $filters['max_price'] }}" placeholder="{{ number_format($priceBounds['max'], 0, '.', '') }}">
            </label>
        </div>
        <div class="sf-caption mt-3 flex justify-between text-[var(--store-muted)]">
            <span>{{ $currencyMark }}{{ number_format($priceBounds['min']) }}</span>
            <span>{{ $currencyMark }}{{ number_format($priceBounds['max']) }}</span>
        </div>
    </section>

    <section class="border-t border-[var(--store-line)] p-5" data-filter-section="category">
        <h3 class="sf-label-md mb-3 uppercase">Category</h3>
        <nav class="grid gap-1" aria-label="Filter by category">
            <a href="{{ $filterUrl($storefrontRoute($store)) }}#products" class="sf-body-md rounded-md px-2 py-2 font-semibold {{ $selectedCategory === '' ? 'text-[var(--store-secondary)]' : 'text-[var(--store-muted)]' }} hover:bg-[var(--store-soft)]">All categories</a>
            @foreach ($filterRootCategories as $category)
                @include('storefront::partials.product-filter-category-item', [
                    'category' => $category,
                    'allCategories' => $productCategories,
                    'filterUrl' => $filterUrl,
                ])
            @endforeach
        </nav>
    </section>

    <label class="sf-body-md flex cursor-pointer items-center gap-3 border-t border-[var(--store-line)] px-5 py-5">
        <input class="h-5 w-5 accent-[var(--store-secondary)]" type="checkbox" name="in_stock" value="1" @checked($filters['in_stock'])>
        <span>In Stock Only</span>
    </label>

    <label class="sf-body-md flex cursor-pointer items-center gap-3 border-t border-[var(--store-line)] px-5 py-5">
        <input class="h-5 w-5 accent-[var(--store-secondary)]" type="checkbox" name="on_sale" value="1" @checked($filters['on_sale'])>
        <span>On Sale Only</span>
    </label>

    <div class="grid gap-2 border-t border-[var(--store-line)] p-5">
        <button type="submit" class="store-btn store-btn-primary w-full">Apply filters</button>
        @if ($activeFilterQuery !== [])
            <a href="{{ url()->current() }}#products" class="sf-label-md py-2 text-center text-[var(--store-muted)] hover:underline">Clear filters</a>
        @endif
    </div>
</form>
