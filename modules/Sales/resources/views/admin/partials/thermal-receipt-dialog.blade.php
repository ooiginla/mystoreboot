@once
<style>
    .thermal-receipt-dialog { width: min(430px, calc(100vw - 24px)); }
    .thermal-receipt-dialog .dialog-body { background: #f3f4f6; }
    .thermal-receipt-paper { width: 80mm; max-width: 100%; margin: 0 auto; background: #fff; color: #111; padding: 14px 12px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; font-size: 11px; line-height: 1.4; box-shadow: 0 1px 4px rgba(16,24,40,.12); }
    .thermal-receipt-paper strong { font-weight: 800; }
    .thermal-receipt-paper .receipt-business { display: block; font-size: 15px; text-transform: uppercase; letter-spacing: .04em; }
    .thermal-receipt-paper .receipt-center { display: grid; gap: 2px; text-align: center; }
    .thermal-receipt-paper .receipt-rule { border-top: 1px dashed #111; margin: 9px 0; }
    .thermal-receipt-paper .receipt-meta, .thermal-receipt-paper .receipt-totals { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 4px 8px; }
    .thermal-receipt-paper .receipt-meta strong, .thermal-receipt-paper .receipt-totals strong { text-align: right; }
    .thermal-receipt-paper .thermal-items { width: 100%; border-collapse: collapse; }
    .thermal-receipt-paper .thermal-items th, .thermal-receipt-paper .thermal-items td { padding: 3px 0; vertical-align: top; border: 0; color: #111; }
    .thermal-receipt-paper .thermal-items th { border-bottom: 1px dashed #111; font-size: 10px; text-align: left; }
    .thermal-receipt-paper .thermal-items th:nth-child(2), .thermal-receipt-paper .thermal-items td:nth-child(2) { text-align: center; width: 28px; }
    .thermal-receipt-paper .thermal-items th:nth-child(3), .thermal-receipt-paper .thermal-items td:nth-child(3) { text-align: right; width: 62px; }
    .thermal-receipt-paper .thermal-items td span { display: block; color: #333; font-size: 10px; }
    .thermal-receipt-paper .receipt-grand-total { font-size: 14px; text-transform: uppercase; }
    .receipt-actions { margin-top: 16px; }
    @media print {
        body:has(dialog[open]) .shell { display: block; }
        body:has(dialog[open]) .sidebar, body:has(dialog[open]) .topbar, body:has(dialog[open]) .tab-layout, body:has(dialog[open]) .sales-metrics, body:has(dialog[open]) .rpos, body:has(dialog[open]) .admin-context-bar { display: none; }
        dialog[open] { display: block; position: static; width: 100%; max-width: none; box-shadow: none; }
        dialog[open]::backdrop, dialog[open] .dialog-header .icon-btn, dialog[open] [data-print-dialog], dialog[open] [data-dialog-close] { display: none; }
        .dialog-body { max-height: none; overflow: visible; }
        dialog[open].thermal-receipt-dialog { width: 80mm; max-width: 80mm; margin: 0 auto; }
        dialog[open].thermal-receipt-dialog .dialog-header, dialog[open].thermal-receipt-dialog .receipt-actions { display: none; }
        dialog[open].thermal-receipt-dialog .dialog-body { padding: 0; background: #fff; }
        dialog[open].thermal-receipt-dialog .thermal-receipt-paper { width: 80mm; max-width: 80mm; padding: 4mm 3mm; box-shadow: none; }
    }
</style>
@endonce
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
