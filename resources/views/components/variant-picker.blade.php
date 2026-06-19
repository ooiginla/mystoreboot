@props([
    'name' => 'product_variant_id',
    'label' => 'Product variant',
    'required' => true,
    'class' => '',
    'selectedVariant' => null,
])

@php
    $selectedVariantLabel = $selectedVariant
        ? $selectedVariant->product?->name.' / '.$selectedVariant->variant_name.' ('.$selectedVariant->sku.')'
        : '';
@endphp

<div class="field {{ $class }}" data-variant-picker>
    <label>{{ $label }}</label>
    <input
        type="text"
        list="variant-options"
        data-variant-search
        placeholder="Search by product, variant, SKU, or barcode"
        autocomplete="off"
        value="{{ $selectedVariantLabel }}"
        @required($required)
    >
    <input type="hidden" name="{{ $name }}" data-variant-value value="{{ $selectedVariant?->id }}" @required($required)>
</div>
