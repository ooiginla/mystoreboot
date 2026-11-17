<dialog class="dialog" id="{{ $resetDialogId }}">
    <div class="dialog-header">
        <div>
            <h2 class="panel-title">Reset {{ $tenant->name }}</h2>
            <p class="subtle">Clear all transactions so this business starts from a clean dashboard.</p>
        </div>
        <button class="icon-btn" type="button" data-dialog-close aria-label="Close">✕</button>
    </div>
    <div class="dialog-body">
        <div class="alert errors">
            This permanently removes <strong>all transactional data</strong>: sales orders &amp; payments, returns, journals, expenses, till sessions, online settlements, wallet activity, inventory movements, stock counts, purchase orders &amp; goods receipts, production runs, reseller orders and payroll runs. Inventory stock, wallet balances and customer balances are reset to <strong>0</strong>.
        </div>
        <div class="alert success">
            Kept intact: the account, settings &amp; configuration, products &amp; categories, branches, roles, users, customers, vendors, payment accounts and the online store setup.
        </div>

        <form class="mini-form" method="POST" action="{{ route('admin.business.organizations.reset', $tenant) }}">
            @csrf

            <div class="field">
                <label for="{{ $resetDialogId }}-confirmation">Enter <strong>{{ $tenant->name }}</strong> to confirm</label>
                <input
                    id="{{ $resetDialogId }}-confirmation"
                    name="confirmation"
                    autocomplete="off"
                    required
                >
            </div>

            <div class="dialog-actions">
                <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                <button class="btn warning" type="submit">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 12a9 9 0 1 0 3-6.7L3 8"/>
                        <path d="M3 3v5h5"/>
                    </svg>
                    Reset transactions
                </button>
            </div>
        </form>
    </div>
</dialog>
