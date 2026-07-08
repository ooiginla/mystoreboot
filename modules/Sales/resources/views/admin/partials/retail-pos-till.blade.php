<style>
    .till-status-band { border: 1px solid #b7d8ce; border-left: 5px solid #009a53; border-radius: 12px; background: #f1fbf7; padding: 14px 16px; display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap; align-items: center; margin-bottom: 16px; }
    .till-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .till-action-button { display: inline-flex; align-items: center; gap: 7px; border: 0; border-radius: 10px; color: #fff; padding: 10px 14px; cursor: pointer; font-weight: 700; font-size: 13px; box-shadow: 0 6px 14px -6px rgba(16,24,40,.3); }
    .till-action-button svg { width: 16px; height: 16px; }
    .till-action-button.cash-in { background: #009a53; }
    .till-action-button.cash-out { background: #b42318; }
    .till-action-button.petty-cash-withdrawal { background: #9a3412; }
    .till-action-button.cash-deposit { background: #1d4ed8; }
    .till-action-button:hover { filter: brightness(.94); }
    .till-variance { font-weight: 800; }
    .till-variance.ok { color: #067647; }
    .till-variance.bad { color: #b42318; }
    .till-close-warning { border: 1px solid #fecdca; border-radius: 9px; background: #fef3f2; color: #b42318; padding: 10px 12px; font-weight: 700; margin: 12px 0; }
    .till-section-title { margin: 20px 0 8px; font-size: 14px; font-weight: 800; color: #111827; }
    .success-text { color: #067647; font-weight: 800; }
    .danger-text { color: #b42318; font-weight: 800; }
    #rpos-till-dialog input[disabled] { background: var(--panel-soft); }
    #rpos-till-dialog [data-till-variance].bad { color: #dc2626 !important; border-color: #fecaca; background: #fef2f2; }
    #rpos-till-dialog [data-till-variance].ok { color: #067647; }
    .till-book-variance { margin: 12px 0; border: 1px solid #fecaca; background: #fef2f2; border-radius: 12px; padding: 14px; display: grid; gap: 10px; }
    .till-book-variance .tbv-head { display: flex; gap: 10px; align-items: flex-start; }
    .till-book-variance .tbv-head svg { width: 22px; height: 22px; color: #dc2626; flex: 0 0 auto; margin-top: 1px; }
    .till-book-variance .tbv-head strong { color: #b42318; font-size: 14px; }
    .till-book-variance .tbv-head p { margin: 2px 0 0; font-size: 12.5px; color: #98514c; }
    .till-book-variance .tbv-check { display: inline-flex; align-items: center; gap: 10px; font-weight: 700; color: #b42318; cursor: pointer; }
    .till-book-variance input[type="text"], .till-book-variance input:not([type]) { border: 1px solid #f0c9c4; border-radius: 9px; padding: 9px 11px; background: #fff; }
</style>

<dialog class="dialog" id="rpos-till-dialog" style="width:min(940px,calc(100vw - 24px));">
    <div class="dialog-header">
        <div>
            <h2 class="panel-title">Till &amp; Cash Management</h2>
            <p class="subtle">{{ $activeTill->session_number }} · {{ $activeTill->branch?->name }} · opened {{ $activeTill->opened_at->format('M j, H:i') }}</p>
        </div>
        <button class="icon-btn" type="button" data-dialog-close aria-label="Close">✕</button>
    </div>
    <div class="dialog-body">
        <div class="till-status-band">
            <div>
                <strong>{{ $currency }} {{ $money($activeTill->opening_float_minor) }} opening float</strong>
                <div class="subtle">Cashier till: {{ $activeTill->cashLocation?->name ?? 'Pending' }} · Safe vault: {{ $activeTill->vaultCashLocation?->name ?? 'Pending' }}</div>
            </div>
            @php
                $movementIcons = [
                    'cash_in' => 'M12 5v14m0 0 6-6m-6 6-6-6',
                    'cash_out' => 'M12 19V5m0 0 6 6m-6-6-6 6',
                    'petty_cash_withdrawal' => 'M3 7h16a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h12M17 13.5h.01',
                    'cash_deposit' => 'M12 3 3 8v2h18V8l-9-5ZM5 10v8m4-8v8m6-8v8m4-8v8M3 20h18',
                ];
            @endphp
            <div class="till-actions">
                @foreach ($movementTypes as $value => $label)
                    <button class="till-action-button {{ str_replace('_', '-', $value) }}" type="button" data-dialog-open="till-movement-{{ $value }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $movementIcons[$value] ?? '' }}"/></svg>
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        <div class="till-section-title">Close &amp; reconcile</div>
        <form class="mini-form" method="POST" action="{{ route('admin.sales.tills.close', $activeTill) }}" data-till-close-form>
            @csrf
            <table class="table">
                <thead><tr><th>Payment method</th><th>Collected</th><th>Movement</th><th>Expected</th><th>Actual counted</th><th>Variance</th></tr></thead>
                <tbody>
                    @foreach ($activeTillRows as $row)
                        @php $actualValue = old('actuals.'.$row['method'], '0.00'); @endphp
                        <tr>
                            <td><strong>{{ $row['method'] }}</strong></td>
                            <td>{{ $currency }} {{ $money($row['collected_minor']) }}</td>
                            <td>{{ $signedMoney($row['movement_minor']) }}</td>
                            <td><strong>{{ $currency }} {{ $money($row['expected_minor']) }}</strong></td>
                            <td><input name="actuals[{{ $row['method'] }}]" type="text" inputmode="decimal" data-money-input data-till-actual data-expected="{{ $row['expected_minor'] / 100 }}" value="{{ $actualValue }}" placeholder="0.00" style="max-width:150px;"></td>
                            <td><input type="text" data-till-variance value="{{ $currency }} 0.00" disabled style="max-width:130px; font-weight:800;"><span class="till-variance ok" data-till-variance-label hidden>0</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="till-close-warning" data-till-close-warning hidden>Count each drawer and enter the actual amount. Variances must be 0 to close — or book the variance below.</div>

            <div class="till-book-variance" data-book-variance hidden>
                <div class="tbv-head">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                    <div>
                        <strong>Drawer is out by <span data-variance-amount>{{ $currency }} 0.00</span></strong>
                        <p>Recount to balance, or book the difference to <em>Cash Short &amp; Over</em> to close.</p>
                    </div>
                </div>
                <label class="tbv-check"><input type="checkbox" name="book_variance" value="1" data-book-variance-check class="switch-input"> <span>Book this variance as loss/gain</span></label>
                <input name="variance_note" placeholder="Reason for the variance (optional)" data-variance-note>
            </div>

            <div class="field"><label>Closing note</label><textarea name="closing_note" rows="2" placeholder="Optional"></textarea></div>
            <div class="button-row">
                <button class="btn secondary" type="button" data-dialog-open="till-breakdown-dialog" style="border-color:#bfdbfe; background:#eff6ff; color:#1d4ed8;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 14l3-3 3 3 5-6"/></svg>
                    View breakdown
                </button>
                <button class="btn primary" type="submit" data-till-close-button>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    Close till
                </button>
            </div>
        </form>

        <div class="till-section-title">Cash movements</div>
        <table class="table">
            <thead><tr><th>Time</th><th>Type</th><th>Amount</th><th>Reference</th><th>Note</th></tr></thead>
            <tbody>
                @forelse ($activeTill->movements->sortByDesc('occurred_at') as $movement)
                    <tr><td>{{ $movement->occurred_at->format('H:i') }}</td><td>{{ $movementTypes[$movement->movement_type] ?? $movement->movement_type }}</td><td>{{ $currency }} {{ $money($movement->amount_minor) }}</td><td>{{ $movement->reference_number ?: 'Not set' }}</td><td>{{ $movement->notes ?: 'Not set' }}</td></tr>
                @empty
                    <tr><td colspan="5"><div class="empty">No till movements recorded.</div></td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="till-section-title">Recent till sessions</div>
        <table class="table">
            <thead><tr><th>Session</th><th>Branch</th><th>Status</th><th>Opened</th><th>Closed</th><th>Expected</th><th>Actual</th><th>Variance</th></tr></thead>
            <tbody>
                @forelse ($recentTillSessions as $session)
                    <tr><td>{{ $session->session_number }}</td><td>{{ $session->branch?->name ?? 'Not set' }}</td><td><span class="sales-tag {{ $session->status === 'open' ? 'success' : 'neutral' }}">{{ ucfirst($session->status) }}</span></td><td>{{ $session->opened_at->format('M j, H:i') }}</td><td>{{ $session->closed_at?->format('M j, H:i') ?? '—' }}</td><td>{{ $currency }} {{ $money($session->expected_total_minor) }}</td><td>{{ $currency }} {{ $money($session->actual_total_minor) }}</td><td>{{ $signedMoney($session->variance_total_minor) }}</td></tr>
                @empty
                    <tr><td colspan="8"><div class="empty">No till session yet.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</dialog>

{{-- Cash movement dialogs --}}
@foreach ($movementTypes as $value => $label)
    <dialog class="dialog" id="till-movement-{{ $value }}" style="width:min(460px,calc(100vw - 24px));">
        <div class="dialog-header"><div><h2 class="panel-title">{{ $label }}</h2><p class="subtle">{{ $activeTill->session_number }}</p></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">✕</button></div>
        <div class="dialog-body">
            <form class="mini-form" method="POST" action="{{ route('admin.sales.tills.movements.store', $activeTill) }}">
                @csrf
                <input type="hidden" name="movement_type" value="{{ $value }}">
                <div class="field"><label>Amount</label><input name="amount" type="text" inputmode="decimal" data-money-input required></div>
                <div class="field"><label>Reference</label><input name="reference_number"></div>
                <div class="field"><label>Notes</label><textarea name="notes" rows="2"></textarea></div>
                <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Cancel</button><button class="btn primary" type="submit">Save movement</button></div>
            </form>
        </div>
    </dialog>
@endforeach

{{-- Breakdown dialog --}}
<dialog class="dialog" id="till-breakdown-dialog" style="width:min(940px,calc(100vw - 24px));">
    <div class="dialog-header"><div><h2 class="panel-title">Expected till breakdown</h2><p class="subtle">{{ $activeTill->session_number }}</p></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">✕</button></div>
    <div class="dialog-body">
        <div style="overflow-x:auto;">
        <table class="table" style="font-size:13px; white-space:nowrap;">
            <thead><tr><th>Time</th><th>Type</th><th>Details</th><th>Method</th><th style="text-align:right;">Amount</th></tr></thead>
            <tbody>
                <tr>
                    <td>{{ $activeTill->opened_at->format('H:i') }}</td><td>Opening float</td><td>{{ $activeTill->opening_note ?: 'Till opened' }}</td><td>Cash</td>
                    <td class="success-text" style="text-align:right;">+{{ $currency }} {{ $money($activeTill->opening_float_minor) }}</td>
                </tr>
                @foreach ($activeTill->payments->sortByDesc('created_at') as $payment)
                    <tr><td>{{ $payment->created_at?->format('H:i') ?? $payment->payment_date->format('H:i') }}</td><td>Sale payment</td><td>{{ $payment->order?->order_number ?? 'Order' }} · {{ $payment->order?->customer?->name ?? 'Walk-In' }}</td><td>{{ $payment->payment_method }}</td><td class="success-text" style="text-align:right;">+{{ $currency }} {{ $money($payment->amount_minor) }}</td></tr>
                @endforeach
                @foreach ($activeTill->movements->sortByDesc('occurred_at') as $movement)
                    @php $movementSign = $movement->movement_type === 'cash_in' ? 1 : -1; $movementAmount = $movementSign * (int) $movement->amount_minor; @endphp
                    <tr><td>{{ $movement->occurred_at->format('H:i') }}</td><td>{{ $movementTypes[$movement->movement_type] ?? $movement->movement_type }}</td><td>{{ $movement->reference_number ?: 'No reference' }} · {{ $movement->notes ?: 'No note' }}</td><td>{{ $movement->payment_method }}</td><td class="{{ $movementAmount < 0 ? 'danger-text' : 'success-text' }}" style="text-align:right;">{{ $movementAmount < 0 ? '-' : '+' }}{{ $currency }} {{ $money(abs($movementAmount)) }}</td></tr>
                @endforeach
            </tbody>
        </table>
        </div>
        <div class="summary-grid" style="margin-top:16px;">
            @foreach ($activeTillRows as $row)
                <div class="summary-item"><span>{{ $row['method'] }} expected</span><strong>{{ $currency }} {{ $money($row['expected_minor']) }}</strong></div>
            @endforeach
            <div class="summary-item"><span>Total expected</span><strong>{{ $currency }} {{ $money($activeTillRows->sum('expected_minor')) }}</strong></div>
        </div>
        <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Close</button></div>
    </div>
</dialog>
