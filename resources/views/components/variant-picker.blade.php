@props([
    'name' => 'product_variant_id',
    'label' => 'Product variant',
    'required' => true,
    'class' => '',
    'selectedVariant' => null,
    'enhanced' => false,
    'optionsId' => 'variant-options',
])

@php
    $selectedVariantLabel = $selectedVariant
        ? $selectedVariant->product?->name.' / '.$selectedVariant->variant_name.' ('.$selectedVariant->sku.')'
        : '';
@endphp

<div class="field {{ $class }}" data-variant-picker>
    <label>{{ $label }}</label>
    @if ($enhanced)
        <div class="variant-search-picker" data-variant-search-picker>
            <div class="variant-search-control">
                <input
                    type="text"
                    data-variant-search
                    data-variant-options-id="{{ $optionsId }}"
                    placeholder="Type to search..."
                    autocomplete="off"
                    role="combobox"
                    aria-autocomplete="list"
                    aria-expanded="false"
                    value="{{ $selectedVariantLabel }}"
                    @required($required)
                >
                <button class="variant-search-button" type="button" data-variant-search-toggle aria-label="Search product variants">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 5 5"/></svg>
                </button>
            </div>
            <div class="variant-search-options" data-variant-search-options role="listbox" hidden></div>
        </div>
    @else
        <input
            type="text"
            list="{{ $optionsId }}"
            data-variant-search
            placeholder="Search by product, variant, SKU, or barcode"
            autocomplete="off"
            value="{{ $selectedVariantLabel }}"
            @required($required)
        >
    @endif
    <input type="hidden" name="{{ $name }}" data-variant-value value="{{ $selectedVariant?->id }}" @required($required)>
</div>
