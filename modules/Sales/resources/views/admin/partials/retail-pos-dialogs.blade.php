{{-- ============ Customer ============ --}}
<dialog class="dialog" id="rpos-customer-dialog" style="width:min(560px,calc(100vw - 24px));">
    <div class="dialog-header"><div><h2 class="panel-title">Select customer</h2><p class="subtle">Attach a customer or create a new one</p></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">✕</button></div>
    <div class="dialog-body">
        <div class="field"><input type="search" data-customer-search placeholder="Search name or phone..." autocomplete="off"></div>
        <div style="display:flex; gap:8px; margin:12px 0;">
            <button class="btn" type="button" data-select-customer data-id="{{ $walkInCustomer->id }}" data-name="{{ $walkInCustomer->name }}" data-sub="Walk-in customer" style="flex:1; gap:8px; border:1px solid var(--brand-200); background:var(--brand-050); color:var(--brand-strong); font-weight:700;">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 20v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/></svg>
                Walk-in customer
            </button>
        </div>
        <div data-customer-list style="display:grid; gap:6px; max-height:220px; overflow-y:auto;">
            @foreach ($customers as $customer)
                @continue($customer->phone === 'WALK-IN')
                <button class="held-item" type="button" data-select-customer data-search="{{ strtolower($customer->name.' '.$customer->phone) }}" data-id="{{ $customer->id }}" data-name="{{ $customer->name }}" data-sub="{{ $customer->phone ?: 'No phone' }}">
                    <span style="text-align:left;"><strong style="display:block;">{{ $customer->name }}</strong><span class="subtle">{{ $customer->phone ?: 'No phone' }}</span></span>
                    <span class="subtle">Select ›</span>
                </button>
            @endforeach
        </div>

        <div style="margin-top:16px; border-top:1px solid var(--line); padding-top:14px;">
            <p style="font-weight:700; margin:0 0 10px;">New customer</p>
            <div class="form-grid">
                <div class="field"><label>First name</label><input data-newc-first placeholder="e.g. Amaka"></div>
                <div class="field"><label>Last name</label><input data-newc-last placeholder="Optional"></div>
                <div class="field"><label>Phone</label><input data-newc-phone inputmode="tel" placeholder="e.g. 0803..."></div>
                <div class="field"><label>Email</label><input data-newc-email type="email" placeholder="Optional"></div>
            </div>
            <div class="button-row"><button class="btn primary" type="button" data-create-customer>Create &amp; select</button></div>
            <p class="subtle" data-newc-error style="color:#b42318; margin-top:6px;" hidden></p>
        </div>
    </div>
</dialog>

{{-- ============ Coupon ============ --}}
<dialog class="dialog" id="rpos-coupon-dialog" style="width:min(420px,calc(100vw - 24px));">
    <div class="dialog-header"><div><h2 class="panel-title">Apply coupon</h2></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">✕</button></div>
    <div class="dialog-body">
        <div class="field"><label>Coupon code</label><input data-coupon-input placeholder="Enter code" style="text-transform:uppercase;"></div>
        <p class="subtle" data-coupon-msg style="margin-top:8px;" hidden></p>
        <div class="button-row"><button class="btn secondary" type="button" data-clear-coupon>Remove</button><button class="btn primary" type="button" data-apply-coupon>Apply coupon</button></div>
    </div>
</dialog>

{{-- ============ Discount ============ --}}
<dialog class="dialog" id="rpos-discount-dialog" style="width:min(440px,calc(100vw - 24px));">
    <div class="dialog-header"><div><h2 class="panel-title">Manual discount</h2></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">✕</button></div>
    <div class="dialog-body">
        <div class="pay-methods" style="margin-bottom:12px;">
            <button class="pay-method active" type="button" data-disc-type="amount">Amount ({{ $currency }})</button>
            <button class="pay-method" type="button" data-disc-type="percentage">Percentage (%)</button>
        </div>
        <div class="field"><label>Value</label><input data-disc-value inputmode="decimal" value="0"></div>
        <div class="button-row"><button class="btn secondary" type="button" data-clear-discount>Clear</button><button class="btn primary" type="button" data-apply-discount>Apply discount</button></div>
    </div>
</dialog>

{{-- ============ Delivery ============ --}}
<dialog class="dialog" id="rpos-delivery-dialog" style="width:min(520px,calc(100vw - 24px));">
    <div class="dialog-header"><div><h2 class="panel-title">Delivery</h2></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">✕</button></div>
    <div class="dialog-body">
        <div class="form-grid">
            <div class="field"><label>Delivery method</label>
                <select data-delivery-method data-no-search>
                    <option value="" data-price="0">No delivery</option>
                    @foreach ($deliveryMethods as $method)<option value="{{ $method['name'] }}" data-price="{{ $method['price'] ?? 0 }}">{{ $method['name'] }}</option>@endforeach
                </select>
            </div>
            <div class="field"><label>Shipping fee</label><input data-delivery-shipping inputmode="decimal" value="0.00"></div>
            <div class="field full"><label>Delivery status</label>
                <select data-delivery-status data-no-search>
                    <option value="delivered" selected>Delivered</option>
                    <option value="pending">Pending</option>
                    <option value="processing">Processing</option>
                    <option value="out_for_delivery">Out for delivery</option>
                    <option value="failed">Failed delivery</option>
                    <option value="returned">Returned</option>
                </select>
            </div>
            <div class="field full"><label>Delivery address / recipient</label><textarea data-delivery-address rows="2" placeholder="Address, recipient contact..."></textarea></div>
        </div>
        <div class="button-row"><button class="btn primary" type="button" data-apply-delivery>Save delivery</button></div>
    </div>
