@once
    <style>
        /* Order details as a right-side drawer, so it can breathe. */
        .dialog.order-drawer {
            position: fixed;
            inset: 0 0 0 auto;
            height: 100dvh;
            max-height: 100dvh;
            width: min(580px, 100vw);
            max-width: 100vw;
            margin: 0;
            padding: 0;
            border: 0;
            border-radius: 0;
            box-shadow: -24px 0 60px rgba(15, 23, 42, .18);
            overflow: hidden;
            transform: translateX(100%);
            transition: transform .28s cubic-bezier(.22, 1, .36, 1);
        }
        .dialog.order-drawer[open] { transform: translateX(0); }
        @starting-style { .dialog.order-drawer[open] { transform: translateX(100%); } }
        .dialog.order-drawer::backdrop { background: rgba(15, 23, 42, .45); }

        .od { display: flex; flex-direction: column; height: 100%; }
        .od-head { position: sticky; top: 0; z-index: 2; padding: 18px 22px; border-bottom: 1px solid var(--line, #e4eae7); background: var(--panel, #fff); display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
        .od-head-title { font-size: 18px; font-weight: 800; margin: 0; }
        .od-head-sub { font-size: 12px; color: var(--muted, #64748b); margin: 2px 0 0; }
        .od-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
        .od-actionbar { display: flex; flex-wrap: wrap; gap: 8px; padding: 12px 22px; border-bottom: 1px solid var(--line, #e4eae7); background: var(--panel-soft, #f8fafc); }
        .od-actionbar .btn { flex: 0 0 auto; }
        .od-scroll { flex: 1 1 auto; overflow-y: auto; padding: 20px 22px 8px; }
        .od-section { margin-bottom: 22px; }
        .od-section > h3 { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--muted, #64748b); margin: 0 0 10px; }
        .od-facts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 16px; }
        .od-fact span { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: var(--muted, #64748b); }
        .od-fact strong { font-size: 14px; }
        .od-forms { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        @media (max-width: 520px) { .od-forms, .od-facts { grid-template-columns: 1fr; } }

        .od-items { display: flex; flex-direction: column; gap: 10px; }
        .od-item { display: grid; grid-template-columns: 52px 1fr auto; gap: 12px; align-items: center; padding: 10px; border: 1px solid var(--line, #e9edf1); border-radius: 12px; }
        .od-thumb { width: 52px; height: 52px; border-radius: 10px; overflow: hidden; background: var(--panel-soft, #f1f5f9); display: grid; place-items: center; flex: 0 0 auto; }
        .od-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .od-thumb span { font-weight: 800; color: var(--brand, #027a45); font-size: 15px; }
        .od-item-name { font-weight: 600; font-size: 14px; line-height: 1.3; }
        .od-item-price { text-align: right; white-space: nowrap; }
        .od-item-price strong { font-size: 14px; }
        .od-qty-badge { display: inline-flex; align-items: center; justify-content: center; min-width: 22px; height: 22px; padding: 0 6px; border-radius: 999px; background: var(--brand, #027a45); color: #fff; font-size: 12px; font-weight: 700; }
        .od-totals { display: grid; gap: 6px; }
        .od-totals .row { display: flex; justify-content: space-between; font-size: 14px; color: var(--muted, #475569); }
        .od-totals .row.grand { border-top: 1px solid var(--line, #e4eae7); padding-top: 8px; margin-top: 2px; font-size: 16px; font-weight: 800; color: var(--ink, #0f1b16); }
        .od-foot { position: sticky; bottom: 0; padding: 12px 22px; border-top: 1px solid var(--line, #e4eae7); background: var(--panel, #fff); display: flex; justify-content: flex-end; }
    </style>
@endonce

@php
    $orderItemImage = function ($item): ?string {
        $path = $item->variant?->product?->image_path;

        return $path ? '/storage/'.ltrim($path, '/') : null;
    };
@endphp

<dialog class="dialog order-drawer" id="order-view-{{ $order->id }}">
    <div class="od">
        <header class="od-head">
            <div>
                <h2 class="od-head-title">{{ $order->order_number }}</h2>
                <p class="od-head-sub">Invoice {{ $order->invoice_number }} · Receipt {{ $order->receipt_number }}@if ($order->tracking_reference) · Tracking {{ $order->tracking_reference }}@endif</p>
                <div class="od-chips">
                    <span class="sales-tag {{ $statusClass($order->order_status->value) }}">Order: {{ $order->order_status->label() }}</span>
                    <span class="sales-tag {{ $statusClass($order->payment_status->value) }}">Payment: {{ $order->payment_status->label() }}</span>
                    <span class="sales-tag {{ $statusClass($order->delivery_status ?? 'delivered') }}">Delivery: {{ $deliveryStatusLabel($order->delivery_status ?? 'delivered') }}</span>
                    <span class="sales-tag {{ $order->is_credit_sale ? 'warning' : 'success' }}">{{ $order->is_credit_sale ? 'Credit sale' : 'Standard sale' }}</span>
                </div>
            </div>
            <button class="icon-btn" type="button" data-dialog-close aria-label="Close">&times;</button>
        </header>

        <div class="od-actionbar" data-order-dialog-actions>
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

        <div class="od-scroll">
            {{-- Items — with product thumbnails so packers see exactly what was ordered --}}
            <div class="od-section">
                <h3>Items ({{ $order->items->sum('quantity') }})</h3>
                <div class="od-items">
                    @foreach ($order->items as $item)
                        @php ($thumb = $orderItemImage($item))
                        <div class="od-item">
                            <div class="od-thumb">
                                @if ($thumb)
                                    <img src="{{ $thumb }}" alt="{{ $item->item_name }}" loading="lazy">
                                @else
                                    <span>{{ \Illuminate\Support\Str::of($item->item_name)->substr(0, 2)->upper() }}</span>
                                @endif
                            </div>
                            <div>
                                <div class="od-item-name">{{ $item->item_name }}</div>
                                @if ($item->sku)<div class="cell-sub">SKU {{ $item->sku }}</div>@endif
                                @if ($item->custom_selections)<div class="cell-sub">{{ collect($item->custom_selections)->map(fn ($value, $key) => $key.': '.$value)->join(' · ') }}</div>@endif
                                @if (data_get($item->personalization, 'requested'))
                                    <div class="cell-sub"><strong>Personalization:</strong>@if (data_get($item->personalization, 'customized_text')) Text: {{ data_get($item->personalization, 'customized_text') }}@endif @if (data_get($item->personalization, 'additional_info')) · Note: {{ data_get($item->personalization, 'additional_info') }}@endif @if (data_get($item->personalization, 'photograph_path')) · <a href="{{ asset('storage/'.ltrim(data_get($item->personalization, 'photograph_path'), '/')) }}" target="_blank" rel="noopener">View photo</a>@endif</div>
                                @endif
                                <div class="cell-sub"><span class="od-qty-badge">×{{ $item->quantity }}</span> @ {{ $currencySymbol }} {{ $money($item->unit_price_minor) }} each @if ($item->quantity_returned > 0) · <span style="color: var(--warning, #b45309);">{{ $item->quantity_returned }} returned</span>@endif</div>
                            </div>
                            <div class="od-item-price"><strong>{{ $currencySymbol }} {{ $money($item->line_total_minor) }}</strong></div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Totals --}}
            <div class="od-section">
                <h3>Payment summary</h3>
                <div class="od-totals">
                    <div class="row"><span>Subtotal</span><span>{{ $currencySymbol }} {{ $money($order->subtotal_minor) }}</span></div>
                    @if ($order->tax_minor > 0)<div class="row"><span>Tax</span><span>{{ $currencySymbol }} {{ $money($order->tax_minor) }}</span></div>@endif
                    <div class="row"><span>Delivery fee</span><span>{{ $currencySymbol }} {{ $money($order->shipping_minor) }}</span></div>
                    @if ($order->gateway_charge_minor > 0)<div class="row"><span>Gateway fee</span><span>{{ $currencySymbol }} {{ $money($order->gateway_charge_minor) }}</span></div>@endif
                    @if (($order->coupon_discount_minor + $order->admin_discount_minor) > 0)<div class="row"><span>Discounts</span><span>−{{ $currencySymbol }} {{ $money($order->coupon_discount_minor + $order->admin_discount_minor) }}</span></div>@endif
                    <div class="row grand"><span>Total</span><span>{{ $currencySymbol }} {{ $money($order->total_minor) }}</span></div>
                    @if ($order->balance_minor > 0)<div class="row"><span>Balance due</span><span>{{ $currencySymbol }} {{ $money($order->balance_minor) }}</span></div>@endif
                    @if ($order->customer_credit_minor > 0)<div class="row"><span>Customer credit held</span><span>{{ $currencySymbol }} {{ $money($order->customer_credit_minor) }}</span></div>@endif
                </div>
            </div>

            {{-- Status controls --}}
            <div class="od-section">
                <h3>Update status</h3>
                <div class="od-forms">
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
            </div>

            {{-- Customer & fulfilment --}}
            <div class="od-section">
                <h3>Customer &amp; fulfilment</h3>
                <div class="od-facts">
                    <div class="od-fact"><span>Customer</span><strong>{{ $order->customer?->name ?? 'Walk-In' }}</strong></div>
                    <div class="od-fact"><span>Phone</span><strong>{{ $order->customer?->phone ?? 'Not set' }}</strong></div>
                    <div class="od-fact"><span>Branch</span><strong>{{ $order->branch?->name ?? 'Not set' }}</strong></div>
                    <div class="od-fact"><span>Cashier</span><strong>{{ $order->cashier?->name ?? 'Not set' }}</strong></div>
                    <div class="od-fact"><span>Delivery method</span><strong>{{ $order->delivery_method ?: 'No delivery' }}</strong></div>
                    <div class="od-fact"><span>Payment method</span><strong>{{ $order->payment_method ?: 'Not set' }}</strong></div>
                </div>
                @if ($order->delivery_address)
                    <div class="od-fact" style="margin-top: 12px;"><span>Delivery address</span><strong style="font-weight: 500;">{{ collect([$order->delivery_address, $order->delivery_city])->filter()->join(', ') }}</strong></div>
                @endif
                @if ($order->notes)
                    <div class="od-fact" style="margin-top: 12px;"><span>Order instructions</span><strong style="font-weight: 500; white-space: pre-wrap;">{{ $order->notes }}</strong></div>
                @endif
            </div>

            {{-- Payments --}}
            <div class="od-section">
                <div class="panel-header" style="padding: 0; border: 0; margin-bottom: 10px;">
                    <div>
                        <h3 style="margin: 0;">Payments received</h3>
                        @if ($order->balance_minor > 0 && ! $canRecordOrderPayment($order))
                            <p class="subtle" style="margin-top: 4px;">Open a till for {{ $order->branch?->name ?? 'this order branch' }} to record payment.</p>
                        @endif
                    </div>
                    @if ($order->balance_minor > 0)
                        <button class="btn primary" type="button" data-dialog-open="order-payment-{{ $order->id }}" @disabled(! $canRecordOrderPayment($order))>Record payment</button>
                    @endif
                </div>
                <table class="table">
                    <thead><tr><th>Date</th><th>Method</th><th>Amount</th><th>Reference</th><th></th></tr></thead>
                    <tbody>@forelse ($order->payments as $payment)<tr><td>{{ $payment->payment_date->format('M j, Y') }}</td><td>{{ $payment->payment_method }}</td><td>{{ $currencySymbol }} {{ $money($payment->amount_minor) }}</td><td>{{ $payment->reference_number ?: 'Not set' }}</td><td><button class="btn secondary" type="button" data-dialog-open="payment-receipt-{{ $payment->id }}">Receipt</button></td></tr>@empty<tr><td colspan="5"><div class="empty">No payments recorded.</div></td></tr>@endforelse</tbody>
                </table>
            </div>
        </div>

        <footer class="od-foot">
            <button class="btn secondary" type="button" data-dialog-close>Close</button>
        </footer>
    </div>
</dialog>
