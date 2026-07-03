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
    $hasVariants = (bool) old('has_variants', $product?->has_variants ?? false);
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
                <a href="#{{ $dialogId }}-tags-attributes" data-local-tab-target="{{ $dialogId }}-tags-attributes">Tags & Attributes</a>
                @if (! $isService)
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
                        <select name="category_id">
                            <option value="">Uncategorized</option>
                            @foreach ($categoryOptions as $option)
                                <option value="{{ $option['category']->id }}" @selected((string) old('category_id', $product?->category_id) === (string) $option['category']->id)>{{ $option['label'] }}</option>
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
                        <input name="image" type="file" accept="image/*">
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
                        <label>Description</label>
                        <textarea name="description">{{ old('description', $product?->description) }}</textarea>
                    </div>
                    <div class="field full">
                        <div class="catalog-image-uploader" data-product-image-uploader>
                            <div class="catalog-image-uploader-header">
                                <h3>Additional Product Images</h3>
                                <p>To upload a product image, please use the option below to select and upload the relevant file.</p>
                            </div>
                            <label class="catalog-drop-zone" data-product-image-drop-zone>
                                <input name="images[]" type="file" accept="image/*" multiple data-product-image-input>
                                <span class="catalog-upload-icon">⇧</span>
                                <strong>Drop files here or click to upload.</strong>
                                <span>You can drag images here, or browse files via the button below.</span>
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
                    <div class="field full">
                        <label>Tags</label>
                        <div class="catalog-inline-box">
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

                            <div class="catalog-inline-create" data-new-attribute-list>
                                <div class="catalog-inline-heading">
                                    <strong>Create new attribute</strong>
                                </div>
                                <div class="variant-row-editor">
                                    <div class="variant-grid">
                                        <div class="field">
                                            <label>Attribute name</label>
                                            <input type="text" value="" placeholder="Material" data-new-attribute-name data-inline-add-input>
                                        </div>
                                        <div class="field" style="grid-column: span 2;">
                                            <label>Values</label>
                                            <div class="catalog-inline-add-row">
                                                <input type="text" value="" placeholder="Cotton, Linen, Polyester" data-new-attribute-values data-inline-add-input>
                                                <button class="btn inline-add" type="button" data-add-inline-attribute>Add</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            @if (! $isService)
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
