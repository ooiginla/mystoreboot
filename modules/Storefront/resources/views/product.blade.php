@extends('storefront::layout', [
    'title' => $seo['title'] ?? ($product->name.' | '.$store->store_name),
    'metaDescription' => $seo['description'] ?? null,
    'metaKeywords' => $seo['keywords'] ?? null,
    'ogType' => 'product',
    'ogImage' => $seoImage ?? null,
])

@php
    $currency = $store->tenant?->currency_code ?? 'NGN';
    $currencySymbol = [
        'NGN' => '₦',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'GHS' => '₵',
        'KES' => 'KSh',
        'ZAR' => 'R',
        'CAD' => '$',
        'AUD' => '$',
    ][strtoupper($currency)] ?? strtoupper($currency);
    $money = fn (int|float|null $minor): string => number_format(((int) $minor) / 100, 2);
    $variants = $product->variants->values();
    $variant = $variants->first();
    $priceMinor = (int) ($variant?->selling_price_minor ?? $product->base_price_minor);
    $compareMinor = (int) ($variant?->compare_at_price_minor ?? $product->compare_at_price_minor ?? 0);
    $gallery = collect([$product->image_path])
        ->merge($product->images->pluck('image_path'))
        ->merge($product->variants->pluck('image_path'))
        ->filter()
        ->unique()
        ->map(fn ($path) => '/storage/'.ltrim($path, '/'))
        ->values();
    $primaryImage = $gallery->first();
    $optionGroups = $variants
        ->flatMap(fn ($row) => $row->optionValues)
        ->filter(fn ($value) => $value->option)
        ->groupBy(fn ($value) => $value->option->id)
        ->map(fn ($values) => [
            'name' => $values->first()->option->name,
            'values' => $values->unique('id')->sortBy('sort_order')->values(),
        ]);
    $attributeGroups = $product->attributeValues->groupBy(fn ($value) => $value->definition?->name ?? 'Attributes');
    $customerCustomFields = collect($product->custom_fields ?? [])
        ->filter(fn (array $field): bool => (bool) ($field['is_customer_selectable'] ?? false) && collect($field['values'] ?? [])->isNotEmpty())
        ->values();
    $isService = ($catalogType ?? $product->product_type) === \Modules\Catalog\Enums\ProductType::Service;
    $personalizationSettings = (array) ($product->personalization_settings ?? []);
    $personalizationFields = (array) ($personalizationSettings['fields'] ?? []);
    $personalizationEnabled = ! $isService && (bool) ($personalizationSettings['enabled'] ?? false);
    $personalizationProductId = (int) $product->id;
    $selectedOptionValueIds = $variant?->optionValues->pluck('id')->map(fn ($id) => (int) $id)->all() ?? [];
    $selectedImage = $variant?->image_path
        ? '/storage/'.ltrim($variant->image_path, '/')
        : $primaryImage;
    $payload = [
        'id' => 'product-'.$product->id.($variant ? '-variant-'.$variant->id : ''),
        'productVariantId' => $variant?->id,
        'name' => $product->name.($variant && $product->has_variants ? ' - '.$variant->variant_name : ''),
        'priceMinor' => $priceMinor,
        'image' => $selectedImage,
    ];
    $variantPayloads = $variants->map(function ($row) use ($product, $primaryImage): array {
        $image = $row->image_path
            ? '/storage/'.ltrim($row->image_path, '/')
            : $primaryImage;

        return [
            'id' => (int) $row->id,
            'name' => $row->variant_name,
            'sku' => $row->sku,
            'priceMinor' => (int) $row->selling_price_minor,
            'compareMinor' => (int) ($row->compare_at_price_minor ?? 0),
            'image' => $image,
            'optionValueIds' => $row->optionValues->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all(),
            'cart' => [
                'id' => 'product-'.$product->id.'-variant-'.$row->id,
                'productVariantId' => (int) $row->id,
                'name' => $product->name.' - '.$row->variant_name,
                'priceMinor' => (int) $row->selling_price_minor,
                'image' => $image,
            ],
        ];
    })->values();
    $detailRouteName = $isService ? 'services.show' : 'products.show';
    $shareUrl = $storefrontRoute($store, $detailRouteName, [
        $isService ? 'serviceSlug' : 'productSlug' => $product->slug,
    ]);
