<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <title>Order confirmation {{ $order->order_number }}</title>
</head>
<body style="margin:0; padding:0; width:100%; background-color:#f4f7f5; -webkit-font-smoothing:antialiased; -webkit-text-size-adjust:100%;">
@php
    $currencyCode = $store->tenant?->currency_code ?? 'NGN';
    $currency = ['NGN' => '₦', 'USD' => '$', 'GBP' => '£', 'EUR' => '€', 'GHS' => 'GH₵', 'KES' => 'KSh', 'ZAR' => 'R'][$currencyCode] ?? $currencyCode;
    $money = fn (int $minor) => $currency.' '.number_format($minor / 100, 2);
@endphp
<div style="display:none; max-height:0; overflow:hidden; opacity:0;">We received order {{ $order->order_number }} from {{ $store->store_name }}.</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f7f5;">
    <tr>
        <td align="center" style="padding:28px 12px;">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:100%; background-color:#ffffff; border-radius:16px; overflow:hidden; border:1px solid #e4eae7;">
                <tr>
                    <td style="background-color:#0a1712; padding:26px 32px;">
                        <div style="font-family:Arial,Helvetica,sans-serif; font-size:21px; font-weight:800; color:#ffffff;">{{ $store->store_name }}</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:34px 32px 0;">
                        <div style="font-family:Arial,Helvetica,sans-serif; font-size:12px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#027a45;">Order received</div>
                        <h1 style="margin:10px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:26px; line-height:1.25; color:#0f1b16;">Thank you, {{ $order->customer?->first_name }}.</h1>
                        <p style="margin:14px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:15px; line-height:1.65; color:#475569;">We have received your order. We’ll contact you as it moves through processing and delivery.</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:22px 32px 0;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#ecfdf3; border:1px solid #d1fadf; border-radius:12px;">
                            <tr>
                                <td style="padding:15px 17px; font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:1.7; color:#3f6b57;">
                                    <strong style="color:#027a45;">Order {{ $order->order_number }}</strong><br>
                                    Placed {{ $order->created_at->format('M j, Y \a\t g:i A') }}<br>
                                    Order: {{ $order->order_status->label() }} · Payment: {{ $order->payment_status->label() }}<br>
                                    Tracking reference: <strong style="letter-spacing:.03em; color:#0f1b16;">{{ $order->tracking_reference }}</strong>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 32px 0;">
                        <a href="{{ \Modules\Storefront\Support\StorefrontUrl::route($store, 'track', ['reference' => $order->tracking_reference]) }}"
                           style="display:inline-block; background-color:#027a45; color:#ffffff; font-family:Arial,Helvetica,sans-serif; font-size:14px; font-weight:700; text-decoration:none; padding:12px 22px; border-radius:10px;">
                            Track your order
                        </a>
                        <div style="margin-top:8px; font-family:Arial,Helvetica,sans-serif; font-size:12px; color:#64748b;">Or go to the store and enter <strong>{{ $order->tracking_reference }}</strong> under “Track order”.</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:26px 32px 0;">
                        <h2 style="margin:0 0 12px; font-family:Arial,Helvetica,sans-serif; font-size:17px; color:#0f1b16;">Order details</h2>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            @foreach ($order->items as $item)
                                <tr>
                                    <td style="padding:11px 0; border-bottom:1px solid #edf1ef; font-family:Arial,Helvetica,sans-serif; font-size:14px; line-height:1.45; color:#0f1b16;">
                                        <strong>{{ $item->item_name }}</strong>
                                        @if ($item->custom_selections)<br><span style="font-size:12px; color:#64748b;">{{ collect($item->custom_selections)->map(fn ($value, $key) => $key.': '.$value)->join(' · ') }}</span>@endif
                                        @if (data_get($item->personalization, 'requested'))
                                            <br>
                                            <span style="font-size:12px; color:#64748b;">
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
                                            </span>
                                        @endif
                                        @if ($item->sku)<br><span style="font-size:12px; color:#64748b;">SKU {{ $item->sku }}</span>@endif
                                        <br><span style="font-size:13px; color:#64748b;">{{ $item->quantity }} × {{ $money((int) $item->unit_price_minor) }}</span>
                                    </td>
                                    <td align="right" style="padding:11px 0; border-bottom:1px solid #edf1ef; font-family:Arial,Helvetica,sans-serif; font-size:14px; font-weight:700; color:#0f1b16;">{{ $money((int) $item->line_total_minor) }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:18px 32px 0;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#475569;">
                            <tr><td style="padding:5px 0;">Subtotal</td><td align="right">{{ $money((int) $order->subtotal_minor) }}</td></tr>
                            @if ($order->tax_minor > 0)<tr><td style="padding:5px 0;">Tax</td><td align="right">{{ $money((int) $order->tax_minor) }}</td></tr>@endif
                            <tr><td style="padding:5px 0;">Shipping</td><td align="right">{{ $money((int) $order->shipping_minor) }}</td></tr>
                            @if (($order->gateway_charge_minor ?? 0) > 0)<tr><td style="padding:5px 0;">Online payment charge</td><td align="right">{{ $money((int) $order->gateway_charge_minor) }}</td></tr>@endif
                            <tr><td style="padding:10px 0 0; border-top:1px solid #dfe6e2; font-size:17px; font-weight:800; color:#0f1b16;">Total</td><td align="right" style="padding:10px 0 0; border-top:1px solid #dfe6e2; font-size:17px; font-weight:800; color:#027a45;">{{ $money((int) $order->total_minor) }}</td></tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:25px 32px 0;">
                        <h2 style="margin:0 0 8px; font-family:Arial,Helvetica,sans-serif; font-size:17px; color:#0f1b16;">Shipping information</h2>
                        <p style="margin:0; font-family:Arial,Helvetica,sans-serif; font-size:14px; line-height:1.65; color:#475569;">
                            {{ $order->customer?->name }}<br>
                            {{ $order->customer?->phone }}<br>
                            {{ collect([$order->delivery_address, $order->delivery_city])->filter()->join(', ') }}<br>
                            <strong>Method:</strong> {{ $order->delivery_method ?: 'Standard shipping' }}
                        </p>
                        @if ($order->notes)
                            <p style="margin:14px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; line-height:1.65; color:#475569;">
                                <strong>Additional instructions:</strong><br>{!! nl2br(e($order->notes)) !!}
                            </p>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:26px 32px 32px;">
                        <div style="height:1px; background:#e4eae7;"></div>
                        <p style="margin:18px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:1.6; color:#64748b;">
                            Questions about your order? Contact {{ $store->site_email ?: config('mail.from.address') }}@if ($store->store_phone), or call {{ $store->store_phone }}@endif.
                        </p>
                        <p style="margin:14px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; font-weight:800; color:#0f1b16;">{{ $store->store_name }}</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
