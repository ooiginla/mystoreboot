@php
    $money = fn (?int $minor): string => $tenant->currency_code.' '.number_format(($minor ?? 0) / 100, 2);
    $ledgerMoney = fn (int $minor): string => ($minor % 100 === 0)
        ? number_format($minor / 100, 0)
        : number_format($minor / 100, 2);
    $headline = fn (?string $value): string => \Illuminate\Support\Str::headline((string) $value);
    $activeBranchForView = app(\App\Support\ActiveBranchManager::class)->stateForRequest(request(), auth()->user())['activeBranch'];
    $journalFilterQuery = array_filter([
        'tenant' => $tenant->id,
        'journal_date_from' => $journalFilters['date_from'] ?? '',
        'journal_date_to' => $journalFilters['date_to'] ?? '',
        'journal_category' => $journalFilters['category'] ?? '',
        'journal_type' => $journalFilters['type'] ?? '',
        'journal_account' => $journalFilters['account'] ?? '',
        'journal_branch_id' => $journalFilters['branch'] ?? '',
    ], fn ($value) => $value !== '' && $value !== null);
@endphp

<x-layouts.admin title="Journals">
    <style>
        .chart-search { min-width: min(360px, 100%); }
        .chart-search-meta { margin-top: 6px; }
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
        .banking-actions { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 18px; }
        .banking-action { border: 1px solid var(--line); border-radius: var(--radius-sm); padding: 14px; background: #fff; display: grid; gap: 8px; }
        .banking-action strong { font-size: 14px; }
        .banking-action .balance { font-size: 18px; font-weight: 800; color: var(--brand-strong); }
        @media (max-width: 1100px) { .banking-actions { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 700px) { .banking-actions { grid-template-columns: 1fr; } }
    </style>

    <div class="topbar">
        <div>
            <div class="eyebrow">Finance ledger</div>
            <h1>Journals</h1>
            <p class="subtle">Review accounts, maintain expense categories, and post or inspect journal entries for {{ $tenant->name }}.</p>
        </div>
        @if ($isPlatformAdmin)
            <form method="GET" action="{{ route('admin.finance.journals') }}" style="min-width: 260px;">
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
        <div class="alert errors"><strong>Check the journal details.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="stats-grid" style="margin-bottom: 18px;">
        <div class="stat"><span class="subtle">Accounts</span><strong>{{ $accounts->count() }}</strong></div>
        <div class="stat"><span class="subtle">Expense categories</span><strong>{{ $expenseCategories->count() }}</strong></div>
        <div class="stat"><span class="subtle">Bank movements</span><strong>{{ $bankMovements->count() }}</strong></div>
        <div class="stat"><span class="subtle">Journal entries</span><strong>{{ $journalEntries->total() }}</strong></div>
        <div class="stat"><span class="subtle">Visible lines</span><strong>{{ $journalEntries->getCollection()->sum(fn ($entry) => $entry->lines->count()) }}</strong></div>
    </div>

    <div class="tab-layout">
        <nav class="pill-nav" aria-label="Journal sections" role="tablist">
            <a href="#chart-of-accounts" role="tab" data-tab-target="chart-of-accounts">Chart of Accounts <span class="badge neutral">{{ $accounts->count() }}</span></a>
            <a href="#banking" role="tab" data-tab-target="banking">Banking <span class="badge neutral">{{ $bankMovements->count() }}</span></a>
            <a href="#expense-categories" role="tab" data-tab-target="expense-categories">Expense Categories <span class="badge neutral">{{ $expenseCategories->count() }}</span></a>
            <a href="#journal-entries" role="tab" data-tab-target="journal-entries">Journal Entries <span class="badge neutral">{{ $journalEntries->total() }}</span></a>
            <a href="#branch-ledger" role="tab" data-tab-target="branch-ledger">Branch Ledger</a>
            <a href="#receivables-payables" role="tab" data-tab-target="receivables-payables">Receivables &amp; Payables</a>
        </nav>

        <div class="content-stack">
            <section class="panel tab-panel" id="chart-of-accounts" role="tabpanel" data-tab-panel>
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Chart of Accounts</h2>
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
                        <thead><tr><th>Code</th><th>Account</th><th>Category</th><th>Description</th><th>Type</th><th>Normal balance</th><th>System</th><th>Status</th></tr></thead>
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

            <section class="panel tab-panel" id="banking" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Banking</h2>
                        <p class="subtle">Move cash from vault or clear transfer, POS, and online settlements into actual bank accounts.</p>
                    </div>
                    <form method="GET" action="{{ route('admin.finance.journals') }}#banking" style="display: flex; gap: 10px; align-items: end; flex-wrap: wrap;">
                        <input type="hidden" name="tenant" value="{{ $tenant->id }}">
                        <div class="field" style="min-width: 190px;">
                            <label>Branch</label>
                            <select name="journal_branch_id" onchange="this.form.submit()">
                                <option value="">All branches</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected((string) $branch->id === ($journalFilters['branch'] ?? ''))>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </form>
                    <button class="btn accent" type="button" data-dialog-open="bank-movement-dialog">Move to bank</button>
                </div>
                <div class="panel-body">
                    <div class="banking-actions">
                        @foreach ($bankMovementSources as $source)
                            <div class="banking-action">
                                <strong>{{ $source['label'] }}</strong>
                                <span class="subtle">{{ $source['code'] }} · {{ $source['account']?->name ?? 'Account pending' }}</span>
                                <span class="balance">{{ $money($source['balance_minor']) }}</span>
                                <span class="subtle">{{ $source['description'] }}</span>
                            </div>
                        @endforeach
                    </div>

                    <div style="overflow-x: auto;">
                        <table class="table">
                            <thead><tr><th>Movement</th><th>Date</th><th>Action</th><th>From</th><th>To bank</th><th>Branch</th><th>Gross</th><th>Charges</th><th>Net</th><th>Reference</th></tr></thead>
                            <tbody>
                                @forelse ($bankMovements as $movement)
                                    <tr>
                                        <td>{{ $movement->movement_number }}<br><span class="subtle">{{ $movement->postedBy?->name ?? 'System' }}</span></td>
                                        <td>{{ $movement->movement_date->format('M j, Y') }}</td>
                                        <td>{{ $headline($movement->movement_type) }}</td>
                                        <td>{{ $movement->sourceAccount?->code }} · {{ $movement->sourceAccount?->name }}</td>
                                        <td>{{ $movement->destinationAccount?->code }} · {{ $movement->destinationAccount?->name }}</td>
                                        <td>{{ $movement->branch?->name ?? 'Unassigned' }}</td>
                                        <td>{{ $money($movement->gross_amount_minor) }}</td>
                                        <td>{{ $money($movement->fee_amount_minor) }}</td>
                                        <td>{{ $money($movement->net_amount_minor) }}</td>
                                        <td>{{ $movement->reference_number ?: 'Not set' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="10"><div class="empty">No banking movements yet.</div></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="panel tab-panel" id="expense-categories" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Expense Categories</h2>
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

            <section class="panel tab-panel" id="journal-entries" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header journal-filter-header">
                    <div>
                        <h2 class="panel-title">Journal Entries</h2>
                        <p class="subtle">List ledger postings and post balanced manual journals.</p>
                    </div>
                    <form class="journal-filter-form" method="GET" action="{{ route('admin.finance.journals') }}#journal-entries">
                        <input type="hidden" name="tenant" value="{{ $tenant->id }}">
                        <div class="field"><label>Date from</label><input name="journal_date_from" type="date" value="{{ $journalFilters['date_from'] }}"></div>
                        <div class="field"><label>Date to</label><input name="journal_date_to" type="date" value="{{ $journalFilters['date_to'] }}"></div>
                        <div class="field">
                            <label>Branch</label>
                            <select name="journal_branch_id">
                                <option value="">All branches</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected((string) $branch->id === ($journalFilters['branch'] ?? ''))>{{ $branch->name }}</option>
                                @endforeach
                            </select>
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
                        <div class="field search"><label>Code / name</label><input name="journal_account" value="{{ $journalFilters['account'] }}" placeholder="EXP-6130 or Utilities"></div>
                        <div class="journal-filter-actions">
                            <button class="btn secondary" type="submit">Filter</button>
                            <a class="btn secondary" href="{{ route('admin.finance.journals', ['tenant' => $tenant->id]) }}#journal-entries">Reset</a>
                            <a class="btn secondary" href="{{ route('admin.finance.journals.download', $journalFilterQuery) }}">Download</a>
                            <button class="btn accent" type="button" data-dialog-open="journal-dialog">Post journal</button>
                        </div>
                    </form>
                </div>
                <div class="panel-body" style="overflow-x: auto;">
                    <table class="journal-ledger-table">
                        <thead><tr><th>DATE</th><th>Source</th><th>PARTICULARS</th><th>POST REF</th><th>DEBIT (DR)</th><th>CREDIT (CR)</th></tr></thead>
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
                                        'finance_bank_movement' => 'Banking movement',
                                        'manual_journal' => 'Manual journal',
                                        null => 'Manual journal',
                                        default => $headline($entry->source_type),
                                    };
                                    $branchNames = $lines->map(fn ($line) => $line->branch?->name ?? 'Unassigned branch')->unique()->join(', ');
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
                                    <tr><td class="journal-ledger-date">{{ $entry->entry_date->format('Y-M-d') }}</td><td class="journal-ledger-source">{{ $source }}</td><td class="journal-ledger-particulars">No journal lines</td><td class="journal-ledger-ref"></td><td class="journal-ledger-money"></td><td class="journal-ledger-money"></td></tr>
                                @endforelse
                                <tr class="journal-ledger-meta">
                                    <td class="journal-ledger-meta-spacer"></td>
                                    <td colspan="5"><strong>Branch:</strong> {{ $branchNames ?: 'Unassigned branch' }} &nbsp;&nbsp;&nbsp;&nbsp; <strong>Memo:</strong> {{ $entry->memo }} &nbsp;&nbsp;&nbsp;&nbsp; <strong>Entry No:</strong> {{ $entry->entry_number }}</td>
                                </tr>
                                <tr class="journal-ledger-gap"><td colspan="6"></td></tr>
                            @empty
                                <tr><td colspan="6"><div class="empty">No journal entries yet.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div style="margin-top: 14px;">{{ $journalEntries->links() }}</div>
                </div>
            </section>

            <section class="panel tab-panel" id="branch-ledger" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Branch ledger snapshot</h2>
                        <p class="subtle">{{ $selectedBranch?->name ?? 'All branches' }} · {{ \Carbon\CarbonImmutable::parse($ledgerDateFrom)->format('M j, Y') }} to {{ \Carbon\CarbonImmutable::parse($ledgerDateTo)->format('M j, Y') }}. Use the branch &amp; date filters above to change the scope.</p>
                    </div>
                </div>
                <div class="panel-body" style="overflow-x: auto;">
                    <table class="table">
                        <thead><tr><th>Account</th><th>Type</th><th>Debit</th><th>Credit</th><th>Net movement</th></tr></thead>
                        <tbody>
                            @forelse ($branchLedgerSummary as $row)
                                <tr>
                                    <td>{{ $row['account']->code }} · {{ $row['account']->name }}<br><span class="subtle">{{ $row['account']->category ?: 'Not set' }}</span></td>
                                    <td>{{ $headline($row['account']->type) }}</td>
                                    <td>{{ $money($row['debit_minor']) }}</td>
                                    <td>{{ $money($row['credit_minor']) }}</td>
                                    <td><strong>{{ $money($row['net_minor']) }}</strong></td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><div class="empty">No posted ledger activity for this branch and period.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel tab-panel" id="receivables-payables" role="tabpanel" data-tab-panel hidden>
                <div class="panel-header"><div><h2 class="panel-title">Receivables, payables &amp; party balances</h2><p class="subtle">Customer debt/credit and vendor payable/prepaid balances from posted journal entries.</p></div></div>
                <div class="panel-body summary-grid">
                    <div class="summary-item"><span>Accounts receivable</span><strong>{{ $money($partySummary['accounts_receivable_minor']) }}</strong></div>
                    <div class="summary-item"><span>Accounts payable</span><strong>{{ $money($partySummary['accounts_payable_minor']) }}</strong></div>
                    <div class="summary-item"><span>Petty cash</span><strong>{{ $money($partySummary['petty_cash_minor']) }}</strong></div>
                </div>
                <div class="panel-body" style="display: grid; gap: 18px;">
                    <div style="overflow-x: auto;">
                        <table class="table"><thead><tr><th>Customer</th><th>Debt</th><th>Credit</th></tr></thead><tbody>@forelse ($customerBalances as $row)<tr><td>{{ $row['customer']->name }}</td><td>{{ $money($row['debt_minor']) }}</td><td>{{ $money($row['credit_minor']) }}</td></tr>@empty<tr><td colspan="3"><div class="empty">No customer receivable or credit balances yet.</div></td></tr>@endforelse</tbody></table>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="table"><thead><tr><th>Vendor</th><th>Payable</th><th>Prepaid</th></tr></thead><tbody>@forelse ($vendorBalances as $row)<tr><td>{{ $row['vendor']->name }}</td><td>{{ $money($row['payable_minor']) }}</td><td>{{ $money($row['prepaid_minor']) }}</td></tr>@empty<tr><td colspan="3"><div class="empty">No vendor payable balances yet.</div></td></tr>@endforelse</tbody></table>
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

    <dialog class="dialog" id="bank-movement-dialog">
        <div class="dialog-header"><div><h2 class="panel-title">Move to bank</h2><p class="subtle">Choose the action and the actual bank account that received the money.</p></div><button class="icon-btn" type="button" data-dialog-close>&times;</button></div>
        <div class="dialog-body">
            <form class="mini-form" method="POST" action="{{ route('admin.finance.bank-movements.store') }}">
                @csrf
                <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                <div class="form-grid">
                    <div class="field">
                        <label>Action</label>
                        <select name="movement_type" required data-bank-movement-type>
                            <option value="bank_cash" data-source-code="1030">Bank Cash from Vault</option>
                            <option value="reconcile_transfer" data-source-code="1040">Reconcile Bank Transfer</option>
                            <option value="settle_pos" data-source-code="1050">Settle POS/Card</option>
                            <option value="settle_online" data-source-code="1060">Settle Online Payment</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Source</label>
                        <select name="source_account_code" required data-bank-source-select>
                            @foreach ($bankMovementSources as $source)
                                <option value="{{ $source['code'] }}">{{ $source['code'] }} · {{ $source['account']?->name ?? $source['label'] }} · {{ $money($source['balance_minor']) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>Destination bank</label>
                        <select name="destination_account_code" required>
                            <option value="">Choose actual bank account</option>
                            @foreach ($bankAccounts as $account)
                                <option value="{{ $account->code }}">{{ $account->code }} · {{ $account->name }}</option>
                            @endforeach
                        </select>
                        @if ($bankAccounts->isEmpty())
                            <span class="subtle">Add an active bank account in Business Profile first.</span>
                        @endif
                    </div>
                    <div class="field"><label>Date</label><input name="movement_date" type="date" value="{{ now()->toDateString() }}" required></div>
                    <div class="field"><label>Branch</label><select name="branch_id"><option value="">Unassigned</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected((int) old('branch_id', $activeBranchForView?->id) === $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
                    <div class="field"><label>Gross amount</label><input name="gross_amount" inputmode="decimal" data-money-input required></div>
                    <div class="field"><label>Charges</label><input name="fee_amount" inputmode="decimal" data-money-input placeholder="0.00" data-bank-fee-input></div>
                    <div class="field"><label>Reference</label><input name="reference_number" placeholder="Deposit slip, POS batch, bank ref"></div>
                    <div class="field full"><label>Notes</label><textarea name="notes"></textarea></div>
                </div>
                <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Cancel</button><button class="btn accent" type="submit" @disabled($bankAccounts->isEmpty())>Post movement</button></div>
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
                                <td><select name="lines[{{ $index }}][branch_id]"><option value="">Unassigned</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected((int) old("lines.{$index}.branch_id", $activeBranchForView?->id) === $branch->id)>{{ $branch->name }}</option>@endforeach</select></td>
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
        document.addEventListener('DOMContentLoaded', () => {
            const search = document.querySelector('[data-chart-search]');
            const rows = Array.from(document.querySelectorAll('[data-chart-account-row]'));
            const empty = document.querySelector('[data-chart-empty-row]');
            const visibleCount = document.querySelector('[data-chart-visible-count]');

            const applySearch = () => {
                const terms = (search?.value || '').trim().toLowerCase().split(/\s+/).filter(Boolean);
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

            const movementType = document.querySelector('[data-bank-movement-type]');
            const sourceSelect = document.querySelector('[data-bank-source-select]');
            const feeInput = document.querySelector('[data-bank-fee-input]');

            const syncBankMovementSource = () => {
                if (! movementType || ! sourceSelect) {
                    return;
                }

                const selected = movementType.options[movementType.selectedIndex];
                const sourceCode = selected?.dataset.sourceCode || '';
                sourceSelect.value = sourceCode;
                sourceSelect.querySelectorAll('option').forEach((option) => {
                    option.disabled = option.value !== sourceCode;
                    option.hidden = option.value !== sourceCode;
                });

                if (feeInput) {
                    feeInput.disabled = sourceCode === '1030';
                    if (feeInput.disabled) {
                        feeInput.value = '';
                    }
                }
            };

            movementType?.addEventListener('change', syncBankMovementSource);
            syncBankMovementSource();
        });
    </script>
</x-layouts.admin>
