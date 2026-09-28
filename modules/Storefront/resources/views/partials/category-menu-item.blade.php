@php
    $depth = $depth ?? 0;
    $mobile = $mobile ?? false;
    $children = $allCategories
        ->filter(fn ($candidate) => (int) $candidate->parent_id === (int) $category->id)
        ->values();
    $categoryUrl = $storefrontRoute($store, 'categories.show', ['categorySlug' => $category->slug]);
@endphp

@if ($children->isNotEmpty())
    <details
        class="group/category"
        data-category-menu-parent="{{ $category->id }}"
        @if ($depth === 0) data-category-menu-root="{{ $category->id }}" @endif
    >
        <summary class="sf-body-md flex cursor-pointer list-none items-center justify-between rounded-md px-3 py-2 font-semibold text-[var(--store-muted)] hover:bg-[var(--store-soft)] hover:text-[var(--store-primary)]">
            <span>{{ $category->name }}</span>
            <span class="transition-transform group-open/category:rotate-90" data-category-menu-chevron>
                @include('storefront::partials.icon', ['name' => 'chevron_right', 'class' => 'h-5 w-5'])
            </span>
        </summary>
        <div class="ml-3 grid gap-1 border-l border-[var(--store-line)] pb-1 pl-2" data-category-menu-children="{{ $category->id }}">
            <a href="{{ $categoryUrl }}" class="sf-caption rounded-md px-3 py-2 font-bold uppercase tracking-wide text-[var(--store-secondary)] hover:bg-[var(--store-soft)]">
                View all {{ $category->name }}
            </a>
            @foreach ($children as $child)
                @include('storefront::partials.category-menu-item', [
                    'category' => $child,
                    'allCategories' => $allCategories,
                    'depth' => $depth + 1,
                    'mobile' => $mobile,
                ])
            @endforeach
        </div>
    </details>
@else
    <a
        href="{{ $categoryUrl }}"
        class="sf-body-md block rounded-md px-3 py-2 font-semibold text-[var(--store-muted)] hover:bg-[var(--store-soft)] hover:text-[var(--store-primary)]"
        data-category-menu-leaf="{{ $category->id }}"
        @if ($depth === 0) data-category-menu-root="{{ $category->id }}" @endif
    >{{ $category->name }}</a>
@endif
