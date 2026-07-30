@php
    $refundMethods = collect($paymentMethods)
        ->push('Paystack')
        ->filter()
        ->unique(fn ($method) => strtolower((string) $method));
@endphp
<dialog class="dialog" id="order-refund-{{ $order->id }}">
    <div class="dialog-header">
        <div>
            <h2 class="panel-title">Record refund</h2>
            <p class="subtle">{{ $order->order_number }} · {{ $currencySymbol }} {{ $money($order->customer_credit_minor) }}</p>
        </div>
        <button class="icon-btn" type="button" data-dialog-close aria-label="Close">✕</button>
    </div>
    <form method="POST" action="{{ route('admin.sales.orders.mark-refunded', $order) }}" data-order-refund-form data-order-branch="{{ $order->branch_id }}">
        @csrf
        <div class="dialog-body">
            <p class="subtle" style="margin-top: 0;">Process the transfer or provider reversal first, then record it here using the account the money was actually paid from.</p>
            <div class="form-grid">
                <div class="field">
                    <label for="refund-date-{{ $order->id }}">Refund date</label>
                    <input id="refund-date-{{ $order->id }}" name="refund_date" type="date" value="{{ now()->toDateString() }}" required>
                </div>
                <div class="field">
                    <label for="refund-method-{{ $order->id }}">Refund method</label>
                    <select id="refund-method-{{ $order->id }}" name="payment_method" data-refund-method required>
                        @foreach ($refundMethods as $method)
                            <option value="{{ $method }}">{{ $method }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" data-refund-till>
                    <label for="refund-till-{{ $order->id }}">Cash paid from</label>
                    <select id="refund-till-{{ $order->id }}" name="sales_till_session_id">
                        <option value="">Cash on Hand (general)</option>
                        @foreach ($recentTillSessions->where('branch_id', $order->branch_id) as $till)
                            <option value="{{ $till->id }}">{{ $till->cashLocation?->name ?? 'Till' }} · {{ $till->opened_at?->format('M j, g:i A') }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" data-refund-account hidden>
                    <label for="refund-account-{{ $order->id }}">Account paid from</label>
                    <select id="refund-account-{{ $order->id }}" name="business_payment_account_id" disabled>
                        <option value="">Select refund account</option>
                        @foreach ($refundPaymentAccounts as $account)
                            @foreach ((array) $account->supported_payment_methods as $method)
                                <option value="{{ $account->id }}" data-account-method="{{ $method }}" data-account-branch="{{ $account->branch_id }}">{{ $account->identifier }}{{ $account->branch ? ' · '.$account->branch->name : '' }}</option>
                            @endforeach
                        @endforeach
                    </select>
                    <span class="subtle" data-refund-account-empty hidden>No configured account supports this refund method; the standard accounting account will be used.</span>
                </div>
                <div class="field">
                    <label for="refund-reference-{{ $order->id }}">Reference number</label>
                    <input id="refund-reference-{{ $order->id }}" name="reference_number" maxlength="120" placeholder="Bank, POS, or provider reference">
                </div>
                <div class="field">
                    <label>Refund amount</label>
                    <input value="{{ $currencySymbol }} {{ $money($order->customer_credit_minor) }}" readonly>
                </div>
            </div>
            <div class="field" style="margin-top: 14px;">
                <label for="refund-notes-{{ $order->id }}">Notes</label>
                <textarea id="refund-notes-{{ $order->id }}" name="notes" rows="3" maxlength="1000" placeholder="Optional refund note"></textarea>
            </div>
            <div class="button-row">
                <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                <button class="btn danger" type="submit">Record Refund</button>
            </div>
        </div>
    </form>
</dialog>