</dialog>

{{-- ============ Note ============ --}}
<dialog class="dialog" id="rpos-note-dialog" style="width:min(460px,calc(100vw - 24px));">
    <div class="dialog-header"><div><h2 class="panel-title">Order note</h2></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">✕</button></div>
    <div class="dialog-body">
        <div class="field"><textarea data-note-input rows="4" placeholder="Internal note for this transaction..."></textarea></div>
        <div class="button-row"><button class="btn primary" type="button" data-apply-note>Save note</button></div>
    </div>
</dialog>

{{-- ============ Pay ============ --}}
<dialog class="dialog" id="rpos-pay-dialog" style="width:min(720px,calc(100vw - 24px));">
    <div class="dialog-header"><div><h2 class="panel-title">Take payment</h2><p class="subtle" data-pay-customer>Walk-in customer</p></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">✕</button></div>
    <div class="dialog-body">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:18px;">
            <div style="display:grid; gap:12px; align-content:start;">
                <div class="pay-amount-box"><span>Amount due</span><strong data-pay-total>{{ $currency }} 0.00</strong></div>
                <div class="field"><label>Amount received</label><input data-pay-received inputmode="decimal" value="0.00" placeholder="0.00" style="font-size:24px; font-weight:800; text-align:right;"></div>
                <div class="pay-quick" data-pay-quick></div>
                <div class="pay-status exact" data-pay-status><span data-pay-status-label>Change</span><strong data-pay-change>{{ $currency }} 0.00</strong></div>
                <label class="sales-inline-check" style="display:inline-flex; align-items:center; gap:10px; font-weight:700; cursor:pointer;">
                    <input type="checkbox" data-pay-credit class="switch-input"> <span>Credit sale (pay later)</span>
                </label>
            </div>
            <div style="display:grid; gap:12px; align-content:start;">
                <div>
                    <label style="display:block; margin-bottom:6px; font-weight:600;">Payment method</label>
                    <div class="pay-methods" data-pay-methods>
                        @foreach ($paymentMethods as $i => $method)
                            <button class="pay-method {{ $i === 0 ? 'active' : '' }}" type="button" data-pay-method="{{ $method }}">{{ $method }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="rpos-keypad" data-pay-keypad>
                    @foreach (['1','2','3','4','5','6','7','8','9'] as $k)<button type="button" data-key="{{ $k }}">{{ $k }}</button>@endforeach
                    <button type="button" data-key=".">.</button>
                    <button type="button" data-key="0">0</button>
                    <button type="button" data-key="back">⌫</button>
                    <button type="button" class="clear wide" data-key="clear">Clear</button>
                    <button type="button" data-key="00">00</button>
                </div>
            </div>
        </div>
        <div class="pay-error" data-pay-error hidden></div>
        <div class="button-row" style="margin-top:16px;">
            <button class="rpos-cancel" type="button" data-dialog-close>Cancel</button>
            <button class="rpos-pay" type="button" data-confirm-pay style="width:auto; margin:0; padding:13px 26px;">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                Confirm &amp; complete sale
            </button>
        </div>
    </div>
</dialog>

{{-- ============ Session orders ============ --}}
<dialog class="dialog" id="rpos-orders-dialog" style="width:min(940px,calc(100vw - 24px));">
    <div class="dialog-header"><div><h2 class="panel-title">This till's orders</h2><p class="subtle">Orders sold on the current till session only</p></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">✕</button></div>
    <div class="dialog-body" style="padding-top:8px;">
        <div style="overflow-x:auto;">
        <table class="table" style="font-size:13px; white-space:nowrap;">
            <thead><tr><th>Order #</th><th>Time</th><th>Customer</th><th>Payment</th><th style="text-align:right;">Total</th><th></th></tr></thead>
            <tbody>
                @forelse ($sessionOrders as $order)
                    <tr>
                        <td style="font-weight:700;">{{ $order->order_number }}</td>
                        <td class="subtle">{{ $order->created_at?->format('M j, H:i') ?? $order->order_date->format('M j') }}</td>
                        <td>{{ $order->customer?->name ?? 'Walk-In' }}</td>
                        <td><span class="badge {{ $order->payment_status->value === 'paid' ? '' : 'neutral' }}">{{ $order->payment_status->label() }}</span></td>
                        <td style="text-align:right; font-weight:800; font-variant-numeric:tabular-nums;">{{ $currency }} {{ $money($order->total_minor) }}</td>
                        <td style="text-align:right;"><button class="btn secondary" type="button" style="padding:6px 14px;" data-dialog-open="sales-receipt-{{ $order->id }}">Receipt</button></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="empty">No sales on this till session yet.</div></td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <p class="subtle" style="margin-top:14px;">Looking for online, offline &amp; historical orders? <a href="{{ route('admin.sales.index', ['tenant' => $tenant->id]) }}" style="color:var(--brand-strong); font-weight:700;">Open the full orders page →</a></p>
    </div>
</dialog>

{{-- ============ Held sales ============ --}}
<dialog class="dialog" id="rpos-held-dialog" style="width:min(520px,calc(100vw - 24px));">
    <div class="dialog-header"><div><h2 class="panel-title">Held sales</h2><p class="subtle">Parked carts you can recall</p></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">✕</button></div>
    <div class="dialog-body">
        <div data-held-list style="display:grid; gap:8px;"></div>
        <div class="empty" data-held-empty>No sales are on hold.</div>
    </div>
</dialog>
