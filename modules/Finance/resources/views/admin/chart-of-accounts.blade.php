@php
    $headline = fn (?string $value): string => \Illuminate\Support\Str::headline((string) $value);
@endphp

<x-layouts.admin title="Chart of Accounts">
    <div class="topbar">
        <div>
            <div class="eyebrow">Finance ledger</div>
            <h1>Chart of Accounts</h1>
            <p class="subtle">Tenant ledger accounts used by sales, inventory, receivables, payables, expenses, petty cash, and journal entries for {{ $tenant->name }}.</p>
        </div>
        @if ($isPlatformAdmin)
            <form method="GET" action="{{ route('admin.finance.chart-of-accounts') }}" style="min-width: 260px;">
                <select name="tenant" onchange="this.form.submit()">
                    @foreach ($tenants as $visibleTenant)
                        <option value="{{ $visibleTenant->id }}" @selected($visibleTenant->id === $tenant->id)>{{ $visibleTenant->name }}</option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Accounts</h2>
                <p class="subtle">System accounts are generated automatically to keep postings consistent.</p>
            </div>
        </div>
        <div class="panel-body" style="overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr><th>Code</th><th>Account</th><th>Type</th><th>Normal balance</th><th>System</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @foreach ($accounts as $account)
                        <tr>
                            <td>{{ $account->code }}</td>
                            <td>{{ $account->name }}</td>
                            <td>{{ $headline($account->type) }}</td>
                            <td>{{ $headline($account->normal_balance) }}</td>
                            <td><span class="badge {{ $account->is_system ? '' : 'neutral' }}">{{ $account->is_system ? 'System' : 'Custom' }}</span></td>
                            <td><span class="badge {{ $account->is_active ? '' : 'neutral' }}">{{ $account->is_active ? 'Active' : 'Inactive' }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.admin>
