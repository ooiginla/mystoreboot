@php
    $children = $allCategories
        ->filter(fn ($candidate) => (int) $candidate->parent_id === (int) $category->id)
        ->values();
    $isSelected = $selectedCategory === $category->slug;
    $containsSelectedChild = $children->contains(fn ($child) => $selectedCategory === $child->slug);
    $categoryUrl = $filterUrl($storefrontRoute($store, 'categories.show', ['categorySlug' => $category->slug])).'#products';
@endphp

@if ($children->isNotEmpty())
    <details @if ($isSelected || $containsSelectedChild) open @endif>
        <summary class="sf-body-md flex cursor-pointer list-none items-center justify-between rounded-md px-2 py-2 font-semibold {{ $isSelected ? 'text-[var(--store-secondary)]' : 'text-[var(--store-muted)]' }} hover:bg-[var(--store-soft)]">
            <span>{{ $category->name }}</span>
            @include('storefront::partials.icon', ['name' => 'chevron_right', 'class' => 'h-5 w-5 rotate-90'])
        </summary>
        <div class="ml-3 grid gap-1 border-l border-[var(--store-line)] pl-2">
            <a href="{{ $categoryUrl }}" class="sf-caption rounded-md px-2 py-2 font-bold uppercase text-[var(--store-secondary)] hover:bg-[var(--store-soft)]">All {{ $category->name }}</a>
            @foreach ($children as $child)
                @include('storefront::partials.product-filter-category-item', [
                    'category' => $child,
                    'allCategories' => $allCategories,
                    'filterUrl' => $filterUrl,
                ])
            @endforeach
        </div>
    </details>
@else
    <a href="{{ $categoryUrl }}" class="sf-body-md rounded-md px-2 py-2 font-semibold {{ $isSelected ? 'text-[var(--store-secondary)]' : 'text-[var(--store-muted)]' }} hover:bg-[var(--store-soft)]">{{ $category->name }}</a>
@endif
