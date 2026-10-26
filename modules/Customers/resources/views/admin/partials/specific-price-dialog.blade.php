@php
    $selectedPrice ??= null;
    $dialogId ??= $selectedPrice ? 'specific-price-edit-'.$selectedPrice->id : 'specific-price-dialog';
    $specificPriceAction = $selectedPrice
        ? route('admin.customers.specific-prices.update', $selectedPrice)
        : route('admin.customers.specific-prices.store');
@endphp

<dialog class="dialog" id="{{ $dialogId }}">
    <div class="dialog-header">
        <div>
            <h2 class="panel-title">{{ $selectedPrice ? 'Edit customer price' : 'Add customer price' }}</h2>
            <p class="subtle">This price takes priority over pricelist and retail pricing for the selected customer.</p>
        </div>
        <button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button>
    </div>
    <div class="dialog-body">
        <form class="mini-form" method="POST" action="{{ $specificPriceAction }}">
            @csrf
            @if ($selectedPrice)
                @method('PUT')
            @endif
            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
            <div class="form-grid">
                <div class="field">
                    <label>Customer</label>
                    <select name="customer_id" required>
                        <option value="">Select customer</option>
                        @foreach ($allCustomers as $customerOption)
                            @continue($customerOption->phone === 'WALK-IN')
                            <option value="{{ $customerOption->id }}" @selected($selectedPrice?->customer_id === $customerOption->id)>{{ $customerOption->name }} · {{ $customerOption->phone }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Product</label>
                    <select name="product_variant_id" required>
                        <option value="">Select product</option>
                        @foreach ($pricingVariants as $variant)
                            <option value="{{ $variant->id }}" @selected($selectedPrice?->product_variant_id === $variant->id)>
                                {{ $variant->product?->name }}{{ $variant->variant_name !== 'Default' ? ' · '.$variant->variant_name : '' }} · {{ $variant->sku }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Minimum quantity</label>
                    <input type="number" name="minimum_quantity" min="0.0001" step="0.0001" value="{{ $selectedPrice?->minimum_quantity ?? 1 }}" required>
                </div>
                <div class="field">
                    <label>Unit price</label>
                    <input name="unit_price" inputmode="decimal" data-money-input value="{{ $selectedPrice ? $money($selectedPrice->unit_price_minor) : '' }}" required>
                </div>
                <div class="field"><label>Starts</label><input type="date" name="starts_at" value="{{ $selectedPrice?->starts_at?->toDateString() }}"></div>
                <div class="field"><label>Ends</label><input type="date" name="ends_at" value="{{ $selectedPrice?->ends_at?->toDateString() }}"></div>
                <div class="field"><label class="inline-check"><input type="checkbox" name="is_active" value="1" @checked($selectedPrice?->is_active ?? true)> Active</label></div>
                <div class="field"><label class="inline-check"><input type="checkbox" name="allow_additional_discounts" value="1" @checked($selectedPrice?->allow_additional_discounts)> Allow additional discounts</label></div>
                <div class="field full"><label>Notes</label><textarea name="notes">{{ $selectedPrice?->notes }}</textarea></div>
            </div>
            <div class="button-row">
                <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                <button class="btn primary" type="submit">{{ $selectedPrice ? 'Save changes' : 'Add customer price' }}</button>
            </div>
        </form>
    </div>
</dialog>
