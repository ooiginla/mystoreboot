@php $qty = fn($value) => \Modules\Inventory\Support\Quantity::format($value); @endphp
<x-layouts.admin title="Scheduled Orders">
    <style>
        .orders-date-filter { display:grid; grid-template-columns:minmax(170px,1fr) minmax(170px,1fr) auto; gap:16px; align-items:end; }
        .production-orders-table { min-width:1250px; }
        .production-orders-table td { vertical-align:top; }
        @media (max-width:640px) { .orders-date-filter { grid-template-columns:1fr; } }
    </style>

    <div class="topbar">
        <div>
            <div class="eyebrow">F&amp;B production</div>
            <h1>Scheduled orders</h1>
            <p class="subtle">A single view of every scheduled production item for {{ $branch->name }}.</p>
        </div>
    </div>

    <nav class="pill-nav" aria-label="Production planning sections">
        <a href="{{ route('admin.inventory.production.planning', ['tenant' => $tenant->id]) }}">Planning</a>
        <a class="active" href="{{ route('admin.inventory.production.orders', ['tenant' => $tenant->id]) }}" aria-current="page">Scheduled Orders</a>
    </nav>

    @if(session('status'))<div class="alert">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="alert errors">{{ $errors->first() }}</div>@endif

    <form class="orders-date-filter" method="GET" style="margin-bottom:16px;">
        <input type="hidden" name="tenant" value="{{ $tenant->id }}">
        <div class="field"><label>From</label><input type="date" name="from" value="{{ $from->toDateString() }}"></div>
        <div class="field"><label>To</label><input type="date" name="to" value="{{ $to->toDateString() }}"></div>
        <div class="field"><button class="btn primary">Apply</button></div>
    </form>

    <div class="stats-grid" style="margin-bottom:16px">
        <div class="stat"><span class="subtle">Schedule lines</span><strong>{{ $plans->count() }}</strong></div>
        <div class="stat"><span class="subtle">Planned</span><strong>{{ $plans->where('status', 'planned')->count() }}</strong></div>
        <div class="stat"><span class="subtle">In progress</span><strong>{{ $plans->where('status', 'in_progress')->count() }}</strong></div>
        <div class="stat"><span class="subtle">Completed</span><strong>{{ $plans->where('status', 'completed')->count() }}</strong></div>
    </div>

    <section class="panel">
        <div @class(['panel-body', 'production-table-scroll' => $plans->isNotEmpty()])>
            @if($plans->isEmpty())
                <div class="empty">No production is scheduled for this period.</div>
            @else
                <table class="table production-details-table production-orders-table">
                    <thead><tr><th>Scheduled</th><th>Product</th><th>Order</th><th>Quantity</th><th>Station</th><th>Shift</th><th>Lead</th><th>Wave</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                    @foreach($plans as $plan)
                        <tr>
                            <td><strong>{{ $plan->production_date->format('M j, Y') }}</strong><br><span class="subtle">{{ $plan->production_date->format('l') }} · {{ $plan->scheduled_time ? substr($plan->scheduled_time, 0, 5) : 'Any time' }}</span></td>
                            <td><strong>{{ $plan->recipe?->outputVariant?->product?->name ?? '—' }}</strong><br><span class="subtle">{{ $plan->recipe?->outputVariant?->variant_name }}</span></td>
                            <td>{{ $plan->workOrder?->order_number ?? 'Schedule #'.$plan->id }}</td>
                            <td>{{ $qty($plan->planned_quantity) }}</td>
                            <td>{{ $plan->station?->name ?? '—' }}</td>
                            <td>{{ $plan->shift?->name ?? '—' }}</td>
                            <td>{{ $plan->lead?->name ?? '—' }}</td>
                            <td>{{ $plan->wave ?: '—' }}</td>
                            <td><span class="badge {{ $plan->status === 'completed' ? 'success' : ($plan->status === 'cancelled' ? 'neutral' : '') }}">{{ str($plan->status)->headline() }}</span></td>
                            <td>
                                @if($plan->status === 'planned' || $plan->productionOrder)
                                    <details class="table-action-menu">
                                        <summary class="btn primary">Action <span aria-hidden="true">⌄</span></summary>
                                        <div class="table-action-dropdown">
                                            <div class="table-action-heading">Actions</div>
                                            @if($plan->status === 'planned')
                                                <a class="table-action-option edit" href="{{ route('admin.inventory.production.planning.day', ['tenant' => $tenant->id, 'date' => $plan->production_date->toDateString(), 'edit' => $plan->id]) }}">Edit Schedule</a>
                                                <form method="POST" action="{{ route('admin.inventory.production.planning.start', $plan) }}">@csrf<input type="hidden" name="tenant" value="{{ $tenant->id }}"><button class="table-action-option start" type="submit">Start Production</button></form>
                                                <form method="POST" action="{{ route('admin.inventory.production.planning.cancel', $plan) }}">@csrf<input type="hidden" name="tenant" value="{{ $tenant->id }}"><button class="table-action-option cancel" type="submit">Cancel Schedule</button></form>
                                            @elseif($plan->productionOrder)
                                                <a class="table-action-option open" href="{{ route('admin.inventory.production.runs.index', ['tenant' => $tenant->id]).'#run-'.$plan->production_order_id }}">Production Run</a>
                                            @endif
                                        </div>
                                    </details>
                                @else
                                    <span class="subtle">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </section>
</x-layouts.admin>
