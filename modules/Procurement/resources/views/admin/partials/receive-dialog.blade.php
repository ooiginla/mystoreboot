<dialog class="dialog" id="receive-po-{{ $po->id }}">
    <div class="dialog-header"><div><h2 class="panel-title">Receive {{ $po->po_number }}</h2><p class="subtle">Post goods received and update branch/location inventory.</p></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button></div>
    <div class="dialog-body">
        <form class="mini-form" method="POST" action="{{ route('admin.procurement.purchase-orders.receive', $po) }}">
            @csrf
            <div class="form-grid">
                <div class="field"><label>Receipt number</label><input name="receipt_number" placeholder="auto-generated"></div>
                <div class="field"><label>Received date</label><input name="received_at" type="date" value="{{ now()->toDateString() }}" required></div>
                <div class="field"><label>Delivery reference</label><input name="reference_number"></div>
            </div>
            <table class="table">
                <thead><tr><th>Item</th><th>Pending</th><th>Receive</th><th>Batch</th><th>Expiry</th></tr></thead>
                <tbody>
                    @foreach ($po->items as $index => $item)
                        @continue($item->quantity_pending <= 0)
                        @php $baseUnitCode = $item->variant?->baseUnit?->code ?? 'pc'; @endphp
                        <tr>
                            <td>{{ $variantLabel($item->variant) }}<input type="hidden" name="items[{{ $index }}][purchase_order_item_id]" value="{{ $item->id }}"></td>
                            <td>{{ \Modules\Inventory\Support\Quantity::format($item->quantity_pending) }} {{ $baseUnitCode }}</td>
                            <td><input name="items[{{ $index }}][quantity_received]" type="number" min="0.0001" max="{{ $item->quantity_pending }}" step="any" value="0" required aria-label="Quantity received in {{ $baseUnitCode }}"></td>
                            <td><input name="items[{{ $index }}][batch_number]"></td>
                            <td><input name="items[{{ $index }}][expiry_date]" type="date"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="field"><label>Notes</label><textarea name="notes"></textarea></div>
            <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Cancel</button><button class="btn primary" type="submit">Post goods received</button></div>
        </form>
    </div>
</dialog>
