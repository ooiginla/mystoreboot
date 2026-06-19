<dialog class="dialog" id="order-return-{{ $order->id }}">
    <div class="dialog-header"><div><h2 class="panel-title">Sales return</h2><p class="subtle">Return items and calculate refund for {{ $order->order_number }}.</p></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button></div>
    <div class="dialog-body">
        <form class="mini-form" method="POST" action="{{ route('admin.sales.orders.returns.store', $order) }}">
            @csrf
            <div class="form-grid">
                <div class="field"><label>Return date</label><input name="return_date" type="date" value="{{ now()->toDateString() }}" required></div>
                <div class="field full"><label>Reason</label><textarea name="reason"></textarea></div>
            </div>
            <table class="table">
                <thead><tr><th>Item</th><th>Sold</th><th>Returned</th><th>Return now</th></tr></thead>
                <tbody>
                    @foreach ($order->items as $index => $item)
                        <tr>
                            <td>{{ $item->item_name }}<input type="hidden" name="items[{{ $index }}][sales_order_item_id]" value="{{ $item->id }}"></td>
                            <td>{{ $item->quantity }}</td>
                            <td>{{ $item->quantity_returned }}</td>
                            <td><input name="items[{{ $index }}][quantity]" type="number" min="0" max="{{ $item->quantity_returnable }}" step="1" value="0"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Cancel</button><button class="btn primary" type="submit">Process return</button></div>
        </form>
    </div>
</dialog>
