@php
    $selectedItem ??= null;
    $dialogId ??= $selectedItem ? 'price-list-item-edit-'.$selectedItem->id : 'price-list-item-dialog';
    $itemAction = $selectedItem
        ? route('admin.customers.price-list-items.update', $selectedItem)
        : route('admin.customers.price-list-items.store');
    $selectedPriceType = $selectedItem?->price_type ?: 'fixed';
@endphp

<dialog class="dialog" id="{{ $dialogId }}" style="width:min(620px,calc(100vw - 24px));">
    <div class="dialog-header">
        <div>
            <h2 class="panel-title">{{ $selectedItem ? 'Edit product price' : 'Add product price' }}</h2>
            <p class="subtle">{{ $priceList->name }} · Set a fixed price or a discount from the current retail price.</p>
        </div>
        <button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button>
    </div>
    <div class="dialog-body">
        <form class="mini-form" method="POST" action="{{ $itemAction }}" data-price-rule-form>
            @csrf
            @if ($selectedItem)
                @method('PUT')
            @endif
            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
            <input type="hidden" name="customer_price_list_id" value="{{ $priceList->id }}">
            <div class="form-grid">
                <div class="field full">
                    <label>Product</label>
                    <select name="product_variant_id" required>
                        <option value="">Select product</option>
                        @foreach ($pricingVariants as $variant)
                            <option value="{{ $variant->id }}" @selected($selectedItem?->product_variant_id === $variant->id)>
                                {{ $variant->product?->name }}{{ $variant->variant_name !== 'Default' ? ' · '.$variant->variant_name : '' }} · {{ $variant->sku }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Minimum quantity</label>
                    <input type="number" name="minimum_quantity" min="0.0001" step="0.0001" value="{{ $selectedItem?->minimum_quantity ?? 1 }}" required>
                </div>
                <div class="field">
                    <label>Price method</label>
                    <select name="price_type" data-price-rule-type required>
                        <option value="fixed" @selected($selectedPriceType === 'fixed')>Fixed amount</option>
                        <option value="percentage" @selected($selectedPriceType === 'percentage')>Percentage discount</option>
                    </select>
                </div>
                <div class="field full" data-price-rule-panel="fixed" @if($selectedPriceType !== 'fixed') hidden @endif>
                    <label>Unit price</label>
                    <input name="unit_price" inputmode="decimal" data-money-input value="{{ $selectedItem && $selectedPriceType === 'fixed' ? $money($selectedItem->unit_price_minor) : '' }}" @required($selectedPriceType === 'fixed')>
                </div>
                <div class="field full" data-price-rule-panel="percentage" @if($selectedPriceType !== 'percentage') hidden @endif>
                    <label>Discount from retail price (%)</label>
                    <input type="number" name="discount_percent" min="0.01" max="100" step="0.01" value="{{ $selectedItem?->discount_percent }}" placeholder="e.g. 10" @required($selectedPriceType === 'percentage')>
                    <span class="subtle">The selling price is recalculated automatically whenever the product's retail price changes.</span>
                </div>
            </div>
            <div class="button-row">
                <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                <button class="btn primary" type="submit">{{ $selectedItem ? 'Save changes' : 'Add product' }}</button>
            </div>
        </form>
    </div>
</dialog>
