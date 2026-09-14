@php
    $variantOptions = '<option value="">Select product…</option>';
    foreach ($variants as $v) {
        $variantOptions .= '<option value="'.$v->id.'">'.e(($v->product?->name ?? 'Item').' / '.$v->variant_name.' ('.$v->sku.')').'</option>';
    }
    $unitOptions = '<option value="">unit</option>';
    foreach ($units as $u) {
        $unitOptions .= '<option value="'.$u->id.'">'.e($u->code).'</option>';
    }
    $reqRow = function (string $idx) use ($variantOptions, $unitOptions): string {
        return '<div class="ing-row">'
            .'<div class="field"><label>Type</label><select data-req-type><option value="">All</option><option value="raw_material">Raw material</option><option value="product">Product</option></select></div>'
            .'<div class="field"><label>Product</label><select name="items['.$idx.'][product_variant_id]" data-req-product required></select></div>'
            .'<div class="field"><label>Qty</label><input name="items['.$idx.'][requested_quantity]" type="number" step="0.0001" min="0" required data-req-qty><small class="subtle" data-req-avail></small></div>'
            .'<div class="field"><label>Unit</label><select name="items['.$idx.'][unit_id]" data-req-unit><option value="">each</option></select></div>'
            .'<button type="button" class="btn ghost" data-remove-row aria-label="Remove">✕</button>'
            .'</div>';
    };
@endphp

<dialog class="dialog" id="requisition-dialog">
    <div class="dialog-header">
        <div>
            <h2 class="panel-title">New requisition</h2>
            <p class="subtle">Request stock from one store to another. Fulfilment transfers the stock.</p>
        </div>
        <button class="icon-btn" type="button" data-dialog-close aria-label="Close">✕</button>
    </div>
    <div class="dialog-body">
        <form class="mini-form" method="POST" action="{{ route('admin.inventory.requisitions.store') }}">
            @csrf
            <input type="hidden" name="tenant" value="{{ request('tenant') }}">
            <div class="form-grid">
                <div class="field">
                    <label>From (source store)</label>
                    <select name="source_location_id" required>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}">{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>To (requesting store)</label>
                    <select name="destination_location_id" required>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}">{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <h3 style="margin: 16px 0 8px;">Items</h3>
            <div id="req-items">{!! $reqRow('0') !!}</div>
            <button type="button" id="req-add-item" class="btn ghost">+ Add item</button>

            <div class="field full" style="margin-top: 12px;">
                <label>Notes</label>
                <input name="notes" placeholder="optional">
            </div>

            <div class="dialog-actions" style="margin-top: 16px;">
                <button class="btn" type="button" data-dialog-close>Cancel</button>
                <button class="btn primary" type="submit">Submit requisition</button>
            </div>
        </form>
    </div>
</dialog>

<template id="req-item-template">{!! $reqRow('__INDEX__') !!}</template>
