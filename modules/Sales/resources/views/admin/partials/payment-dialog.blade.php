<dialog class="dialog" id="order-payment-{{ $order->id }}">
    <div class="dialog-header"><div><h2 class="panel-title">Add payment</h2><p class="subtle">{{ $order->order_number }} balance: {{ $tenant->currency_code }} {{ $money($order->balance_minor) }}</p></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button></div>
    <div class="dialog-body">
        <form class="mini-form" method="POST" action="{{ route('admin.sales.orders.payments.store', $order) }}">
            @csrf
            @if ($activeTill)
                <input type="hidden" name="sales_till_session_id" value="{{ $activeTill->id }}">
            @endif
            <div class="form-grid">
                <div class="field"><label>Payment date</label><input name="payment_date" type="date" value="{{ now()->toDateString() }}" required></div>
                <div class="field"><label>Payment method</label><select name="payment_method">@foreach ($paymentMethods as $method)<option value="{{ $method }}">{{ $method }}</option>@endforeach</select></div>
                <div class="field"><label>Amount</label><input name="amount" type="text" inputmode="decimal" data-money-input value="{{ $money($order->balance_minor) }}" required></div>
                <div class="field"><label>Reference</label><input name="reference_number"></div>
                <div class="field full"><label>Notes</label><textarea name="notes"></textarea></div>
            </div>
            <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Cancel</button><button class="btn primary" type="submit" @disabled(! $activeTill || $activeTill->branch_id !== $order->branch_id)>Record payment</button></div>
        </form>
    </div>
</dialog>
