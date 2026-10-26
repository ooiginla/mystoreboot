<dialog class="dialog" id="balance-adjustment-{{ $customer->id }}" style="width:min(540px,calc(100vw - 24px));">
    <div class="dialog-header"><div><h2 class="panel-title">Adjust customer balance</h2><p class="subtle">{{ $customer->name }} currently owes {{ $tenant->currency_code }} {{ $money($customer->account_balance_minor) }}. This posts to Accounts Receivable and Opening Balance Equity.</p></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button></div>
    <div class="dialog-body">
        <form class="mini-form" method="POST" action="{{ route('admin.customers.customers.balance-adjustments.store', $customer) }}">
            @csrf <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
            @if (!empty($returnToCustomer))
                <input type="hidden" name="return_to" value="customer">
            @endif
            <div class="form-grid">
                <div class="field"><label>Date</label><input type="date" name="adjustment_date" value="{{ today()->toDateString() }}" required></div>
                <div class="field"><label>Direction</label><select name="direction" required><option value="increase">Increase amount owed</option><option value="decrease">Decrease amount owed</option></select></div>
                <div class="field full"><label>Amount</label><input name="amount" inputmode="decimal" data-money-input required></div>
                <div class="field full"><label>Reason</label><input name="reason" placeholder="Opening balance or verified correction" required></div>
                <div class="field full"><label>Notes</label><textarea name="notes"></textarea></div>
            </div>
            <div class="button-row"><button class="btn danger" type="button" data-dialog-close>Cancel</button><button class="btn primary" type="submit">Post adjustment</button></div>
        </form>
    </div>
</dialog>
