<dialog class="dialog thermal-receipt-dialog" id="sales-receipt-{{ $order->id }}">
    <div class="dialog-header">
        <div>
            <h2 class="panel-title">Sales receipt</h2>
            <p class="subtle">{{ $order->receipt_number }} · {{ $order->order_date->format('M j, Y') }}</p>
        </div>
        <button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button>
    </div>
    <div class="dialog-body">
        <div class="thermal-receipt-paper">
            <div class="receipt-center">
                <strong class="receipt-business">{{ $tenant->name }}</strong>
                @if ($tenant->address)
                    <span>{{ $tenant->address }}</span>
                @endif
                <span>{{ $tenant->phone ?: 'No phone' }} @if ($tenant->email) · {{ $tenant->email }} @endif</span>
                <span>Receipt: {{ $order->receipt_number }}</span>
                <span>Invoice: {{ $order->invoice_number }}</span>
            </div>

            <div class="receipt-rule"></div>

            <div class="receipt-meta">
                <span>Date</span><strong>{{ $order->order_date->format('Y-m-d') }}</strong>
                <span>Time</span><strong>{{ $order->created_at?->format('H:i') ?? now()->format('H:i') }}</strong>
                <span>Served by</span><strong>{{ $order->cashier?->name ?? 'Not set' }}</strong>
                <span>Branch</span><strong>{{ $order->branch?->name ?? 'Not set' }}</strong>
                <span>Customer</span><strong>{{ $order->customer?->name ?? 'Walk-In' }}</strong>
            </div>

            <div class="receipt-rule"></div>

            <table class="thermal-items">
                <thead>
                    <tr><th>Item</th><th>Qty</th><th>Total</th></tr>
                </thead>
                <tbody>
                    @foreach ($order->items as $item)
                        <tr>
                            <td>
                                <strong>{{ $item->item_name }}</strong>
                                @if ($item->sku)
                                    <span>{{ $item->sku }}</span>
                                @endif
                                <span>@ {{ $tenant->currency_code }} {{ $money($item->unit_price_minor) }}</span>
                            </td>
                            <td>{{ $item->quantity }}</td>
                            <td>{{ $money($item->line_total_minor) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="receipt-rule"></div>

            <div class="receipt-totals">
                <span>Subtotal</span><strong>{{ $tenant->currency_code }} {{ $money($order->subtotal_minor) }}</strong>
                <span>Tax</span><strong>{{ $tenant->currency_code }} {{ $money($order->tax_minor) }}</strong>
                <span>Delivery</span><strong>{{ $tenant->currency_code }} {{ $money($order->shipping_minor) }}</strong>
                <span>Discount</span><strong>-{{ $tenant->currency_code }} {{ $money($order->coupon_discount_minor + $order->admin_discount_minor) }}</strong>
                <span class="receipt-grand-total">Total</span><strong class="receipt-grand-total">{{ $tenant->currency_code }} {{ $money($order->total_minor) }}</strong>
                <span>Paid</span><strong>{{ $tenant->currency_code }} {{ $money($order->paid_minor) }}</strong>
                <span>Balance</span><strong>{{ $tenant->currency_code }} {{ $money($order->balance_minor) }}</strong>
            </div>

            <div class="receipt-rule"></div>

            <div class="receipt-meta">
                <span>Payment</span><strong>{{ $order->payment_method ?: 'Not set' }}</strong>
                <span>Status</span><strong>{{ $order->payment_status->label() }}</strong>
                <span>Sale type</span><strong>{{ $order->is_credit_sale ? 'Credit sale' : 'Paid sale' }}</strong>
            </div>

            @if ($order->delivery_method || $order->delivery_address)
                <div class="receipt-rule"></div>
                <div>
                    <strong>Delivery</strong>
                    <p>{{ $order->delivery_method ?: 'No delivery method' }} · {{ $deliveryStatusLabel($order->delivery_status ?? 'delivered') }}</p>
                    @if ($order->delivery_address)
                        <p>{{ $order->delivery_address }}</p>
                    @endif
                </div>
            @endif

            @if ($order->notes)
                <div class="receipt-rule"></div>
                <div>
                    <strong>Note</strong>
                    <p>{{ $order->notes }}</p>
                </div>
            @endif

            <div class="receipt-rule"></div>
            <div class="receipt-center">
                <span>Thank you for your patronage.</span>
                <span>{{ now()->format('Y-m-d H:i') }}</span>
            </div>
        </div>
        <div class="button-row receipt-actions">
            <button class="btn secondary" type="button" data-dialog-close>Close</button>
            <button class="btn primary" type="button" data-print-dialog>Print receipt</button>
        </div>
    </div>
</dialog>
