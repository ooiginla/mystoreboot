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
                <div class="summary-item"><span>Amount received</span><strong>{{ $tenant->currency_code }} {{ $money($payment->amount_minor) }}</strong></div>
                <div class="summary-item"><span>Reference</span><strong>{{ $payment->reference_number ?: 'Not set' }}</strong></div>
                <div class="summary-item"><span>Order total</span><strong>{{ $tenant->currency_code }} {{ $money($order->total_minor) }}</strong></div>
                <div class="summary-item"><span>Balance</span><strong>{{ $tenant->currency_code }} {{ $money($order->balance_minor) }}</strong></div>
            </div>
            @if ($payment->notes)
                <div><strong>Notes</strong><p class="subtle">{{ $payment->notes }}</p></div>
            @endif
        </div>
        <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Close</button><button class="btn primary" type="button" data-print-dialog>Print / Save PDF</button></div>
    </div>
</dialog>
