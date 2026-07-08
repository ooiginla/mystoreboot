@php
    $money = fn (?int $minor): string => $tenant->currency_code.' '.number_format(($minor ?? 0) / 100, 2);
    $headline = fn (?string $value): string => \Illuminate\Support\Str::headline((string) $value);
    $activeBranchForView = app(\App\Support\ActiveBranchManager::class)->stateForRequest(request(), auth()->user())['activeBranch'];
    $expenseRows = $operationalExpenses->getCollection();
@endphp

<x-layouts.admin title="Expenses">
    <style>
        .expense-filter-header { align-items: stretch; flex-wrap: wrap; }
        .expense-filter-form { display: flex; flex-wrap: wrap; gap: 10px; justify-content: flex-end; align-items: end; }
        .expense-filter-form .field { min-width: 132px; max-width: 190px; }
        .expense-filter-form .field.search { min-width: 190px; }
        .expense-filter-actions { display: flex; gap: 8px; align-items: center; }
    </style>

    <div class="topbar">
        <div>
            <div class="eyebrow">Expense management</div>
            <h1>Expenses</h1>
            <p class="subtle">Log, search, and review operational expenses for {{ $tenant->name }}.</p>
        </div>
        @if ($isPlatformAdmin)
            <form method="GET" action="{{ route('admin.finance.expenses') }}" style="min-width: 260px;">
                <select name="tenant" onchange="this.form.submit()">
                    @foreach ($tenants as $visibleTenant)
                        <option value="{{ $visibleTenant->id }}" @selected($visibleTenant->id === $tenant->id)>{{ $visibleTenant->name }}</option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    @if (session('status'))
        <div class="alert">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert errors"><strong>Check the expense details.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="stats-grid" style="margin-bottom: 18px;">
        <div class="stat"><span class="subtle">Filtered records</span><strong>{{ $operationalExpenses->total() }}</strong></div>
        <div class="stat"><span class="subtle">Page expense total</span><strong>{{ $money((int) $expenseRows->sum('amount_minor')) }}</strong></div>
        <div class="stat"><span class="subtle">Page paid total</span><strong>{{ $money((int) $expenseRows->sum('paid_minor')) }}</strong></div>
        <div class="stat"><span class="subtle">Petty cash</span><strong>{{ $money($pettyCashBalanceMinor) }}</strong></div>
    </div>

    <section class="panel" id="expense-list">
        <div class="panel-header expense-filter-header">
            <div>
                <h2 class="panel-title">Expense list</h2>
                <p class="subtle">Filter by date, category, payment status, accounts, payee, or reference.</p>
            </div>
            <form class="expense-filter-form" method="GET" action="{{ route('admin.finance.expenses') }}#expense-list">
                <input type="hidden" name="tenant" value="{{ $tenant->id }}">
                <div class="field">
                    <label>Date from</label>
                    <input name="expense_date_from" type="date" value="{{ $expenseFilters['date_from'] }}">
                </div>
                <div class="field">
                    <label>Date to</label>
                    <input name="expense_date_to" type="date" value="{{ $expenseFilters['date_to'] }}">
                </div>
                <div class="field">
                    <label>Category</label>
                    <select name="expense_category_id">
                        <option value="">All categories</option>
                        @foreach ($expenseCategories as $category)
                            <option value="{{ $category->id }}" @selected((string) $category->id === $expenseFilters['category'])>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Status</label>
                    <select name="expense_payment_status">
                        <option value="">All statuses</option>
                        @foreach (['paid', 'partially_paid', 'unpaid'] as $status)
                            <option value="{{ $status }}" @selected($expenseFilters['status'] === $status)>{{ $headline($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Expense line</label>
                    <select name="expense_account_code">
                        <option value="">All lines</option>
                        @foreach ($expenseAccounts as $account)
                            <option value="{{ $account->code }}" @selected($expenseFilters['expense_account'] === $account->code)>{{ $account->code }} · {{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Paid from</label>
                    <select name="expense_payment_account_code">
                        <option value="">All accounts</option>
                        @foreach ($assetAccounts as $account)
                            <option value="{{ $account->code }}" @selected($expenseFilters['payment_account'] === $account->code)>{{ $account->code }} · {{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field search">
                    <label>Payee</label>
                    <input name="expense_payee" value="{{ $expenseFilters['payee'] }}" placeholder="Payee name">
                </div>
                <div class="field search">
                    <label>Reference</label>
                    <input name="expense_reference" value="{{ $expenseFilters['reference'] }}" placeholder="Number, reference, note">
                </div>
                <div class="expense-filter-actions">
                    <button class="btn secondary" type="submit">Filter</button>
                    <a class="btn secondary" href="{{ route('admin.finance.expenses', ['tenant' => $tenant->id]) }}#expense-list">Reset</a>
                    <button class="btn accent" type="button" data-dialog-open="expense-dialog">Log expense</button>
                </div>
            </form>
        </div>
        <div class="panel-body" style="overflow-x: auto;">
            <table class="table">
                <thead><tr><th>Expense</th><th>Date</th><th>Category</th><th>Expense line</th><th>Payee</th><th>Status</th><th>Amount</th><th>Paid from</th><th>Paid</th></tr></thead>
                <tbody>
                    @forelse ($operationalExpenses as $expense)
                        <tr>
                            <td>{{ $expense->expense_number }}<br><span class="subtle">{{ $expense->reference_number ?: 'No reference' }}</span></td>
                            <td>{{ $expense->expense_date->format('M j, Y') }}</td>
                            <td>{{ $expense->category->name }}</td>
                            <td>{{ $expense->expenseAccount?->code ?? $expense->category->account?->code }} · {{ $expense->expenseAccount?->name ?? $expense->category->account?->name }}</td>
                            <td>{{ $expense->payee_name ?: 'Not set' }}</td>
                            <td>{{ $headline($expense->payment_status) }}</td>
                            <td>{{ $money($expense->amount_minor) }}</td>
                            <td>{{ $expense->paymentAccount ? $expense->paymentAccount->code.' · '.$expense->paymentAccount->name : 'Not paid' }}</td>
                            <td>{{ $money($expense->paid_minor) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="empty">No operational expenses match the current filters.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
            <div style="margin-top: 14px;">
                {{ $operationalExpenses->links() }}
            </div>
        </div>
    </section>

    <dialog class="dialog" id="expense-dialog">
        <div class="dialog-header"><div><h2 class="panel-title">Log expense</h2><p class="subtle">Post an operational expense and matching journal entry.</p></div><button class="icon-btn" type="button" data-dialog-close>&times;</button></div>
        <div class="dialog-body">
            <form class="mini-form" method="POST" action="{{ route('admin.finance.expenses.store') }}">
                @csrf
                <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                <div class="form-grid">
                    <div class="field"><label>Category</label><select name="expense_category" required data-expense-category-select>@foreach ($expenseAccountCategories as $category)<option value="{{ $category }}" @selected(old('expense_category') === $category)>{{ $category }}</option>@endforeach</select></div>
                    <div class="field"><label>Expense line</label><select name="expense_account_code" required data-expense-line-select>@foreach ($expenseAccounts as $account)<option value="{{ $account->code }}" data-expense-category="{{ $account->category }}" @selected(old('expense_account_code') === $account->code)>{{ $account->code }} · {{ $account->name }}</option>@endforeach</select></div>
                    <div class="field"><label>Date</label><input name="expense_date" type="date" value="{{ now()->toDateString() }}" required></div>
                    <div class="field"><label>Branch</label><select name="branch_id"><option value="">Unassigned</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected((int) old('branch_id', $activeBranchForView?->id) === $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
                    <div class="field"><label>Payee</label><input name="payee_name"></div>
                    <div class="field"><label>Paid from</label><select name="payment_account_code"><option value="">Only for unpaid expenses</option>@foreach ($assetAccounts as $account)<option value="{{ $account->code }}" @selected(old('payment_account_code') === $account->code)>{{ $account->code }} · {{ $account->name }}</option>@endforeach</select></div>
                    <div class="field"><label>Status</label><select name="payment_status"><option value="paid">Paid</option><option value="partially_paid">Partially paid</option><option value="unpaid">Unpaid</option></select></div>
                    <div class="field"><label>Amount</label><input name="amount" inputmode="decimal" data-money-input required></div>
                    <div class="field"><label>Paid amount</label><input name="paid_amount" inputmode="decimal" data-money-input></div>
                    <div class="field"><label>Reference</label><input name="reference_number"></div>
                    <div class="field full"><label>Description</label><textarea name="description"></textarea></div>
                </div>
                <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Cancel</button><button class="btn accent" type="submit">Log expense</button></div>
            </form>
        </div>
    </dialog>

    <script>
        document.querySelectorAll('[data-expense-category-select]').forEach((categorySelect) => {
            const form = categorySelect.closest('form');
            const lineSelect = form?.querySelector('[data-expense-line-select]');

            if (! lineSelect) {
                return;
            }

            const syncExpenseLines = () => {
                const category = categorySelect.value;
                let firstVisible = null;
                let selectedVisible = false;

                lineSelect.querySelectorAll('option').forEach((option) => {
                    const visible = option.dataset.expenseCategory === category;
                    option.hidden = ! visible;
                    option.disabled = ! visible;

                    if (visible && firstVisible === null) {
                        firstVisible = option;
                    }

                    if (visible && option.selected) {
                        selectedVisible = true;
                    }
                });

                if (! selectedVisible && firstVisible) {
                    lineSelect.value = firstVisible.value;
                }
            };

            categorySelect.addEventListener('change', syncExpenseLines);
            syncExpenseLines();
        });
    </script>
</x-layouts.admin>
