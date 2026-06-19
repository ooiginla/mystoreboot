@php
    $settings = $tenant->settings ?? [];
    $paymentMethods = collect($settings['payment_methods'] ?? []);
    $bankDetails = collect($settings['bank_details'] ?? []);
    $seo = $settings['seo'] ?? [];
    $openingHours = collect($tenant->opening_hours ?? []);
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
@endphp

<x-layouts.admin title="{{ $tenant->name }}">
    <style>
        .profile-logo { width: 74px; height: 74px; border: 1px solid var(--line); border-radius: 8px; object-fit: cover; background: #f8fafc; }
        .profile-section { display: grid; gap: 14px; }
        .profile-section + .profile-section { margin-top: 18px; }
        .profile-section-title { margin: 0; color: #111827; font-size: 15px; font-weight: 900; }
        .profile-pill-row { display: flex; flex-wrap: wrap; gap: 8px; }
        .profile-hour-row { display: grid; grid-template-columns: 110px 1fr; gap: 12px; padding: 8px 0; border-bottom: 1px solid var(--line); }
        .profile-hour-row:last-child { border-bottom: 0; }
    </style>

    <div class="topbar">
        <div>
            <div class="eyebrow">Organization details</div>
            <h1>{{ $tenant->name }}</h1>
            <p class="subtle">{{ $tenant->slug }} · {{ $businessTypes[$tenant->business_type] ?? $tenant->business_type ?? 'Business type not set' }}</p>
        </div>
        <a class="btn primary" href="{{ route('admin.business.index', ['tenant' => $tenant->id]) }}">Manage setup</a>
    </div>

    <div class="stats-grid" style="margin-bottom: 18px;">
        <div class="stat"><span class="subtle">Status</span><strong>{{ $tenant->status->label() }}</strong></div>
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
                <div class="profile-section">
                    <h3 class="profile-section-title">Identity</h3>
                    <div class="form-grid">
                        <div style="display: flex; gap: 14px; align-items: center;">
                            @if ($tenant->logo_path)
                                <img class="profile-logo" src="{{ '/storage/'.ltrim($tenant->logo_path, '/') }}" alt="{{ $tenant->name }} logo">
                            @else
                                <div class="profile-logo" aria-hidden="true"></div>
                            @endif
                            <div><span class="subtle">Logo</span><br><strong>{{ $tenant->logo_path ? 'Uploaded' : 'Not set' }}</strong></div>
                        </div>
                        <div><span class="subtle">Business type</span><br><strong>{{ $businessTypes[$tenant->business_type] ?? $tenant->business_type ?? 'Not set' }}</strong></div>
                        <div><span class="subtle">Registration number</span><br><strong>{{ $tenant->registration_number ?: 'Not set' }}</strong></div>
                        <div><span class="subtle">Website</span><br><strong>{{ $tenant->website ?: 'Not set' }}</strong></div>
                        <div><span class="subtle">Email</span><br><strong>{{ $tenant->email ?: 'Not set' }}</strong></div>
                        <div><span class="subtle">Phone</span><br><strong>{{ $tenant->phone ?: 'Not set' }}</strong></div>
                        <div class="field full"><span class="subtle">Address</span><br><strong>{{ $tenant->address ?: 'Not set' }}</strong></div>
                    </div>
                </div>

                <div class="profile-section">
                    <h3 class="profile-section-title">Tax, locale & subscription</h3>
                    <div class="form-grid">
                        <div><span class="subtle">Country</span><br><strong>{{ $tenant->country_code }}</strong></div>
                        <div><span class="subtle">Timezone</span><br><strong>{{ $tenant->timezone }}</strong></div>
                        <div><span class="subtle">Currency</span><br><strong>{{ $tenant->currency_code }}</strong></div>
                        <div><span class="subtle">Tax identifier</span><br><strong>{{ $tenant->tax_identifier ?: 'Not set' }}</strong></div>
                        <div><span class="subtle">Default tax rate</span><br><strong>{{ $tenant->default_tax_rate }}%</strong></div>
                        <div><span class="subtle">Subscription plan</span><br><strong>{{ $selectedPlan ?? 'None' }}</strong></div>
                        <div><span class="subtle">Maintenance mode</span><br><strong>{{ ($settings['maintenance_mode'] ?? false) ? 'Enabled' : 'Disabled' }}</strong></div>
                    </div>
                </div>

                <div class="profile-section">
                    <h3 class="profile-section-title">Payment methods</h3>
                    <div class="profile-pill-row">
                        @forelse ($paymentMethods as $method)
                            <span class="badge neutral">{{ $method }}</span>
                        @empty
                            <span class="subtle">Not set</span>
                        @endforelse
                    </div>
                </div>

                <div class="profile-section">
                    <h3 class="profile-section-title">Bank details</h3>
                    <table class="table">
                        <thead><tr><th>Bank</th><th>Account name</th><th>Account number</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse ($bankDetails as $account)
                                <tr><td>{{ $account['bank_name'] ?? 'Not set' }}</td><td>{{ $account['account_name'] ?? 'Not set' }}</td><td>{{ $account['account_number'] ?? 'Not set' }}</td><td>{{ ucfirst($account['status'] ?? 'active') }}</td></tr>
                            @empty
                                <tr><td colspan="4"><div class="empty">No bank account has been added.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="profile-section">
                    <h3 class="profile-section-title">SEO and policy links</h3>
                    <div class="form-grid">
                        <div><span class="subtle">Meta title</span><br><strong>{{ $seo['meta_title'] ?? 'Not set' }}</strong></div>
                        <div><span class="subtle">Privacy policy URL</span><br><strong>{{ $seo['privacy_policy_url'] ?? 'Not set' }}</strong></div>
                        <div><span class="subtle">Terms & Conditions URL</span><br><strong>{{ $seo['terms_url'] ?? 'Not set' }}</strong></div>
                        <div class="field full"><span class="subtle">Meta description</span><br><strong>{{ $seo['meta_description'] ?? 'Not set' }}</strong></div>
                    </div>
                </div>

                <div class="profile-section">
                    <h3 class="profile-section-title">Opening and closing hours</h3>
                    <div>
                        @foreach ($days as $day)
                            @php
                                $hours = $openingHours->get($day, []);
                                $isOpen = (bool) ($hours['is_open'] ?? false);
                            @endphp
                            <div class="profile-hour-row">
                                <strong>{{ ucfirst($day) }}</strong>
                                <span>{{ $isOpen ? (($hours['opens_at'] ?? '09:00').' - '.($hours['closes_at'] ?? '17:00')) : 'Closed' }}</span>
                            </div>
                        @endforeach
                    </div>
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
                            <span class="badge neutral">{{ $membership->status->label() }}</span>
                        </div>
                    @empty
                        <div class="empty">No users attached to this organization yet.</div>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>
</x-layouts.admin>
