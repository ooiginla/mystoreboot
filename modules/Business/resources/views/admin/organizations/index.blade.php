<x-layouts.admin title="Organizations">
    <div class="topbar">
        <div>
            <div class="eyebrow">Platform administration</div>
            <h1>Organizations</h1>
            <p class="subtle">Platform-admin view of every tenant registered on Storeboot.</p>
        </div>
        <a class="btn accent" href="{{ route('admin.business.index', ['new' => 1]) }}">Create new organization</a>
    </div>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Tenant directory</h2>
                <p class="subtle">{{ $tenants->total() }} organizations found</p>
            </div>
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
                            <tr>
                                <td>
                                    <strong>{{ $tenant->name }}</strong><br>
                                    <span class="subtle">{{ $tenant->email ?: $tenant->slug }}</span>
                                </td>
                                <td>{{ $tenant->business_type ?? 'Not set' }}</td>
                                <td><span class="badge neutral">{{ $tenant->status }}</span></td>
                                <td>{{ $tenant->branches_count }}</td>
                                <td>{{ $tenant->roles_count }}</td>
                                <td><a class="btn secondary" href="{{ route('admin.business.organizations.show', $tenant) }}">Details</a></td>
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
</x-layouts.admin>
