<dialog class="dialog" id="{{ $deleteDialogId }}">
    <div class="dialog-header">
        <div>
            <h2 class="panel-title">Delete {{ $tenant->name }}</h2>
            <p class="subtle">This permanently deletes the organization and all of its related data.</p>
        </div>
        <button class="icon-btn" type="button" data-dialog-close aria-label="Close">✕</button>
    </div>
    <div class="dialog-body">
        <div class="alert errors">
            This cannot be undone. Branches, users’ memberships, products, inventory, sales, customers, finance records, subscriptions, and all other organization records will be deleted.
        </div>

        <form class="mini-form" method="POST" action="{{ route('admin.business.organizations.destroy', $tenant) }}">
            @csrf
            @method('DELETE')

            <div class="field">
                <label for="{{ $deleteDialogId }}-confirmation">Enter <strong>{{ $tenant->name }}</strong> to confirm</label>
                <input
                    id="{{ $deleteDialogId }}-confirmation"
                    name="confirmation"
                    autocomplete="off"
                    required
                >
            </div>

            <div class="dialog-actions">
                <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                <button class="btn danger" type="submit">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 6h18"/>
                        <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                    </svg>
                    Permanently delete organization
                </button>
            </div>
        </form>
    </div>
</dialog>
