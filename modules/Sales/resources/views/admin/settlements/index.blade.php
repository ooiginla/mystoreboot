<x-layouts.admin title="Online Payment Settlements">
    @php
        $money = fn (int $minor): string => $tenant->currency_code.' '.number_format($minor / 100, 2);
    @endphp

    <div class="topbar">
        <div>
            <div class="eyebrow">Online payments</div>
            <h1>Business Settlements</h1>
            <p class="subtle">View settlement batches and online collections for this business.</p>
        </div>
        @if ($isPlatformAdmin)
            <form method="GET" action="{{ route('admin.sales.settlements.index') }}" style="min-width: 260px;">
                <label for="tenant">Organization</label>
                <select id="tenant" name="tenant" onchange="this.form.submit()">
                    @foreach ($tenants as $visibleTenant)
                        <option value="{{ $visibleTenant->id }}" @selected($visibleTenant->id === $tenant->id)>{{ $visibleTenant->name }}</option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    @if (session('status'))
        <div class="alert">{{ session('status') }}</div>
    @endif
    <div class="stats-grid" style="margin-bottom: 18px;">
        <div class="stat"><span class="subtle">Unsettled payments</span><strong>{{ $stats['unsettled_count'] }}</strong></div>
        <div class="stat"><span class="subtle">Unsettled amount</span><strong>{{ $money($stats['unsettled_minor']) }}</strong></div>
        <div class="stat"><span class="subtle">Total settled</span><strong>{{ $money($stats['settled_minor']) }}</strong></div>
        <div class="stat"><span class="subtle">Gateway charges</span><strong>{{ $money($stats['total_gateway_charge_minor']) }}</strong></div>
        <div class="stat"><span class="subtle">Storeboot charges</span><strong>{{ $money($stats['storeboot_charges_minor']) }}</strong></div>
    </div>

    <div class="grid">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Settlement history</h2>
                    <p class="subtle">Batches created from successful online collections.</p>
                </div>
            </div>
            <div class="panel-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Date</th>
                            <th>Payments</th>
                            <th>Gateway charges</th>
                            <th>Net amount</th>
                            <th>Storeboot charges</th>
                            <th>Settled</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($settlements as $settlement)
                            <tr>
                                <td><strong>{{ $settlement->reference }}</strong><br><span class="badge neutral">{{ $settlement->status }}</span></td>
                                <td>{{ $settlement->settlement_date?->format('M j, Y') ?? 'Not set' }}</td>
                                <td>{{ $settlement->payment_count }}</td>
                                <td>{{ $money((int) $settlement->total_gateway_charge_minor) }}</td>
                                <td>{{ $money((int) $settlement->total_net_amount_minor) }}</td>
                                <td>{{ $money((int) $settlement->storeboot_charges_minor) }}</td>
                                <td>{{ $money((int) $settlement->total_settled_minor) }}</td>
                                <td><a class="btn secondary" href="{{ route('admin.sales.settlements.show', $settlement) }}">View</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="8"><div class="empty">No settlements have been created yet.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="stack">
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Unsettled payments</h2>
                        <p class="subtle">Successful online collections waiting for settlement.</p>
                    </div>
                </div>
                <div class="panel-body">
                    <table class="table">
                        <thead><tr><th>Order</th><th>Amount</th><th>Ref</th></tr></thead>
                        <tbody>
                            @forelse ($unsettledPayments as $payment)
                                <tr>
                                    <td>{{ $payment->order?->order_number }}<br><span class="subtle">{{ $payment->customer_email }}</span></td>
                                    <td>{{ $money((int) $payment->amount_minor) }}</td>
                                    <td>{{ $payment->provider_reference }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3"><div class="empty">No unsettled successful payments.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </aside>
    </div>
</x-layouts.admin>
