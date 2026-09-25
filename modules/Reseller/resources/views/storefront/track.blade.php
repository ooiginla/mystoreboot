@extends('storefront::layout', ['title' => 'Track order · '.$store->store_name])

@section('content')
    <section class="store-shell py-14">
        <div class="mx-auto max-w-2xl">
            <h1 class="sf-headline-lg text-[var(--store-primary)]">Track your order</h1>
            <form method="GET" class="mt-6 flex gap-2"><input class="store-input" name="reference" value="{{ $reference }}" placeholder="RS-20260925-XXXXXXXX" required><button class="store-btn store-btn-primary" type="submit">Track</button></form>
            @if ($reference && ! $order)<div class="store-card mt-6 p-6">No order was found with that reference.</div>@endif
            @if ($order)
                <div class="store-card mt-6 p-6"><h2 class="sf-headline-md">{{ $order->order_reference }}</h2><div class="mt-4 grid gap-3"><p><strong>Order:</strong> {{ str($order->order_status)->headline() }}</p><p><strong>Payment:</strong> {{ str($order->payment_status)->headline() }}</p><p><strong>Fulfilment:</strong> {{ str($order->fulfilment_status)->headline() }}</p></div><div class="mt-5 divide-y">@foreach ($order->items as $item)<div class="flex justify-between py-3"><span>{{ $item->product_name }} × {{ $item->quantity }}</span><span>{{ $order->currency_code }} {{ number_format($item->line_total_minor / 100, 2) }}</span></div>@endforeach</div></div>
            @endif
        </div>
    </section>
@endsection