@endphp

@push('head')
    @php
        $ld = array_filter([
            '@context' => 'https://schema.org/',
            '@type' => $isService ? 'Service' : 'Product',
            'name' => $product->name,
            'description' => $seo['description'] ?? null,
            'image' => $seoImage ? [$seoImage] : null,
            'sku' => $variant?->sku ?: null,
            'brand' => $product->brand ? ['@type' => 'Brand', 'name' => $product->brand] : null,
            'category' => $product->category?->name ?: null,
            'keywords' => $seo['keywords'] ?? null,
            'offers' => [
                '@type' => 'Offer',
                'priceCurrency' => strtoupper($currency),
                'price' => number_format(((int) $priceMinor) / 100, 2, '.', ''),
                'availability' => $product->status === \Modules\Catalog\Enums\ProductStatus::Active
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'url' => $shareUrl,
            ],
        ], fn ($value) => $value !== null && $value !== '');
    @endphp
    <script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
    <section class="store-shell py-10 md:py-14">
        <div class="grid grid-cols-1 gap-10 lg:grid-cols-12">
            <div class="lg:col-span-7">
                <div class="relative aspect-[4/5] overflow-hidden rounded-lg bg-[var(--store-soft)]">
                    @if ($primaryImage)
                        <img src="{{ $primaryImage }}" alt="{{ $seo['image_alt'] ?? $product->name }}" class="h-full w-full object-contain mix-blend-multiply" data-product-main-image>
                    @else
                        <div class="sf-display-xl flex h-full w-full items-center justify-center text-[var(--store-primary)]">{{ Str::of($product->name)->substr(0, 2)->upper() }}</div>
                    @endif
                    @if ($gallery->count() > 1)
                        <div class="absolute inset-x-4 top-1/2 flex -translate-y-1/2 justify-between">
                            <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full bg-white shadow-xl" data-gallery-prev aria-label="Previous image">@include('storefront::partials.icon', ['name' => 'chevron_left', 'class' => 'h-5 w-5'])</button>
                            <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full bg-white shadow-xl" data-gallery-next aria-label="Next image">@include('storefront::partials.icon', ['name' => 'chevron_right', 'class' => 'h-5 w-5'])</button>
                        </div>
                    @endif
                </div>
                <div class="mt-4 grid grid-cols-4 gap-4" data-gallery-thumbnails>
                    @forelse ($gallery as $image)
                        <button type="button" class="aspect-square overflow-hidden rounded-lg border border-[var(--store-line)] bg-[var(--store-soft)] p-2 ring-[var(--store-primary)] first:ring-2" data-gallery-image="{{ $image }}" aria-label="View product image">
                            <img src="{{ $image }}" alt="" class="h-full w-full object-contain">
                        </button>
                    @empty
                        @for ($i = 0; $i < 4; $i++)
                            <div class="aspect-square rounded-lg bg-[var(--store-soft)]"></div>
                        @endfor
                    @endforelse
                </div>
            </div>

            <div class="lg:col-span-5" data-variant-product>
                <p class="sf-label-md uppercase text-[var(--store-secondary)]">{{ $product->category?->name ?? 'Product' }}</p>
                <h1 class="sf-headline-lg mt-2 text-[var(--store-ink)]">{{ $product->name }}</h1>
                <div class="mt-4 flex items-center gap-3">
                    <strong class="sf-headline-lg text-[var(--store-primary)]" data-variant-price>{{ $currencySymbol }}{{ $money($priceMinor) }}</strong>
                    <span class="sf-body-lg text-[var(--store-muted)] line-through" data-variant-compare @if (! $compareMinor || $compareMinor <= $priceMinor) hidden @endif>{{ $currencySymbol }}{{ $money($compareMinor) }}</span>
                </div>
                @if ($variant)
                    <p class="sf-body-md mt-2 text-[var(--store-muted)]" data-selected-variant-meta>{{ $variant->variant_name }} · SKU {{ $variant->sku }}</p>
                @endif

                <div class="mt-6">
                    <span class="sf-body-md font-bold">Quantity</span>
                    <div class="mt-2 flex w-fit items-center overflow-hidden rounded-full border border-[var(--store-line)] bg-white">
                        <button type="button" class="px-4 py-2 hover:bg-[var(--store-soft)]" data-detail-qty="-1">@include('storefront::partials.icon', ['name' => 'remove', 'class' => 'h-5 w-5'])</button>
                        <span class="sf-body-md min-w-12 text-center font-bold" data-detail-quantity>1</span>
                        <button type="button" class="px-4 py-2 hover:bg-[var(--store-soft)]" data-detail-qty="1">@include('storefront::partials.icon', ['name' => 'add', 'class' => 'h-5 w-5'])</button>
                    </div>
                </div>

                <div class="mt-5 grid gap-4">
                    @foreach ($optionGroups as $group)
                        <label class="grid gap-2">
                            <span class="sf-body-md font-bold">{{ $group['name'] }}</span>
                            <select class="store-input" data-variant-option>
                                @foreach ($group['values'] as $value)
                                    <option value="{{ $value->id }}" @selected(in_array((int) $value->id, $selectedOptionValueIds, true))>{{ $value->value }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endforeach

                    @if ($optionGroups->isEmpty() && $variants->count() > 1)
                        <label class="grid gap-2">
                            <span class="sf-body-md font-bold">Variant</span>
                            <select class="store-input" data-direct-variant>
                                @foreach ($variants as $row)
                                    <option value="{{ $row->id }}" @selected($variant?->id === $row->id)>{{ $row->variant_name }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif

                    @foreach ($customerCustomFields as $customField)
                        <label class="grid gap-2">
                            <span class="sf-body-md font-bold">{{ $customField['key'] }}</span>
                            <select class="store-input" data-custom-selection data-custom-key="{{ $customField['key'] }}">
                                @foreach ($customField['values'] as $customValue)
                                    <option value="{{ $customValue }}">{{ $customValue }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endforeach

                    @if ($personalizationEnabled)
                        <div class="rounded-lg border border-[var(--store-line)] bg-[var(--store-soft)] p-4" data-product-personalization>
                            <p class="sf-body-md font-bold">Would you like to personalise this item?</p>
                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                <label class="sf-body-md flex cursor-pointer items-center gap-2 rounded-lg border border-[var(--store-line)] bg-white p-3">
                                    <input type="radio" name="product_personalization" value="no" checked data-personalization-choice>
                                    <span>No, keep it plain</span>
                                </label>
                                <label class="sf-body-md flex cursor-pointer items-center gap-2 rounded-lg border border-[var(--store-line)] bg-white p-3">
                                    <input type="radio" name="product_personalization" value="yes" data-personalization-choice>
                                    <span>Yes, personalise it</span>
                                </label>
                            </div>
                            <div class="mt-4 grid gap-4" data-personalization-form hidden>
                                @if ((bool) ($personalizationFields['customized_text'] ?? false))
                                    <label class="grid gap-2">
                                        <span class="sf-body-md font-bold">Customized Text</span>
                                        <textarea class="store-input min-h-24" maxlength="500" placeholder="Enter the text you want customized on this item" data-personalization-text></textarea>
                                    </label>
                                @endif
                                @if ((bool) ($personalizationFields['additional_info'] ?? false))
                                    <label class="grid gap-2">
                                        <span class="sf-body-md font-bold">Additional Info/Note</span>
                                        <textarea class="store-input min-h-24" maxlength="2000" placeholder="Suggest text colour, font size, engraving position, or any special instruction" data-personalization-note></textarea>
                                    </label>
                                @endif
                                @if ((bool) ($personalizationFields['photograph'] ?? false))
                                    <label class="grid gap-2">
                                        <span class="sf-body-md font-bold">Upload photograph</span>
                                        <input class="store-input" type="file" accept="image/jpeg,image/png,image/webp,image/gif,image/bmp" data-personalization-photo>
                                        <span class="sf-caption text-[var(--store-muted)]">JPG, PNG, GIF, BMP, or WebP. Maximum 8 MB.</span>
                                        <span class="sf-body-sm text-[var(--store-muted)]">JPEG, PNG, WebP, or GIF up to 5 MB.</span>
                                        <span class="sf-body-sm font-semibold" data-personalization-photo-status aria-live="polite"></span>
                                    </label>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
                <p class="sf-body-md mt-3 font-semibold text-red-600" data-variant-unavailable hidden>This option combination is currently unavailable.</p>

                <div class="mt-6">
                    <p class="sf-body-md font-bold">Share this product</p>
                    <div class="mt-3 flex gap-3">
                        <a class="flex h-10 w-10 items-center justify-center rounded-full border border-[var(--store-line)] text-[var(--store-primary)] hover:bg-[var(--store-soft)]" href="https://facebook.com/sharer/sharer.php?u={{ urlencode($shareUrl) }}" aria-label="Share on Facebook">@include('storefront::partials.social-icon', ['network' => 'facebook'])</a>
                        <a class="flex h-10 w-10 items-center justify-center rounded-full border border-[var(--store-line)] text-[var(--store-primary)] hover:bg-[var(--store-soft)]" href="https://wa.me/?text={{ urlencode($product->name.' '.$shareUrl) }}" aria-label="Share on WhatsApp">@include('storefront::partials.social-icon', ['network' => 'whatsapp'])</a>
                        <a class="flex h-10 w-10 items-center justify-center rounded-full border border-[var(--store-line)] text-[var(--store-primary)] hover:bg-[var(--store-soft)]" href="https://twitter.com/intent/tweet?text={{ urlencode($product->name) }}&url={{ urlencode($shareUrl) }}" aria-label="Share on X"><span class="sf-label-md">X</span></a>
                    </div>
                </div>

                <div class="mt-7 border-t border-[var(--store-line)] pt-5">
                    <div class="flex gap-2 border-b border-[var(--store-line)]" role="tablist">
                        <button type="button" class="sf-label-md border-b-2 border-[var(--store-primary)] px-4 py-3 text-[var(--store-primary)]" data-tab-button="description">Product Description</button>
                        <button type="button" class="sf-label-md border-b-2 border-transparent px-4 py-3 text-[var(--store-muted)]" data-tab-button="reviews">Reviews</button>
                    </div>
                    <div class="sf-body-md py-5 text-[var(--store-muted)]" data-tab-panel="description">{{ $product->description ?: 'No product description has been added yet.' }}</div>
                    <div class="sf-body-md hidden py-5 text-[var(--store-muted)]" data-tab-panel="reviews">No customer reviews yet.</div>
                </div>

                @if ($attributeGroups->isNotEmpty())
                    <div class="mt-5 grid gap-2">
                        @foreach ($attributeGroups as $name => $values)
                            <p class="sf-body-md text-[var(--store-muted)]"><strong class="text-[var(--store-ink)]">{{ $name }}:</strong> {{ $values->pluck('value')->unique()->join(', ') }}</p>
                        @endforeach
                    </div>
                @endif

                <div class="mt-3 flex flex-wrap gap-2">
                    @forelse ($product->tags as $tag)
                        <span class="sf-caption rounded-full bg-[var(--store-soft)] px-3 py-1 font-bold uppercase text-[var(--store-muted)]">{{ $tag->name }}</span>
                    @empty
                        <span class="sf-caption rounded-full bg-[var(--store-soft)] px-3 py-1 font-bold uppercase text-[var(--store-muted)]">No tags</span>
                    @endforelse
                </div>

                <div class="mt-7 flex flex-col gap-3 sm:flex-row">
                    <button type="button" class="store-btn store-btn-secondary flex-1" data-add-to-cart data-variant-cart-button data-use-detail-quantity="true" data-product='@json($payload)' @disabled(! $variant)>Add to Cart</button>
                    <button type="button" class="store-btn store-btn-primary flex-1" data-add-to-cart data-variant-cart-button data-use-detail-quantity="true" data-product='@json($payload)' @disabled(! $variant)>Buy It Now</button>
                </div>
            </div>
        </div>
    </section>

    <section class="store-shell border-t border-[var(--store-line)] py-12">
        <h2 class="sf-headline-lg text-[var(--store-primary)]">YOU MIGHT ALSO LIKE</h2>
        <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-5">
            @forelse ($relatedProducts as $relatedProduct)
                @include('storefront::partials.related-product-card', ['product' => $relatedProduct, 'detailRouteName' => $detailRouteName])
            @empty
                <div class="sf-body-md store-card col-span-full p-8 text-center text-[var(--store-muted)]">No related products yet.</div>
            @endforelse
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        (() => {
            const root = document.querySelector('[data-variant-product]');
            if (!root) return;

            const variants = @json($variantPayloads);
            if (!variants.length) return;

            const optionFields = Array.from(root.querySelectorAll('[data-variant-option]'));
            const directField = root.querySelector('[data-direct-variant]');
            const customFields = Array.from(root.querySelectorAll('[data-custom-selection]'));
            const personalizationRoot = root.querySelector('[data-product-personalization]');
            const personalizationChoices = Array.from(root.querySelectorAll('[data-personalization-choice]'));
            const personalizationForm = root.querySelector('[data-personalization-form]');
            const personalizationText = root.querySelector('[data-personalization-text]');
            const personalizationNote = root.querySelector('[data-personalization-note]');
            const personalizationPhoto = root.querySelector('[data-personalization-photo]');
            const personalizationPhotoStatus = root.querySelector('[data-personalization-photo-status]');
            const personalizationPhotoUrl = @json($storefrontRoute($store, 'personalization.photo'));
            let personalizationUpload = null;
            let personalizationUploading = false;
            const price = root.querySelector('[data-variant-price]');
            const compare = root.querySelector('[data-variant-compare]');
            const meta = root.querySelector('[data-selected-variant-meta]');
            const unavailable = root.querySelector('[data-variant-unavailable]');
            const image = document.querySelector('[data-product-main-image]');
            const cartButtons = Array.from(root.querySelectorAll('[data-variant-cart-button]'));
            const currencySymbol = @json($currencySymbol);
            const formatMoney = (minor) => currencySymbol + (Number(minor || 0) / 100).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });

            const selectedVariant = () => {
                if (directField) {
                    return variants.find((variant) => variant.id === Number(directField.value));
                }

                if (!optionFields.length) return variants[0];

                const selectedIds = optionFields.map((field) => Number(field.value)).sort((a, b) => a - b);
                return variants.find((variant) =>
                    variant.optionValueIds.length === selectedIds.length
                    && variant.optionValueIds.every((id, index) => id === selectedIds[index])
                );
            };

            const selectedCustomSelections = () => Object.fromEntries(
                customFields.map((field) => [field.dataset.customKey, field.value])
            );

            const wantsPersonalization = () => personalizationChoices.find((field) => field.checked)?.value === 'yes';
            const selectedPersonalization = () => {
                if (!personalizationRoot || !wantsPersonalization()) return null;

                return {
                    requested: true,
                    ...(personalizationText ? { customized_text: personalizationText.value.trim() } : {}),
                    ...(personalizationNote ? { additional_info: personalizationNote.value.trim() } : {}),
                    ...(personalizationUpload ? {
                        photograph_token: personalizationUpload.token,
                        photograph_name: personalizationUpload.name,
                    } : {}),
                };
            };

            const cartWithCustomSelections = (cart) => {
                const customSelections = selectedCustomSelections();
                const personalization = selectedPersonalization();
                const signature = [
                    Object.entries(customSelections).map(([key, value]) => `${key}:${value}`).join('|'),
                    personalization ? JSON.stringify(personalization) : '',
                ].filter(Boolean).join('|');

                return {
                    ...cart,
                    id: signature ? `${cart.id}-custom-${encodeURIComponent(signature)}` : cart.id,
                    customSelections,
                    personalization,
                };
            };

            const renderVariant = () => {
                const variant = selectedVariant();
                unavailable.hidden = Boolean(variant);
                cartButtons.forEach((button) => {
                    button.disabled = !variant || personalizationUploading;
                });

                if (!variant) return;

                price.textContent = formatMoney(variant.priceMinor);
                compare.textContent = formatMoney(variant.compareMinor);
                compare.hidden = !variant.compareMinor || variant.compareMinor <= variant.priceMinor;
                if (meta) meta.textContent = `${variant.name} · SKU ${variant.sku}`;
                if (image && variant.image) image.src = variant.image;
                cartButtons.forEach((button) => {
                    button.dataset.product = JSON.stringify(cartWithCustomSelections(variant.cart));
                });
            };

            optionFields.forEach((field) => field.addEventListener('change', renderVariant));
            directField?.addEventListener('change', renderVariant);
            customFields.forEach((field) => field.addEventListener('change', renderVariant));
            const syncPersonalization = () => {
                if (personalizationForm) personalizationForm.hidden = !wantsPersonalization();
                if (personalizationText) personalizationText.required = wantsPersonalization();
                if (personalizationPhoto) personalizationPhoto.required = wantsPersonalization();
                renderVariant();
            };
            personalizationChoices.forEach((field) => field.addEventListener('change', syncPersonalization));
            personalizationText?.addEventListener('input', renderVariant);
            personalizationNote?.addEventListener('input', renderVariant);
            personalizationPhoto?.addEventListener('change', async () => {
                personalizationUpload = null;
                const photograph = personalizationPhoto.files?.[0];
                if (!photograph) {
                    if (personalizationPhotoStatus) personalizationPhotoStatus.textContent = '';
                    renderVariant();
                    return;
                }

                if (photograph.size > 8 * 1024 * 1024) {
                    personalizationPhoto.value = '';
                    if (personalizationPhotoStatus) {
                        personalizationPhotoStatus.textContent = 'The photograph must not be larger than 8 MB.';
                        personalizationPhotoStatus.classList.remove('text-green-700');
                        personalizationPhotoStatus.classList.add('text-red-600');
                    }
                    renderVariant();
                    return;
                }

                personalizationUploading = true;
                if (personalizationPhotoStatus) personalizationPhotoStatus.textContent = 'Uploading photograph…';
                renderVariant();

                try {
                    const body = new FormData();
                    body.append('product_id', @json($personalizationProductId));
                    body.append('photograph', photograph);

                    const response = await fetch(personalizationPhotoUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body,
                    });
                    const responseBody = await response.text();
                    let result = {};
                    try {
                        result = responseBody ? JSON.parse(responseBody) : {};
                    } catch (parseError) {
                        if (response.status === 413) {
                            throw new Error('The photograph is too large for the server. Please choose a smaller image.');
                        }
                        throw new Error('The server could not process the photograph. Please try again or choose a different image.');
                    }
                    if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'Upload failed.');

                    personalizationUpload = { token: result.token, name: result.name };
                    if (personalizationPhotoStatus) {
                        personalizationPhotoStatus.textContent = `${result.name} uploaded successfully.`;
                        personalizationPhotoStatus.classList.remove('text-red-600');
                        personalizationPhotoStatus.classList.add('text-green-700');
                    }
                } catch (error) {
                    personalizationPhoto.value = '';
                    if (personalizationPhotoStatus) {
                        personalizationPhotoStatus.textContent = error.message || 'Could not upload the photograph. Please try again.';
                        personalizationPhotoStatus.classList.remove('text-green-700');
                        personalizationPhotoStatus.classList.add('text-red-600');
                    }
                } finally {
                    personalizationUploading = false;
                    renderVariant();
                }
            });

            window.storefrontPrepareCartProduct = (cart, button) => {
                if (!root.contains(button) || !personalizationRoot || !wantsPersonalization()) return cart;

                if (personalizationText && !personalizationText.reportValidity()) return null;
                if (personalizationUploading) {
                    if (personalizationPhotoStatus) personalizationPhotoStatus.textContent = 'Please wait for the photograph to finish uploading.';
                    return null;
                }
                if (personalizationPhoto && !personalizationUpload) {
                    personalizationPhoto.reportValidity();
                    return null;
                }

                return cartWithCustomSelections(cart);
            };
            syncPersonalization();
            renderVariant();
        })();
    </script>
@endpush
