@php
    $money = fn (?int $minor): string => $tenant->currency_code.' '.number_format(($minor ?? 0) / 100, 2);
    $ledgerMoney = fn (int $minor): string => ($minor % 100 === 0)
        ? number_format($minor / 100, 0)
        : number_format($minor / 100, 2);
    $headline = fn (?string $value): string => \Illuminate\Support\Str::headline((string) $value);
    $journalFilterQuery = array_filter([
        'tenant' => $tenant->id,
        'journal_date_from' => $journalFilters['date_from'] ?? '',
        'journal_date_to' => $journalFilters['date_to'] ?? '',
        'journal_category' => $journalFilters['category'] ?? '',
        'journal_type' => $journalFilters['type'] ?? '',
        'journal_account' => $journalFilters['account'] ?? '',
    ], fn ($value) => $value !== '' && $value !== null);
@endphp

<x-layouts.admin title="Expenses">
    <style>
        .journal-ledger-table { border-collapse: collapse; min-width: 1080px; width: 100%; color: #0f172a; }
        .journal-ledger-table th,
        .journal-ledger-table td { border: 2px solid #111827; padding: 6px 8px; vertical-align: middle; background: #fff; }
        .journal-ledger-table th { background: #101828; color: #fff; font-size: 15px; line-height: 1.1; text-align: left; text-transform: none; }
        .journal-ledger-date,
        .journal-ledger-source { font-size: 16px; white-space: nowrap; }
        .journal-ledger-particulars { min-width: 360px; }
        .journal-ledger-ref { min-width: 150px; white-space: nowrap; }
        .journal-ledger-money { min-width: 130px; text-align: right; white-space: nowrap; }
        .journal-ledger-meta td { background: #101828; color: #fff; font-size: 15px; line-height: 1.15; font-weight: 500; }
        .journal-ledger-meta strong { font-weight: 900; }
        .journal-ledger-meta-spacer { background: #fff !important; }
        .journal-ledger-gap td { height: 22px; padding: 0; border-left-width: 2px; border-right-width: 2px; }
        .journal-filter-header { align-items: stretch; flex-wrap: wrap; }
        .journal-filter-form { display: flex; flex-wrap: wrap; gap: 10px; justify-content: flex-end; align-items: end; }
        .journal-filter-form .field { min-width: 132px; max-width: 190px; }
        .journal-filter-form .field.search { min-width: 190px; }
        .journal-filter-actions { display: flex; gap: 8px; align-items: center; }
    </style>

    <div class="topbar">
        <div>
            <div class="eyebrow">Expense management</div>
            <h1>Expenses</h1>
            <p class="subtle">Manage expense categories, operational expenses, and manual journals for {{ $tenant->name }}.</p>
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
        <div class="stat"><span class="subtle">Categories</span><strong>{{ $expenseCategories->count() }}</strong></div>
        <div class="stat"><span class="subtle">Expenses</span><strong>{{ $operationalExpenses->count() }}</strong></div>
        <div class="stat"><span class="subtle">Expense total</span><strong>{{ $money($operationalExpenses->sum('amount_minor')) }}</strong></div>
        <div class="stat"><span class="subtle">Petty cash</span><strong>{{ $money($pettyCashBalanceMinor) }}</strong></div>
    </div>

    <div class="tab-layout">
        <nav class="pill-nav" aria-label="Expense sections" role="tablist">
            <a href="#categories" role="tab" data-tab-target="categories">Categories <span class="badge neutral">{{ $expenseCategories->count() }}</span></a>
            <a href="#expense-list" role="tab" data-tab-target="expense-list">Expenses <span class="badge neutral">{{ $operationalExpenses->count() }}</span></a>
            <a href="#journals" role="tab" data-tab-target="journals">Journal entries <span class="badge neutral">{{ $journalEntries->total() }}</span></a>
        </nav>

        <div class="content-stack">
            <section class="panel tab-panel" id="categories" role="tabpanel" data-tab-panel>
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Expense categories</h2>
                        <p class="subtle">Create and edit categories used for operational expense postings.</p>
                    </div>
                    <button class="btn accent" type="button" data-dialog-open="expense-category-dialog">Add category</button>
                </div>
                <div class="panel-body" style="overflow-x: auto;">
                    <table class="table">
                        <thead><tr><th>Category</th><th>Code</th><th>Ledger account</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($expenseCategories as $category)
                                <tr>
                                    <td>{{ $category->name }}<br><span class="subtle">{{ $category->description ?: 'No description' }}</span></td>
                                    <td>{{ $category->code }}</td>
                                    <td>{{ $category->account?->code }} · {{ $category->account?->name }}</td>
                                    <td><span class="badge {{ $category->is_active ? '' : 'neutral' }}">{{ $category->is_active ? 'Active' : 'Inactive' }}</span></td>
                                    <td><button class="btn secondary" type="button" data-dialog-open="expense-category-edit-{{ $category->id }}">Edit</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><div class="empty">No expense categories yet.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel tab-panel" id="expense-list" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Expense list</h2>
                        <p class="subtle">Operational expense records and their payment status.</p>
                    </div>
                    <button class="btn accent" type="button" data-dialog-open="expense-dialog">Log expense</button>
                </div>
                <div class="panel-body" style="overflow-x: auto;">
                    <table class="table">
                        <thead><tr><th>Expense</th><th>Date</th><th>Category</th><th>Expense line</th><th>Payee</th><th>Status</th><th>Amount</th><th>Paid from</th><th>Paid</th></tr></thead>
                        <tbody>
                            @forelse ($operationalExpenses as $expense)
                                <tr><td>{{ $expense->expense_number }}<br><span class="subtle">{{ $expense->reference_number ?: 'No reference' }}</span></td><td>{{ $expense->expense_date->format('M j, Y') }}</td><td>{{ $expense->category->name }}</td><td>{{ $expense->expenseAccount?->code ?? $expense->category->account?->code }} · {{ $expense->expenseAccount?->name ?? $expense->category->account?->name }}</td><td>{{ $expense->payee_name ?: 'Not set' }}</td><td>{{ $headline($expense->payment_status) }}</td><td>{{ $money($expense->amount_minor) }}</td><td>{{ $expense->paymentAccount ? $expense->paymentAccount->code.' · '.$expense->paymentAccount->name : 'Not paid' }}</td><td>{{ $money($expense->paid_minor) }}</td></tr>
                            @empty
                                <tr><td colspan="9"><div class="empty">No operational expenses yet.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel tab-panel" id="journals" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header journal-filter-header">
                    <div>
                        <h2 class="panel-title">Journal entries</h2>
                        <p class="subtle">List ledger postings and post balanced manual journals.</p>
                    </div>
                    <form class="journal-filter-form" method="GET" action="{{ route('admin.finance.expenses') }}#journals">
                        <input type="hidden" name="tenant" value="{{ $tenant->id }}">
                        <div class="field">
                            <label>Date from</label>
                            <input name="journal_date_from" type="date" value="{{ $journalFilters['date_from'] }}">
                        </div>
                        <div class="field">
                            <label>Date to</label>
                            <input name="journal_date_to" type="date" value="{{ $journalFilters['date_to'] }}">
                        </div>
                        <div class="field">
                            <label>Category</label>
                            <select name="journal_category">
                                <option value="">All categories</option>
                                @foreach ($journalAccountCategories as $category)
                                    <option value="{{ $category }}" @selected($journalFilters['category'] === $category)>{{ $category }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>Type</label>
                            <select name="journal_type">
                                <option value="">All types</option>
                                @foreach ($journalAccountTypes as $type)
                                    <option value="{{ $type }}" @selected($journalFilters['type'] === $type)>{{ $headline($type) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field search">
                            <label>Code / name</label>
                            <input name="journal_account" value="{{ $journalFilters['account'] }}" placeholder="EXP-6130 or Utilities">
                        </div>
                        <div class="journal-filter-actions">
                            <button class="btn secondary" type="submit">Filter</button>
                            <a class="btn secondary" href="{{ route('admin.finance.expenses', ['tenant' => $tenant->id]) }}#journals">Reset</a>
                            <a class="btn secondary" href="{{ route('admin.finance.journals.download', $journalFilterQuery) }}">Download</a>
                            <button class="btn accent" type="button" data-dialog-open="journal-dialog">Post journal</button>
                        </div>
                    </form>
                </div>
                <div class="panel-body" style="overflow-x: auto;">
                    <table class="journal-ledger-table">
                        <thead>
                            <tr>
                                <th>DATE</th>
                                <th>Source</th>
                                <th>PARTICULARS</th>
                                <th>POST REF</th>
                                <th>DEBIT (DR)</th>
                                <th>CREDIT (CR)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($journalEntries as $entry)
                                @php
                                    $lines = $entry->lines;
                                    $lineCount = max(1, $lines->count());
                                    $source = match ($entry->source_type) {
                                        'hr_payroll_run' => 'Payroll posting',
                                        'finance_expense' => 'Expense posting',
                                        'vendor_payment' => 'Vendor payment posting',
                                        'purchase_order' => 'Purchase posting',
                                        'sales_order' => 'Sales posting',
                                        'sales_payment' => 'Sales payment posting',
                                        'sales_return' => 'Sales return posting',
                                        'inventory_movement' => 'Inventory posting',
                                        'till_movement' => 'Till posting',
                                        'manual_journal' => 'Manual journal',
                                        null => 'Manual journal',
                                        default => $headline($entry->source_type),
                                    };
                                    $branchNames = $lines
                                        ->map(fn ($line) => $line->branch?->name ?? 'Unassigned branch')
                                        ->unique()
                                        ->join(', ');
                                @endphp
                                @forelse ($lines as $lineIndex => $line)
                                    <tr>
                                        @if ($lineIndex === 0)
                                            <td class="journal-ledger-date" rowspan="{{ $lineCount }}">{{ $entry->entry_date->format('Y-M-d') }}</td>
                                            <td class="journal-ledger-source" rowspan="{{ $lineCount }}">{{ $source }}</td>
                                        @endif
                                        <td class="journal-ledger-particulars">{{ $line->memo ?: $line->account->name }}</td>
                                        <td class="journal-ledger-ref">{{ $line->account->code }}</td>
                                        <td class="journal-ledger-money">{{ $line->debit_minor > 0 ? $ledgerMoney((int) $line->debit_minor) : '' }}</td>
                                        <td class="journal-ledger-money">{{ $line->credit_minor > 0 ? $ledgerMoney((int) $line->credit_minor) : '' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="journal-ledger-date">{{ $entry->entry_date->format('Y-M-d') }}</td>
                                        <td class="journal-ledger-source">{{ $source }}</td>
                                        <td class="journal-ledger-particulars">No journal lines</td>
                                        <td class="journal-ledger-ref"></td>
                                        <td class="journal-ledger-money"></td>
                                        <td class="journal-ledger-money"></td>
                                    </tr>
                                @endforelse
                                <tr class="journal-ledger-meta">
                                    <td class="journal-ledger-meta-spacer"></td>
                                    <td colspan="5">
                                        <strong>Branch:</strong> {{ $branchNames ?: 'Unassigned branch' }}
                                        &nbsp;&nbsp;&nbsp;&nbsp;
                                        <strong>Memo:</strong> {{ $entry->memo }}
                                        &nbsp;&nbsp;&nbsp;&nbsp;
                                        <strong>Entry No:</strong> {{ $entry->entry_number }}
                                    </td>
                                </tr>
                                <tr class="journal-ledger-gap"><td colspan="6"></td></tr>
                            @empty
                                <tr><td colspan="6"><div class="empty">No journal entries yet.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div style="margin-top: 14px;">
                        {{ $journalEntries->links() }}
                    </div>
                </div>
            </section>
        </div>
    </div>

    <dialog class="dialog" id="expense-category-dialog">
        <div class="dialog-header"><div><h2 class="panel-title">Add expense category</h2><p class="subtle">Create a category and linked expense ledger account.</p></div><button class="icon-btn" type="button" data-dialog-close>&times;</button></div>
        <div class="dialog-body">
            <form class="mini-form" method="POST" action="{{ route('admin.finance.expense-categories.store') }}">
                @csrf
                <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                <div class="form-grid">
                    <div class="field"><label>Category name</label><input name="name" required></div>
                    <div class="field"><label>Code</label><input name="code" required></div>
                    <div class="field full"><label>Description</label><textarea name="description"></textarea></div>
                </div>
                <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Cancel</button><button class="btn accent" type="submit">Create category</button></div>
            </form>
        </div>
    </dialog>

    @foreach ($expenseCategories as $category)
        <dialog class="dialog" id="expense-category-edit-{{ $category->id }}">
            <div class="dialog-header"><div><h2 class="panel-title">Edit expense category</h2><p class="subtle">{{ $category->account?->code }} · {{ $category->account?->name }}</p></div><button class="icon-btn" type="button" data-dialog-close>&times;</button></div>
            <div class="dialog-body">
                <form class="mini-form" method="POST" action="{{ route('admin.finance.expense-categories.update', $category) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                    <div class="form-grid">
                        <div class="field"><label>Category name</label><input name="name" value="{{ $category->name }}" required></div>
                        <div class="field"><label>Code</label><input name="code" value="{{ $category->code }}" required></div>
                        <div class="field full"><label>Description</label><textarea name="description">{{ $category->description }}</textarea></div>
                        <label class="field full" style="display: flex; gap: 8px; align-items: center;"><input type="checkbox" name="is_active" value="1" @checked($category->is_active)> Active</label>
                    </div>
                    <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Cancel</button><button class="btn accent" type="submit">Save category</button></div>
                </form>
            </div>
        </dialog>
    @endforeach

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
                    <div class="field"><label>Branch</label><select name="branch_id"><option value="">Unassigned</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></div>
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

    <dialog class="dialog" id="journal-dialog">
        <div class="dialog-header"><div><h2 class="panel-title">Post journal</h2><p class="subtle">Debits and credits must balance before posting.</p></div><button class="icon-btn" type="button" data-dialog-close>&times;</button></div>
        <div class="dialog-body">
            <form class="mini-form" method="POST" action="{{ route('admin.finance.journals.store') }}">
                @csrf
                <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                <div class="form-grid">
                    <div class="field"><label>Entry date</label><input name="entry_date" type="date" value="{{ now()->toDateString() }}" required></div>
                    <div class="field"><label>Memo</label><input name="memo" required></div>
                </div>
                <table class="table">
                    <thead><tr><th>Account</th><th>Branch</th><th>Debit</th><th>Credit</th><th>Memo</th></tr></thead>
                    <tbody>
                        @for ($index = 0; $index < 4; $index++)
                            <tr>
                                <td><select name="lines[{{ $index }}][account_code]"><option value="">Choose account</option>@foreach ($accounts as $account)<option value="{{ $account->code }}">{{ $account->code }} · {{ $account->name }}</option>@endforeach</select></td>
                                <td><select name="lines[{{ $index }}][branch_id]"><option value="">Unassigned</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></td>
                                <td><input name="lines[{{ $index }}][debit]" inputmode="decimal" data-money-input></td>
                                <td><input name="lines[{{ $index }}][credit]" inputmode="decimal" data-money-input></td>
                                <td><input name="lines[{{ $index }}][memo]"></td>
                            </tr>
                        @endfor
                    </tbody>
                </table>
                <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Cancel</button><button class="btn accent" type="submit">Post journal</button></div>
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
