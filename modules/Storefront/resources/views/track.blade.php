@extends('storefront::layout', ['title' => 'Track order | '.$store->store_name, 'robots' => 'noindex, follow'])

@php
    $currencyCode = $store->tenant?->currency_code ?? 'NGN';
    $currency = ['NGN' => '₦', 'USD' => '$', 'GBP' => '£', 'EUR' => '€', 'GHS' => 'GH₵', 'KES' => 'KSh', 'ZAR' => 'R'][$currencyCode] ?? $currencyCode;
    $money = fn (int $minor) => $currency.' '.number_format($minor / 100, 2);
    $deliveryLabels = [
        'pending' => 'Pending', 'processing' => 'Processing', 'out_for_delivery' => 'Out for delivery',
        'delivered' => 'Delivered', 'failed' => 'Failed delivery', 'returned' => 'Returned',
    ];
@endphp

@push('styles')
    <style>
        .track-wrap { max-width: 760px; margin: 0 auto; padding: 40px 0 64px; }
        .track-card { background: #fff; border: 1px solid var(--store-line); border-radius: 16px; padding: 24px; }
        .track-badges { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
        .track-badge { font-size: 12px; font-weight: 700; padding: 5px 11px; border-radius: 999px; background: var(--store-soft); color: var(--store-primary); }
        .track-items td { padding: 11px 0; border-bottom: 1px solid var(--store-line); font-size: 14px; }
        .track-timeline { list-style: none; margin: 0; padding: 0; position: relative; }
        .track-timeline li { position: relative; padding: 0 0 22px 30px; }
        .track-timeline li::before { content: ''; position: absolute; left: 8px; top: 18px; bottom: -4px; width: 2px; background: var(--store-line); }
        .track-timeline li:last-child::before { display: none; }
        .track-dot { position: absolute; left: 0; top: 2px; width: 18px; height: 18px; border-radius: 999px; border: 2px solid var(--store-line); background: #fff; }
        .track-dot.done { border-color: var(--store-primary); background: var(--store-primary); }
        .track-step-label { font-weight: 700; color: #0f1b16; }
        .track-step-note { font-size: 13px; color: var(--store-muted); }
        .track-step-at { font-size: 12px; color: var(--store-muted); margin-top: 2px; }
    </style>
@endpush

@section('content')
<section class="store-shell track-wrap">
    <h1 style="font-size: 26px; font-weight: 800; color: #0f1b16;">Track your order</h1>
    <p style="color: var(--store-muted); margin-top: 6px;">Enter the tracking reference from your order confirmation email.</p>

    <form method="GET" action="{{ $storefrontRoute($store, 'track') }}" style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 20px;">
        <input
            type="text"
            name="reference"
            value="{{ $reference }}"
            placeholder="e.g. TRK-4KQ7MPX2"
            required
            style="flex: 1 1 220px; min-width: 0; padding: 12px 14px; border: 1px solid var(--store-line); border-radius: 10px; font-size: 15px; text-transform: uppercase;"
        >
        <button type="submit" style="padding: 12px 20px; border-radius: 10px; border: none; background: var(--store-primary); color: #fff; font-weight: 700; cursor: pointer;">Track order</button>
    </form>

    @if ($searched && ! $order)
        <div class="track-card" style="margin-top: 22px; border-color: #f2c7c1; background: #fff5f4;">
            <strong style="color: #b42318;">No order found</strong>
            <p style="margin: 6px 0 0; color: var(--store-muted);">We couldn't find an order with reference <strong>{{ $reference }}</strong> for this store. Check the reference in your confirmation email and try again.</p>
        </div>
    @endif

    @if ($order)
        <div class="track-card" style="margin-top: 22px;">
            <div style="display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
                <div>
                    <div style="font-size: 12px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--store-primary);">Tracking {{ $order->tracking_reference }}</div>
                    <div style="font-size: 18px; font-weight: 800; color: #0f1b16; margin-top: 4px;">Order {{ $order->order_number }}</div>
                    <div style="font-size: 13px; color: var(--store-muted);">Placed {{ $order->created_at->format('M j, Y \a\t g:i A') }}</div>
                </div>
                <div style="text-align: right; font-size: 12px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--store-muted);">Total<br><span style="font-size: 20px; color: #0f1b16;">{{ $money((int) $order->total_minor) }}</span></div>
            </div>
            <div class="track-badges">
                <span class="track-badge">Order: {{ $order->order_status->label() }}</span>
                <span class="track-badge">Payment: {{ $order->payment_status->label() }}</span>
                <span class="track-badge">Delivery: {{ $deliveryLabels[$order->delivery_status ?? 'pending'] ?? ucfirst((string) $order->delivery_status) }}</span>
            </div>
        </div>

        <div class="track-card" style="margin-top: 16px;">
            <h2 style="margin: 0 0 12px; font-size: 16px; color: #0f1b16;">Progress</h2>
            <ul class="track-timeline">
                @foreach ($timeline as $step)
                    <li>
                        <span class="track-dot {{ $step['done'] ? 'done' : '' }}"></span>
                        <div class="track-step-label">{{ $step['label'] }}</div>
                        <div class="track-step-note">{{ $step['note'] }}</div>
                        @if ($step['at'])
                            <div class="track-step-at">{{ $step['at']->format('M j, Y \a\t g:i A') }}</div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="track-card" style="margin-top: 16px;">
            <h2 style="margin: 0 0 4px; font-size: 16px; color: #0f1b16;">Items</h2>
            <table class="track-items" width="100%" cellpadding="0" cellspacing="0">
                @foreach ($order->items as $item)
                    <tr>
                        <td>
                            <strong>{{ $item->item_name }}</strong>
                            @if ($item->custom_selections)
                                <div style="font-size: 13px; color: var(--store-muted);">{{ collect($item->custom_selections)->map(fn ($value, $key) => $key.': '.$value)->join(' · ') }}</div>
                            @endif
                            @if (data_get($item->personalization, 'requested'))
                                <div style="font-size: 13px; color: var(--store-muted);">
                                    <strong>Personalization</strong>
                                    @if (data_get($item->personalization, 'customized_text'))
                                        · Text: {{ data_get($item->personalization, 'customized_text') }}
                                    @endif
                                    @if (data_get($item->personalization, 'additional_info'))
                                        · Note: {{ data_get($item->personalization, 'additional_info') }}
                                    @endif
                                    @if (data_get($item->personalization, 'photograph_path'))
                                        · Photograph attached
                                    @endif
                                </div>
                            @endif
                            <div style="font-size: 13px; color: var(--store-muted);">{{ $item->quantity }} × {{ $money((int) $item->unit_price_minor) }}</div>
                        </td>
                        <td align="right" style="font-weight: 700;">{{ $money((int) $item->line_total_minor) }}</td>
                    </tr>
                @endforeach
            </table>
            <div style="margin-top: 14px; display: grid; gap: 4px; font-size: 14px; color: var(--store-muted);">
                <div style="display: flex; justify-content: space-between;"><span>Subtotal</span><span>{{ $money((int) $order->subtotal_minor) }}</span></div>
                @if ($order->tax_minor > 0)<div style="display: flex; justify-content: space-between;"><span>Tax</span><span>{{ $money((int) $order->tax_minor) }}</span></div>@endif
                <div style="display: flex; justify-content: space-between;"><span>Shipping</span><span>{{ $money((int) $order->shipping_minor) }}</span></div>
                <div style="display: flex; justify-content: space-between; font-weight: 800; color: #0f1b16; border-top: 1px solid var(--store-line); padding-top: 8px; margin-top: 4px;"><span>Total</span><span>{{ $money((int) $order->total_minor) }}</span></div>
            </div>
            @if ($order->delivery_address)
                <div style="margin-top: 16px; font-size: 13px; color: var(--store-muted);">
                    <strong style="color: #0f1b16;">Delivering to</strong><br>
                    {{ $order->customer?->name }}<br>
                    {{ collect([$order->delivery_address, $order->delivery_city])->filter()->join(', ') }}
                </div>
            @endif
            @if ($order->notes)
                <div style="margin-top: 16px; font-size: 13px; color: var(--store-muted);">
                    <strong style="color: #0f1b16;">Additional instructions</strong><br>
                    <span style="white-space: pre-wrap;">{{ $order->notes }}</span>
                </div>
            @endif
        </div>
    @endif
</section>
@endsection
