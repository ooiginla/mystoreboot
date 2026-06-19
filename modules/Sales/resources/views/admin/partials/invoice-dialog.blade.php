<dialog class="dialog" id="invoice-{{ $order->id }}">
    <div class="dialog-header"><div><h2 class="panel-title">Invoice {{ $order->invoice_number }}</h2><p class="subtle">{{ $order->order_number }} · {{ $order->order_date->format('M j, Y') }}</p></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button></div>
    <div class="dialog-body">
        <div class="print-document">
            <div class="print-document-header">
                <div>
                    <h1 class="print-document-title">Invoice</h1>
                    <strong>{{ $tenant->name }}</strong>
                    <div class="subtle">{{ $tenant->email ?: 'No email' }} · {{ $tenant->phone ?: 'No phone' }}</div>
                </div>
                <div style="text-align: right;">
                    <strong>{{ $order->invoice_number }}</strong>
                    <div class="subtle">Order {{ $order->order_number }}</div>
                    <div class="subtle">{{ $order->order_date->format('M j, Y') }}</div>
                </div>
            </div>
            <div class="summary-grid">
                <div class="summary-item"><span>Bill to</span><strong>{{ $order->customer?->name ?? 'Walk-In' }}</strong></div>
                <div class="summary-item"><span>Phone</span><strong>{{ $order->customer?->phone ?? 'Not set' }}</strong></div>
                <div class="summary-item"><span>Status</span><strong>{{ $order->payment_status->label() }}</strong></div>
            </div>
            <table class="table">
                <thead><tr><th>Item</th><th>Qty</th><th>Unit</th><th>Total</th></tr></thead>
                <tbody>@foreach ($order->items as $item)<tr><td>{{ $item->item_name }}</td><td>{{ $item->quantity }}</td><td>{{ $tenant->currency_code }} {{ $money($item->unit_price_minor) }}</td><td>{{ $tenant->currency_code }} {{ $money($item->line_total_minor) }}</td></tr>@endforeach</tbody>
            </table>
            <div class="summary-grid">
                <div class="summary-item"><span>Subtotal</span><strong>{{ $tenant->currency_code }} {{ $money($order->subtotal_minor) }}</strong></div>
                <div class="summary-item"><span>Tax</span><strong>{{ $tenant->currency_code }} {{ $money($order->tax_minor) }}</strong></div>
                <div class="summary-item"><span>Delivery</span><strong>{{ $tenant->currency_code }} {{ $money($order->shipping_minor) }}</strong></div>
                <div class="summary-item"><span>Discount</span><strong>{{ $tenant->currency_code }} {{ $money($order->coupon_discount_minor + $order->admin_discount_minor) }}</strong></div>
                <div class="summary-item"><span>Total</span><strong>{{ $tenant->currency_code }} {{ $money($order->total_minor) }}</strong></div>
                <div class="summary-item"><span>Balance</span><strong>{{ $tenant->currency_code }} {{ $money($order->balance_minor) }}</strong></div>
            </div>
        </div>
        <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Close</button><button class="btn primary" type="button" data-print-dialog>Print / Save PDF</button></div>
    </div>
</dialog>
