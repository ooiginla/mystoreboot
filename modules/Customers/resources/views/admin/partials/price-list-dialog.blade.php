@php
    $selectedPriceList ??= null;
    $dialogId ??= $selectedPriceList ? 'price-list-edit-'.$selectedPriceList->id : 'price-list-dialog';
    $priceListAction = $selectedPriceList ? route('admin.customers.price-lists.update', $selectedPriceList) : route('admin.customers.price-lists.store');
@endphp
<dialog class="dialog" id="{{ $dialogId }}" style="width:min(620px,calc(100vw - 24px));">
    <div class="dialog-header"><div><h2 class="panel-title">{{ $selectedPriceList ? 'Edit pricelist' : 'Create pricelist' }}</h2><p class="subtle">Reusable customer pricing with optional validity dates.</p></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button></div>
    <div class="dialog-body">
        <form class="mini-form" method="POST" action="{{ $priceListAction }}">
            @csrf @if($selectedPriceList) @method('PUT') @endif
            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
            @if (!empty($returnToPriceList))
                <input type="hidden" name="return_to" value="price_list">
            @endif
            <div class="form-grid">
                <div class="field"><label>Name</label><input name="name" value="{{ $selectedPriceList?->name }}" placeholder="Wholesale" required></div>
                <div class="field"><label>Code</label><input name="code" value="{{ $selectedPriceList?->code }}" placeholder="WHOLESALE" required></div>
                <div class="field"><label>Starts</label><input type="date" name="starts_at" value="{{ $selectedPriceList?->starts_at?->toDateString() }}"></div>
                <div class="field"><label>Ends</label><input type="date" name="ends_at" value="{{ $selectedPriceList?->ends_at?->toDateString() }}"></div>
                <div class="field"><label class="inline-check"><input type="checkbox" name="is_active" value="1" @checked($selectedPriceList?->is_active ?? true)> Active</label></div>
                <div class="field"><label class="inline-check"><input type="checkbox" name="allow_additional_discounts" value="1" @checked($selectedPriceList?->allow_additional_discounts)> Allow coupons and manual discounts</label></div>
                <div class="field full"><label>Description</label><textarea name="description">{{ $selectedPriceList?->description }}</textarea></div>
            </div>
            <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Cancel</button><button class="btn primary" type="submit">Save pricelist</button></div>
        </form>
    </div>
</dialog>
