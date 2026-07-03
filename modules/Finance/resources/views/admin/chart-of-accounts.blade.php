@php
    $headline = fn (?string $value): string => \Illuminate\Support\Str::headline((string) $value);
@endphp

<x-layouts.admin title="Chart of Accounts">
    <style>
        .chart-search { min-width: min(360px, 100%); }
        .chart-search-meta { margin-top: 6px; }
    </style>

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
            <div class="field chart-search">
                <label for="chart-account-search">Search accounts</label>
                <input id="chart-account-search" type="search" placeholder="Search code, name, category, type, or description" data-chart-search>
                <span class="subtle chart-search-meta"><span data-chart-visible-count>{{ $accounts->count() }}</span> of {{ $accounts->count() }} accounts shown</span>
            </div>
        </div>
        <div class="panel-body" style="overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr><th>Code</th><th>Account</th><th>Category</th><th>Description</th><th>Type</th><th>Normal balance</th><th>System</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @foreach ($accounts as $account)
                        <tr data-chart-account-row data-search-text="{{ \Illuminate\Support\Str::lower(implode(' ', [$account->code, $account->name, $account->category, $account->description, $account->type, $account->normal_balance, $account->is_system ? 'system' : 'custom', $account->is_active ? 'active' : 'inactive'])) }}">
                            <td>{{ $account->code }}</td>
                            <td>{{ $account->name }}</td>
                            <td>{{ $account->category ?: 'Not set' }}</td>
                            <td>{{ $account->description ?: 'Not set' }}</td>
                            <td>{{ $headline($account->type) }}</td>
                            <td>{{ $headline($account->normal_balance) }}</td>
                            <td><span class="badge {{ $account->is_system ? '' : 'neutral' }}">{{ $account->is_system ? 'System' : 'Custom' }}</span></td>
                            <td><span class="badge {{ $account->is_active ? '' : 'neutral' }}">{{ $account->is_active ? 'Active' : 'Inactive' }}</span></td>
                        </tr>
                    @endforeach
                    <tr data-chart-empty-row hidden><td colspan="8"><div class="empty">No accounts match your search.</div></td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const search = document.querySelector('[data-chart-search]');
            const rows = Array.from(document.querySelectorAll('[data-chart-account-row]'));
            const empty = document.querySelector('[data-chart-empty-row]');
            const visibleCount = document.querySelector('[data-chart-visible-count]');

            const applySearch = () => {
                const terms = search.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
                let shown = 0;

                rows.forEach((row) => {
                    const haystack = row.dataset.searchText || '';
                    const isVisible = terms.every((term) => haystack.includes(term));

                    row.hidden = ! isVisible;
                    if (isVisible) shown += 1;
                });

                if (empty) empty.hidden = shown > 0;
                if (visibleCount) visibleCount.textContent = shown.toString();
            };

            search?.addEventListener('input', applySearch);
        });
    </script>
</x-layouts.admin>
