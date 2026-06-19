@php
    $isEdit = (bool) $product;
    $isService = $productType === \Modules\Catalog\Enums\ProductType::Service->value;
    $action = $isEdit ? route('admin.catalog.products.update', $product) : route('admin.catalog.products.store');
    $minorToMoney = fn (?int $minor): string => number_format(($minor ?? 0) / 100, 2, '.', '');
    $availableCategories = $isService ? $serviceCategories : $productCategories;
    $hasVariants = (bool) old('has_variants', $product?->has_variants ?? false);
    $selectedTagIds = collect(old('tag_ids', $product?->tags?->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id);
    $selectedAttributeValueIds = collect(old('attribute_value_ids', $product?->attributeValues?->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id);
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
                'discount_price' => $row->discount_price_minor ? $minorToMoney($row->discount_price_minor) : '',
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
            'discount_price' => '',
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
                        @foreach ($availableCategories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('category_id', $product?->category_id) === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Brand</label>
                    <input name="brand" value="{{ old('brand', $product?->brand) }}">
                </div>
                <div class="field full">
                    <label>Tags</label>
                    <div class="check-grid">
                        @forelse ($tags as $tag)
                            <label class="inline-check"><input type="checkbox" name="tag_ids[]" value="{{ $tag->id }}" @checked($selectedTagIds->contains($tag->id))> {{ $tag->name }}</label>
                        @empty
                            <span class="subtle">No tags yet. Add tags from the Tags pill first.</span>
                        @endforelse
                    </div>
                </div>
                <div class="field full">
                    <label>Attributes</label>
                    @forelse ($attributes as $attribute)
                        <div class="variant-row-editor">
                            <strong>{{ $attribute->name }}</strong>
                            <div class="check-grid">
                                @forelse ($attribute->values as $value)
                                    <label class="inline-check"><input type="checkbox" name="attribute_value_ids[]" value="{{ $value->id }}" @checked($selectedAttributeValueIds->contains($value->id))> {{ $value->value }}</label>
                                @empty
                                    <span class="subtle">No values configured.</span>
                                @endforelse
                            </div>
                        </div>
                    @empty
                        <span class="subtle">No attributes yet. Add attributes from the Attributes pill first.</span>
                    @endforelse
                </div>
                <div class="field">
                    <label>Selling price</label>
                    <input name="base_price" type="text" inputmode="decimal" data-money-input value="{{ old('base_price', $minorToMoney($product?->base_price_minor)) }}" required>
                </div>
                <div class="field">
                    <label>Cost price</label>
                    <input name="base_cost_price" type="text" inputmode="decimal" data-money-input value="{{ old('base_cost_price', $minorToMoney($product?->base_cost_price_minor)) }}">
                </div>
                <div class="field">
                    <label>Discount price</label>
                    <input name="discount_price" type="text" inputmode="decimal" data-money-input value="{{ old('discount_price', $product?->discount_price_minor ? $minorToMoney($product->discount_price_minor) : '') }}">
                </div>
                <div class="field">
                    <label>Status</label>
                    <select name="status" required>
                        @foreach ($productStatuses as $status)
                            <option value="{{ $status->value }}" @selected(old('status', $product?->status->value ?? \Modules\Catalog\Enums\ProductStatus::Active->value) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" @if (! $isService) data-simple-variant-field @endif>
                    <label>SKU</label>
                    <input name="sku" value="{{ old('sku', $variant?->sku) }}" placeholder="auto-generated">
                </div>
                <div class="field" @if (! $isService) data-simple-variant-field @endif>
                    <label>Barcode</label>
                    <input name="barcode" value="{{ old('barcode', $variant?->barcode) }}">
                </div>
                <div class="field">
                    <label>Tax behavior</label>
                    <select name="tax_behavior" required>
                        @foreach ($taxBehaviors as $value => $label)
                            <option value="{{ $value }}" @selected(old('tax_behavior', $product?->tax_behavior->value ?? \Modules\Catalog\Enums\TaxBehavior::Taxable->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Tax rate (%)</label>
                    <input name="tax_rate" type="number" step="0.01" min="0" max="100" value="{{ old('tax_rate', $product?->tax_rate) }}">
                </div>

                @if (! $isService)
                    <label><input type="checkbox" name="has_variants" value="1" data-variant-toggle {{ $hasVariants ? 'checked' : '' }}> This item has sellable variants</label>
                @else
                    <input type="hidden" name="has_variants" value="0">
                @endif

                <div class="field">
                    <label>Image</label>
                    <input name="image" type="file" accept="image/*">
                    @if ($product?->image_path && $imageUrl($product->image_path))
                        <span class="subtle">Current image</span>
                        <div class="product-thumb" style="width: 140px; height: 110px;">
                            <img src="{{ $imageUrl($product->image_path) }}" alt="{{ $product->name }} image preview">
                        </div>
                    @endif
                </div>
                <div class="field full">
                    <label>Description</label>
                    <textarea name="description">{{ old('description', $product?->description) }}</textarea>
                </div>
            </div>

            @if (! $isService)
                <div class="panel" data-variant-editor {{ $hasVariants ? '' : 'hidden' }}>
                    <div class="panel-header">
                        <div>
                            <h3 class="panel-title">Options & sellable variants</h3>
                            <p class="subtle">Define option groups, generate combinations, then tune SKU, price, stock, and status.</p>
                        </div>
                        <button class="btn secondary" type="button" data-add-option>Add option</button>
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
                                            <button class="btn secondary" type="button" data-remove-option>Remove option</button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="button-row" style="justify-content: flex-start;">
                            <button class="btn accent" type="button" data-generate-variants>Generate variants</button>
                            <button class="btn secondary" type="button" data-add-variant>Add manual variant</button>
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
                                </div>
                                <div class="variant-grid">
                                    <div class="field">
                                        <label>Selling price</label>
                                        <input name="variants[{{ $index }}][selling_price]" type="text" inputmode="decimal" data-money-input value="{{ $row['selling_price'] ?? '' }}">
                                    </div>
                                    <div class="field">
                                        <label>Cost price</label>
                                        <input name="variants[{{ $index }}][cost_price]" type="text" inputmode="decimal" data-money-input value="{{ $row['cost_price'] ?? '' }}">
                                    </div>
                                    <div class="field">
                                        <label>Discount price</label>
                                        <input name="variants[{{ $index }}][discount_price]" type="text" inputmode="decimal" data-money-input value="{{ $row['discount_price'] ?? '' }}">
                                    </div>
                                </div>
                                <div class="variant-grid">
                                    <div></div>
                                    <div class="subtle" style="align-content: end;">Stock and reorder levels are managed per branch in Inventory.</div>
                                    <div></div>
                                    <div class="field" style="align-content: end;">
                                        <button class="btn secondary" type="button" data-remove-variant>Remove variant</button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="button-row">
                <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                <button class="btn primary" type="submit">{{ $isEdit ? 'Save changes' : ($isService ? 'Add service' : 'Add product') }}</button>
            </div>
        </form>
    </div>
</dialog>
