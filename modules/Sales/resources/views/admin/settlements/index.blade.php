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
            @if ($isPlatformAdmin)
                <form method="POST" action="{{ route('admin.sales.settlements.payout-mode', ['tenant' => $tenant->id]) }}" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; margin-bottom:6px;">
                    @csrf
                    <div class="field" style="min-width:300px; margin:0;">
                        <label for="payout_mode">Payout mode <span class="subtle">(platform admin only)</span></label>
                        <select id="payout_mode" name="payout_mode">
                            @foreach (\Modules\Sales\Enums\PayoutMode::cases() as $mode)
                                <option value="{{ $mode->value }}" @selected($mode === $payoutMode)>{{ $mode->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn secondary">Update mode</button>
                </form>
                <p class="subtle" style="margin:0 0 16px;">{{ $payoutMode->description() }}</p>
            @endif
            <div class="stats-grid">
                <div class="stat"><span class="subtle">Online earnings</span><strong>{{ $money($stats['earnings_minor']) }}</strong></div>
                <div class="stat"><span class="subtle">{{ $isDirect ? 'Settled to your bank' : 'Settled' }}</span><strong>{{ $money($stats['earnings_settled_minor']) }}</strong></div>
                <div class="stat"><span class="subtle">Pending</span><strong>{{ $money($stats['earnings_pending_minor']) }}</strong></div>
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header" style="gap:14px; flex-wrap:wrap;">
            <div>
                <h2 class="panel-title">Settlement report</h2>
                <p class="subtle">Every verified online sale, the mode it settled under, and its status — a permanent trail across payout-mode changes.</p>
            </div>
            <form method="GET" action="{{ route('admin.sales.settlements.index') }}" style="display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap;">
                @if ($isPlatformAdmin)<input type="hidden" name="tenant" value="{{ $tenant->id }}">@endif
                <div class="field" style="margin:0;">
                    <label for="mode">Mode</label>
                    <select id="mode" name="mode">
                        <option value="">All modes</option>
                        @foreach ($payoutModes as $mode)
                            <option value="{{ $mode->value }}" @selected($filters['mode'] === $mode->value)>{{ $mode->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="margin:0;">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="">All</option>
                        <option value="settled" @selected($filters['status'] === 'settled')>Settled</option>
                        <option value="pending" @selected($filters['status'] === 'pending')>Pending</option>
                    </select>
                </div>
                <div class="field" style="margin:0;"><label for="from">From</label><input type="date" id="from" name="from" value="{{ $filters['from'] }}"></div>
                <div class="field" style="margin:0;"><label for="to">To</label><input type="date" id="to" name="to" value="{{ $filters['to'] }}"></div>
                <div class="field" style="margin:0;"><label for="search">Search</label><input type="text" id="search" name="search" value="{{ $filters['search'] }}" placeholder="Order / ref / email"></div>
                <button type="submit" class="btn secondary">Filter</button>
                <a class="btn secondary" href="{{ route('admin.sales.settlements.statement', array_filter(array_merge(['tenant' => $isPlatformAdmin ? $tenant->id : null], $filters))) }}">Download CSV</a>
            </form>
        </div>
        <div class="panel-body">
            @if ($payments->isEmpty())
                <div class="empty">No online payments match these filters.</div>
            @else
                <div class="table-scroll">
                    <table class="table">
                        <thead><tr><th>Date</th><th>Order</th><th>Customer</th><th>Gateway ref</th><th>Mode</th><th>Earnings</th><th>Gateway charge</th><th>Fees</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach ($payments as $payment)
                                @php $mode = \Modules\Sales\Enums\PayoutMode::tryFrom((string) $payment->payout_mode); @endphp
                                <tr>
                                    <td class="subtle">{{ $payment->collected_at?->format('M j, Y') }}</td>
                                    <td><div class="cell-title">{{ $payment->order?->order_number ?? '—' }}</div></td>
                                    <td>{{ $payment->order?->customer?->name ?? $payment->customer_email }}</td>
                                    <td class="subtle">{{ $payment->provider_reference }}</td>
                                    <td><span class="badge neutral">{{ $mode?->label() ?? ($payment->payout_mode ?? '—') }}</span></td>
                                    <td>{{ $money((int) $payment->customer_total_minor) }}</td>
                                    <td>{{ $money((int) $payment->gateway_charge_minor) }}</td>
                                    <td>{{ $money((int) $payment->fees_minor) }}</td>
                                    <td>
                                        @if ($payment->is_settled)
                                            <span class="badge success">Settled</span>
                                        @else
                                            <span class="badge neutral">Pending</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="subtle" style="margin-top:12px;">Showing the {{ $payments->count() }} most recent matching transactions. Download the CSV for the full statement.</p>
            @endif
        </div>
    </section>
</x-layouts.admin>
