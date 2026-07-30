<dialog class="dialog" id="order-view-{{ $order->id }}">
    <div class="dialog-header">
        <div>
            <h2 class="panel-title">{{ $order->order_number }}</h2>
            <p class="subtle">Invoice {{ $order->invoice_number }} · Receipt {{ $order->receipt_number }}</p>
            <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px;">
                <span class="sales-tag {{ $statusClass($order->order_status->value) }}">Order: {{ $order->order_status->label() }}</span>
                <span class="sales-tag {{ $statusClass($order->payment_status->value) }}">Payment: {{ $order->payment_status->label() }}</span>
                <span class="sales-tag {{ $statusClass($order->delivery_status ?? 'delivered') }}">Delivery: {{ $deliveryStatusLabel($order->delivery_status ?? 'delivered') }}</span>
                <span class="sales-tag {{ $order->is_credit_sale ? 'warning' : 'success' }}">{{ $order->is_credit_sale ? 'Credit sale' : 'Standard sale' }}</span>
            </div>
        </div>
        <button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button>
    </div>
    <div class="dialog-body">
        <div class="order-dialog-actions" data-order-dialog-actions>
            @if (in_array($order->order_status, [\Modules\Sales\Enums\SalesOrderStatus::Pending, \Modules\Sales\Enums\SalesOrderStatus::Processing], true))
                <form method="POST" action="{{ route('admin.sales.orders.cancel', $order) }}" onsubmit="return confirm('{{ $order->paid_minor > $order->refunded_minor ? 'Cancel this order and hold the received payment as customer credit?' : 'Cancel this order?' }}');">
                    @csrf
                    <button class="btn danger order-dialog-action" type="submit">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/></svg>
                        Cancel Order
                    </button>
                </form>
            @endif
            @if ($order->customer_credit_minor > 0)
                <button class="btn danger order-dialog-action" type="button" data-dialog-open="order-refund-{{ $order->id }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6v6h6M21 18v-6h-6"/><path d="M6.5 17.5A8 8 0 0 0 19 12M17.5 6.5A8 8 0 0 0 5 12"/></svg>
                    Record Refund
                </button>
            @endif
            @if (in_array($order->order_status, [\Modules\Sales\Enums\SalesOrderStatus::Completed, \Modules\Sales\Enums\SalesOrderStatus::PartiallyReturned], true))
                <button class="btn order-return order-dialog-action" type="button" data-dialog-open="order-return-{{ $order->id }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 7 4 12l5 5"/><path d="M20 7v3a2 2 0 0 1-2 2H4"/></svg>
                    Return Order
                </button>
            @endif
            <button class="btn order-receipt order-dialog-action" type="button" data-dialog-open="sales-receipt-{{ $order->id }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V3h12v6"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v7H6z"/></svg>
                Generate Receipt
            </button>
            <button class="btn primary order-dialog-action" type="button" data-dialog-open="invoice-{{ $order->id }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3h9l3 3v15H6z"/><path d="M14 3v4h4M9 12h6M9 16h6"/></svg>
                Generate Invoice
            </button>
        </div>
        <div class="summary-grid">
            <div class="summary-item"><span>Customer</span><strong>{{ $order->customer?->name ?? 'Walk-In' }}</strong></div>
            <div class="summary-item"><span>Phone</span><strong>{{ $order->customer?->phone ?? 'Not set' }}</strong></div>
            <div class="summary-item"><span>Branch</span><strong>{{ $order->branch?->name ?? 'Not set' }}</strong></div>
            <div class="summary-item"><span>Cashier</span><strong>{{ $order->cashier?->name ?? 'Not set' }}</strong></div>
            <div class="summary-item"><span>Delivery</span><strong>{{ $order->delivery_method ?: 'No delivery' }}</strong></div>
            <div class="summary-item"><span>Delivery status</span><strong>{{ $deliveryStatusLabel($order->delivery_status ?? 'delivered') }}</strong></div>
            <div class="summary-item"><span>Payment method</span><strong>{{ $order->payment_method ?: 'Not set' }}</strong></div>
        </div>
        <div class="form-grid" style="margin-top: 16px;">
            @if (in_array($order->order_status, [\Modules\Sales\Enums\SalesOrderStatus::Pending, \Modules\Sales\Enums\SalesOrderStatus::Processing, \Modules\Sales\Enums\SalesOrderStatus::Completed], true))
                <form class="mini-form" method="POST" action="{{ route('admin.sales.orders.status.update', $order) }}" data-order-status-form data-order-number="{{ $order->order_number }}" data-current-order-status="{{ $order->order_status->value }}">
                    @csrf
                    <div class="field">
                        <label for="order-status-{{ $order->id }}">Order status</label>
                        <select id="order-status-{{ $order->id }}" name="order_status" required>
                            <option value="pending" @selected($order->order_status === \Modules\Sales\Enums\SalesOrderStatus::Pending)>Pending</option>
                            <option value="processing" @selected($order->order_status === \Modules\Sales\Enums\SalesOrderStatus::Processing)>Processing</option>
                            <option value="completed" @selected($order->order_status === \Modules\Sales\Enums\SalesOrderStatus::Completed)>Completed</option>
                        </select>
                    </div>
                    <button class="btn primary" type="submit" style="margin-top: 10px;">Update order status</button>
                </form>
            @endif
            <form class="mini-form" method="POST" action="{{ route('admin.sales.orders.delivery-status.update', $order) }}">
                @csrf
                <div class="field">
                    <label for="delivery-status-{{ $order->id }}">Delivery status</label>
                    <select id="delivery-status-{{ $order->id }}" name="delivery_status" required>
                        @foreach ($deliveryStatuses as $value => $label)
                            <option value="{{ $value }}" @selected(($order->delivery_status ?? 'delivered') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn primary" type="submit" style="margin-top: 10px;">Update delivery status</button>
            </form>
        </div>
        <table class="table" style="margin-top: 16px;">
            <thead><tr><th>Item</th><th>SKU</th><th>Qty</th><th>Returned</th><th>Unit</th><th>Total</th></tr></thead>
            <tbody>@foreach ($order->items as $item)<tr><td>{{ $item->item_name }}</td><td>{{ $item->sku }}</td><td>{{ $item->quantity }}</td><td>{{ $item->quantity_returned }}</td><td>{{ $currencySymbol }} {{ $money($item->unit_price_minor) }}</td><td>{{ $currencySymbol }} {{ $money($item->line_total_minor) }}</td></tr>@endforeach</tbody>
        </table>
        <div class="summary-grid" style="margin-top: 16px;">
            <div class="summary-item"><span>Subtotal</span><strong>{{ $currencySymbol }} {{ $money($order->subtotal_minor) }}</strong></div>
            <div class="summary-item"><span>Tax</span><strong>{{ $currencySymbol }} {{ $money($order->tax_minor) }}</strong></div>
            <div class="summary-item"><span>Delivery fee</span><strong>{{ $currencySymbol }} {{ $money($order->shipping_minor) }}</strong></div>
            <div class="summary-item"><span>Discounts</span><strong>{{ $currencySymbol }} {{ $money($order->coupon_discount_minor + $order->admin_discount_minor) }}</strong></div>
            <div class="summary-item"><span>Total</span><strong>{{ $currencySymbol }} {{ $money($order->total_minor) }}</strong></div>
            <div class="summary-item"><span>Balance</span><strong>{{ $currencySymbol }} {{ $money($order->balance_minor) }}</strong></div>
            @if ($order->customer_credit_minor > 0)
                <div class="summary-item"><span>Customer credit held</span><strong>{{ $currencySymbol }} {{ $money($order->customer_credit_minor) }}</strong></div>
            @endif
        </div>
        <div class="panel-header" style="margin-top: 18px; padding: 0; border: 0;">
            <div>
                <h3 class="panel-title">Payments received</h3>
                @if ($order->balance_minor > 0 && ! $canRecordOrderPayment($order))
                    <p class="subtle">Open a till for {{ $order->branch?->name ?? 'this order branch' }} to record payment.</p>
                @endif
            </div>
            @if ($order->balance_minor > 0)
                <button class="btn primary" type="button" data-dialog-open="order-payment-{{ $order->id }}" @disabled(! $canRecordOrderPayment($order))>Record payment</button>
            @endif
        </div>
        <table class="table" style="margin-top: 8px;">
            <thead><tr><th>Date</th><th>Method</th><th>Amount</th><th>Reference</th><th></th></tr></thead>
            <tbody>@forelse ($order->payments as $payment)<tr><td>{{ $payment->payment_date->format('M j, Y') }}</td><td>{{ $payment->payment_method }}</td><td>{{ $currencySymbol }} {{ $money($payment->amount_minor) }}</td><td>{{ $payment->reference_number ?: 'Not set' }}</td><td><button class="btn secondary" type="button" data-dialog-open="payment-receipt-{{ $payment->id }}">Receipt</button></td></tr>@empty<tr><td colspan="5"><div class="empty">No payments recorded.</div></td></tr>@endforelse</tbody>
        </table>
        @if ($order->delivery_address)
            <div style="margin-top: 16px;"><strong>Delivery information</strong><p class="subtle">{{ $order->delivery_address }}</p></div>
        @endif
        @if ($order->notes)
            <div style="margin-top: 16px;"><strong>Note</strong><p class="subtle">{{ $order->notes }}</p></div>
        @endif
        <div class="button-row">
            <button class="btn secondary" type="button" data-dialog-close>Close</button>
        </div>
    </div>
</dialog>
