<dialog class="dialog" id="view-po-{{ $po->id }}">
    <div class="dialog-header">
        <div>
            <h2 class="panel-title">{{ $po->po_number }}</h2>
            <p class="subtle">{{ $po->vendor->name }}</p>
            <div class="tag-row">
                <span class="status-tag neutral">{{ $po->vendor->name }}</span>
                <span class="status-tag {{ $poStatusClass($po->status->value) }}">{{ $po->status->label() }}</span>
                <span class="status-tag {{ $paymentStatusClass($po->payment_status->value) }}">{{ $po->payment_status->label() }}</span>
            </div>
        </div>
        <button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button>
    </div>
    <div class="dialog-body">
        <div class="summary-grid">
            <div class="summary-item"><span>Order date</span><strong>{{ $po->order_date->format('M j, Y') }}</strong></div>
            <div class="summary-item"><span>Expected delivery</span><strong>{{ $po->expected_delivery_date?->format('M j, Y') ?? 'Not set' }}</strong></div>
            <div class="summary-item"><span>Balance</span><strong>{{ $tenant->currency_code }} {{ $money($po->balance_minor) }}</strong></div>
        </div>
        <table class="table" style="margin-top: 16px;">
            <thead><tr><th>Item</th><th>Location</th><th>Ordered</th><th>Received</th><th>Quantity left</th><th>Unit cost</th><th>Line total</th></tr></thead>
            <tbody>
                @foreach ($po->items as $item)
                    <tr><td>{{ $variantLabel($item->variant) }}</td><td>{{ $item->location->name }}</td><td>{{ $item->quantity_ordered }}</td><td>{{ $item->quantity_received }}</td><td class="danger-text">{{ $item->quantity_pending }}</td><td>{{ $tenant->currency_code }} {{ $money($item->unit_cost_minor) }}</td><td>{{ $tenant->currency_code }} {{ $money($item->line_total_minor) }}</td></tr>
                @endforeach
            </tbody>
        </table>
        <div class="summary-grid" style="margin-top: 16px;">
            <div class="summary-item"><span>Product total</span><strong>{{ $tenant->currency_code }} {{ $money($po->subtotal_minor) }}</strong></div>
            <div class="summary-item"><span>Tax amount</span><strong>{{ $tenant->currency_code }} {{ $money($po->tax_minor) }}</strong></div>
            <div class="summary-item"><span>Shipping amount</span><strong>{{ $tenant->currency_code }} {{ $money($po->shipping_minor) }}</strong></div>
            <div class="summary-item"><span>Expected amount</span><strong>{{ $tenant->currency_code }} {{ $money($po->total_minor) }}</strong></div>
            <div class="summary-item"><span>Total paid</span><strong>{{ $tenant->currency_code }} {{ $money($po->paid_minor) }}</strong></div>
            <div class="summary-item"><span>Balance</span><strong>{{ $tenant->currency_code }} {{ $money($po->balance_minor) }}</strong></div>
        </div>
        <table class="table" style="margin-top: 16px;">
            <thead><tr><th>Payment date</th><th>Amount</th><th>Method</th><th>Reference</th></tr></thead>
            <tbody>
                @forelse ($po->payments as $payment)
                    <tr><td>{{ $payment->payment_date->format('M j, Y') }}</td><td>{{ $tenant->currency_code }} {{ $money($payment->amount_minor) }}</td><td>{{ $payment->payment_method ?: 'Not set' }}</td><td>{{ $payment->reference_number ?: 'Not set' }}</td></tr>
                @empty
                    <tr><td colspan="4"><div class="empty">No payments recorded for this purchase order.</div></td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="button-row">
            <button class="btn secondary" type="button" data-dialog-close>Close</button>
            @if (in_array($po->status->value, ['approved', 'partially_received', 'received'], true) && $po->balance_minor > 0)
                <button class="btn primary" type="button" data-dialog-open="payment-po-{{ $po->id }}">Record payment</button>
            @endif
        </div>
    </div>
</dialog>
