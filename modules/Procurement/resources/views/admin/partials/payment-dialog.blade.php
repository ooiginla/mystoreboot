@php
    $dialogId ??= 'payment-dialog';
    $selectedPo ??= null;
    $payableOrders = $allPurchaseOrders->filter(fn ($po) => in_array($po->status->value, ['approved', 'partially_received', 'received'], true) && $po->balance_minor > 0);
@endphp

<dialog class="dialog" id="{{ $dialogId }}">
    <div class="dialog-header"><div><h2 class="panel-title">Record vendor payment</h2><p class="subtle">Track payments against suppliers and purchase orders.</p></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button></div>
    <div class="dialog-body">
        <form class="mini-form" method="POST" action="{{ route('admin.procurement.payments.store') }}">
            @csrf
            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
            <div class="form-grid">
                <div class="field"><label>Vendor</label><select name="vendor_id" required>@foreach ($allVendors as $vendor)<option value="{{ $vendor->id }}" @selected($selectedPo?->vendor_id === $vendor->id)>{{ $vendor->name }}</option>@endforeach</select></div>
                <div class="field"><label>Purchase order</label><select name="purchase_order_id" data-payment-po-select><option value="" data-total="" data-paid="" data-balance="">General payment</option>@foreach ($payableOrders as $po)<option value="{{ $po->id }}" data-vendor-id="{{ $po->vendor_id }}" data-total="{{ $money($po->total_minor) }}" data-paid="{{ $money($po->paid_minor) }}" data-balance="{{ $money($po->balance_minor) }}" @selected($selectedPo?->id === $po->id)>{{ $po->po_number }} · {{ $po->vendor->name }}</option>@endforeach</select></div>
                <div class="field"><label>PO amount</label><input type="text" value="{{ $selectedPo ? $money($selectedPo->total_minor) : '' }}" data-payment-total disabled></div>
                <div class="field"><label>Amount paid</label><input type="text" value="{{ $selectedPo ? $money($selectedPo->paid_minor) : '' }}" data-payment-paid disabled></div>
                <div class="field"><label>Debt left</label><input type="text" value="{{ $selectedPo ? $money($selectedPo->balance_minor) : '' }}" data-payment-balance disabled></div>
                <div class="field"><label>Payment date</label><input name="payment_date" type="date" value="{{ now()->toDateString() }}" required></div>
                <div class="field"><label>Amount</label><input name="amount" type="text" inputmode="decimal" data-money-input value="{{ $selectedPo ? $money($selectedPo->balance_minor) : '' }}" required></div>
                <div class="field"><label>Payment method</label><select name="payment_method"><option value="">Select method</option>@foreach ($paymentMethods as $method)<option value="{{ $method }}">{{ $method }}</option>@endforeach</select></div>
                <div class="field"><label>Reference number</label><input name="reference_number"></div>
                <div class="field full"><label>Notes</label><textarea name="notes"></textarea></div>
            </div>
            <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Cancel</button><button class="btn primary" type="submit">Record payment</button></div>
        </form>
    </div>
</dialog>
