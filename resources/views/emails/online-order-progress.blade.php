<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <title>Order update {{ $order->order_number }}</title>
</head>
@php
    $currencyCode = $store->tenant?->currency_code ?? 'NGN';
    $currency = ['NGN' => '₦', 'USD' => '$', 'GBP' => '£', 'EUR' => '€', 'GHS' => 'GH₵', 'KES' => 'KSh', 'ZAR' => 'R'][$currencyCode] ?? $currencyCode;
    $money = fn (int $minor) => $currency.' '.number_format($minor / 100, 2);

    $hex = ltrim($store->theme_primary_color ?: '#006554', '#');
    if (strlen($hex) === 3) { $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2]; }
    $hex = preg_match('/^[0-9a-fA-F]{6}$/', $hex) ? $hex : '006554';
    $r = hexdec(substr($hex, 0, 2)); $g = hexdec(substr($hex, 2, 2)); $b = hexdec(substr($hex, 4, 2));
    $lum = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;
    $primary = '#'.$hex;
    $onPrimary = $lum > 0.62 ? '#10241c' : '#ffffff';
    $softTint = 'rgba('.$r.','.$g.','.$b.',0.08)';
    $logoUrl = $store->logo_path ? url('/storage/'.ltrim($store->logo_path, '/')) : null;
    $trackUrl = \Modules\Storefront\Support\StorefrontUrl::route($store, 'track', ['reference' => $order->tracking_reference]);

    $isOutForDelivery = $progress === \App\Mail\OnlineOrderProgressMail::OUT_FOR_DELIVERY;
    $eyebrow = $isOutForDelivery ? 'Out for delivery' : 'Order processing';
    $heading = $isOutForDelivery ? 'Your order is on its way 🚚' : 'We’re preparing your order 📦';
    $message = $isOutForDelivery
        ? 'Your order has left the store and is now on its way to you. Please keep your phone nearby in case the delivery team needs to reach you.'
        : 'Good news—your order is now being prepared. We’ll let you know again when it leaves for delivery.';
    $preheader = $isOutForDelivery
        ? 'Your order '.$order->order_number.' is now out for delivery.'
        : 'Your order '.$order->order_number.' is now being prepared.';

    $itemImageUrl = function ($item): ?string {
        $variant = $item->variant;
        $product = $variant?->product;
        $path = $variant?->image_path ?: $product?->image_path ?: $product?->images?->first()?->image_path;

        return $path
            ? url('/storage/'.ltrim($path, '/'))
            : $product?->externalImages?->first()?->url;
    };
@endphp
<body style="margin:0; padding:0; width:100%; background-color:#f4f7f5; -webkit-font-smoothing:antialiased; -webkit-text-size-adjust:100%;">
<div style="display:none; max-height:0; overflow:hidden; opacity:0;">{{ $preheader }}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f7f5;">
    <tr>
        <td align="center" style="padding:26px 12px;">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:100%; background-color:#ffffff; border-radius:16px; overflow:hidden; border:1px solid #e4eae7;">
                <tr>
                    <td align="center" style="padding:28px 32px 18px;">
                        @if ($logoUrl)
                            <img src="{{ $logoUrl }}" alt="{{ $store->store_name }}" height="46" style="height:46px; width:auto; max-width:230px; display:inline-block;">
                        @else
                            <div style="font-family:Arial,Helvetica,sans-serif; font-size:22px; font-weight:800; color:{{ $primary }};">{{ $store->store_name }}</div>
                        @endif
                    </td>
                </tr>
                <tr><td style="height:4px; background-color:{{ $primary }}; line-height:4px; font-size:4px;">&nbsp;</td></tr>

                <tr>
                    <td style="padding:32px 32px 0;">
                        <div style="font-family:Arial,Helvetica,sans-serif; font-size:12px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:{{ $primary }};">{{ $eyebrow }}</div>
                        <h1 style="margin:9px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:25px; line-height:1.25; color:#0f1b16;">{{ $heading }}</h1>
                        <p style="margin:12px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:15px; line-height:1.65; color:#475569;">Hi{{ $order->customer?->first_name ? ' '.$order->customer->first_name : '' }}, {{ $message }}</p>
                    </td>
                </tr>

                <tr>
                    <td style="padding:20px 32px 0;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:{{ $softTint }}; border-radius:12px;">
                            <tr>
                                <td style="padding:15px 18px; font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:1.7; color:#3f4a52;">
                                    <strong style="color:#0f1b16; font-size:14px;">Order {{ $order->order_number }}</strong><br>
                                    Current status: <strong style="color:{{ $primary }};">{{ $eyebrow }}</strong><br>
                                    Total: <strong style="color:#0f1b16;">{{ $money((int) $order->total_minor) }}</strong>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:24px 32px 0;">
                        <h2 style="margin:0 0 8px; font-family:Arial,Helvetica,sans-serif; font-size:16px; color:#0f1b16;">Order items</h2>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            @foreach ($order->items as $item)
                                @php($itemImage = $itemImageUrl($item))
                                <tr>
                                    <td width="84" valign="top" style="width:84px; padding:11px 12px 11px 0; border-bottom:1px solid #edf1ef;">
                                        @if ($itemImage)
                                            <img src="{{ $itemImage }}" alt="{{ $item->item_name }}" width="72" height="72" style="display:block; width:72px; height:72px; border:1px solid #e4eae7; border-radius:10px; object-fit:cover;">
                                        @else
                                            <div style="width:72px; height:72px; border:1px solid #e4eae7; border-radius:10px; background-color:#f4f7f5; text-align:center; line-height:72px; font-family:Arial,Helvetica,sans-serif; font-size:22px; font-weight:800; color:{{ $primary }};">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($item->item_name, 0, 1)) }}</div>
                                        @endif
                                    </td>
                                    <td valign="top" style="padding:11px 0; border-bottom:1px solid #edf1ef; font-family:Arial,Helvetica,sans-serif; font-size:14px; line-height:1.45; color:#0f1b16;">
                                        <strong>{{ $item->item_name }}</strong><br>
                                        <span style="font-size:13px; color:#64748b;">{{ $item->quantity }} × {{ $money((int) $item->unit_price_minor) }}</span>
                                    </td>
                                    <td align="right" valign="top" style="padding:11px 0; border-bottom:1px solid #edf1ef; font-family:Arial,Helvetica,sans-serif; font-size:14px; font-weight:700; color:#0f1b16; white-space:nowrap;">{{ $money((int) $item->line_total_minor) }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:26px 32px 0;">
                        <a href="{{ $trackUrl }}" style="display:inline-block; background-color:{{ $primary }}; color:{{ $onPrimary }}; font-family:Arial,Helvetica,sans-serif; font-size:14px; font-weight:700; text-decoration:none; padding:13px 24px; border-radius:10px;">Track your order →</a>
                        <p style="margin:12px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:12px; line-height:1.6; color:#94a3b8;">Tracking reference: <strong>{{ $order->tracking_reference }}</strong></p>
                    </td>
                </tr>

                <tr>
                    <td style="padding:24px 32px 30px;">
                        <div style="height:1px; background:#e4eae7;"></div>
                        <p style="margin:16px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:1.6; color:#64748b;">Questions about your order? Reply to this email{{ $store->site_email ? ', or contact '.$store->site_email : '' }}.</p>
                        <p style="margin:14px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; font-weight:800; color:{{ $primary }};">{{ $store->store_name }}</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
