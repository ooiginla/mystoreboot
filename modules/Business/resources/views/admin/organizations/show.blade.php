<x-layouts.admin title="{{ $tenant->name }}">
    <div class="topbar">
        <div>
            <div class="eyebrow">Organization details</div>
            <h1>{{ $tenant->name }}</h1>
            <p class="subtle">{{ $tenant->slug }} · {{ $tenant->business_type ?? 'Business type not set' }}</p>
        </div>
        <a class="btn primary" href="{{ route('admin.business.index', ['tenant' => $tenant->id]) }}">Manage setup</a>
    </div>

    <div class="stats-grid" style="margin-bottom: 18px;">
        <div class="stat"><span class="subtle">Status</span><strong>{{ $tenant->status }}</strong></div>
        <div class="stat"><span class="subtle">Plan</span><strong>{{ $selectedPlan ?? 'None' }}</strong></div>
        <div class="stat"><span class="subtle">Branches</span><strong>{{ $tenant->branches->count() }}</strong></div>
        <div class="stat"><span class="subtle">Users</span><strong>{{ $memberships->count() }}</strong></div>
    </div>

    <div class="grid">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Business profile</h2>
                    <p class="subtle">Registered organization information.</p>
                </div>
            </div>
            <div class="panel-body">
                <div class="form-grid">
                    <div><span class="subtle">Email</span><br><strong>{{ $tenant->email ?: 'Not set' }}</strong></div>
                    <div><span class="subtle">Phone</span><br><strong>{{ $tenant->phone ?: 'Not set' }}</strong></div>
                    <div><span class="subtle">Currency</span><br><strong>{{ $tenant->currency_code }}</strong></div>
                    <div><span class="subtle">Tax rate</span><br><strong>{{ $tenant->default_tax_rate }}%</strong></div>
                    <div class="field full"><span class="subtle">Address</span><br><strong>{{ $tenant->address ?: 'Not set' }}</strong></div>
                </div>
            </div>
        </section>

        <aside class="stack">
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Branches</h2>
                        <p class="subtle">{{ $tenant->branches->count() }} configured</p>
                    </div>
                </div>
                <div class="panel-body list">
                    @forelse ($tenant->branches as $branch)
                        <div class="item">
                            <div>
                                <div class="item-title">{{ $branch->name }}</div>
                                <div class="subtle">{{ $branch->code }} · {{ $branch->status }}</div>
                            </div>
                            @if ($branch->is_primary)
                                <span class="badge">Primary</span>
                            @endif
                        </div>
                    @empty
                        <div class="empty">No branches yet.</div>
                    @endforelse
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Users</h2>
                        <p class="subtle">{{ $memberships->count() }} membership records</p>
                    </div>
                </div>
                <div class="panel-body list">
                    @forelse ($memberships as $membership)
                        <div class="item">
                            <div>
                                <div class="item-title">{{ $membership->user->name }}</div>
                                <div class="subtle">
                                    {{ $membership->user->email }}
                                    @if ($membership->role) · {{ $membership->role->name }} @endif
                                </div>
                            </div>
                            <span class="badge neutral">{{ $membership->status }}</span>
                        </div>
                    @empty
                        <div class="empty">No users attached to this organization yet.</div>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>
</x-layouts.admin>
