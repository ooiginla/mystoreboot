<dialog class="dialog" id="payment-receipt-{{ $payment->id }}">
    <div class="dialog-header"><div><h2 class="panel-title">Payment receipt</h2><p class="subtle">{{ $order->order_number }} · {{ $payment->payment_date->format('M j, Y') }}</p></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button></div>
    <div class="dialog-body">
        <div class="print-document">
            <div class="print-document-header">
                <div>
                    <h1 class="print-document-title">Receipt</h1>
                    <strong>{{ $tenant->name }}</strong>
                    <div class="subtle">{{ $tenant->email ?: 'No email' }} · {{ $tenant->phone ?: 'No phone' }}</div>
                </div>
                <div style="text-align: right;">
                    <strong>{{ $order->receipt_number }}</strong>
                    <div class="subtle">Invoice {{ $order->invoice_number }}</div>
                    <div class="subtle">{{ $payment->payment_date->format('M j, Y') }}</div>
                </div>
            </div>
            <div class="summary-grid">
                <div class="summary-item"><span>Customer</span><strong>{{ $order->customer?->name ?? 'Walk-In' }}</strong></div>
                <div class="summary-item"><span>Method</span><strong>{{ $payment->payment_method }}</strong></div>
                <div class="summary-item"><span>Amount received</span><strong>{{ $currencySymbol }} {{ $money($payment->amount_minor) }}</strong></div>
                <div class="summary-item"><span>Reference</span><strong>{{ $payment->reference_number ?: 'Not set' }}</strong></div>
                <div class="summary-item"><span>Order total</span><strong>{{ $currencySymbol }} {{ $money($order->total_minor) }}</strong></div>
                <div class="summary-item"><span>Balance</span><strong>{{ $currencySymbol }} {{ $money($order->balance_minor) }}</strong></div>
            </div>
            <div>
                <h2 class="panel-title">Order items paid for</h2>
                <p class="subtle">Order {{ $order->order_number }} · Invoice {{ $order->invoice_number }}</p>
            </div>
            <table class="table">
                <thead><tr><th>Item</th><th>Qty</th><th>Unit price</th><th>Line total</th></tr></thead>
                <tbody>
                    @foreach ($order->items as $item)
                        <tr>
                            <td>{{ $item->item_name }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>{{ $currencySymbol }} {{ $money($item->unit_price_minor) }}</td>
                            <td>{{ $currencySymbol }} {{ $money($item->line_total_minor) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="summary-grid">
                <div class="summary-item"><span>Subtotal</span><strong>{{ $currencySymbol }} {{ $money($order->subtotal_minor) }}</strong></div>
                <div class="summary-item"><span>Tax</span><strong>{{ $currencySymbol }} {{ $money($order->tax_minor) }}</strong></div>
                <div class="summary-item"><span>Delivery</span><strong>{{ $currencySymbol }} {{ $money($order->shipping_minor) }}</strong></div>
                <div class="summary-item"><span>Discount</span><strong>-{{ $currencySymbol }} {{ $money($order->coupon_discount_minor + $order->admin_discount_minor) }}</strong></div>
                <div class="summary-item"><span>Total paid to date</span><strong>{{ $currencySymbol }} {{ $money($order->paid_minor) }}</strong></div>
                <div class="summary-item"><span>Outstanding balance</span><strong>{{ $currencySymbol }} {{ $money($order->balance_minor) }}</strong></div>
            </div>
            @if ($payment->notes)
                <div><strong>Notes</strong><p class="subtle">{{ $payment->notes }}</p></div>
            @endif
        </div>
        <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Close</button><button class="btn primary" type="button" data-print-dialog>Print / Save PDF</button></div>
    </div>
</dialog>
