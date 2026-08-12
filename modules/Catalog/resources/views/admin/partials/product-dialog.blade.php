@php
    $isEdit = (bool) $product;
    $isService = $productType === \Modules\Catalog\Enums\ProductType::Service->value;
    $action = $isEdit ? route('admin.catalog.products.update', $product) : route('admin.catalog.products.store');
    $minorToMoney = fn (?int $minor): string => number_format(($minor ?? 0) / 100, 2);
    $availableCategories = $isService ? $serviceCategories : $productCategories;
    $categoryOptions = collect();
    $appendCategories = function ($parentId = null, int $depth = 0) use (&$appendCategories, $availableCategories, $categoryOptions): void {
        $availableCategories
            ->where('parent_id', $parentId)
            ->sortBy('name')
            ->each(function ($category) use (&$appendCategories, $categoryOptions, $depth): void {
                $categoryOptions->push([
                    'category' => $category,
                    'label' => str_repeat('— ', $depth).$category->name,
                ]);
                $appendCategories($category->id, $depth + 1);
            });
    };
    $appendCategories();
    $defaultProductCategoryId = $isService
        ? null
        : $availableCategories->first(fn ($category) => strtolower($category->name) === 'uncategorized')?->id;
    $selectedCategoryId = old('category_id', $product?->category_id ?? $defaultProductCategoryId);
    $hasVariants = (bool) old('has_variants', $product?->has_variants ?? false);
    $selectedCollectionIds = collect(old('collection_ids', $product?->collections?->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id);
    $selectedBadgeIds = collect(old('badge_ids', $product?->badges?->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id);
    $selectedTagIds = collect(old('tag_ids', $product?->tags?->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id);
    $selectedTaxIds = collect(old('tax_ids', $product?->taxes?->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id);
    $selectedAttributeValueIds = collect(old('attribute_value_ids', $product?->attributeValues?->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id);
    $pendingNewTags = collect(explode(',', (string) old('new_tags')))
        ->map(fn ($value) => trim($value))
        ->filter()
        ->values();
    $pendingNewAttributeValues = collect(old('new_attribute_values', []))
        ->map(fn ($values) => collect(explode(',', (string) $values))->map(fn ($value) => trim($value))->filter()->values());
    $newAttributeRows = collect(old('new_attributes', []))
        ->filter(fn ($row) => trim((string) ($row['name'] ?? '')) !== '' && trim((string) ($row['values'] ?? '')) !== '')
        ->values()
        ->all();
    $optionRows = old('options');
    $variantRows = old('variants');
    $submittedCustomFields = old('custom_fields');
    $selectedCustomFields = collect(is_array($submittedCustomFields) ? $submittedCustomFields : ($product?->custom_fields ?? []))
        ->filter(fn (array $field): bool => (bool) ($field['is_assigned'] ?? true))
        ->keyBy(fn (array $field): string => strtolower(trim((string) ($field['key'] ?? ''))));
    $personalizationSettings = old('personalization', $product?->personalization_settings ?? [
        'enabled' => false,
        'fields' => [
            'customized_text' => true,
            'additional_info' => true,
            'photograph' => false,
        ],
    ]);
    $personalizationEnabled = filter_var($personalizationSettings['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $personalizationFields = (array) ($personalizationSettings['fields'] ?? []);

    if (! is_array($optionRows)) {
        $optionRows = $product?->options
            ->sortBy('sort_order')
            ->map(fn ($option): array => [
                'name' => $option->name,
                'values' => $option->values->sortBy('sort_order')->pluck('value')->implode(', '),
            ])
            ->values()
            ->all() ?? [];
    }

    if ($optionRows === [] && ! $isService) {
        $optionRows = [
            ['name' => '', 'values' => ''],
        ];
    }

    if (! is_array($variantRows)) {
        $variantRows = $product?->variants
            ->map(fn ($row): array => [
                'id' => $row->id,
                'option_signature' => $row->optionValues
                    ->sortBy(fn ($value) => $value->option?->sort_order ?? 0)
                    ->map(fn ($value): string => $value->option?->name.':'.$value->value)
                    ->filter()
                    ->implode('|'),
                'variant_name' => $row->variant_name,
                'sku' => $row->sku,
                'barcode' => $row->barcode,
                'selling_price' => $minorToMoney($row->selling_price_minor),
                'cost_price' => $minorToMoney($row->cost_price_minor),
                'compare_at_price' => ($row->compare_at_price_minor ?? $row->discount_price_minor) ? $minorToMoney($row->compare_at_price_minor ?? $row->discount_price_minor) : '',
                'status' => $row->status->value,
            ])
            ->values()
            ->all() ?? [];
    }

    if ($variantRows === [] && ! $isService) {
        $variantRows = [[
            'id' => null,
            'option_signature' => '',
            'variant_name' => '',
            'sku' => '',
            'barcode' => '',
            'selling_price' => $minorToMoney($product?->base_price_minor),
            'cost_price' => $minorToMoney($product?->base_cost_price_minor),
            'compare_at_price' => '',
            'status' => \Modules\Catalog\Enums\ProductStatus::Active->value,
        ]];
    }
@endphp

<dialog class="dialog" id="{{ $dialogId }}">
    <div class="dialog-header">
        <div>
            <h2 class="panel-title">{{ $title }}</h2>
            <p class="subtle">{{ $isService ? 'Service pricing and profitability details.' : 'Product identity, pricing, stock, barcode, and tax details.' }}</p>
        </div>
        <button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button>
    </div>
    <div class="dialog-body">
        <form class="mini-form" method="POST" action="{{ $action }}" enctype="multipart/form-data">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
            <input type="hidden" name="product_type" value="{{ $productType }}">

            <div class="dialog-local-tabs" role="tablist">
                <a href="#{{ $dialogId }}-basic" class="active" data-local-tab-target="{{ $dialogId }}-basic">Basic</a>
                <a href="#{{ $dialogId }}-pricing" data-local-tab-target="{{ $dialogId }}-pricing">Pricing</a>
                <a href="#{{ $dialogId }}-tags-attributes" data-local-tab-target="{{ $dialogId }}-tags-attributes">Tags, Badges & Attributes</a>
                @if (! $isService)
                    <a href="#{{ $dialogId }}-custom" data-local-tab-target="{{ $dialogId }}-custom">Custom</a>
                    <a href="#{{ $dialogId }}-personalization" data-local-tab-target="{{ $dialogId }}-personalization">Personalization</a>
                    <a href="#{{ $dialogId }}-variants" data-local-tab-target="{{ $dialogId }}-variants">Variants</a>
                @endif
            </div>

            <section data-local-tab-panel id="{{ $dialogId }}-basic">
                <div class="form-grid">
                    <div class="field">
                        <label>Name</label>
                        <input name="name" value="{{ old('name', $product?->name) }}" required>
                    </div>
                    <div class="field">
                        <label>Slug</label>
                        <input name="slug" value="{{ old('slug', $product?->slug) }}" placeholder="auto-generated">
                    </div>
                    <div class="field">
                        <label>Category</label>
                        <select name="category_id" data-product-category-select>
                            @if ($isService)
                                <option value="">Uncategorized</option>
                            @endif
                            <option value="__add_new__" data-add-category-option>+ Add new category</option>
                            @foreach ($categoryOptions as $option)
                                <option value="{{ $option['category']->id }}" @selected((string) $selectedCategoryId === (string) $option['category']->id)>{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>Brand</label>
                        <input name="brand" value="{{ old('brand', $product?->brand) }}">
                    </div>
                    <div class="field">
                        <label>Status</label>
                        <select name="status" required>
                            @foreach ($productStatuses as $status)
                                <option value="{{ $status->value }}" @selected(old('status', $product?->status->value ?? \Modules\Catalog\Enums\ProductStatus::Active->value) === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>Main Image</label>
                        <label class="catalog-main-image-control" data-main-image-control>
                            <input name="image" type="file" accept="image/*" data-main-image-input>
                            <span class="catalog-main-image-button">Upload</span>
                            <span class="catalog-main-image-name" data-main-image-name>No file selected</span>
                        </label>
                        @if ($product?->image_path && $imageUrl($product->image_path))
                            <span class="subtle">Current image</span>
                            <div class="product-thumb" style="width: 140px; height: 110px;">
                                <img src="{{ $imageUrl($product->image_path) }}" alt="{{ $product->name }} image preview">
                            </div>
                        @endif
                    </div>
                    <div class="field" @if (! $isService) data-simple-variant-field @endif>
                        <label>SKU</label>
                        <input name="sku" value="{{ old('sku', $variant?->sku) }}" placeholder="auto-generated">
                    </div>
                    <div class="field" @if (! $isService) data-simple-variant-field @endif>
                        <label>Barcode</label>
                        <input name="barcode" value="{{ old('barcode', $variant?->barcode) }}">
                    </div>
                    <div class="field full">
                        <label>Description <button type="button" class="catalog-ai-generate-btn" data-product-ai-generate data-product-ai-field="description">✨ Generate with AI</button></label>
                        <textarea name="description">{{ old('description', $product?->description) }}</textarea>
                    </div>
                    @if (! $isService)
                        <div class="field full">
                            <label>Specifications <span class="subtle">(optional)</span> <button type="button" class="catalog-ai-generate-btn" data-product-ai-generate data-product-ai-field="specifications">✨ Generate with AI</button></label>
                            <textarea name="specifications" rows="6" placeholder="e.g. Material: 100% cotton&#10;Fit: Regular&#10;Care: Machine wash cold">{{ old('specifications', $product?->specifications) }}</textarea>
                        </div>
                    @endif
                    <div class="field full">
                        <div class="catalog-image-uploader" data-product-image-uploader>
                            <div class="catalog-image-uploader-header">
                                <h3>Additional Product Images</h3>
                                <p>To upload a product image, please use the option below to select and upload the relevant file.</p>
                            </div>
                            <label class="catalog-drop-zone" data-product-image-drop-zone>
                                <input name="images[]" type="file" accept="image/*" multiple data-product-image-input data-max-files="5">
                                <span class="catalog-upload-icon">⇧</span>
                                <strong>Drop files here or click to upload.</strong>
                                <span>You can upload up to 5 images at a time.</span>
                                <span class="catalog-browse-button">Browse Images</span>
                            </label>
                            @if ($product?->images?->isNotEmpty())
                                <div class="catalog-current-images">
                                    @foreach ($product->images as $image)
                                        @if ($imageUrl($image->image_path))
                                            <img src="{{ $imageUrl($image->image_path) }}" alt="{{ $product->name }} gallery image">
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                            <div class="catalog-selected-images" data-product-image-list hidden></div>
                        </div>
                    </div>
                </div>
            </section>

            <section data-local-tab-panel id="{{ $dialogId }}-pricing" hidden>
                <div class="form-grid">
                    <div class="field">
                        <label>Selling price</label>
                        <input name="base_price" type="text" inputmode="decimal" data-money-input value="{{ old('base_price', $minorToMoney($product?->base_price_minor)) }}" required>
                    </div>
                    <div class="field">
                        <label>Estimated cost price</label>
                        <input name="base_cost_price" type="text" inputmode="decimal" data-money-input value="{{ old('base_cost_price', $minorToMoney($product?->base_cost_price_minor)) }}">
                    </div>
                    <div class="catalog-profit-summary" data-profit-summary hidden>
                        <span>Profit: <strong data-profit-value></strong></span>
                        <span>Margin: <strong data-margin-value></strong></span>
                    </div>
                    <div class="field">
                        <label>Compare at price</label>
                        <input name="compare_at_price" type="text" inputmode="decimal" data-money-input value="{{ old('compare_at_price', ($product?->compare_at_price_minor ?? $product?->discount_price_minor) ? $minorToMoney($product->compare_at_price_minor ?? $product->discount_price_minor) : '') }}">
                        <span class="subtle">This is original price with a strikethrough.</span>
                    </div>
                    <div class="field">
                        <label>Tax behavior</label>
                        <select name="tax_behavior" required data-tax-behavior-select>
                            @foreach ($taxBehaviors as $value => $label)
                                <option value="{{ $value }}" @selected(old('tax_behavior', $product?->tax_behavior->value ?? \Modules\Catalog\Enums\TaxBehavior::Taxable->value) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field full" data-tax-list-field>
                        <label>Apply taxes</label>
                        <div class="catalog-inline-box">
                            <div class="check-grid">
                                @forelse ($taxes as $tax)
                                    <label class="inline-check">
                                        <input type="checkbox" name="tax_ids[]" value="{{ $tax->id }}" data-inactive="{{ $tax->is_active ? '0' : '1' }}" @checked($selectedTaxIds->contains($tax->id)) @disabled(! $tax->is_active)>
                                        {{ $tax->name }} ({{ $tax->rate }}%)
                                        @if (! $tax->is_active)
                                            <span class="subtle">Inactive</span>
                                        @endif
                                    </label>
                                @empty
                                    <span class="subtle">No taxes created yet. Add taxes from the Taxes section.</span>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section data-local-tab-panel id="{{ $dialogId }}-tags-attributes" hidden>
                <div class="form-grid">
                    @if (! $isService)
                        <div class="field full">
                            <label>Collections</label>
                            <div class="catalog-inline-box">
                                <div class="variant-row-editor catalog-attribute-panel" data-attribute-panel data-checkbox-accordion>
                                    <button class="catalog-attribute-toggle" type="button" data-attribute-toggle aria-expanded="false">
                                        <span class="catalog-attribute-chevron">›</span>
                                        <strong>Product collections</strong>
                                        <span class="badge neutral" data-checkbox-accordion-count>{{ $selectedCollectionIds->count() }} {{ Str::plural('item', $selectedCollectionIds->count()) }}</span>
                                    </button>
                                    <div class="catalog-attribute-body" data-attribute-body hidden>
                                        <div class="check-grid">
                                            @forelse ($productCollections as $collection)
                                                <label class="inline-check">
                                                    <input type="checkbox" name="collection_ids[]" value="{{ $collection->id }}" @checked($selectedCollectionIds->contains($collection->id))>
                                                    {{ $collection->name }}
                                                </label>
                                            @empty
                                                <span class="subtle">No product collections yet.</span>
                                            @endforelse
                                        </div>
                                        <details class="catalog-inline-create">
                                            <summary class="catalog-inline-create-link">+ Create a new collection</summary>
                                            <div class="variant-row-editor catalog-inline-create-form">
                                                <div class="field">
                                                    <label>Collection name</label>
                                                    <input name="new_collection[name]" value="{{ old('new_collection.name') }}" placeholder="e.g. Summer picks">
                                                </div>
                                                <input type="hidden" name="new_collection[is_visible]" value="0">
                                                <label class="inline-check">
                                                    <input type="checkbox" name="new_collection[is_visible]" value="1" @checked(old('new_collection.is_visible', true))>
                                                    Visible on store
                                                </label>
                                                <span class="subtle">The new collection will be created and assigned when you save the product.</span>
                                            </div>
                                        </details>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="field full">
                            <label>Badges</label>
                            <div class="catalog-inline-box">
                                <div class="variant-row-editor catalog-attribute-panel" data-attribute-panel data-checkbox-accordion>
                                    <button class="catalog-attribute-toggle" type="button" data-attribute-toggle aria-expanded="false">
                                        <span class="catalog-attribute-chevron">›</span>
                                        <strong>Storefront badges</strong>
                                        <span class="badge neutral" data-checkbox-accordion-count>{{ $selectedBadgeIds->count() }} {{ Str::plural('item', $selectedBadgeIds->count()) }}</span>
                                    </button>
                                    <div class="catalog-attribute-body" data-attribute-body hidden>
                                        <div class="check-grid">
                                            @forelse ($productBadges as $badge)
                                                <label class="inline-check">
                                                    <input type="checkbox" name="badge_ids[]" value="{{ $badge->id }}" @checked($selectedBadgeIds->contains($badge->id))>
                                                    <span class="badge" style="background: {{ $badge->background_color }}; color: {{ $badge->text_color }};">{{ $badge->name }}</span>
                                                </label>
                                            @empty
                                                <span class="subtle">No badges yet.</span>
                                            @endforelse
                                        </div>
                                        <span class="subtle">You can assign several; the store displays the first two.</span>
                                        <details class="catalog-inline-create">
                                            <summary class="catalog-inline-create-link">+ Create a new badge</summary>
                                            <div class="variant-row-editor catalog-inline-create-form">
                                                <div class="field">
                                                    <label>Badge name</label>
                                                    <input name="new_badge[name]" value="{{ old('new_badge.name') }}" placeholder="e.g. New">
                                                </div>
                                                <div class="catalog-badge-colours">
                                                    <div class="field">
                                                        <label>Background colour</label>
                                                        <input name="new_badge[background_color]" type="color" value="{{ old('new_badge.background_color', '#111827') }}">
                                                    </div>
                                                    <div class="field">
                                                        <label>Text colour</label>
                                                        <input name="new_badge[text_color]" type="color" value="{{ old('new_badge.text_color', '#ffffff') }}">
                                                    </div>
                                                </div>
                                                <span class="subtle">The new badge will be created and assigned when you save the product.</span>
                                            </div>
                                        </details>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                    <div class="field full">
                        <label>Tags</label>
                        <div class="catalog-inline-box">
                            <div class="variant-row-editor catalog-attribute-panel" data-attribute-panel>
                                <button class="catalog-attribute-toggle" type="button" data-attribute-toggle aria-expanded="false">
                                    <span class="catalog-attribute-chevron">›</span>
                                    <strong>Available tags</strong>
                                    <span class="badge neutral">{{ $tags->count() + $pendingNewTags->count() }}</span>
                                </button>
                                <div class="catalog-attribute-body" data-attribute-body hidden>
                                    <div class="check-grid" data-inline-tag-list>
                                        @forelse ($tags as $tag)
                                            <label class="inline-check"><input type="checkbox" name="tag_ids[]" value="{{ $tag->id }}" @checked($selectedTagIds->contains($tag->id))> {{ $tag->name }}</label>
                                        @empty
                                            <span class="subtle">No tags yet.</span>
                                        @endforelse
                                        @foreach ($pendingNewTags as $tag)
                                            <label class="inline-check"><input type="checkbox" checked data-inline-pending-value="{{ $tag }}"> {{ $tag }}</label>
                                        @endforeach
                                    </div>
                                    <input type="hidden" name="new_tags" value="{{ old('new_tags') }}" data-inline-tags-value>
                                    <div class="field">
                                        <label>Add new tag</label>
                                        <div class="catalog-inline-add-row">
                                            <input type="text" value="" placeholder="e.g Summer" data-inline-tag-input data-inline-add-input>
                                            <button class="btn inline-add" type="button" data-add-inline-tag>Add</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="field full">
                        <label>Attributes</label>
                        <div class="catalog-inline-box" data-inline-attributes>
                            @forelse ($attributes as $attribute)
                                <div class="variant-row-editor catalog-attribute-panel" data-attribute-panel>
                                    <button class="catalog-attribute-toggle" type="button" data-attribute-toggle aria-expanded="false">
                                        <span class="catalog-attribute-chevron">›</span>
                                        <strong>{{ $attribute->name }}</strong>
                                    </button>
                                    <div class="catalog-attribute-body" data-attribute-body hidden>
                                        <div class="check-grid" data-inline-attribute-value-list="{{ $attribute->id }}">
                                            @forelse ($attribute->values as $value)
                                                <label class="inline-check"><input type="checkbox" name="attribute_value_ids[]" value="{{ $value->id }}" @checked($selectedAttributeValueIds->contains($value->id))> {{ $value->value }}</label>
                                            @empty
                                                <span class="subtle">No values configured.</span>
                                            @endforelse
                                            @foreach (($pendingNewAttributeValues->get($attribute->id) ?? collect()) as $value)
                                                <label class="inline-check"><input type="checkbox" checked data-inline-pending-value="{{ $value }}"> {{ $value }}</label>
                                            @endforeach
                                        </div>
                                        <input type="hidden" name="new_attribute_values[{{ $attribute->id }}]" value="{{ old('new_attribute_values.'.$attribute->id) }}" data-inline-attribute-values="{{ $attribute->id }}">
                                        <div class="field">
                                            <label>Add value under {{ $attribute->name }}</label>
                                            <div class="catalog-inline-add-row">
                                                <input type="text" value="" placeholder="e.g Red" data-inline-attribute-value-input="{{ $attribute->id }}" data-inline-add-input>
                                                <button class="btn inline-add" type="button" data-add-inline-attribute-value="{{ $attribute->id }}">Add</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <span class="subtle">No attributes yet.</span>
                            @endforelse

                            <div class="list" data-added-inline-attribute-list>
                                @foreach ($newAttributeRows as $index => $row)
                                    <div class="variant-row-editor catalog-attribute-panel" data-attribute-panel data-new-attribute-row>
                                        <input type="hidden" name="new_attributes[{{ $index }}][name]" value="{{ $row['name'] ?? '' }}">
                                        <input type="hidden" name="new_attributes[{{ $index }}][values]" value="{{ $row['values'] ?? '' }}">
                                        <div class="catalog-attribute-toggle-row">
                                            <button class="catalog-attribute-toggle" type="button" data-attribute-toggle aria-expanded="false">
                                                <span class="catalog-attribute-chevron">›</span>
                                                <strong>{{ $row['name'] ?? '' }}</strong>
                                            </button>
                                            <button class="btn danger" type="button" data-remove-inline-attribute>Remove</button>
                                        </div>
                                        <div class="catalog-attribute-body" data-attribute-body hidden>
                                            <div class="check-grid">
                                                @foreach (collect(explode(',', (string) ($row['values'] ?? '')))->map(fn ($value) => trim($value))->filter() as $value)
                                                    <label class="inline-check"><input type="checkbox" checked data-inline-pending-value="{{ $value }}"> {{ $value }}</label>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <details class="catalog-inline-create" data-new-attribute-list>
                                <summary class="catalog-inline-create-link">+ Create new attribute</summary>
                                <div class="variant-row-editor catalog-inline-create-form">
                                    <div class="variant-grid catalog-new-attribute-grid">
                                        <div class="field">
                                            <label>Attribute name</label>
                                            <input type="text" value="" placeholder="Material" data-new-attribute-name data-inline-add-input>
                                        </div>
                                        <div class="field" style="grid-column: span 2;">
                                            <label>Values</label>
                                            <div class="catalog-inline-add-row">
                                                <div class="catalog-value-tag-input" data-attribute-value-tag-input>
                                                    <input type="text" value="" placeholder="Type a value" data-new-attribute-values autocomplete="off">
                                                </div>
                                                <button class="btn inline-add" type="button" data-add-inline-attribute>Add</button>
                                            </div>
                                            <span class="subtle">Press Enter or type a comma after each value.</span>
                                        </div>
                                    </div>
                                </div>
                            </details>
                        </div>
                    </div>
                </div>
            </section>

            @if (! $isService)
                <section data-local-tab-panel id="{{ $dialogId }}-custom" hidden>
                    <div class="panel catalog-variant-editor" data-product-custom-assignments>
                        <div class="panel-header"><div><h3 class="panel-title">Custom product choices</h3><p class="subtle">Assign keys from the Custom library to this product, then choose whether customers can select a value on the online store.</p></div></div>
                        <div class="panel-body">
                            @forelse ($customDefinitions as $customIndex => $definition)
                                @php($selectedCustomField = $selectedCustomFields->get(strtolower($definition->name)))
                                <div class="variant-row-editor" data-product-custom-assignment>
                                    <input type="hidden" name="custom_fields[{{ $customIndex }}][key]" value="{{ $definition->name }}">
                                    <input type="hidden" name="custom_fields[{{ $customIndex }}][values]" value="{{ collect($definition->values)->implode(', ') }}">
                                    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap;">
                                        <div>
                                            <strong>{{ $definition->name }}</strong>
                                            <div style="display:flex; gap:6px; flex-wrap:wrap; margin-top:8px;">
                                                @foreach ($definition->values as $value)
                                                    <span class="catalog-value-tag"><span>{{ $value }}</span></span>
                                                @endforeach
                                            </div>
                                        </div>
                                        <div style="display:grid; gap:8px;">
                                            <input type="hidden" name="custom_fields[{{ $customIndex }}][is_assigned]" value="0">
                                            <label class="inline-check"><input type="checkbox" name="custom_fields[{{ $customIndex }}][is_assigned]" value="1" data-custom-assigned @checked((bool) $selectedCustomField)> Assign to product</label>
                                            <input type="hidden" name="custom_fields[{{ $customIndex }}][is_customer_selectable]" value="0">
                                            <label class="inline-check"><input type="checkbox" name="custom_fields[{{ $customIndex }}][is_customer_selectable]" value="1" data-custom-customer-selectable @checked((bool) ($selectedCustomField['is_customer_selectable'] ?? $definition->is_customer_selectable))> Customer can select</label>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="empty">No custom keys exist yet. Create one from the main Custom tab first.</div>
                            @endforelse
                        </div>
                    </div>
                </section>

                <section data-local-tab-panel id="{{ $dialogId }}-personalization" hidden>
                    <div class="panel catalog-variant-editor" data-product-personalization>
                        <div class="panel-header">
                            <div>
                                <h3 class="panel-title">Product personalization</h3>
                                <p class="subtle">Offer personalization for this product and choose the information customers must provide on the storefront.</p>
                            </div>
                        </div>
                        <div class="panel-body" style="display:grid; gap:16px;">
                            <input type="hidden" name="personalization[enabled]" value="0">
                            <label class="inline-check">
                                <input type="checkbox" name="personalization[enabled]" value="1" data-personalization-enabled @checked($personalizationEnabled)>
                                Enable personalization for this product
                            </label>

                            <div class="variant-row-editor" data-personalization-fields>
                                <div>
                                    <strong>Information to collect</strong>
                                    <p class="subtle" style="margin-top:4px;">Customized Text and Additional Info/Note are selected by default. Enable photograph upload only when the product requires an image.</p>
                                </div>
                                <input type="hidden" name="personalization[fields][customized_text]" value="0">
                                <label class="inline-check">
                                    <input type="checkbox" name="personalization[fields][customized_text]" value="1" @checked(filter_var($personalizationFields['customized_text'] ?? true, FILTER_VALIDATE_BOOLEAN))>
                                    <span><strong>Customized Text</strong><span class="subtle" style="display:block;">The text the customer wants customized on the item.</span></span>
                                </label>
                                <input type="hidden" name="personalization[fields][additional_info]" value="0">
                                <label class="inline-check">
                                    <input type="checkbox" name="personalization[fields][additional_info]" value="1" @checked(filter_var($personalizationFields['additional_info'] ?? true, FILTER_VALIDATE_BOOLEAN))>
                                    <span><strong>Additional Info/Note</strong><span class="subtle" style="display:block;">Text colour, font size, engraving position, or special instructions.</span></span>
                                </label>
                                <input type="hidden" name="personalization[fields][photograph]" value="0">
                                <label class="inline-check">
                                    <input type="checkbox" name="personalization[fields][photograph]" value="1" @checked(filter_var($personalizationFields['photograph'] ?? false, FILTER_VALIDATE_BOOLEAN))>
                                    <span><strong>Upload photograph</strong><span class="subtle" style="display:block;">Require the customer to upload an image for this item.</span></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </section>

                <section data-local-tab-panel id="{{ $dialogId }}-variants" hidden>
                    <div class="field">
                        <label>This item has sellable variants</label>
                        <div class="catalog-radio-row">
                            <label class="inline-check"><input type="radio" name="has_variants" value="0" data-variant-toggle @checked(! $hasVariants)> No</label>
                            <label class="inline-check"><input type="radio" name="has_variants" value="1" data-variant-toggle @checked($hasVariants)> Yes</label>
                        </div>
                    </div>
                    <div class="panel catalog-variant-editor" data-variant-editor {{ $hasVariants ? '' : 'hidden' }}>
                    <div class="panel-header">
                        <div>
                            <h3 class="panel-title">Options & sellable variants</h3>
                            <p class="subtle">Define option groups, generate combinations, then tune SKU, price, stock, and status.</p>
                        </div>
                        <button class="btn secondary" type="button" data-add-option>Add</button>
                    </div>
                    <div class="panel-body">
                        <div class="list" data-option-list>
                            @foreach ($optionRows as $optionIndex => $optionRow)
                                <div class="variant-row-editor" data-option-row>
                                    <div class="variant-grid">
                                        <div class="field">
                                            <label>Option name</label>
                                            <input name="options[{{ $optionIndex }}][name]" value="{{ $optionRow['name'] ?? '' }}" placeholder="Size">
                                        </div>
                                        <div class="field" style="grid-column: span 2;">
                                            <label>Values</label>
                                            <input name="options[{{ $optionIndex }}][values]" value="{{ $optionRow['values'] ?? '' }}" placeholder="Small, Medium, Large">
                                        </div>
                                        <div class="field" style="align-content: end;">
                                            <button class="btn danger" type="button" data-remove-option>Remove</button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="button-row" style="justify-content: flex-start;">
                            <button class="btn accent" type="button" data-generate-variants>Auto Generate Variant</button>
                            <button class="btn secondary" type="button" data-add-variant>Add Manually</button>
                        </div>
                    </div>

                    <div class="panel-body" data-variant-list style="border-top: 1px solid var(--line);">
                        @foreach ($variantRows as $index => $row)
                            <div class="variant-row-editor" data-variant-row>
                                <input type="hidden" name="variants[{{ $index }}][id]" value="{{ $row['id'] ?? '' }}">
                                <input type="hidden" name="variants[{{ $index }}][option_signature]" value="{{ $row['option_signature'] ?? '' }}" data-option-signature>
                                <div class="variant-grid">
                                    <div class="field">
                                        <label>Variant name</label>
                                        <input name="variants[{{ $index }}][variant_name]" value="{{ $row['variant_name'] ?? '' }}" placeholder="Black / Size 42">
                                    </div>
                                    <div class="field">
                                        <label>Selling price</label>
                                        <input name="variants[{{ $index }}][selling_price]" type="text" inputmode="decimal" data-money-input value="{{ $row['selling_price'] ?? '' }}">
                                    </div>
                                    <div class="field">
                                        <label>Cost price</label>
                                        <input name="variants[{{ $index }}][cost_price]" type="text" inputmode="decimal" data-money-input value="{{ $row['cost_price'] ?? '' }}">
                                    </div>
                                    <div class="field">
                                        <label>Compare at price</label>
                                        <input name="variants[{{ $index }}][compare_at_price]" type="text" inputmode="decimal" data-money-input value="{{ $row['compare_at_price'] ?? '' }}">
                                    </div>
                                </div>
                                <div class="variant-grid">
                                    <div class="field">
                                        <label>SKU</label>
                                        <input name="variants[{{ $index }}][sku]" value="{{ $row['sku'] ?? '' }}" placeholder="auto-generated">
                                    </div>
                                    <div class="field">
                                        <label>Barcode</label>
                                        <input name="variants[{{ $index }}][barcode]" value="{{ $row['barcode'] ?? '' }}">
                                    </div>
                                    <div class="field">
                                        <label>Status</label>
                                        <select name="variants[{{ $index }}][status]">
                                            @foreach ($productStatuses as $status)
                                                <option value="{{ $status->value }}" @selected(($row['status'] ?? \Modules\Catalog\Enums\ProductStatus::Active->value) === $status->value)>{{ $status->label() }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field" style="align-content: end;">
                                        <button class="btn danger" type="button" data-remove-variant>Remove</button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    </div>
                </section>
            @else
                <input type="hidden" name="has_variants" value="0">
            @endif

            <div class="button-row">
                <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                <button class="btn primary" type="submit">{{ $isEdit ? 'Save changes' : ($isService ? 'Add service' : 'Add product') }}</button>
            </div>
        </form>
    </div>
</dialog>

<dialog class="dialog" id="{{ $dialogId }}-category-dialog" data-product-category-dialog>
    <div class="dialog-header">
        <div>
            <h2 class="panel-title">Add new category</h2>
            <p class="subtle">Create a {{ $isService ? 'service' : 'product' }} category and select it for this {{ $isService ? 'service' : 'product' }}.</p>
        </div>
        <button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button>
    </div>
    <div class="dialog-body">
        <form
            class="mini-form"
            method="POST"
            action="{{ route('admin.catalog.categories.store') }}"
            data-product-category-form
            data-product-dialog-id="{{ $dialogId }}"
        >
            @csrf
            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
            <input type="hidden" name="category_type" value="{{ $isService ? \Modules\Catalog\Enums\CategoryType::Service->value : \Modules\Catalog\Enums\CategoryType::Product->value }}">
            <div class="field">
                <label for="{{ $dialogId }}-category-name">Category name</label>
                <input id="{{ $dialogId }}-category-name" name="name" maxlength="140" required autofocus>
            </div>
            <p class="catalog-category-dialog-feedback" data-category-dialog-feedback aria-live="polite"></p>
            <div class="button-row">
                <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                <button class="btn primary" type="submit">Add category</button>
            </div>
        </form>
    </div>
</dialog>
