@php
    use Illuminate\Support\Str;
    use Modules\Catalog\Enums\CategoryType;
    use Modules\Catalog\Enums\ProductStatus;
    use Modules\Catalog\Enums\ProductType;

    $money = fn (?int $minor): string => number_format(($minor ?? 0) / 100, 2);
    $imageUrl = fn (?string $path): ?string => $path ? '/storage/'.ltrim($path, '/') : null;
@endphp

<x-layouts.admin title="Product & Services">
    <style>
        .catalog-toolbar { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 12px; margin-bottom: 16px; }
        .catalog-search { position: relative; }
        .catalog-search input { height: 46px; padding-left: 42px; padding-right: 72px; border-width: 2px; box-shadow: 0 1px 3px rgba(16,24,40,.08); }
        .catalog-search .search-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #667085; font-size: 20px; }
        .catalog-search kbd { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); border: 1px solid #d0d5dd; border-radius: 7px; padding: 3px 8px; background: #f8fafc; color: #344054; font-weight: 700; }
        .catalog-filter-row { display: none; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-bottom: 16px; }
        .catalog-filter-row.visible { display: grid; }
        .product-card-list { display: grid; gap: 12px; }
        .product-card { border: 1px solid var(--line); border-radius: 8px; background: #fff; padding: 14px; display: grid; grid-template-columns: 88px minmax(0, 1fr) auto; gap: 16px; align-items: center; box-shadow: 0 1px 3px rgba(16,24,40,.05); }
        .product-thumb { width: 88px; height: 72px; border: 1px solid var(--line); border-radius: 8px; background: #f8fafc; display: grid; place-items: center; overflow: hidden; color: #667085; font-weight: 800; }
        .product-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .product-name-link { border: 0; background: transparent; padding: 0; color: #101828; cursor: pointer; font-weight: 800; font-size: 16px; text-align: left; }
        .product-name-link:hover { color: var(--accent); }
        .product-meta { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 8px; color: #344054; }
        .product-meta strong { color: #101828; }
        .product-price-block { display: flex; align-items: center; gap: 12px; }
        .product-price { min-width: 104px; text-align: right; font-size: 18px; font-weight: 850; color: #101828; }
        .old-price { display: block; color: #667085; font-size: 13px; font-weight: 650; text-decoration: line-through; }
        .dialog-local-tabs { display: flex; gap: 8px; flex-wrap: wrap; border-bottom: 1px solid var(--line); padding-bottom: 10px; }
        .dialog-local-tabs a { border: 1px solid var(--line); border-radius: 999px; background: #fff; padding: 8px 12px; color: #344054; font-size: 13px; font-weight: 850; }
        .dialog-local-tabs a.active { border-color: var(--accent); background: #eff6ff; color: var(--accent-dark); }
        [data-local-tab-panel] { margin-top: 14px; }
        .catalog-profit-summary { grid-column: 1 / -1; display: flex; gap: 18px; flex-wrap: wrap; border: 1px solid #bfdbfe; border-radius: 8px; background: #eff6ff; color: #344054; padding: 10px 12px; font-size: 14px; font-weight: 750; }
        .catalog-profit-summary strong { color: #1d4ed8; }
        .catalog-image-uploader { border: 1px solid var(--line); border-radius: 8px; background: #fff; box-shadow: 0 1px 2px rgba(16,24,40,.04); overflow: hidden; }
        .catalog-image-uploader-header { padding: 18px 20px; border-bottom: 1px dashed var(--line); }
        .catalog-image-uploader-header h3 { margin: 0; color: #344054; font-size: 18px; }
        .catalog-image-uploader-header p { margin: 8px 0 0; color: #98a2b3; font-size: 15px; }
        .catalog-drop-zone { margin: 20px; min-height: 260px; border: 2px dashed #d9dee7; border-radius: 8px; background: #fff; display: grid; place-items: center; align-content: center; gap: 14px; text-align: center; cursor: pointer; color: #475467; padding: 28px; }
        .catalog-drop-zone.dragging { border-color: var(--accent); background: #eff6ff; }
        .catalog-drop-zone input { position: absolute; inline-size: 1px; block-size: 1px; opacity: 0; pointer-events: none; }
        .catalog-upload-icon { width: 54px; height: 54px; border-radius: 999px; display: grid; place-items: center; background: #e0f2fe; color: #38bdf8; font-size: 30px; font-weight: 900; }
        .catalog-drop-zone strong { color: #344054; font-size: 20px; }
        .catalog-drop-zone span:not(.catalog-upload-icon):not(.catalog-browse-button) { color: #98a2b3; font-size: 15px; }
        .catalog-browse-button { border: 1px solid #d0d5dd; border-radius: 6px; background: #fff; color: #344054; padding: 8px 14px; font-weight: 750; box-shadow: 0 1px 2px rgba(16,24,40,.08); }
        .catalog-current-images, .catalog-selected-images { margin: 0 20px 20px; display: flex; gap: 10px; flex-wrap: wrap; }
        .catalog-current-images img { width: 86px; height: 70px; object-fit: cover; border: 1px solid var(--line); border-radius: 8px; background: #f8fafc; }
        .catalog-selected-images span { border: 1px solid #d0d5dd; border-radius: 999px; background: #f8fafc; padding: 6px 10px; color: #344054; font-size: 13px; font-weight: 750; }
        .drawer { width: min(720px, calc(100vw - 24px)); max-width: none; height: 100vh; max-height: 100vh; margin: 0 0 0 auto; border: 0; padding: 0; border-radius: 8px 0 0 8px; box-shadow: -24px 0 60px rgba(16,24,40,.22); }
        .drawer::backdrop { background: rgba(16,24,40,.42); backdrop-filter: blur(2px); }
        .drawer-header { padding: 22px 24px; border-bottom: 1px solid var(--line); display: flex; justify-content: space-between; gap: 16px; align-items: start; }
        .drawer-body { padding: 24px; max-height: calc(100vh - 84px); overflow: auto; }
        .drawer-hero { border: 1px solid var(--line); border-radius: 8px; background: #f8fafc; min-height: 220px; display: grid; place-items: center; overflow: hidden; margin-bottom: 22px; }
        .drawer-hero img { width: 100%; height: 260px; object-fit: contain; }
        .drawer-title { font-size: 24px; margin: 0 0 10px; line-height: 1.25; }
        .detail-grid { display: grid; grid-template-columns: 180px minmax(0, 1fr); gap: 12px; margin-top: 20px; }
        .detail-grid dt { color: #344054; }
        .detail-grid dd { margin: 0; font-weight: 750; overflow-wrap: anywhere; }
        .variant-table { margin-top: 20px; overflow-x: auto; }
        .variant-row-editor { border: 1px solid var(--line); border-radius: 8px; padding: 12px; display: grid; gap: 10px; }
        .variant-row-editor + .variant-row-editor { margin-top: 10px; }
        .variant-grid { display: grid; grid-template-columns: 1.4fr 1fr 1fr 1fr; gap: 10px; }
        .check-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .inline-check { display: inline-flex; gap: 8px; align-items: center; color: #344054; font-weight: 750; }
        .inline-check input { width: auto; min-width: 16px; height: 16px; }
        .catalog-inline-box { border: 1px solid var(--line); border-radius: 8px; background: #f8fafc; padding: 12px; display: grid; gap: 12px; }
        .catalog-inline-create { border-top: 1px solid var(--line); padding-top: 12px; display: grid; gap: 10px; }
        .catalog-inline-heading { display: flex; justify-content: space-between; gap: 10px; align-items: center; }
        .catalog-inline-add-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 8px; align-items: center; }
        .catalog-radio-row { display: flex; gap: 16px; flex-wrap: wrap; }
        .catalog-variant-editor { margin-top: 16px; }
        .catalog-attribute-panel { padding: 0; overflow: hidden; }
        .catalog-attribute-toggle { width: 100%; border: 0; background: #eef4ff; color: #101828; padding: 12px; display: flex; align-items: center; gap: 8px; cursor: pointer; text-align: left; font-weight: 800; }
        .catalog-attribute-toggle:hover { background: #e0ebff; }
        .catalog-attribute-chevron { display: inline-grid; place-items: center; width: 18px; height: 18px; color: #2563eb; font-size: 22px; line-height: 1; transition: transform .16s ease; }
        .catalog-attribute-toggle[aria-expanded="true"] .catalog-attribute-chevron { transform: rotate(90deg); }
        .catalog-attribute-toggle-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: stretch; background: #eef4ff; }
        .catalog-attribute-toggle-row .catalog-attribute-toggle { background: transparent; }
        .catalog-attribute-toggle-row .btn.danger { align-self: center; margin-right: 8px; }
        .catalog-attribute-body { padding: 12px; display: grid; gap: 10px; }
        .btn.inline-add { background: #2563eb; color: #fff; border: 1px solid #1d4ed8; padding-inline: 16px; }
        .btn.inline-add:hover { background: #1d4ed8; }
        .category-type-pill { text-transform: capitalize; }
        .catalog-row-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; justify-content: flex-end; }
        @media (max-width: 900px) {
            .catalog-toolbar, .catalog-filter-row, .product-card { grid-template-columns: 1fr; }
            .product-card { align-items: start; }
            .product-price-block { justify-content: space-between; }
            .product-price { text-align: left; }
            .variant-grid { grid-template-columns: 1fr; }
            .check-grid { grid-template-columns: 1fr; }
            .catalog-inline-add-row { grid-template-columns: 1fr; }
            .detail-grid { grid-template-columns: 1fr; }
        }
    </style>

    <div class="topbar">
        <div>
            <div class="eyebrow">Catalog management</div>
            <h1>Product & Services</h1>
            <p class="subtle">Managing catalog records for {{ $tenant->name }}.</p>
        </div>

        @if ($isPlatformAdmin)
            <form method="GET" action="{{ route('admin.catalog.index') }}" style="min-width: 260px;">
                <select name="tenant" onchange="this.form.submit()">
                    @foreach ($tenants as $visibleTenant)
                        <option value="{{ $visibleTenant->id }}" @selected($visibleTenant->id === $tenant->id)>{{ $visibleTenant->name }}</option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    @if (session('status'))
        <div class="alert">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert errors">
            <strong>Check the highlighted catalog details.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="stats-grid" style="margin-bottom: 18px;">
        <div class="stat"><span class="subtle">Products</span><strong>{{ $stats['products'] }}</strong></div>
        <div class="stat"><span class="subtle">Services</span><strong>{{ $stats['services'] }}</strong></div>
        <div class="stat"><span class="subtle">Categories</span><strong>{{ $stats['categories'] }}</strong></div>
        <div class="stat"><span class="subtle">Visible variants</span><strong>{{ $stats['variants'] }}</strong></div>
    </div>

    <div class="tab-layout">
        <nav class="pill-nav" aria-label="Catalog sections" role="tablist">
            <a href="#products" role="tab" data-tab-target="products">Products <span class="badge neutral">{{ $stats['products'] }}</span></a>
            <a href="#services" role="tab" data-tab-target="services">Services <span class="badge neutral">{{ $stats['services'] }}</span></a>
            <a href="#categories" role="tab" data-tab-target="categories">Categories <span class="badge neutral">{{ $categories->count() }}</span></a>
            <a href="#tags" role="tab" data-tab-target="tags">Tags <span class="badge neutral">{{ $tags->count() }}</span></a>
            <a href="#attributes" role="tab" data-tab-target="attributes">Attributes <span class="badge neutral">{{ $attributes->count() }}</span></a>
            <a href="#taxes" role="tab" data-tab-target="taxes">Taxes <span class="badge neutral">{{ $taxes->count() }}</span></a>
        </nav>

        <div class="content-stack">
            <section class="panel tab-panel" id="products" role="tabpanel" data-tab-panel>
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Products</h2>
                        <p class="subtle">Physical items, grouped by product categories. Click a name to view details.</p>
                    </div>
                    <button class="btn accent" type="button" data-dialog-open="product-dialog">Add product</button>
                </div>
                <div class="panel-body">
                    @include('catalog::admin.partials.catalog-filter', [
                        'scope' => 'products',
                        'categories' => $productCategories,
                        'statuses' => $productStatuses,
                    ])

                    <div class="product-card-list" data-catalog-list="products">
                        @forelse ($productItems as $product)
                            @include('catalog::admin.partials.product-card', [
                                'item' => $product,
                                'tenant' => $tenant,
                                'money' => $money,
                                'imageUrl' => $imageUrl,
                            ])
                        @empty
                            <div class="empty">No products yet. Add products with SKU, pricing, cost, barcode, stock, and variant fields for future analytics.</div>
                        @endforelse
                        <div class="empty" data-catalog-empty="products" hidden>No products match this filter.</div>
                    </div>
                    @if ($productItems->hasPages())
                        <div style="margin-top: 14px;">
                            {{ $productItems->links() }}
                        </div>
                    @endif
                </div>
            </section>

            <section class="panel tab-panel" id="services" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Services</h2>
                        <p class="subtle">Non-stock sellable services grouped by service categories.</p>
                    </div>
                    <button class="btn accent" type="button" data-dialog-open="service-dialog">Add service</button>
                </div>
                <div class="panel-body">
                    @include('catalog::admin.partials.catalog-filter', [
                        'scope' => 'services',
                        'categories' => $serviceCategories,
                        'statuses' => $productStatuses,
                    ])

                    <div class="product-card-list" data-catalog-list="services">
                        @forelse ($serviceItems as $service)
                            @include('catalog::admin.partials.product-card', [
                                'item' => $service,
                                'tenant' => $tenant,
                                'money' => $money,
                                'imageUrl' => $imageUrl,
                            ])
                        @empty
                            <div class="empty">No services yet. Add services so sales can capture non-stock revenue correctly.</div>
                        @endforelse
                        <div class="empty" data-catalog-empty="services" hidden>No services match this filter.</div>
                    </div>
                    @if ($serviceItems->hasPages())
                        <div style="margin-top: 14px;">
                            {{ $serviceItems->links() }}
                        </div>
                    @endif
                </div>
            </section>

            <section class="panel tab-panel" id="categories" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Categories</h2>
                        <p class="subtle">Product and service categories are kept separate for cleaner reporting.</p>
                    </div>
                    <button class="btn accent" type="button" data-dialog-open="category-dialog">Add category</button>
                </div>
                <div class="panel-body">
                    <div class="list">
                        @forelse ($categories as $category)
                            <div class="item">
                                <div>
                                    <div class="item-title">{{ $category->name }}</div>
                                    <div class="subtle">{{ $category->parent?->name ? 'Under '.$category->parent->name : 'Top-level category' }}</div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span class="badge category-type-pill">{{ $category->category_type->label() }}</span>
                                    <span class="badge neutral">{{ $category->status }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="empty">No categories yet. Add categories before building a larger catalog.</div>
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="panel tab-panel" id="tags" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Tags</h2>
                        <p class="subtle">Reusable merchandising labels like 50% Off, Trending, or New Arrival.</p>
                    </div>
                    <button class="btn accent" type="button" data-dialog-open="tag-dialog">Add tag</button>
                </div>
                <div class="panel-body">
                    <div class="list">
                        @forelse ($tags as $tag)
                            <div class="item">
                                <div>
                                    <div class="item-title">{{ $tag->name }}</div>
                                    <div class="subtle">{{ $tag->slug }}</div>
                                </div>
                                <div class="catalog-row-actions">
                                    <button class="btn secondary" type="button" data-dialog-open="tag-edit-{{ $tag->id }}">Edit</button>
                                </div>
                            </div>
                        @empty
                            <div class="empty">No tags yet. Add tags to highlight products in the store.</div>
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="panel tab-panel" id="attributes" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Attributes</h2>
                        <p class="subtle">Reusable product details like Color, Size, Material, or Fit.</p>
                    </div>
                    <button class="btn accent" type="button" data-dialog-open="attribute-dialog">Add attribute</button>
                </div>
                <div class="panel-body">
                    <div class="list">
                        @forelse ($attributes as $attribute)
                            <div class="item">
                                <div>
                                    <div class="item-title">{{ $attribute->name }}</div>
                                    <div class="subtle">{{ $attribute->values->pluck('value')->join(', ') ?: 'No values yet' }}</div>
                                </div>
                                <div class="catalog-row-actions">
                                    <button class="btn secondary" type="button" data-dialog-open="attribute-edit-{{ $attribute->id }}">Edit</button>
                                </div>
                            </div>
                        @empty
                            <div class="empty">No attributes yet. Add attributes before assigning product details.</div>
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="panel tab-panel" id="taxes" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Taxes</h2>
                        <p class="subtle">Reusable tax rates that can be applied to products and services.</p>
                    </div>
                    <button class="btn accent" type="button" data-dialog-open="tax-dialog">Add tax</button>
                </div>
                <div class="panel-body">
                    <div class="list">
                        @forelse ($taxes as $tax)
                            <div class="item">
                                <div>
                                    <div class="item-title">{{ $tax->name }} - {{ $tax->rate }}%</div>
                                    <div class="subtle">{{ $tax->description ?: $tax->slug }}</div>
                                </div>
                                <div class="catalog-row-actions">
                                    <span class="badge {{ $tax->is_active ? 'success' : 'neutral' }}">{{ $tax->is_active ? 'Active' : 'Inactive' }}</span>
                                    <button class="btn secondary" type="button" data-dialog-open="tax-edit-{{ $tax->id }}">Edit</button>
                                    <form method="POST" action="{{ route('admin.catalog.taxes.destroy', $tax) }}" onsubmit="return confirm('Delete this tax? Products using it will no longer have this tax applied.');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn danger" type="submit">Delete</button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <div class="empty">No taxes yet. Add taxes before applying them to products.</div>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>
    </div>

    @include('catalog::admin.partials.product-dialog', [
        'dialogId' => 'product-dialog',
        'title' => 'Add product',
        'productType' => ProductType::Product->value,
        'product' => null,
        'variant' => null,
    ])

    @include('catalog::admin.partials.product-dialog', [
        'dialogId' => 'service-dialog',
        'title' => 'Add service',
        'productType' => ProductType::Service->value,
        'product' => null,
        'variant' => null,
    ])

    @foreach ($products as $product)
        @include('catalog::admin.partials.product-dialog', [
            'dialogId' => 'edit-product-'.$product->id,
            'title' => 'Edit '.$product->name,
            'productType' => $product->product_type->value,
            'product' => $product,
            'variant' => $product->variants->first(),
        ])

        @include('catalog::admin.partials.product-drawer', [
            'item' => $product,
            'tenant' => $tenant,
            'money' => $money,
            'imageUrl' => $imageUrl,
        ])
    @endforeach

    <dialog class="dialog" id="category-dialog">
        <div class="dialog-header">
            <div>
                <h2 class="panel-title">Add category</h2>
                <p class="subtle">Choose whether this category is for products or services.</p>
            </div>
            <button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button>
        </div>
        <div class="dialog-body">
            <form class="mini-form" method="POST" action="{{ route('admin.catalog.categories.store') }}">
                @csrf
                <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                <div class="form-grid">
                    <div class="field">
                        <label>Category type</label>
                        <select name="category_type" data-category-type-select required>
                            @foreach ($categoryTypes as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field"><label>Name</label><input name="name" required></div>
                    <div class="field"><label>Slug</label><input name="slug" placeholder="auto-generated"></div>
                    <div class="field">
                        <label>Parent category</label>
                        <select name="parent_id" data-category-parent-select>
                            <option value="">Top-level category</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" data-category-type="{{ $category->category_type->value }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="field"><label>Description</label><textarea name="description"></textarea></div>
                <div class="button-row">
                    <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                    <button class="btn primary" type="submit">Add category</button>
                </div>
            </form>
        </div>
    </dialog>

    <dialog class="dialog" id="tag-dialog">
        <div class="dialog-header">
            <div>
                <h2 class="panel-title">Add tag</h2>
                <p class="subtle">Create a reusable product tag.</p>
            </div>
            <button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button>
        </div>
        <div class="dialog-body">
            <form class="mini-form" method="POST" action="{{ route('admin.catalog.tags.store') }}">
                @csrf
                <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                <div class="form-grid">
                    <div class="field"><label>Name</label><input name="name" placeholder="50% Off" required></div>
                    <div class="field"><label>Slug</label><input name="slug" placeholder="auto-generated"></div>
                </div>
                <div class="button-row">
                    <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                    <button class="btn primary" type="submit">Save tag</button>
                </div>
            </form>
        </div>
    </dialog>

    @foreach ($tags as $tag)
        <dialog class="dialog" id="tag-edit-{{ $tag->id }}">
            <div class="dialog-header">
                <div>
                    <h2 class="panel-title">Edit tag</h2>
                    <p class="subtle">Update this product tag.</p>
                </div>
                <button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button>
            </div>
            <div class="dialog-body">
                <form class="mini-form" method="POST" action="{{ route('admin.catalog.tags.update', $tag) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                    <div class="form-grid">
                        <div class="field"><label>Name</label><input name="name" value="{{ $tag->name }}" required></div>
                        <div class="field"><label>Slug</label><input name="slug" value="{{ $tag->slug }}"></div>
                    </div>
                    <div class="button-row">
                        <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                        <button class="btn primary" type="submit">Save tag</button>
                    </div>
                </form>
            </div>
        </dialog>
    @endforeach

    <dialog class="dialog" id="attribute-dialog">
        <div class="dialog-header">
            <div>
                <h2 class="panel-title">Add attribute</h2>
                <p class="subtle">Create a reusable attribute and comma-separated values.</p>
            </div>
            <button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button>
        </div>
        <div class="dialog-body">
            <form class="mini-form" method="POST" action="{{ route('admin.catalog.attributes.store') }}">
                @csrf
                <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                <div class="form-grid">
                    <div class="field"><label>Name</label><input name="name" placeholder="Color" required></div>
                    <div class="field"><label>Slug</label><input name="slug" placeholder="auto-generated"></div>
                    <div class="field full"><label>Possible Values</label><input name="values" placeholder="Red, Blue, Green, Black" required></div>
                </div>
                <div class="button-row">
                    <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                    <button class="btn primary" type="submit">Save attribute</button>
                </div>
            </form>
        </div>
    </dialog>

    @foreach ($attributes as $attribute)
        <dialog class="dialog" id="attribute-edit-{{ $attribute->id }}">
            <div class="dialog-header">
                <div>
                    <h2 class="panel-title">Edit attribute</h2>
                    <p class="subtle">Update this reusable attribute and its possible values.</p>
                </div>
                <button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button>
            </div>
            <div class="dialog-body">
                <form class="mini-form" method="POST" action="{{ route('admin.catalog.attributes.update', $attribute) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                    <div class="form-grid">
                        <div class="field"><label>Name</label><input name="name" value="{{ $attribute->name }}" required></div>
                        <div class="field"><label>Slug</label><input name="slug" value="{{ $attribute->slug }}"></div>
                        <div class="field full"><label>Possible Values</label><input name="values" value="{{ $attribute->values->pluck('value')->join(', ') }}" required></div>
                    </div>
                    <div class="button-row">
                        <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                        <button class="btn primary" type="submit">Save attribute</button>
                    </div>
                </form>
            </div>
        </dialog>
    @endforeach

    <dialog class="dialog" id="tax-dialog">
        <div class="dialog-header">
            <div>
                <h2 class="panel-title">Add tax</h2>
                <p class="subtle">Create a reusable tax rate.</p>
            </div>
            <button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button>
        </div>
        <div class="dialog-body">
            <form class="mini-form" method="POST" action="{{ route('admin.catalog.taxes.store') }}">
                @csrf
                <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                <input type="hidden" name="is_active" value="0">
                <div class="form-grid">
                    <div class="field"><label>Name</label><input name="name" placeholder="VAT" required></div>
                    <div class="field"><label>Slug</label><input name="slug" placeholder="auto-generated"></div>
                    <div class="field"><label>Rate (%)</label><input name="rate" type="number" step="0.01" min="0" max="100" required></div>
                    <label class="inline-check" style="align-self: end;"><input type="checkbox" name="is_active" value="1" checked> Active</label>
                    <div class="field full"><label>Description</label><textarea name="description" placeholder="Applies to taxable retail products."></textarea></div>
                </div>
                <div class="button-row">
                    <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                    <button class="btn primary" type="submit">Save tax</button>
                </div>
            </form>
        </div>
    </dialog>

    @foreach ($taxes as $tax)
        <dialog class="dialog" id="tax-edit-{{ $tax->id }}">
            <div class="dialog-header">
                <div>
                    <h2 class="panel-title">Edit tax</h2>
                    <p class="subtle">Update this reusable tax rate.</p>
                </div>
                <button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button>
            </div>
            <div class="dialog-body">
                <form class="mini-form" method="POST" action="{{ route('admin.catalog.taxes.update', $tax) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                    <input type="hidden" name="is_active" value="0">
                    <div class="form-grid">
                        <div class="field"><label>Name</label><input name="name" value="{{ $tax->name }}" required></div>
                        <div class="field"><label>Slug</label><input name="slug" value="{{ $tax->slug }}"></div>
                        <div class="field"><label>Rate (%)</label><input name="rate" type="number" step="0.01" min="0" max="100" value="{{ $tax->rate }}" required></div>
                        <label class="inline-check" style="align-self: end;"><input type="checkbox" name="is_active" value="1" @checked($tax->is_active)> Active</label>
                        <div class="field full"><label>Description</label><textarea name="description">{{ $tax->description }}</textarea></div>
                    </div>
                    <div class="button-row">
                        <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                        <button class="btn primary" type="submit">Save tax</button>
                    </div>
                </form>
            </div>
        </dialog>
    @endforeach

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            function applyCatalogFilter(scope) {
                const list = document.querySelector(`[data-catalog-list="${scope}"]`);
                const search = document.querySelector(`[data-catalog-search="${scope}"]`);
                const category = document.querySelector(`[data-catalog-category="${scope}"]`);
                const status = document.querySelector(`[data-catalog-status="${scope}"]`);
                const empty = document.querySelector(`[data-catalog-empty="${scope}"]`);

                if (!list || !search) return;

                const query = search.value.trim().toLowerCase();
                const categoryValue = category?.value || '';
                const statusValue = status?.value || '';
                let visible = 0;

                list.querySelectorAll('[data-catalog-card]').forEach((card) => {
                    const matchesQuery = !query || card.dataset.search.includes(query);
                    const matchesCategory = !categoryValue || card.dataset.category === categoryValue;
                    const matchesStatus = !statusValue || card.dataset.status === statusValue;
                    const isVisible = matchesQuery && matchesCategory && matchesStatus;

                    card.hidden = !isVisible;
                    visible += isVisible ? 1 : 0;
                });

                if (empty) empty.hidden = visible > 0;
            }

            document.querySelectorAll('[data-catalog-search], [data-catalog-category], [data-catalog-status]').forEach((control) => {
                const scope = control.dataset.catalogSearch || control.dataset.catalogCategory || control.dataset.catalogStatus;
                control.addEventListener('input', () => applyCatalogFilter(scope));
                control.addEventListener('change', () => applyCatalogFilter(scope));
            });

            document.querySelectorAll('[data-filter-toggle]').forEach((button) => {
                button.addEventListener('click', () => {
                    const row = document.querySelector(`[data-filter-row="${button.dataset.filterToggle}"]`);
                    row?.classList.toggle('visible');
                });
            });

            document.querySelectorAll('[data-category-type-select]').forEach((select) => {
                const parent = select.closest('form')?.querySelector('[data-category-parent-select]');

                function filterParents() {
                    if (!parent) return;

                    parent.querySelectorAll('option[data-category-type]').forEach((option) => {
                        option.hidden = option.dataset.categoryType !== select.value;
                    });
                    parent.value = '';
                }

                select.addEventListener('change', filterParents);
                filterParents();
            });

            const moneyValue = (value) => {
                const parsed = parseFloat((value || '').toString().replace(/,/g, ''));

                return Number.isFinite(parsed) ? parsed : null;
            };

            const formatCatalogMoney = (value) => {
                return '{{ $tenant->currency_code }} ' + value.toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
            };

            const syncProfitSummary = (form) => {
                const sellingInput = form?.querySelector('input[name="base_price"]');
                const costInput = form?.querySelector('input[name="base_cost_price"]');
                const summary = form?.querySelector('[data-profit-summary]');
                const profitValue = form?.querySelector('[data-profit-value]');
                const marginValue = form?.querySelector('[data-margin-value]');
                const selling = moneyValue(sellingInput?.value);
                const cost = moneyValue(costInput?.value);

                if (!summary || !profitValue || !marginValue) return;

                if (selling === null || cost === null || selling <= 0) {
                    summary.hidden = true;
                    return;
                }

                const profit = selling - cost;
                const margin = (profit / selling) * 100;

                profitValue.textContent = formatCatalogMoney(profit);
                marginValue.textContent = `${margin.toFixed(2)}%`;
                summary.hidden = false;
            };

            document.querySelectorAll('form').forEach((form) => {
                const sellingInput = form.querySelector('input[name="base_price"]');
                const costInput = form.querySelector('input[name="base_cost_price"]');

                if (!sellingInput || !costInput) return;

                sellingInput.addEventListener('input', () => syncProfitSummary(form));
                costInput.addEventListener('input', () => syncProfitSummary(form));
                sellingInput.addEventListener('blur', () => syncProfitSummary(form));
                costInput.addEventListener('blur', () => syncProfitSummary(form));
                syncProfitSummary(form);
            });

            document.querySelectorAll('form').forEach((form) => {
                const taxBehavior = form.querySelector('[data-tax-behavior-select]');
                const taxList = form.querySelector('[data-tax-list-field]');

                if (!taxBehavior || !taxList) return;

                const syncTaxes = () => {
                    const taxable = taxBehavior.value === 'taxable';
                    taxList.hidden = !taxable;

                    taxList.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
                        checkbox.disabled = !taxable || checkbox.dataset.inactive === '1';
                        if (!taxable) checkbox.checked = false;
                    });
                };

                taxBehavior.addEventListener('change', syncTaxes);
                syncTaxes();
            });

            const syncProductImageList = (input) => {
                const uploader = input.closest('[data-product-image-uploader]');
                const list = uploader?.querySelector('[data-product-image-list]');
                const files = Array.from(input.files || []);

                if (!list) return;

                list.innerHTML = '';
                list.hidden = files.length === 0;
                files.forEach((file) => {
                    const item = document.createElement('span');
                    item.textContent = file.name;
                    list.appendChild(item);
                });
            };

            document.querySelectorAll('[data-product-image-uploader]').forEach((uploader) => {
                const input = uploader.querySelector('[data-product-image-input]');
                const dropZone = uploader.querySelector('[data-product-image-drop-zone]');

                if (!input || !dropZone) return;

                input.addEventListener('change', () => syncProductImageList(input));

                ['dragenter', 'dragover'].forEach((eventName) => {
                    dropZone.addEventListener(eventName, (event) => {
                        event.preventDefault();
                        dropZone.classList.add('dragging');
                    });
                });

                ['dragleave', 'drop'].forEach((eventName) => {
                    dropZone.addEventListener(eventName, (event) => {
                        event.preventDefault();
                        dropZone.classList.remove('dragging');
                    });
                });

                dropZone.addEventListener('drop', (event) => {
                    const images = Array.from(event.dataTransfer?.files || [])
                        .filter((file) => file.type.startsWith('image/'));

                    if (!images.length) return;

                    const transfer = new DataTransfer();
                    Array.from(input.files || []).forEach((file) => transfer.items.add(file));
                    images.forEach((file) => transfer.items.add(file));
                    input.files = transfer.files;
                    syncProductImageList(input);
                });
            });

            document.querySelectorAll('form').forEach((form) => {
                const toggles = Array.from(form.querySelectorAll('[data-variant-toggle]'));
                const editor = form?.querySelector('[data-variant-editor]');
                const simpleFields = form ? Array.from(form.querySelectorAll('[data-simple-variant-field]')) : [];

                if (!toggles.length) return;

                function syncVariantMode() {
                    const hasVariants = form.querySelector('[data-variant-toggle]:checked')?.value === '1';

                    if (editor) editor.hidden = !hasVariants;
                    simpleFields.forEach((field) => {
                        field.hidden = hasVariants;
                    });
                }

                toggles.forEach((toggle) => toggle.addEventListener('change', syncVariantMode));
                syncVariantMode();
            });

            document.querySelectorAll('[data-add-variant]').forEach((button) => {
                button.addEventListener('click', () => {
                    const form = button.closest('form');
                    const list = form?.querySelector('[data-variant-list]');
                    const firstRow = list?.querySelector('[data-variant-row]');

                    if (!list || !firstRow) return;

                    const index = list.querySelectorAll('[data-variant-row]').length;
                    const row = firstRow.cloneNode(true);

                    row.querySelectorAll('[name]').forEach((field) => {
                        field.name = field.name.replace(/variants\[\d+\]/, `variants[${index}]`);

                        if (field.type === 'hidden') {
                            field.value = '';
                        } else if (field.tagName === 'SELECT') {
                            field.selectedIndex = 0;
                        } else {
                            field.value = '';
                        }
                    });

                    list.appendChild(row);
                });
            });

            document.querySelectorAll('[data-add-option]').forEach((button) => {
                button.addEventListener('click', () => {
                    const list = button.closest('form')?.querySelector('[data-option-list]');
                    const firstRow = list?.querySelector('[data-option-row]');

                    if (!list || !firstRow) return;

                    const index = list.querySelectorAll('[data-option-row]').length;
                    const row = firstRow.cloneNode(true);

                    row.querySelectorAll('[name]').forEach((field) => {
                        field.name = field.name.replace(/options\[\d+\]/, `options[${index}]`);
                        field.value = '';
                    });

                    list.appendChild(row);
                });
            });

            const showLocalPanelForField = (field) => {
                const panel = field.closest('[data-local-tab-panel]');
                const dialog = field.closest('dialog');

                if (!panel || !dialog) return;

                dialog.querySelectorAll('[data-local-tab-panel]').forEach((item) => {
                    item.hidden = item !== panel;
                });

                dialog.querySelectorAll('[data-local-tab-target]').forEach((item) => {
                    item.classList.toggle('active', item.dataset.localTabTarget === panel.id);
                });
            };

            document.addEventListener('invalid', (event) => {
                const field = event.target.closest('input, select, textarea');

                if (!field) return;

                showLocalPanelForField(field);
            }, true);

            const splitInlineValues = (value) => {
                return (value || '')
                    .split(',')
                    .map((item) => item.trim())
                    .filter(Boolean)
                    .filter((item, index, items) => items.findIndex((value) => value.toLowerCase() === item.toLowerCase()) === index);
            };

            const hiddenInputForPendingList = (list) => {
                const form = list?.closest('form');

                if (!list || !form) return null;

                if (list.hasAttribute('data-inline-tag-list')) {
                    return form.querySelector('[data-inline-tags-value]');
                }

                if (list.hasAttribute('data-inline-attribute-value-list')) {
                    const attributeId = list.getAttribute('data-inline-attribute-value-list');

                    return form.querySelector(`[data-inline-attribute-values="${attributeId}"]`);
                }

                return list.closest('[data-new-attribute-row]')?.querySelector('input[name$="[values]"]') || null;
            };

            const syncPendingHiddenInput = (list) => {
                const hiddenInput = hiddenInputForPendingList(list);

                if (!hiddenInput) return;

                hiddenInput.value = Array.from(list.querySelectorAll('[data-inline-pending-value]:checked'))
                    .map((field) => field.dataset.inlinePendingValue)
                    .filter(Boolean)
                    .join(', ');
            };

            const appendInlineCheck = (list, value) => {
                if (!list || !value) return;

                const exists = Array.from(list.querySelectorAll('.inline-check'))
                    .some((item) => item.textContent.trim().toLowerCase() === value.toLowerCase());

                if (exists) return;

                list.querySelectorAll('.subtle').forEach((item) => item.remove());

                const label = document.createElement('label');
                label.className = 'inline-check';

                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.checked = true;
                checkbox.dataset.inlinePendingValue = value;

                label.appendChild(checkbox);
                label.append(` ${value}`);
                list.appendChild(label);
            };

            const addInlineTags = (button) => {
                const form = button.closest('form');
                const input = form?.querySelector('[data-inline-tag-input]');
                const list = form?.querySelector('[data-inline-tag-list]');
                const values = splitInlineValues(input?.value);

                if (!values.length) return;

                values.forEach((value) => appendInlineCheck(list, value));
                syncPendingHiddenInput(list);
                input.value = '';
                input.focus();
            };

            const addInlineAttributeValues = (button) => {
                const attributeId = button.dataset.addInlineAttributeValue;
                const form = button.closest('form');
                const input = form?.querySelector(`[data-inline-attribute-value-input="${attributeId}"]`);
                const list = form?.querySelector(`[data-inline-attribute-value-list="${attributeId}"]`);
                const values = splitInlineValues(input?.value);

                if (!values.length) return;

                values.forEach((value) => appendInlineCheck(list, value));
                syncPendingHiddenInput(list);
                input.value = '';
                input.focus();
            };

            const addInlineAttribute = (button) => {
                const wrapper = button.closest('[data-new-attribute-list]');
                const nameInput = wrapper?.querySelector('[data-new-attribute-name]');
                const valuesInput = wrapper?.querySelector('[data-new-attribute-values]');
                const list = wrapper?.closest('[data-inline-attributes]')?.querySelector('[data-added-inline-attribute-list]');
                const name = nameInput?.value.trim() || '';
                const values = splitInlineValues(valuesInput?.value);

                if (!wrapper || !list || !name || !values.length) return;

                const existing = Array.from(list.querySelectorAll('[data-new-attribute-row] strong'))
                    .some((item) => item.textContent.trim().toLowerCase() === name.toLowerCase());

                if (existing) return;

                const index = `${Date.now()}${list.querySelectorAll('[data-new-attribute-row]').length}`;
                const row = document.createElement('div');
                row.className = 'variant-row-editor catalog-attribute-panel';
                row.dataset.attributePanel = '';
                row.dataset.newAttributeRow = '';

                const hiddenName = document.createElement('input');
                hiddenName.type = 'hidden';
                hiddenName.name = `new_attributes[${index}][name]`;
                hiddenName.value = name;

                const hiddenValues = document.createElement('input');
                hiddenValues.type = 'hidden';
                hiddenValues.name = `new_attributes[${index}][values]`;
                hiddenValues.value = values.join(', ');

                const header = document.createElement('div');
                header.className = 'catalog-attribute-toggle-row';

                const toggle = document.createElement('button');
                toggle.className = 'catalog-attribute-toggle';
                toggle.type = 'button';
                toggle.dataset.attributeToggle = '';
                toggle.setAttribute('aria-expanded', 'true');

                const chevron = document.createElement('span');
                chevron.className = 'catalog-attribute-chevron';
                chevron.textContent = '›';

                const title = document.createElement('strong');
                title.textContent = name;

                toggle.append(chevron, title);

                const remove = document.createElement('button');
                remove.className = 'btn danger';
                remove.type = 'button';
                remove.dataset.removeInlineAttribute = '';
                remove.textContent = 'Remove';
                header.append(toggle, remove);

                const body = document.createElement('div');
                body.className = 'catalog-attribute-body';
                body.dataset.attributeBody = '';
                const valueList = document.createElement('div');
                valueList.className = 'check-grid';
                values.forEach((value) => appendInlineCheck(valueList, value));
                hiddenValues.value = values.join(', ');
                body.appendChild(valueList);

                row.append(hiddenName, hiddenValues, header, body);
                list.appendChild(row);
                nameInput.value = '';
                valuesInput.value = '';
                nameInput.focus();
            };

            document.querySelectorAll('[data-inline-add-input]').forEach((input) => {
                input.addEventListener('keydown', (event) => {
                    if (event.key !== 'Enter') return;

                    event.preventDefault();
                    const field = event.target;
                    const form = field.closest('form');

                    if (field.matches('[data-inline-tag-input]')) {
                        addInlineTags(form?.querySelector('[data-add-inline-tag]'));
                    } else if (field.matches('[data-inline-attribute-value-input]')) {
                        const attributeId = field.getAttribute('data-inline-attribute-value-input');
                        addInlineAttributeValues(form?.querySelector(`[data-add-inline-attribute-value="${attributeId}"]`));
                    } else if (field.matches('[data-new-attribute-name], [data-new-attribute-values]')) {
                        addInlineAttribute(field.closest('[data-new-attribute-list]')?.querySelector('[data-add-inline-attribute]'));
                    }
                });
            });

            document.querySelectorAll('[data-add-inline-tag]').forEach((button) => {
                button.addEventListener('click', () => addInlineTags(button));
            });

            document.querySelectorAll('[data-add-inline-attribute-value]').forEach((button) => {
                button.addEventListener('click', () => addInlineAttributeValues(button));
            });

            document.querySelectorAll('[data-add-inline-attribute]').forEach((button) => {
                button.addEventListener('click', () => addInlineAttribute(button));
            });

            document.addEventListener('click', (event) => {
                const toggle = event.target.closest('[data-attribute-toggle]');

                if (!toggle) return;

                const panel = toggle.closest('[data-attribute-panel]');
                const body = panel?.querySelector('[data-attribute-body]');
                const expanded = toggle.getAttribute('aria-expanded') === 'true';

                toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');

                if (body) {
                    body.hidden = expanded;
                }
            });

            document.addEventListener('change', (event) => {
                if (!event.target.matches('[data-inline-pending-value]')) return;

                syncPendingHiddenInput(event.target.closest('.check-grid'));
            });

            document.querySelectorAll('[data-generate-variants]').forEach((button) => {
                button.addEventListener('click', () => {
                    const form = button.closest('form');
                    const optionRows = Array.from(form?.querySelectorAll('[data-option-row]') || []);
                    const variantList = form?.querySelector('[data-variant-list]');
                    const firstVariant = variantList?.querySelector('[data-variant-row]');

                    if (!form || !variantList || !firstVariant) return;

                    const options = optionRows
                        .map((row) => {
                            const name = row.querySelector('input[name$="[name]"]')?.value.trim();
                            const values = (row.querySelector('input[name$="[values]"]')?.value || '')
                                .split(',')
                                .map((value) => value.trim())
                                .filter(Boolean);

                            return { name, values: Array.from(new Set(values)) };
                        })
                        .filter((option) => option.name && option.values.length);

                    if (!options.length) return;

                    const combinations = options.reduce((sets, option) => {
                        return sets.flatMap((set) => option.values.map((value) => [...set, { name: option.name, value }]));
                    }, [[]]);

                    const existingSignatures = new Set(Array.from(variantList.querySelectorAll('[data-option-signature]')).map((field) => field.value).filter(Boolean));

                    combinations.forEach((combination) => {
                        const signature = combination.map((part) => `${part.name}:${part.value}`).join('|');

                        if (existingSignatures.has(signature)) return;

                        const index = variantList.querySelectorAll('[data-variant-row]').length;
                        const row = firstVariant.cloneNode(true);
                        const variantName = combination.map((part) => part.value).join(' / ');

                        row.querySelectorAll('[name]').forEach((field) => {
                            field.name = field.name.replace(/variants\[\d+\]/, `variants[${index}]`);

                            if (field.type === 'hidden') {
                                field.value = '';
                            } else if (field.tagName === 'SELECT') {
                                field.selectedIndex = 0;
                            } else {
                                field.value = '';
                            }
                        });

                        const signatureInput = row.querySelector('[data-option-signature]');
                        const nameInput = row.querySelector('input[name$="[variant_name]"]');
                        const sellingPriceInput = row.querySelector('input[name$="[selling_price]"]');
                        const costPriceInput = row.querySelector('input[name$="[cost_price]"]');
                        const compareAtPriceInput = row.querySelector('input[name$="[compare_at_price]"]');

                        if (signatureInput) signatureInput.value = signature;
                        if (nameInput) nameInput.value = variantName;
                        if (sellingPriceInput) sellingPriceInput.value = form.querySelector('input[name="base_price"]')?.value || '';
                        if (costPriceInput) costPriceInput.value = form.querySelector('input[name="base_cost_price"]')?.value || '';
                        if (compareAtPriceInput) compareAtPriceInput.value = form.querySelector('input[name="compare_at_price"]')?.value || '';

                        variantList.appendChild(row);
                        existingSignatures.add(signature);
                    });
                });
            });

            document.addEventListener('click', (event) => {
                const removeButton = event.target.closest('[data-remove-variant]');
                if (removeButton) {
                    const row = removeButton.closest('[data-variant-row]');
                    const list = removeButton.closest('[data-variant-list]');

                    if (row && list?.querySelectorAll('[data-variant-row]').length > 1) {
                        row.remove();
                    } else if (row) {
                        row.querySelectorAll('input').forEach((field) => field.value = field.type === 'number' ? '0' : '');
                    }
                }

                const removeOptionButton = event.target.closest('[data-remove-option]');
                if (removeOptionButton) {
                    const row = removeOptionButton.closest('[data-option-row]');
                    const list = removeOptionButton.closest('[data-option-list]');

                    if (row && list?.querySelectorAll('[data-option-row]').length > 1) {
                        row.remove();
                    } else if (row) {
                        row.querySelectorAll('input').forEach((field) => field.value = '');
                    }
                }

                const removeInlineAttributeButton = event.target.closest('[data-remove-inline-attribute]');
                if (removeInlineAttributeButton) {
                    const row = removeInlineAttributeButton.closest('[data-new-attribute-row]');

                    if (row) {
                        row.remove();
                    }
                }

                const editButton = event.target.closest('[data-drawer-edit]');
                if (editButton) {
                    const drawer = editButton.closest('dialog');
                    const editDialog = document.getElementById(editButton.dataset.drawerEdit);

                    drawer?.close();
                    window.setTimeout(() => editDialog?.showModal(), 80);
                }
            });
        });
    </script>
</x-layouts.admin>
