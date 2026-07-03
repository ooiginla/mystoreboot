<dialog class="dialog" id="order-view-{{ $order->id }}">
    <div class="dialog-header">
        <div>
            <h2 class="panel-title">{{ $order->order_number }}</h2>
            <p class="subtle">Invoice {{ $order->invoice_number }} · Receipt {{ $order->receipt_number }}</p>
            <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px;">
                <span class="sales-tag {{ $statusClass($order->order_status->value) }}">Order: {{ $order->order_status->label() }}</span>
                <span class="sales-tag {{ $statusClass($order->payment_status->value) }}">Payment: {{ $order->payment_status->label() }}</span>
                <span class="sales-tag {{ $statusClass($order->delivery_status ?? 'delivered') }}">Delivery: {{ $deliveryStatusLabel($order->delivery_status ?? 'delivered') }}</span>
                <span class="sales-tag {{ $order->is_credit_sale ? 'warning' : 'success' }}">{{ $order->is_credit_sale ? 'Credit sale' : 'Paid sale' }}</span>
            </div>
        </div>
        <button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button>
    </div>
    <div class="dialog-body">
        <div class="summary-grid">
            <div class="summary-item"><span>Customer</span><strong>{{ $order->customer?->name ?? 'Walk-In' }}</strong></div>
            <div class="summary-item"><span>Phone</span><strong>{{ $order->customer?->phone ?? 'Not set' }}</strong></div>
            <div class="summary-item"><span>Branch</span><strong>{{ $order->branch?->name ?? 'Not set' }}</strong></div>
            <div class="summary-item"><span>Cashier</span><strong>{{ $order->cashier?->name ?? 'Not set' }}</strong></div>
            <div class="summary-item"><span>Delivery</span><strong>{{ $order->delivery_method ?: 'No delivery' }}</strong></div>
            <div class="summary-item"><span>Delivery status</span><strong>{{ $deliveryStatusLabel($order->delivery_status ?? 'delivered') }}</strong></div>
            <div class="summary-item"><span>Payment method</span><strong>{{ $order->payment_method ?: 'Not set' }}</strong></div>
        </div>
        <form class="mini-form" method="POST" action="{{ route('admin.sales.orders.delivery-status.update', $order) }}" style="margin-top: 16px;">
            @csrf
            <div class="form-grid">
                <div class="field"><label>Update delivery status</label><select name="delivery_status" required>@foreach ($deliveryStatuses as $value => $label)<option value="{{ $value }}" @selected(($order->delivery_status ?? 'delivered') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="field" style="align-self: end;"><button class="btn primary" type="submit">Save delivery status</button></div>
            </div>
        </form>
        <table class="table" style="margin-top: 16px;">
            <thead><tr><th>Item</th><th>SKU</th><th>Qty</th><th>Returned</th><th>Unit</th><th>Total</th></tr></thead>
            <tbody>@foreach ($order->items as $item)<tr><td>{{ $item->item_name }}</td><td>{{ $item->sku }}</td><td>{{ $item->quantity }}</td><td>{{ $item->quantity_returned }}</td><td>{{ $tenant->currency_code }} {{ $money($item->unit_price_minor) }}</td><td>{{ $tenant->currency_code }} {{ $money($item->line_total_minor) }}</td></tr>@endforeach</tbody>
        </table>
        <div class="summary-grid" style="margin-top: 16px;">
            <div class="summary-item"><span>Subtotal</span><strong>{{ $tenant->currency_code }} {{ $money($order->subtotal_minor) }}</strong></div>
            <div class="summary-item"><span>Tax</span><strong>{{ $tenant->currency_code }} {{ $money($order->tax_minor) }}</strong></div>
            <div class="summary-item"><span>Delivery fee</span><strong>{{ $tenant->currency_code }} {{ $money($order->shipping_minor) }}</strong></div>
            <div class="summary-item"><span>Discounts</span><strong>{{ $tenant->currency_code }} {{ $money($order->coupon_discount_minor + $order->admin_discount_minor) }}</strong></div>
            <div class="summary-item"><span>Total</span><strong>{{ $tenant->currency_code }} {{ $money($order->total_minor) }}</strong></div>
            <div class="summary-item"><span>Balance</span><strong>{{ $tenant->currency_code }} {{ $money($order->balance_minor) }}</strong></div>
            @if ($order->order_status === \Modules\Sales\Enums\SalesOrderStatus::Cancelled && $order->paid_minor > $order->refunded_minor)
                <div class="summary-item"><span>Customer credit held</span><strong>{{ $tenant->currency_code }} {{ $money($order->paid_minor - $order->refunded_minor) }}</strong></div>
            @endif
        </div>
        <h3 class="panel-title" style="margin-top: 18px;">Payments received</h3>
        <table class="table" style="margin-top: 8px;">
            <thead><tr><th>Date</th><th>Method</th><th>Amount</th><th>Reference</th><th></th></tr></thead>
            <tbody>@forelse ($order->payments as $payment)<tr><td>{{ $payment->payment_date->format('M j, Y') }}</td><td>{{ $payment->payment_method }}</td><td>{{ $tenant->currency_code }} {{ $money($payment->amount_minor) }}</td><td>{{ $payment->reference_number ?: 'Not set' }}</td><td><button class="btn secondary" type="button" data-dialog-open="payment-receipt-{{ $payment->id }}">Receipt</button></td></tr>@empty<tr><td colspan="5"><div class="empty">No payments recorded.</div></td></tr>@endforelse</tbody>
        </table>
        @if ($order->delivery_address)
            <div style="margin-top: 16px;"><strong>Delivery information</strong><p class="subtle">{{ $order->delivery_address }}</p></div>
        @endif
        @if ($order->notes)
            <div style="margin-top: 16px;"><strong>Note</strong><p class="subtle">{{ $order->notes }}</p></div>
        @endif
        <div class="button-row">
            @if ($order->order_status === \Modules\Sales\Enums\SalesOrderStatus::Pending)
                <form method="POST" action="{{ route('admin.sales.orders.cancel', $order) }}" onsubmit="return confirm('{{ $order->paid_minor > $order->refunded_minor ? 'Cancel this order and hold the received payment as customer credit?' : 'Cancel this pending order?' }}');">
                    @csrf
                    <button class="btn danger" type="submit">Cancel Order</button>
                </form>
            @endif
            @if ($order->order_status === \Modules\Sales\Enums\SalesOrderStatus::Cancelled && $order->paid_minor > $order->refunded_minor)
                <form method="POST" action="{{ route('admin.sales.orders.mark-refunded', $order) }}" onsubmit="return confirm('Mark this cancelled order as refunded? This will clear the customer credit and post the refund.');">
                    @csrf
                    <button class="btn danger" type="submit">Mark as Refunded</button>
                </form>
            @endif
            <button class="btn secondary" type="button" data-dialog-close>Close</button>
            <button class="btn primary" type="button" data-dialog-open="invoice-{{ $order->id }}">Generate invoice</button>
        </div>
    </div>
</dialog>
