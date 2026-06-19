<dialog class="dialog" id="payment-receipt-{{ $payment->id }}">
    <div class="dialog-header">
        <div>
            <h2 class="panel-title">Payment receipt</h2>
            <p class="subtle">{{ $payment->vendor->name }} · {{ $payment->payment_date->format('M j, Y') }}</p>
        </div>
        <button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button>
    </div>
    <div class="dialog-body">
        <div class="printable-receipt">
            <div>
                <strong>{{ $tenant->name }}</strong>
                <div class="subtle">Vendor payment receipt</div>
            </div>
            <div class="summary-grid">
                <div class="summary-item"><span>Vendor</span><strong>{{ $payment->vendor->name }}</strong></div>
                <div class="summary-item"><span>Purchase order</span><strong>{{ $payment->purchaseOrder?->po_number ?? 'General' }}</strong></div>
                <div class="summary-item"><span>Amount</span><strong>{{ $tenant->currency_code }} {{ $money($payment->amount_minor) }}</strong></div>
                <div class="summary-item"><span>Payment date</span><strong>{{ $payment->payment_date->format('M j, Y') }}</strong></div>
                <div class="summary-item"><span>Method</span><strong>{{ $payment->payment_method ?: 'Not set' }}</strong></div>
                <div class="summary-item"><span>Reference</span><strong>{{ $payment->reference_number ?: 'Not set' }}</strong></div>
            </div>
            @if ($payment->notes)
                <div><strong>Notes</strong><div class="subtle">{{ $payment->notes }}</div></div>
            @endif
        </div>
        <div class="button-row">
            <button class="btn secondary" type="button" data-dialog-close>Close</button>
            <button class="btn primary" type="button" data-print-dialog>Print</button>
        </div>
    </div>
</dialog>
