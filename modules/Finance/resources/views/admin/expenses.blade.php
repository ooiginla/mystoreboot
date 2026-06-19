@php
    $money = fn (?int $minor): string => $tenant->currency_code.' '.number_format(($minor ?? 0) / 100, 2);
    $headline = fn (?string $value): string => \Illuminate\Support\Str::headline((string) $value);
@endphp

<x-layouts.admin title="Expenses">
    <div class="topbar">
        <div>
            <div class="eyebrow">Expense management</div>
            <h1>Expenses</h1>
            <p class="subtle">Manage expense categories, operational expenses, and petty cash for {{ $tenant->name }}.</p>
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
            <a href="#petty-cash" role="tab" data-tab-target="petty-cash">Petty cash <span class="badge neutral">{{ $pettyCashTransactions->count() }}</span></a>
            <a href="#journals" role="tab" data-tab-target="journals">Journal entries <span class="badge neutral">{{ $journalEntries->count() }}</span></a>
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
                        <thead><tr><th>Expense</th><th>Date</th><th>Category</th><th>Payee</th><th>Status</th><th>Amount</th><th>Paid</th></tr></thead>
                        <tbody>
                            @forelse ($operationalExpenses as $expense)
                                <tr><td>{{ $expense->expense_number }}<br><span class="subtle">{{ $expense->reference_number ?: 'No reference' }}</span></td><td>{{ $expense->expense_date->format('M j, Y') }}</td><td>{{ $expense->category->name }}</td><td>{{ $expense->payee_name ?: 'Not set' }}</td><td>{{ $headline($expense->payment_status) }}</td><td>{{ $money($expense->amount_minor) }}</td><td>{{ $money($expense->paid_minor) }}</td></tr>
                            @empty
                                <tr><td colspan="7"><div class="empty">No operational expenses yet.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel tab-panel" id="petty-cash" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Petty cash management</h2>
                        <p class="subtle">Petty cash top-ups, expenses, and returns to bank.</p>
                    </div>
                    <button class="btn accent" type="button" data-dialog-open="petty-cash-dialog">Post petty cash</button>
                </div>
                <div class="panel-body" style="overflow-x: auto;">
                    <table class="table">
                        <thead><tr><th>Transaction</th><th>Date</th><th>Type</th><th>Category</th><th>Amount</th><th>Reference</th></tr></thead>
                        <tbody>
                            @forelse ($pettyCashTransactions as $transaction)
                                <tr><td>{{ $transaction->transaction_number }}</td><td>{{ $transaction->transaction_date->format('M j, Y') }}</td><td>{{ $headline($transaction->transaction_type) }}</td><td>{{ $transaction->category?->name ?? 'Not applicable' }}</td><td>{{ $money($transaction->amount_minor) }}</td><td>{{ $transaction->reference_number ?: 'Not set' }}</td></tr>
                            @empty
                                <tr><td colspan="6"><div class="empty">No petty cash transactions yet.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel tab-panel" id="journals" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Journal entries</h2>
                        <p class="subtle">List ledger postings and post balanced manual journals.</p>
                    </div>
                    <button class="btn accent" type="button" data-dialog-open="journal-dialog">Post journal</button>
                </div>
                <div class="panel-body" style="overflow-x: auto;">
                    <table class="table">
                        <thead><tr><th>Entry</th><th>Date</th><th>Memo</th><th>Source</th><th>Lines</th></tr></thead>
                        <tbody>
                            @forelse ($journalEntries as $entry)
                                <tr>
                                    <td>{{ $entry->entry_number }}</td>
                                    <td>{{ $entry->entry_date->format('M j, Y') }}</td>
                                    <td>{{ $entry->memo }}</td>
                                    <td>{{ $entry->source_type ? $headline($entry->source_type) : 'Manual' }}</td>
                                    <td>
                                        @foreach ($entry->lines as $line)
                                            <div>{{ $line->account->code }} {{ $line->account->name }} · Dr {{ $money($line->debit_minor) }} · Cr {{ $money($line->credit_minor) }}</div>
                                        @endforeach
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><div class="empty">No journal entries yet.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
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
                    <div class="field"><label>Category</label><select name="finance_expense_category_id" required>@foreach ($expenseCategories->where('is_active', true) as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></div>
                    <div class="field"><label>Date</label><input name="expense_date" type="date" value="{{ now()->toDateString() }}" required></div>
                    <div class="field"><label>Payee</label><input name="payee_name"></div>
                    <div class="field"><label>Payment method</label><select name="payment_method"><option>Cash</option><option>Bank transfer</option><option>POS/Card</option><option>Petty cash</option></select></div>
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

    <dialog class="dialog" id="petty-cash-dialog">
        <div class="dialog-header"><div><h2 class="panel-title">Post petty cash</h2><p class="subtle">Record a top-up, expense, or return to bank.</p></div><button class="icon-btn" type="button" data-dialog-close>&times;</button></div>
        <div class="dialog-body">
            <form class="mini-form" method="POST" action="{{ route('admin.finance.petty-cash.store') }}">
                @csrf
                <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                <div class="form-grid">
                    <div class="field"><label>Type</label><select name="transaction_type"><option value="top_up">Top up</option><option value="expense">Expense</option><option value="return_to_bank">Return to bank</option></select></div>
                    <div class="field"><label>Date</label><input name="transaction_date" type="date" value="{{ now()->toDateString() }}" required></div>
                    <div class="field"><label>Expense category</label><select name="finance_expense_category_id"><option value="">Only for expenses</option>@foreach ($expenseCategories->where('is_active', true) as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></div>
                    <div class="field"><label>Amount</label><input name="amount" inputmode="decimal" data-money-input required></div>
                    <div class="field"><label>Payee</label><input name="payee_name"></div>
                    <div class="field"><label>Reference</label><input name="reference_number"></div>
                    <div class="field full"><label>Description</label><textarea name="description"></textarea></div>
                </div>
                <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Cancel</button><button class="btn accent" type="submit">Post transaction</button></div>
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
                    <thead><tr><th>Account</th><th>Debit</th><th>Credit</th><th>Memo</th></tr></thead>
                    <tbody>
                        @for ($index = 0; $index < 4; $index++)
                            <tr>
                                <td><select name="lines[{{ $index }}][account_code]"><option value="">Choose account</option>@foreach ($accounts as $account)<option value="{{ $account->code }}">{{ $account->code }} · {{ $account->name }}</option>@endforeach</select></td>
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
</x-layouts.admin>
