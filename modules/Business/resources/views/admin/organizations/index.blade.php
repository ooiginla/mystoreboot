<x-layouts.admin title="Organizations">
    <style>
        .organization-actions { display: flex; justify-content: flex-end; gap: 8px; flex-wrap: wrap; }
        .organization-search { display: flex; align-items: end; gap: 8px; flex-wrap: wrap; }
        .organization-search .field { min-width: min(320px, 100%); }
        .organization-search input { padding-top: 8px; padding-bottom: 8px; }
        @media (max-width: 700px) {
            .organization-search, .organization-search .field { width: 100%; }
            .organization-actions { justify-content: flex-start; }
        }
    </style>

    <div class="topbar">
        <div>
            <div class="eyebrow">Platform administration</div>
            <h1>Organizations</h1>
            <p class="subtle">Platform-admin view of every tenant registered on Storeboot.</p>
        </div>
        <a class="btn accent" href="{{ route('admin.business.index', ['new' => 1]) }}">Create new organization</a>
    </div>

    @if (session('status'))<div class="alert success">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="alert errors">{{ $errors->first() }}</div>@endif

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Tenant directory</h2>
                <p class="subtle">
                    {{ $tenants->total() }} {{ $organizationSearch !== '' ? 'matching' : '' }} organizations found
                </p>
            </div>
            <form class="organization-search" method="GET" action="{{ route('admin.business.organizations.index') }}">
                <div class="field">
                    <label for="organization-search">Search organizations</label>
                    <input
                        id="organization-search"
                        name="organization_search"
                        type="search"
                        value="{{ $organizationSearch }}"
                        placeholder="Name, email, address or business type"
                        autofocus
                        data-organization-search
                    >
                </div>
                @if ($organizationSearch !== '')
                    <a class="btn ghost" href="{{ route('admin.business.organizations.index') }}">Clear</a>
                @endif
            </form>
        </div>
        <div class="panel-body">
            @if ($tenants->isEmpty())
                <div class="empty">No organizations have been registered yet.</div>
            @else
                <table class="table">
                    <thead>
                        <tr>
                            <th>Organization</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Branches</th>
                            <th>Roles</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tenants as $tenant)
                            <tr data-organization-id="{{ $tenant->id }}">
                                <td>
                                    <strong>{{ $tenant->name }}</strong><br>
                                    <span class="subtle">{{ $tenant->email ?: $tenant->slug }}</span>
                                </td>
                                <td>{{ $tenant->business_type ?? 'Not set' }}</td>
                                <td><span class="badge neutral">{{ $tenant->status->label() }}</span></td>
                                <td>{{ $tenant->branches_count }}</td>
                                <td>{{ $tenant->roles_count }}</td>
                                <td>
                                    <div class="organization-actions">
                                        @if ((string) $activeOrganizationId === (string) $tenant->id)
                                            <span class="badge success">Active organization</span>
                                        @else
                                            <form method="POST" action="{{ route('admin.business.organizations.activate', $tenant) }}">
                                                @csrf
                                                @if ($organizationSearch !== '')
                                                    <input type="hidden" name="organization_search" value="{{ $organizationSearch }}">
                                                @endif
                                                <button class="btn accent" type="submit">Make active</button>
                                            </form>
                                        @endif
                                        <a class="btn secondary" href="{{ route('admin.business.organizations.show', $tenant) }}">Details</a>
                                        <button class="btn danger" type="button" data-dialog-open="delete-organization-{{ $tenant->id }}">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div style="margin-top: 16px;">
                    {{ $tenants->links() }}
                </div>
            @endif
        </div>
    </section>

    @foreach ($tenants as $tenant)
        @include('business::admin.organizations.partials.delete-dialog', [
            'tenant' => $tenant,
            'deleteDialogId' => 'delete-organization-'.$tenant->id,
        ])
    @endforeach

    <script>
        (function () {
            const input = document.querySelector('[data-organization-search]');
            const form = input?.closest('form');
            if (!input || !form) return;

            let timer;
            let composing = false;

            function submitAfterPause() {
                window.clearTimeout(timer);
                timer = window.setTimeout(() => form.requestSubmit(), 350);
            }

            input.addEventListener('compositionstart', () => {
                composing = true;
                window.clearTimeout(timer);
            });
            input.addEventListener('compositionend', () => {
                composing = false;
                submitAfterPause();
            });
            input.addEventListener('input', () => {
                if (!composing) submitAfterPause();
            });

            if (input.value !== '') {
                input.focus();
                input.setSelectionRange(input.value.length, input.value.length);
            }
        })();
    </script>
</x-layouts.admin>
