@php
    $isEdit = (bool) $recipe;
    $outputUnits = ($unitCategories->firstWhere('id', $outputCategoryId)?->units ?? collect())
        ->filter(fn ($u) => $u->to_base_factor !== null)->values();

    $ingRow = function (string $idx, ?string $type = 'raw_material', ?int $selItem = null, ?int $selUnit = null, string $qty = '', string $waste = '0'): string {
        $typeOpt = fn (string $v, string $label): string => '<option value="'.$v.'"'.($type === $v ? ' selected' : '').'>'.$label.'</option>';
        return '<div class="ing-row">'
            .'<div class="field"><label>Type</label><select data-recipe-type>'.$typeOpt('raw_material', 'Raw material').$typeOpt('product', 'Product').'</select></div>'
            .'<div class="field"><label>Ingredient</label><select name="items['.$idx.'][component_product_variant_id]" data-recipe-component data-selected="'.($selItem ?: '').'" required></select></div>'
            .'<div class="field"><label>Qty</label><input name="items['.$idx.'][quantity]" type="number" step="0.0001" min="0" value="'.e($qty).'" required></div>'
            .'<div class="field"><label>Unit</label><select name="items['.$idx.'][unit_id]" data-recipe-unit data-selected="'.($selUnit ?: '').'"><option value="">pc</option></select></div>'
            .'<div class="field"><label>Waste %</label><input name="items['.$idx.'][wastage_percent]" type="number" step="0.001" min="0" value="'.e($waste).'"></div>'
            .'<button type="button" class="btn ghost" data-remove-row aria-label="Remove">✕</button>'
            .'</div>';
    };
@endphp

<dialog class="dialog" id="recipe-dialog">
    <div class="dialog-header">
        <div>
            <h2 class="panel-title">{{ $isEdit ? 'Edit recipe' : 'Add recipe' }}</h2>
            <p class="subtle">For {{ $product->name }}. Set the yield and the raw materials a batch consumes.</p>
        </div>
        <button class="icon-btn" type="button" data-dialog-close aria-label="Close">✕</button>
    </div>
    <div class="dialog-body">
        <form class="mini-form" method="POST" action="{{ $isEdit ? route('admin.inventory.production.recipes.update', $recipe->id) : route('admin.inventory.production.recipes.store') }}">
            @csrf
            @if ($isEdit) @method('PUT') @endif
            <input type="hidden" name="tenant" value="{{ request('tenant') }}">
            <input type="hidden" name="output_product_variant_id" value="{{ $variant->id }}">
            <div class="form-grid">
                <div class="field full">
                    <label>Recipe name</label>
                    <input name="name" required value="{{ old('name', $recipe?->name ?? $product->name) }}">
                </div>
                <div class="field">
                    <label>Yield quantity</label>
                    <input name="yield_quantity" type="number" step="0.0001" min="0" required value="{{ old('yield_quantity', $recipe ? rtrim(rtrim((string) $recipe->yield_quantity, '0'), '.') : '') }}" placeholder="e.g. 10">
                </div>
                <div class="field">
                    <label>Yield unit</label>
                    <select name="yield_unit_id">
                        <option value="">pc</option>
                        @foreach ($outputUnits as $u)
                            <option value="{{ $u->id }}" @selected((int) old('yield_unit_id', $recipe?->yield_unit_id) === $u->id)>{{ $u->code }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Shelf life (days)</label>
                    <input name="shelf_life_days" type="number" min="0" step="1" value="{{ old('shelf_life_days', $recipe?->shelf_life_days) }}" placeholder="Leave blank if it does not expire">
                    <small class="subtle">Each batch you produce gets its own lot. Set this and the lot is given an expiry date, so older trays are used first.</small>
                </div>
                @if (($prepStations ?? collect())->isNotEmpty())
                    <div class="field">
                        <label>Prep station</label>
                        <select name="prep_station_id">
                            <option value="">—</option>
                            @foreach ($prepStations as $station)
                                <option value="{{ $station->id }}" @selected((int) old('prep_station_id', $recipe?->prep_location_id) === $station->id)>{{ $station->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>

            <h3 style="margin: 16px 0 8px;">Ingredients</h3>
            <div id="recipe-items">
                @if ($isEdit && $recipe->items->isNotEmpty())
                    @foreach ($recipe->items as $i => $item)
                        {!! $ingRow((string) $i, $item->componentVariant?->product?->product_type?->value ?? 'raw_material', $item->component_product_variant_id, $item->unit_id, rtrim(rtrim((string) $item->quantity, '0'), '.'), rtrim(rtrim((string) $item->wastage_percent, '0'), '.')) !!}
                    @endforeach
                @else
                    {!! $ingRow('0') !!}
                @endif
            </div>
            <button type="button" id="recipe-add-item" class="btn ghost">+ Add ingredient</button>

            <div class="dialog-actions" style="margin-top: 16px;">
                <button class="btn" type="button" data-dialog-close>Cancel</button>
                <button class="btn primary" type="submit">{{ $isEdit ? 'Save changes' : 'Save recipe' }}</button>
            </div>
        </form>
    </div>
</dialog>

<template id="recipe-item-template">{!! $ingRow('__INDEX__') !!}</template>
