<div class="catalog-toolbar">
    <div class="catalog-search">
        <span class="search-icon">⌕</span>
        <input data-catalog-search="{{ $scope }}" type="search" placeholder="Search by name, SKU, barcode, brand, or category" autocomplete="off">
        <kbd>⌘ K</kbd>
    </div>
    <button class="btn accent" type="button" data-filter-toggle="{{ $scope }}">Filter</button>
</div>

<div class="catalog-filter-row" data-filter-row="{{ $scope }}">
    <div class="field">
        <label>Category</label>
        <select data-catalog-category="{{ $scope }}">
            <option value="">All categories</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}">{{ $category->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label>Status</label>
        <select data-catalog-status="{{ $scope }}">
            <option value="">All statuses</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}">{{ $status->label() }}</option>
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
