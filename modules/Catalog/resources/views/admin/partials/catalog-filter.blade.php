<div class="catalog-toolbar">
    <div class="catalog-search">
        <span class="search-icon">⌕</span>
        <input data-catalog-search="{{ $scope }}" data-catalog-query-param="{{ $scope }}_q" type="search" value="{{ $filters['q'] }}" placeholder="Search by name, SKU, barcode, brand, or category" autocomplete="off">
        <kbd>⌘ K</kbd>
    </div>
    <select class="catalog-toolbar-status" data-catalog-status="{{ $scope }}" data-catalog-query-param="{{ $scope }}_status" aria-label="Filter by status">
        <option value="">All statuses</option>
        @foreach ($statuses as $status)
            <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
        @endforeach
    </select>
    <button class="btn accent" type="button" data-filter-toggle="{{ $scope }}">Filter</button>
</div>

<div class="catalog-filter-row" data-filter-row="{{ $scope }}">
    <div class="field">
        <label>Category</label>
        <select data-catalog-category="{{ $scope }}" data-catalog-query-param="{{ $scope }}_category">
            <option value="">All categories</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected($filters['category'] === $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label>Sort</label>
        <select disabled>
            <option>Newest first</option>
        </select>
    </div>
</div>
