<div class="panel">
    <div class="panel-header"><h3 class="panel-title">{{ $title }}</h3></div>
    <div class="panel-body">
        <div class="mini-list">
            @forelse ($rows as $row)
                @php
                    $customer = is_array($row) ? $row['customer'] : ($kind === 'follow' ? $row->customer : $row);
                @endphp
                <div class="mini-row">
                    <div>
                        <strong>{{ $customer->name }}</strong>
                        <div class="subtle">
                            @if ($kind === 'top')
                                Spend: {{ $tenant->currency_code }} {{ $money($customer->purchases->sum('amount_minor')) }}
                            @elseif ($kind === 'follow')
                                {{ $row->subject }} due {{ $row->due_date->format('M j, Y') }}
                            @elseif ($kind === 'offer')
                                {{ $row['offer'] }}
                            @else
                                Last purchase: {{ $customer->last_purchase_at?->format('M j, Y') ?? 'No purchase yet' }}
                            @endif
                        </div>
                    </div>
                    <button class="btn secondary" type="button" data-dialog-open="customer-view-{{ $customer->id }}">View</button>
                </div>
            @empty
                <div class="empty">No recommendations yet.</div>
            @endforelse
        </div>
    </div>
</div>
