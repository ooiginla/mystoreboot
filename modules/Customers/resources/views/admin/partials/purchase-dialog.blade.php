<dialog class="dialog" id="purchase-dialog">
    <div class="dialog-header"><div><h2 class="panel-title">Record purchase history</h2><p class="subtle">Capture customer purchases for analytics and recommendations.</p></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button></div>
    <div class="dialog-body">
        <form class="mini-form" method="POST" action="{{ route('admin.customers.purchases.store') }}">
            @csrf
            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
            <div class="form-grid">
                <div class="field"><label>Customer</label><select name="customer_id" required>@foreach ($allCustomers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }} · {{ $customer->phone }}</option>@endforeach</select></div>
                <div class="field"><label>Purchase date</label><input name="purchase_date" type="date" value="{{ now()->toDateString() }}" required></div>
                <div class="field"><label>Amount</label><input name="amount" type="text" inputmode="decimal" data-money-input required></div>
                <div class="field"><label>Loyalty awarded</label><input name="loyalty_points_awarded" type="number" min="0" step="1" value="0"></div>
                <div class="field"><label>Reference</label><input name="reference_number"></div>
                <div class="field full"><label>Products/services bought</label><textarea name="product_summary"></textarea></div>
                <div class="field full"><label>Notes</label><textarea name="notes"></textarea></div>
            </div>
            <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Cancel</button><button class="btn primary" type="submit">Record purchase</button></div>
        </form>
    </div>
</dialog>
