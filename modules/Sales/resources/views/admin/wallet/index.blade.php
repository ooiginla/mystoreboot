<x-layouts.admin title="Wallet">
    @php
        $money = fn (int $minor): string => $currency.' '.number_format($minor / 100, 2);
        $isCustodial = $payoutMode->isCustodial();
        $maskedAccount = ($settlementBank['account_number'] ?? '') !== ''
            ? '••••'.substr((string) $settlementBank['account_number'], -4)
            : null;
    @endphp

    <div class="topbar">
        <div>
            <div class="eyebrow">Online payments</div>
            <h1>Wallet</h1>
            <p class="subtle">Your held online earnings and withdrawals to your settlement bank.</p>
        </div>
        @if ($isPlatformAdmin)
            <form method="GET" action="{{ route('admin.sales.wallet.index') }}" style="min-width: 260px;">
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
    @if ($errors->any())
        <div class="alert" style="background:#fef2f2; border-color:#fecaca; color:#b91c1c;">{{ $errors->first() }}</div>
    @endif

    <section class="panel" style="margin-bottom: 18px;">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Payout mode</h2>
                <p class="subtle">How your online earnings reach you.</p>
            </div>
            <span class="badge {{ $isCustodial ? 'neutral' : 'success' }}">{{ $payoutMode->label() }}</span>
        </div>
        <div class="panel-body">
            <p class="subtle" style="margin:0;">
                {{ $payoutMode->description() }}
                @unless ($isCustodial) Any balance shown below is held from a previous wallet period and can still be withdrawn.@endunless
            </p>
        </div>
    </section>

    <div class="stats-grid" style="margin-bottom: 18px;">
        <div class="stat"><span class="subtle">Available to withdraw</span><strong>{{ $money((int) $wallet->available_balance_minor) }}</strong></div>
        <div class="stat"><span class="subtle">Pending (awaiting settlement)</span><strong>{{ $money((int) $wallet->pending_balance_minor) }}</strong></div>
        <div class="stat"><span class="subtle">Total balance</span><strong>{{ $money($wallet->totalBalanceMinor()) }}</strong></div>
    </div>

    <div class="grid">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Withdraw</h2>
                    <p class="subtle">Paid to {{ $settlementBank['account_name'] ?? 'your settlement account' }}@if ($maskedAccount) · {{ $maskedAccount }}@endif.</p>
                </div>
            </div>
            <div class="panel-body">
                @if (! $hasSettlementBank)
                    <div class="alert">Set your settlement bank account under <strong>Online Store → Payment → Storeboot Paystack</strong> before you can withdraw.</div>
                @elseif (! $canWithdraw)
                    <div class="empty">No available balance to withdraw right now.</div>
                @else
                    <form method="POST" action="{{ route('admin.sales.wallet.withdraw', ['tenant' => $tenant->id]) }}"
                          data-withdraw-form data-preview-url="{{ route('admin.sales.wallet.preview', ['tenant' => $tenant->id]) }}">
                        @csrf
                        <div class="field">
                            <label for="amount">Amount to receive ({{ $currency }})</label>
                            <input type="text" inputmode="decimal" id="amount" name="amount" autocomplete="off" placeholder="0.00" data-amount>
                        </div>
                        <div class="fee-breakdown" data-breakdown hidden style="border:1px solid var(--line,#e4eae7); border-radius:10px; padding:12px 14px; margin-bottom:14px; font-size:13.5px;">
                            <div style="display:flex; justify-content:space-between;"><span class="subtle">You receive</span><strong data-b-amount>—</strong></div>
                            <div style="display:flex; justify-content:space-between;"><span class="subtle">Gateway transfer fee</span><span data-b-gateway>—</span></div>
                            <div style="display:flex; justify-content:space-between;"><span class="subtle">Storeboot transfer fee</span><span data-b-platform>—</span></div>
                            <hr style="border:none; border-top:1px solid var(--line,#e4eae7); margin:8px 0;">
                            <div style="display:flex; justify-content:space-between;"><strong>Total debited from wallet</strong><strong data-b-total>—</strong></div>
                            <div data-b-warn class="subtle" style="color:#b91c1c; margin-top:8px;" hidden>Insufficient available balance for this amount plus fees.</div>
                        </div>
                        <button type="submit" class="btn primary" data-submit>Withdraw</button>
                    </form>
                @endif
            </div>
        </section>

        <aside class="stack">
            <section class="panel">
                <div class="panel-header"><div><h2 class="panel-title">Recent withdrawals</h2></div></div>
                <div class="panel-body">
                    <table class="table">
                        <thead><tr><th>Reference</th><th>Amount</th><th>Fees</th><th>Status</th><th>Date</th></tr></thead>
                        <tbody>
                            @forelse ($withdrawals as $w)
                                <tr>
                                    <td><strong>{{ $w->reference }}</strong></td>
                                    <td>{{ $money((int) $w->amount_minor) }}</td>
                                    <td>{{ $money((int) $w->gateway_fee_minor + (int) $w->platform_fee_minor) }}</td>
                                    <td><span class="badge {{ $w->status === 'completed' ? 'success' : ($w->status === 'failed' || $w->status === 'reversed' ? 'danger' : 'neutral') }}">{{ ucfirst($w->status) }}</span></td>
                                    <td>{{ $w->created_at?->format('M j, Y') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><div class="empty">No withdrawals yet.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </aside>
    </div>

    <section class="panel" style="margin-top: 18px;">
        <div class="panel-header" style="gap:14px; flex-wrap:wrap;">
            <div><h2 class="panel-title">Wallet statement</h2><p class="subtle">Every credit and debit on your wallet.</p></div>
            <form method="GET" action="{{ route('admin.sales.wallet.index') }}" style="display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap;">
                @if ($isPlatformAdmin)<input type="hidden" name="tenant" value="{{ $tenant->id }}">@endif
                <div class="field" style="margin:0;"><label for="from">From</label><input type="date" id="from" name="from" value="{{ $from }}"></div>
                <div class="field" style="margin:0;"><label for="to">To</label><input type="date" id="to" name="to" value="{{ $to }}"></div>
                <button type="submit" class="btn secondary">Filter</button>
                <a class="btn secondary" href="{{ route('admin.sales.wallet.statement', array_filter(['tenant' => $isPlatformAdmin ? $tenant->id : null, 'from' => $from ?: null, 'to' => $to ?: null])) }}">Download CSV</a>
            </form>
        </div>
        <div class="panel-body">
            <table class="table">
                <thead><tr><th>Date</th><th>Description</th><th>Type</th><th>State</th><th style="text-align:right;">Amount</th></tr></thead>
                <tbody>
                    @forelse ($transactions as $txn)
                        <tr>
                            <td>{{ $txn->created_at?->format('M j, Y g:ia') }}</td>
                            <td>{{ $txn->description ?? ucfirst(str_replace('_', ' ', $txn->category)) }}</td>
                            <td><span class="badge neutral">{{ ucfirst(str_replace('_', ' ', $txn->category)) }}</span></td>
                            <td><span class="badge {{ $txn->state === 'available' ? 'success' : ($txn->state === 'reversed' ? 'danger' : 'neutral') }}">{{ ucfirst($txn->state) }}</span></td>
                            <td style="text-align:right; color:{{ $txn->direction === 'credit' ? '#067647' : '#b42318' }};">{{ $txn->direction === 'credit' ? '+' : '−' }}{{ $money((int) $txn->amount_minor) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty">No wallet activity yet.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('[data-withdraw-form]');
            if (!form) return;
            const csrf = @json(csrf_token());
            const currency = @json($currency);
            const amount = form.querySelector('[data-amount]');
            const box = form.querySelector('[data-breakdown]');
            const submit = form.querySelector('[data-submit]');
            const fmt = (m) => currency + ' ' + (m / 100).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
            let timer;
            const preview = async () => {
                const v = (amount.value || '').replace(/,/g, '').trim();
                if (!v || parseFloat(v) <= 0) { box.hidden = true; return; }
                try {
                    const body = new URLSearchParams();
                    body.append('_token', csrf); body.append('tenant', @json($tenant->id)); body.append('amount', v);
                    const res = await fetch(form.dataset.previewUrl, {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded', Accept:'application/json'}, body});
                    const d = await res.json();
                    form.querySelector('[data-b-amount]').textContent = fmt(d.amount_minor);
                    form.querySelector('[data-b-gateway]').textContent = fmt(d.gateway_fee_minor);
                    form.querySelector('[data-b-platform]').textContent = fmt(d.platform_fee_minor);
                    form.querySelector('[data-b-total]').textContent = fmt(d.total_minor);
                    form.querySelector('[data-b-warn]').hidden = !!d.affordable;
                    submit.disabled = !d.affordable;
                    submit.style.opacity = d.affordable ? '1' : '.6';
                    box.hidden = false;
                } catch (e) { box.hidden = true; }
            };
            amount.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(preview, 350); });
        });
    </script>
</x-layouts.admin>
