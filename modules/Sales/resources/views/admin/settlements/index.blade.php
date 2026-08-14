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

    @php
        $maskedAccount = ($settlementBank['account_number'] ?? '') !== ''
            ? '••••'.substr((string) $settlementBank['account_number'], -4)
            : null;
        $isDirect = $payoutMode === \Modules\Sales\Enums\PayoutMode::AutoSubaccount;
    @endphp

    <section class="panel" style="margin-bottom: 18px;">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Payouts</h2>
                <p class="subtle">How your online earnings reach your bank.</p>
            </div>
            <span class="badge">{{ $payoutMode->label() }}</span>
        </div>
        <div class="panel-body">
            @if (! empty($settlementBank['account_name']))
                <p class="subtle" style="margin:0 0 14px;">
                    Settlement account: <strong>{{ $settlementBank['account_name'] }}</strong>
                    @if (! empty($settlementBank['bank_name'])) · {{ $settlementBank['bank_name'] }}@endif
                    @if ($maskedAccount) · {{ $maskedAccount }}@endif
                    @if ($isDirect) <span class="subtle">— Paystack settles each sale straight to this account (T+1).</span>@endif
                </p>
            @else
                <div class="alert" style="margin-bottom:14px;">No settlement bank set yet. Add it under <strong>Online Store → Payment → Storeboot Paystack</strong> to receive online payouts.</div>
            @endif
            <div class="stats-grid">
                <div class="stat"><span class="subtle">Online earnings</span><strong>{{ $money($stats['earnings_minor']) }}</strong></div>
                <div class="stat"><span class="subtle">{{ $isDirect ? 'Settled to your bank' : 'Settled' }}</span><strong>{{ $money($stats['earnings_settled_minor']) }}</strong></div>
                <div class="stat"><span class="subtle">Pending</span><strong>{{ $money($stats['earnings_pending_minor']) }}</strong></div>
            </div>
        </div>
    </section>

    <section class="panel" style="margin-bottom: 18px;">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Online payments</h2>
                <p class="subtle">Each verified online sale and its settlement status.</p>
            </div>
        </div>
        <div class="panel-body">
            @if ($payments->isEmpty())
                <div class="empty">No online payments yet.</div>
            @else
                <div class="table-scroll">
                    <table class="table">
                        <thead><tr><th>Date</th><th>Order</th><th>Customer</th><th>Earnings</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach ($payments as $payment)
                                <tr>
                                    <td class="subtle">{{ $payment->collected_at?->format('M j, Y') }}</td>
                                    <td><div class="cell-title">{{ $payment->order?->order_number ?? '—' }}</div></td>
                                    <td>{{ $payment->order?->customer?->name ?? $payment->customer_email }}</td>
                                    <td>{{ $money((int) $payment->customer_total_minor) }}</td>
                                    <td>
                                        @if ($payment->is_settled)
                                            <span class="badge">{{ $isDirect ? 'Settled → bank' : 'Settled' }}</span>
                                        @else
                                            <span class="badge neutral">Pending</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>

    <div class="stats-grid" style="margin-bottom: 18px;">
        <div class="stat"><span class="subtle">Unsettled payments</span><strong>{{ $stats['unsettled_count'] }}</strong></div>
        <div class="stat"><span class="subtle">Unsettled amount</span><strong>{{ $money($stats['unsettled_minor']) }}</strong></div>
    </div>

    <div class="grid">
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
