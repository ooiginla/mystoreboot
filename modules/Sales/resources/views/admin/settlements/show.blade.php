<x-layouts.admin title="Settlement {{ $settlement->reference }}">
    @php
        $money = fn (int $minor): string => $tenant->currency_code.' '.number_format($minor / 100, 2);
    @endphp

    <div class="topbar">
        <div>
            <div class="eyebrow">Settlement details</div>
            <h1>{{ $settlement->reference }}</h1>
            <p class="subtle">{{ $settlement->settlement_date?->format('M j, Y') ?? 'No settlement date' }}</p>
        </div>
        <div class="button-row" style="margin-top: 0;">
            <a class="btn secondary" href="{{ $backRoute ?? route('admin.sales.settlements.index', ['tenant' => $settlement->tenant_id]) }}">Back</a>
            <a class="btn accent" href="{{ route('admin.sales.settlements.download', $settlement) }}">Download Excel CSV</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert">{{ session('status') }}</div>
    @endif

    <div class="stats-grid" style="margin-bottom: 18px;">
        <div class="stat"><span class="subtle">Payments</span><strong>{{ $settlement->payment_count }}</strong></div>
        <div class="stat"><span class="subtle">Product amount</span><strong>{{ $money((int) $settlement->total_product_amount_minor) }}</strong></div>
        <div class="stat"><span class="subtle">Shipping amount</span><strong>{{ $money((int) $settlement->total_shipping_amount_minor) }}</strong></div>
        <div class="stat"><span class="subtle">Gateway charges</span><strong>{{ $money((int) $settlement->total_gateway_charge_minor) }}</strong></div>
        <div class="stat"><span class="subtle">Gateway fees</span><strong>{{ $money((int) $settlement->total_fees_minor) }}</strong></div>
        <div class="stat"><span class="subtle">Net amount</span><strong>{{ $money((int) $settlement->total_net_amount_minor) }}</strong></div>
        <div class="stat"><span class="subtle">Storeboot charges</span><strong>{{ $money((int) $settlement->storeboot_charges_minor) }}</strong></div>
        <div class="stat"><span class="subtle">Settled</span><strong>{{ $money((int) $settlement->total_settled_minor) }}</strong></div>
    </div>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Payments in this settlement</h2>
                <p class="subtle">Each row is a successful online collection tied to this settlement.</p>
            </div>
            <button class="btn secondary" type="button" data-dialog-open="settlement-summary-breakdown">Summary breakdown</button>
        </div>
        <div class="panel-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Collected</th>
                        <th>Product</th>
                        <th>Shipping</th>
                        <th>Gateway</th>
                        <th>Amount</th>
                        <th>Fees</th>
                        <th>Net</th>
                        <th>Reference</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($settlement->payments as $payment)
                        <tr>
                            <td><strong>{{ $payment->order?->order_number }}</strong><br><span class="subtle">{{ $payment->payment_method }}</span></td>
                            <td>{{ $payment->order?->customer?->name ?? 'Customer' }}<br><span class="subtle">{{ $payment->customer_email }}</span></td>
                            <td>{{ $payment->collected_at?->format('M j, Y g:ia') ?? 'Not set' }}</td>
                            <td>{{ $money((int) $payment->product_amount_minor) }}</td>
                            <td>{{ $money((int) $payment->shipping_amount_minor) }}</td>
                            <td>{{ $money((int) $payment->gateway_charge_minor) }}</td>
                            <td>{{ $money((int) $payment->amount_minor) }}</td>
                            <td>{{ $money((int) $payment->fees_minor) }}</td>
                            <td>{{ $money((int) $payment->net_amount_minor) }}</td>
                            <td>{{ $payment->provider_reference }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10"><div class="empty">No payments are tied to this settlement.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @php
        $netFeesCollectedMinor = (int) $settlement->total_fees_minor + (int) $settlement->storeboot_charges_minor;
        $grossCollectedMinor = (int) $settlement->total_product_amount_minor + (int) $settlement->total_shipping_amount_minor + (int) $settlement->total_gateway_charge_minor;
    @endphp
    <dialog class="dialog" id="settlement-summary-breakdown">
        <div class="dialog-header">
            <div>
                <h2 class="panel-title">Summary breakdown</h2>
                <p class="subtle">{{ $settlement->reference }}</p>
            </div>
            <button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button>
        </div>
        <div class="dialog-body">
            <table class="table">
                <tbody>
                    <tr><td>Product amount</td><td style="text-align: right;"><strong>{{ $money((int) $settlement->total_product_amount_minor) }}</strong></td></tr>
                    <tr><td>Shipping amount</td><td style="text-align: right;"><strong>{{ $money((int) $settlement->total_shipping_amount_minor) }}</strong></td></tr>
                    <tr><td>Gateway Charges</td><td style="text-align: right;"><strong>{{ $money((int) $settlement->total_gateway_charge_minor) }}</strong></td></tr>
                    <tr><td colspan="2"><hr></td></tr>
                    <tr><td>Net total collected</td><td style="text-align: right;"><strong>{{ $money($grossCollectedMinor) }}</strong></td></tr>
                    <tr><td colspan="2"><hr></td></tr>
                    <tr><td colspan="2"><strong>Fees</strong></td></tr>
                    <tr><td>Gateway fees</td><td style="text-align: right;"><strong>{{ $money((int) $settlement->total_fees_minor) }}</strong></td></tr>
                    <tr><td>Storeboot Charges</td><td style="text-align: right;"><strong>{{ $money((int) $settlement->storeboot_charges_minor) }}</strong></td></tr>
                    <tr><td>Net Fees Collected</td><td style="text-align: right;"><strong>{{ $money($netFeesCollectedMinor) }}</strong></td></tr>
                    <tr><td colspan="2"><hr></td></tr>
                    <tr><td>Net total collected</td><td style="text-align: right;"><strong>{{ $money($grossCollectedMinor) }}</strong></td></tr>
                    <tr><td>- Net Fees Collected</td><td style="text-align: right;"><strong>({{ $money($netFeesCollectedMinor) }})</strong></td></tr>
                    <tr><td>Net Settled Amount</td><td style="text-align: right;"><strong>{{ $money((int) $settlement->total_settled_minor) }}</strong></td></tr>
                </tbody>
            </table>
            <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Close</button></div>
        </div>
    </dialog>
</x-layouts.admin>
