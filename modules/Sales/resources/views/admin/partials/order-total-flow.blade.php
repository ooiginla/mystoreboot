@once
    <style>
        .receipt-total-flow { width: min(100%, 430px); margin: 18px 0 18px auto; border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); padding: 10px 0; }
        .receipt-total-row { display: grid; grid-template-columns: 20px minmax(0, 1fr) auto; gap: 8px; align-items: baseline; padding: 5px 2px; color: var(--ink-soft); font-size: 14px; }
        .receipt-total-operator { color: var(--muted); text-align: center; font-weight: 800; }
        .receipt-total-amount { color: var(--ink); text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .receipt-total-row.is-total { margin-top: 5px; border-top: 1px solid var(--line); padding-top: 10px; color: var(--ink); font-weight: 800; }
        .receipt-total-row.is-balance { margin-top: 5px; border-top: 1px dashed var(--line); padding-top: 10px; color: var(--ink); font-size: 16px; font-weight: 850; }
    </style>
@endonce

<div class="receipt-total-flow" aria-label="Order total calculation">
    @if ($order->subtotal_minor > 0)<div class="receipt-total-row"><span class="receipt-total-operator"></span><span>Subtotal</span><strong class="receipt-total-amount">{{ $currencySymbol }} {{ $money($order->subtotal_minor) }}</strong></div>@endif
    @if ($order->tax_minor > 0)<div class="receipt-total-row"><span class="receipt-total-operator">+</span><span>Tax</span><strong class="receipt-total-amount">{{ $currencySymbol }} {{ $money($order->tax_minor) }}</strong></div>@endif
    @if ($order->coupon_discount_minor > 0)<div class="receipt-total-row"><span class="receipt-total-operator">−</span><span>Coupon</span><strong class="receipt-total-amount">{{ $currencySymbol }} {{ $money($order->coupon_discount_minor) }}</strong></div>@endif
    @if ($order->admin_discount_minor > 0)<div class="receipt-total-row"><span class="receipt-total-operator">−</span><span>Discount</span><strong class="receipt-total-amount">{{ $currencySymbol }} {{ $money($order->admin_discount_minor) }}</strong></div>@endif
    @if ($order->shipping_minor > 0)<div class="receipt-total-row"><span class="receipt-total-operator">+</span><span>Delivery amount</span><strong class="receipt-total-amount">{{ $currencySymbol }} {{ $money($order->shipping_minor) }}</strong></div>@endif
    @if ($order->gateway_charge_minor > 0)<div class="receipt-total-row"><span class="receipt-total-operator">+</span><span>Gateway fee</span><strong class="receipt-total-amount">{{ $currencySymbol }} {{ $money($order->gateway_charge_minor) }}</strong></div>@endif
    @if ($order->total_minor > 0)<div class="receipt-total-row is-total"><span class="receipt-total-operator">=</span><span>Order total</span><strong class="receipt-total-amount">{{ $currencySymbol }} {{ $money($order->total_minor) }}</strong></div>@endif
    @if ($order->paid_minor > 0)<div class="receipt-total-row"><span class="receipt-total-operator">−</span><span>Amount paid</span><strong class="receipt-total-amount">{{ $currencySymbol }} {{ $money($order->paid_minor) }}</strong></div>@endif
    <div class="receipt-total-row is-balance"><span class="receipt-total-operator">=</span><span>Outstanding balance</span><strong class="receipt-total-amount">{{ $currencySymbol }} {{ $money($order->balance_minor) }}</strong></div>
    @if ($order->change_due_minor > 0)
        <div class="receipt-total-row"><span class="receipt-total-operator"></span><span>Change due</span><strong class="receipt-total-amount">{{ $currencySymbol }} {{ $money($order->change_due_minor) }}</strong></div>
    @endif
</div>
