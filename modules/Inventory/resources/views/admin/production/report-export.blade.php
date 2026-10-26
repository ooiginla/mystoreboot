<!doctype html>
<html>
<head><meta charset="utf-8"><title>Production Report</title></head>
<body>
@php $money=fn($v)=>$tenant->currency_code.' '.number_format($v/100,2); @endphp
<h1>Production Report</h1>
<p><strong>Company:</strong> {{ $tenant->name }}</p>
<p><strong>Branch:</strong> {{ $branch->name }}</p>
<p><strong>Period:</strong> {{ $from->format('M j, Y') }} to {{ $to->format('M j, Y') }}</p>
<p><strong>Production surplus value:</strong> {{ $money($surplusValueMinor) }}</p>
<p><strong>Yield loss value:</strong> {{ $money($yieldLossValueMinor) }}</p>
<p><strong>Waste register value:</strong> {{ $money($waste->sum('value_minor')) }}</p>
<h2>Production and yield</h2>
<table border="1" cellspacing="0" cellpadding="6"><thead><tr><th>Run</th><th>Date</th><th>Product</th><th>Lead/team</th><th>Planned</th><th>Actual</th><th>Variance reason</th><th>Impact value</th></tr></thead><tbody>
@forelse($orders as $order)@php $variance=(float)$order->actual_yield_quantity-(float)$order->planned_quantity; @endphp<tr><td>{{ $order->runNumber() }}</td><td>{{ $order->produced_at?->format('M j, Y') }}</td><td>{{ $order->outputVariant?->product?->name }}</td><td>{{ $order->productionLeadStaff?->name ?? $order->productionLead?->name ?? '—' }} / {{ $order->team->pluck('name')->join(', ') }}</td><td>{{ $order->planned_quantity }}</td><td>{{ $order->actual_yield_quantity }}</td><td>{{ $variance > 0.00001 ? 'Overproduction' : ($order->variance_reason?->label() ?? '—') }}</td><td>{{ abs($variance) > 0.00001 ? ($variance > 0 ? 'Surplus: ' : 'Loss: ').$money($order->variance_value_minor) : '—' }}</td></tr>@empty<tr><td colspan="8">No completed production.</td></tr>@endforelse
</tbody></table>
<h2>Ingredient variance</h2>
<table border="1" cellspacing="0" cellpadding="6"><thead><tr><th>Date/run</th><th>Ingredient</th><th>Planned</th><th>Actual</th><th>Variance</th><th>Value</th></tr></thead><tbody>
@forelse($ingredientVariance as $row)<tr><td>{{ $row['order']->produced_at?->format('M j, Y') }} · {{ $row['order']->runNumber() }}</td><td>{{ $row['item']->componentVariant?->product?->name }}</td><td>{{ $row['planned'] }}</td><td>{{ $row['actual'] }}</td><td>{{ $row['variance'] }}</td><td>{{ $money($row['value_minor']) }}</td></tr>@empty<tr><td colspan="6">No ingredient usage.</td></tr>@endforelse
</tbody></table>
<h2>Waste</h2>
<table border="1" cellspacing="0" cellpadding="6"><thead><tr><th>Date</th><th>Production run</th><th>Item</th><th>Reason</th><th>Quantity</th><th>Value</th><th>Staff</th></tr></thead><tbody>
@forelse($waste as $row)<tr><td>{{ $row->waste_date->format('M j, Y') }}</td><td>{{ $row->productionOrder?->runNumber() ?? '—' }}</td><td>{{ $row->variant?->product?->name }}</td><td>{{ $row->reason->label() }}</td><td>{{ $row->quantity }}</td><td>{{ $money($row->value_minor) }}</td><td>{{ $row->staff?->name ?? '—' }}</td></tr>@empty<tr><td colspan="7">No waste recorded.</td></tr>@endforelse
</tbody></table>
<h2>Staff productivity</h2>
<table border="1" cellspacing="0" cellpadding="6"><thead><tr><th>Staff</th><th>Runs</th><th>Led</th><th>Planned output</th><th>Actual output</th><th>Surplus value</th><th>Yield loss value</th><th>Waste attributed</th></tr></thead><tbody>
@forelse($productivity as $row)<tr><td>{{ $row['staff']->name }}</td><td>{{ $row['runs'] }}</td><td>{{ $row['led'] }}</td><td>{{ $row['planned'] }}</td><td>{{ $row['actual'] }}</td><td>{{ $money($row['surplus_value_minor']) }}</td><td>{{ $money($row['yield_loss_value_minor']) }}</td><td>{{ $money($row['waste_value_minor']) }}</td></tr>@empty<tr><td colspan="8">No production team activity.</td></tr>@endforelse
</tbody></table>
</body>
</html>
