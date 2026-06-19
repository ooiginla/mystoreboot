<dialog class="dialog" id="coupon-dialog">
    <div class="dialog-header"><div><h2 class="panel-title">Add coupon</h2><p class="subtle">Create an amount or percentage coupon for POS sales.</p></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button></div>
    <div class="dialog-body">
        <form class="mini-form" method="POST" action="{{ route('admin.sales.coupons.store') }}">
            @csrf
            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
            <div class="form-grid">
                <div class="field"><label>Code</label><input name="code" required placeholder="SAVE10"></div>
                <div class="field"><label>Discount type</label><select name="discount_type">@foreach ($discountTypes as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach</select></div>
                <div class="field"><label>Discount value</label><input name="discount_value" type="text" inputmode="decimal" data-money-input required></div>
                <div class="field"><label>Starts at</label><input name="starts_at" type="date"></div>
                <div class="field"><label>Expires at</label><input name="expires_at" type="date"></div>
                <label><input type="checkbox" name="is_active" value="1" checked> Active</label>
            </div>
            <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Cancel</button><button class="btn primary" type="submit">Add coupon</button></div>
        </form>
    </div>
</dialog>
