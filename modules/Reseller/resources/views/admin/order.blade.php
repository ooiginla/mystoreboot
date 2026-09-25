@php($money = fn (?int $minor): string => number_format(($minor ?? 0) / 100, 2))

<x-layouts.admin title="Reseller Order {{ $order->order_reference }}">
    <div style="display:grid; gap:20px;">
        @if (session('status'))<div class="notice success">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="notice danger"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <div class="page-heading">
            <div><a class="subtle" href="{{ route('admin.reseller.orders.index', ['tenant' => $tenant->id]) }}">← Reseller orders</a><h1>{{ $order->order_reference }}</h1><p>{{ $order->customer_name }} · {{ $order->customer_phone }} · {{ $order->customer_email }}</p></div>
            <div><span class="badge">{{ str($order->order_status)->headline() }}</span> <span class="badge neutral">{{ str($order->fulfilment_status)->headline() }}</span></div>
        </div>

        <section class="panel">
            <div class="panel-header"><h2 class="panel-title">Order summary</h2></div>
            <div class="panel-body summary-grid">
                <div><span class="subtle">Delivery</span><p>{{ data_get($order->delivery_address, 'address') }}, {{ data_get($order->delivery_address, 'city') }}</p></div>
                <div><span class="subtle">Payment</span><p>{{ str($order->payment_status)->headline() }} · {{ $order->currency_code }} {{ $money($order->total_minor) }}</p></div>
                @permission('sales.update')
                    <form method="POST" action="{{ route('admin.reseller.orders.payment-status.update', $order) }}" style="display:grid; gap:8px; align-content:start;">
                        @csrf @method('PATCH')
                        <label>Update payment status
                            <select name="payment_status">
                                @foreach ($paymentStatuses as $paymentStatus)<option value="{{ $paymentStatus->value }}" @selected($order->payment_status === $paymentStatus->value)>{{ $paymentStatus->label() }}</option>@endforeach
                            </select>
                        </label>
                        <button class="btn primary" type="submit">Save payment status</button>
                    </form>
                @endpermission
            </div>
        </section>

        @foreach ($supplierGroups as $items)
            @php($supplier = $items->first()->supplier)
            <section class="panel">
                <div class="panel-header">
                    <div><h2 class="panel-title">{{ $supplier?->name ?? $items->first()->source_store_name }}</h2><p class="subtle">{{ $supplier?->whatsapp ?: $supplier?->contact_phone ?: $supplier?->contact_email ?: 'No supplier contact saved' }}</p></div>
                    @if ($supplier?->whatsapp)<a class="btn accent" target="_blank" rel="noopener" href="https://wa.me/{{ preg_replace('/\D+/', '', $supplier->whatsapp) }}">Contact on WhatsApp</a>@endif
                </div>
                <div class="panel-body" style="display:grid; gap:12px;">
                    @foreach ($items as $item)
                        <form method="POST" action="{{ route('admin.reseller.orders.items.update', [$order, $item]) }}" class="form-grid" style="border:1px solid var(--line); border-radius:10px; padding:16px;">
                            @csrf @method('PATCH')
                            <div><strong>{{ $item->product_name }} × {{ $item->quantity }}</strong><div class="subtle">{{ $item->sku ?: 'No SKU' }} · {{ $order->currency_code }} {{ $money($item->line_total_minor) }}</div><a class="subtle" href="{{ $item->product_url }}" target="_blank" rel="noopener">Open source product ↗</a></div>
                            <label>Status<select name="fulfilment_status">@foreach (['unfulfilled', 'supplier_notified', 'dispatched', 'delivered', 'cancelled'] as $status)<option value="{{ $status }}" @selected($item->fulfilment_status === $status)>{{ str($status)->headline() }}</option>@endforeach</select></label>
                            <label>Tracking reference<input name="tracking_reference" value="{{ $item->tracking_reference }}"></label>
                            <label>Tracking URL<input type="url" name="tracking_url" value="{{ $item->tracking_url }}"></label>
                            <div class="full"><button class="btn primary" type="submit">Update fulfilment</button></div>
                        </form>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-layouts.admin>
