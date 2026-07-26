@php
    $vendorOrders = $allPurchaseOrders->where('vendor_id', $vendor->id);
    $vendorPayments = $payments->where('vendor_id', $vendor->id);
    $suppliedMinor = $vendorOrders->sum('total_minor');
    $paidMinor = $vendorOrders->sum('paid_minor');
    $outstandingMinor = $vendorOrders->sum(fn ($order): int => $order->balance_minor);
@endphp

<dialog class="dialog" id="vendor-view-{{ $vendor->id }}">
    <div class="dialog-header">
        <div>
            <h2 class="panel-title">{{ $vendor->name }}</h2>
            <p class="subtle">{{ $vendor->contact_name ?: 'No contact' }} · {{ $vendor->phone ?: 'No phone' }} · {{ $vendor->email ?: 'No email' }}</p>
        </div>
        <button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button>
    </div>
    <div class="dialog-body">
        <div class="summary-grid">
            <div class="summary-item"><span>Goods supplied</span><strong>{{ $tenant->currency_code }} {{ $money($suppliedMinor) }}</strong></div>
            <div class="summary-item"><span>Payments made</span><strong>{{ $tenant->currency_code }} {{ $money($paidMinor) }}</strong></div>
            <div class="summary-item"><span>Outstanding</span><strong>{{ $tenant->currency_code }} {{ $money($outstandingMinor) }}</strong></div>
        </div>

        <div class="summary-grid" style="margin-top: 16px;">
            <div class="summary-item"><span>Code</span><strong>{{ $vendor->code ?: 'Not set' }}</strong></div>
            <div class="summary-item"><span>Tax number</span><strong>{{ $vendor->tax_number ?: 'Not set' }}</strong></div>
            <div class="summary-item"><span>Lead time</span><strong><span class="vendor-lead-tag">{{ $vendor->lead_time_days }}-day lead</span></strong></div>
        </div>

        <nav class="vendor-dialog-tabs" role="tablist" aria-label="Vendor sections">
            <a href="#vendor-orders-{{ $vendor->id }}" class="active" role="tab" data-local-tab-target="vendor-orders-{{ $vendor->id }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3h12v18H6zM9 8h6M9 12h6M9 16h4"/></svg>
                <span>Purchase orders</span>
            </a>
            <a href="#vendor-payments-{{ $vendor->id }}" role="tab" data-local-tab-target="vendor-payments-{{ $vendor->id }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18M7 15h3"/></svg>
                <span>Payments</span>
            </a>
            <a href="#vendor-bank-accounts-{{ $vendor->id }}" role="tab" data-local-tab-target="vendor-bank-accounts-{{ $vendor->id }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 10 9-6 9 6M5 10v8M9 10v8M15 10v8M19 10v8M3 20h18"/></svg>
                <span>Bank accounts</span>
                <span class="badge neutral">{{ $vendor->bankAccounts->count() }}</span>
            </a>
        </nav>

        <section data-local-tab-panel id="vendor-orders-{{ $vendor->id }}" style="margin-top: 16px;">
            <table class="table">
                <thead><tr><th>PO</th><th>Date</th><th>PO status</th><th>Payment</th><th>Total</th><th>Balance</th></tr></thead>
                <tbody>
                    @forelse ($vendorOrders as $order)
                        <tr>
                            <td><button class="link-button" type="button" data-dialog-open="view-po-{{ $order->id }}">{{ $order->po_number }}</button></td>
                            <td>{{ $order->order_date->format('M j, Y') }}</td>
                            <td><span class="status-tag {{ $poStatusClass($order->status->value) }}">{{ $order->status->label() }}</span></td>
                            <td><span class="status-tag {{ $paymentStatusClass($order->payment_status->value) }}">{{ $order->payment_status->label() }}</span></td>
                            <td>{{ $tenant->currency_code }} {{ $money($order->total_minor) }}</td>
                            <td>{{ $tenant->currency_code }} {{ $money($order->balance_minor) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty">No purchase orders for this vendor yet.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section data-local-tab-panel id="vendor-payments-{{ $vendor->id }}" style="margin-top: 16px;" hidden>
            <table class="table">
                <thead><tr><th>Date</th><th>Purchase order</th><th>Amount</th><th>Method</th><th>Reference</th></tr></thead>
                <tbody>
                    @forelse ($vendorPayments as $payment)
                        <tr>
                            <td>{{ $payment->payment_date->format('M j, Y') }}</td>
                            <td>
                                @if ($payment->purchaseOrder)
                                    <button class="link-button" type="button" data-dialog-open="view-po-{{ $payment->purchaseOrder->id }}">{{ $payment->purchaseOrder->po_number }}</button>
                                @else
                                    General
                                @endif
                            </td>
                            <td>{{ $tenant->currency_code }} {{ $money($payment->amount_minor) }}</td>
                            <td>{{ $payment->payment_method ?: 'Not set' }}</td>
                            <td>{{ $payment->reference_number ?: 'Not set' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty">No payments for this vendor yet.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section data-local-tab-panel id="vendor-bank-accounts-{{ $vendor->id }}" style="margin-top: 16px;" hidden>
            <table class="table">
                <thead><tr><th>Bank</th><th>Account name</th><th>Account number</th><th>Currency</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($vendor->bankAccounts as $account)
                        <tr>
                            <td>{{ $account->bank_name }}</td>
                            <td>{{ $account->account_name }}</td>
                            <td>{{ $account->account_number }}</td>
                            <td>{{ $account->currency_code ?: $tenant->currency_code }}</td>
                            <td>
                                @if ($account->is_primary)
                                    <span class="status-tag success">Primary</span>
                                @else
                                    <span class="status-tag neutral">Additional</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty">No bank accounts have been added for this vendor.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <div class="button-row">
            <button class="btn secondary" type="button" data-dialog-close>Close</button>
            <button class="btn primary" type="button" data-dialog-open="vendor-edit-{{ $vendor->id }}">Edit vendor</button>
        </div>
    </div>
</dialog>
