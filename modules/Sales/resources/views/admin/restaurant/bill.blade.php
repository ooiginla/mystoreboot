@php
    $money = fn (?int $minor): string => number_format(($minor ?? 0) / 100, 2);
    $qty = fn ($v): string => \Modules\Inventory\Support\Quantity::format($v ?? 0);
    $live = $check->items->whereNull('voided_at');
    $rate = (float) $check->service_charge_rate;
    $isFinal = $check->bill_printed_at !== null || $check->check_status?->value === 'settled';
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bill · Table {{ $check->table?->name }} · {{ $check->order_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f2f4f7; color: #101828; font: 14px/1.45 -apple-system, "Segoe UI", Roboto, Arial, sans-serif; }
        .toolbar { max-width: 420px; margin: 18px auto 0; display: flex; justify-content: space-between; align-items: center; gap: 10px; }
        .toolbar a { color: #344054; font-weight: 700; text-decoration: none; }
        .toolbar button { padding: 10px 18px; border: 0; border-radius: 9px; background: #101828; color: #fff; font-weight: 800; cursor: pointer; }
        .bill { max-width: 420px; margin: 12px auto 30px; background: #fff; padding: 26px 24px; border-radius: 12px; box-shadow: 0 4px 18px rgba(16,24,40,.08); }
        h1 { margin: 0; font-size: 1.3rem; text-align: center; }
        .sub { text-align: center; color: #667085; font-size: .85rem; }
        .draft { margin: 12px 0 0; text-align: center; padding: 6px; border: 2px dashed #f79009; color: #b54708; font-weight: 800; letter-spacing: .06em; }
        dl { display: grid; grid-template-columns: auto 1fr; gap: 3px 12px; margin: 16px 0; font-size: .85rem; }
        dt { color: #667085; } dd { margin: 0; font-weight: 700; text-align: right; }
        table { width: 100%; border-collapse: collapse; border-top: 1px dashed #d0d5dd; border-bottom: 1px dashed #d0d5dd; }
        td { padding: 7px 0; vertical-align: top; }
        td.amt { text-align: right; white-space: nowrap; font-weight: 700; }
        .mod { font-size: .78rem; color: #475467; padding-left: 14px; }
        .row { display: flex; justify-content: space-between; padding: 3px 0; }
        .row.total { font-size: 1.25rem; font-weight: 900; border-top: 2px solid #101828; margin-top: 8px; padding-top: 8px; }
        .row.due { font-weight: 900; }
        .thanks { text-align: center; margin: 18px 0 0; color: #667085; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .bill { box-shadow: none; margin: 0 auto; border-radius: 0; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ $checkUrl }}">← Back to the check</a>
        <button type="button" onclick="window.print()">Print</button>
    </div>

    <main class="bill">
        <h1>{{ $tenant->name }}</h1>
        @if ($check->branch)<div class="sub">{{ $check->branch->name }}</div>@endif
        @unless ($isFinal)
            {{-- Changed since it was last printed — the guest must not pay from this. --}}
            <div class="draft">DRAFT — NOT THE FINAL BILL</div>
        @endunless

        <dl>
            <dt>Table</dt><dd>{{ $check->table?->name ?? '—' }}{{ $check->serviceArea ? ' · '.$check->serviceArea->name : '' }}</dd>
            <dt>Bill</dt><dd>{{ $check->order_number }}</dd>
            @if ($check->server)<dt>Served by</dt><dd>{{ $check->server->name }}</dd>@endif
            <dt>Guests</dt><dd>{{ $check->cover_count }}</dd>
            <dt>Date</dt><dd>{{ ($check->bill_printed_at ?? now())->format('d M Y, H:i') }}</dd>
        </dl>

        <table>
            @foreach ($live as $item)
                <tr>
                    <td>
                        {{ $qty($item->quantity) }} × {{ $item->item_name }}
                        @foreach ($item->modifiers as $modifier)
                            <div class="mod">{{ $modifier->isRemoval() ? '–' : '+' }} {{ $modifier->option_name }}</div>
                        @endforeach
                    </td>
                    <td class="amt">{{ $money((int) round((float) $item->quantity * (int) $item->unit_price_minor)) }}</td>
                </tr>
            @endforeach
        </table>

        <div style="margin-top:10px;">
            <div class="row"><span>Subtotal</span><span>{{ $money($check->subtotal_minor) }}</span></div>
            @if ((int) $check->tax_minor > 0)
                <div class="row"><span>Tax</span><span>{{ $money($check->tax_minor) }}</span></div>
            @endif
            @if ((int) $check->service_charge_minor > 0)
                <div class="row"><span>Service charge ({{ rtrim(rtrim(number_format($rate, 2), '0'), '.') }}%)</span><span>{{ $money($check->service_charge_minor) }}</span></div>
            @endif
            <div class="row total"><span>Total</span><span>{{ $tenant->currency_code }} {{ $money($check->total_minor) }}</span></div>
            @if ((int) $check->paid_minor > 0)
                @foreach ($check->payments as $payment)
                    <div class="row"><span>Paid · {{ $payment->payment_method }}</span><span>{{ $money($payment->amount_minor) }}</span></div>
                @endforeach
                <div class="row due"><span>Balance</span><span>{{ $tenant->currency_code }} {{ $money($check->balance_minor) }}</span></div>
            @endif
        </div>

        <p class="thanks">Thank you for dining with us.</p>
    </main>

    @if ($autoPrint)
        <script>window.addEventListener('load', function () { window.print(); });</script>
    @endif
</body>
</html>
