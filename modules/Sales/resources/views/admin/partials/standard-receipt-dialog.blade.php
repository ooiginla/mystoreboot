<dialog class="dialog" id="sales-receipt-{{ $order->id }}" data-standard-sales-receipt>
    <div class="dialog-header">
        <div>
            <h2 class="panel-title">Receipt {{ $order->receipt_number }}</h2>
            <p class="subtle">{{ $order->order_number }} · {{ $order->order_date->format('M j, Y') }}</p>
        </div>
        <button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button>
    </div>
    <div class="dialog-body">
        <div class="print-document">
            <div class="print-document-header">
                <div>
                    <h1 class="print-document-title">Receipt</h1>
                    <strong>{{ $tenant->name }}</strong>
                    @if ($tenant->address)
                        <div class="subtle">{{ $tenant->address }}</div>
                    @endif
                    <div class="subtle">{{ $tenant->email ?: 'No email' }} · {{ $tenant->phone ?: 'No phone' }}</div>
                </div>
                <div style="text-align: right;">
                    <strong>{{ $order->receipt_number }}</strong>
                    <div class="subtle">Order {{ $order->order_number }}</div>
                    <div class="subtle">Invoice {{ $order->invoice_number }}</div>
                    <div class="subtle">{{ $order->order_date->format('M j, Y') }}</div>
                </div>
            </div>

            <div class="summary-grid">
                <div class="summary-item"><span>Received from</span><strong>{{ $order->customer?->name ?? 'Walk-In' }}</strong></div>
                <div class="summary-item"><span>Phone</span><strong>{{ $order->customer?->phone ?? 'Not set' }}</strong></div>
                <div class="summary-item"><span>Payment method</span><strong>{{ $order->payment_method ?: 'Not set' }}</strong></div>
                <div class="summary-item"><span>Payment status</span><strong>{{ $order->payment_status->label() }}</strong></div>
                <div class="summary-item"><span>Branch</span><strong>{{ $order->branch?->name ?? 'Not set' }}</strong></div>
                <div class="summary-item"><span>Served by</span><strong>{{ $order->cashier?->name ?? 'Not set' }}</strong></div>
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
                <div class="summary-item"><span>Order total</span><strong>{{ $currencySymbol }} {{ $money($order->total_minor) }}</strong></div>
                <div class="summary-item"><span>Amount paid</span><strong>{{ $currencySymbol }} {{ $money($order->paid_minor) }}</strong></div>
                @if ($order->change_due_minor > 0)
                    <div class="summary-item"><span>Change due</span><strong>{{ $currencySymbol }} {{ $money($order->change_due_minor) }}</strong></div>
                @endif
                <div class="summary-item"><span>Outstanding balance</span><strong>{{ $currencySymbol }} {{ $money($order->balance_minor) }}</strong></div>
            </div>

            @if ($order->delivery_method || $order->delivery_address)
                <div>
                    <strong>Delivery information</strong>
                    <p class="subtle">{{ $order->delivery_method ?: 'No delivery method' }} · {{ $deliveryStatusLabel($order->delivery_status ?? 'pending') }}</p>
                    @if ($order->delivery_address)
                        <p class="subtle">{{ $order->delivery_address }}</p>
                    @endif
                </div>
            @endif

            @if ($order->notes)
                <div><strong>Notes</strong><p class="subtle">{{ $order->notes }}</p></div>
            @endif
        </div>
        <div class="button-row">
            <button class="btn secondary" type="button" data-dialog-close>Close</button>
            <button class="btn primary" type="button" data-print-dialog>Print / Save PDF</button>
        </div>
    </div>
</dialog>
