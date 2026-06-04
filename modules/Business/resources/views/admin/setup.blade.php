@php
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    $hours = old('opening_hours', $tenant?->opening_hours ?? []);
@endphp

<x-layouts.admin title="Organization & Branch Management">
    <div class="topbar">
        <div>
            <div class="eyebrow">Business administration</div>
            <h1>Business Setup</h1>
            <p class="subtle">{{ $tenant ? "Managing {$tenant->name}." : 'Register a new organization.' }}</p>
        </div>

        @if ($isPlatformAdmin)
            <a class="btn accent" href="{{ route('admin.business.index', ['new' => 1]) }}">Create new organization</a>
        @elseif ($tenant)
            <span class="badge">{{ $tenant->status }}</span>
        @endif
    </div>

    @if (session('status'))
        <div class="alert">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert errors">
            <strong>Check the highlighted setup details.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (! $tenant && ! $isPlatformAdmin)
        <section class="panel">
            <div class="panel-body">
                <div class="empty">Your user account is not attached to an organization yet.</div>
            </div>
        </section>
    @else
        <div class="tab-layout">
            <nav class="pill-nav" aria-label="Business setup sections" role="tablist">
                <a href="#business-profile" role="tab" data-tab-target="business-profile">Business profile</a>
                <a href="#branches" role="tab" data-tab-target="branches">Branches / stores <span class="badge neutral">{{ $branches->count() }}</span></a>
                <a href="#departments" role="tab" data-tab-target="departments">Departments / units <span class="badge neutral">{{ $departments->count() }}</span></a>
                <a href="#roles" role="tab" data-tab-target="roles">User roles <span class="badge neutral">{{ $roles->count() }}</span></a>
                <a href="#users" role="tab" data-tab-target="users">Organization users <span class="badge neutral">{{ $memberships->count() }}</span></a>
            </nav>

            <div class="content-stack">
                <section class="panel tab-panel" id="business-profile" role="tabpanel" data-tab-panel>
                    <div class="panel-header">
                        <div>
                            <h2 class="panel-title">Business profile</h2>
                            <p class="subtle">Core identity, settings, tax, subscription, and operating hours.</p>
                        </div>
                        <button class="btn primary" type="button" data-dialog-open="business-profile-dialog">{{ $tenant ? 'Edit profile' : 'Create profile' }}</button>
                    </div>
                    <div class="panel-body">
                        @if (! $tenant)
                            <div class="empty">No organization profile exists yet. Create the profile to unlock branches, departments, roles, and users.</div>
                        @else
                            <div class="summary-grid">
                                <div class="summary-item"><span>Business name</span><strong>{{ $tenant->name }}</strong></div>
                                <div class="summary-item"><span>Business type</span><strong>{{ $businessTypes[$tenant->business_type] ?? $tenant->business_type ?? 'Not set' }}</strong></div>
                                <div class="summary-item"><span>Status</span><strong>{{ $tenant->status }}</strong></div>
                                <div class="summary-item"><span>Email</span><strong>{{ $tenant->email ?: 'Not set' }}</strong></div>
                                <div class="summary-item"><span>Phone</span><strong>{{ $tenant->phone ?: 'Not set' }}</strong></div>
                                <div class="summary-item"><span>Website</span><strong>{{ $tenant->website ?: 'Not set' }}</strong></div>
                                <div class="summary-item"><span>Currency</span><strong>{{ $tenant->currency_code }}</strong></div>
                                <div class="summary-item"><span>Tax rate</span><strong>{{ $tenant->default_tax_rate }}%</strong></div>
                                <div class="summary-item"><span>Timezone</span><strong>{{ $tenant->timezone }}</strong></div>
                                <div class="summary-item" style="grid-column: 1 / -1;"><span>Address</span><strong>{{ $tenant->address ?: 'Not set' }}</strong></div>
                            </div>
                        @endif
                    </div>
                </section>

                <section class="panel tab-panel" id="branches" role="tabpanel" data-tab-panel hidden>
                    <div class="panel-header">
                        <div>
                            <h2 class="panel-title">Branches / stores</h2>
                            <p class="subtle">List of store, outlet, warehouse, or operating branch records.</p>
                        </div>
                        @if ($tenant)
                            <button class="btn primary" type="button" data-dialog-open="branch-dialog">Add branch</button>
                        @endif
                    </div>
                    <div class="panel-body">
                        @if (! $tenant)
                            <div class="empty">Save the business profile first, then add branches.</div>
                        @else
                            <div class="list">
                                @forelse ($branches as $branch)
                                    <div class="item">
                                        <div>
                                            <div class="item-title">{{ $branch->name }}</div>
                                            <div class="subtle">{{ $branch->code }} · {{ $branch->status }} · {{ $branch->currency_code ?: $tenant->currency_code }}</div>
                                        </div>
                                        @if ($branch->is_primary)
                                            <span class="badge">Primary</span>
                                        @endif
                                    </div>
                                @empty
                                    <div class="empty">No branches yet. Add the first store or operating location.</div>
                                @endforelse
                            </div>
                        @endif
                    </div>
                </section>

                <section class="panel tab-panel" id="departments" role="tabpanel" data-tab-panel hidden>
                    <div class="panel-header">
                        <div>
                            <h2 class="panel-title">Departments / units</h2>
                            <p class="subtle">List of internal units like Sales, Inventory, Accounts, Support, or Admin.</p>
                        </div>
                        @if ($tenant)
                            <button class="btn primary" type="button" data-dialog-open="department-dialog">Add department</button>
                        @endif
                    </div>
                    <div class="panel-body">
                        @if (! $tenant)
                            <div class="empty">Save the business profile first, then add departments.</div>
                        @else
                            <div class="list">
                                @forelse ($departments as $department)
                                    <div class="item">
                                        <div>
                                            <div class="item-title">{{ $department->name }}</div>
                                            <div class="subtle">{{ $department->code }} @if ($department->branch) · {{ $department->branch->name }} @else · All branches @endif</div>
                                        </div>
                                        <span class="badge neutral">{{ $department->status }}</span>
                                    </div>
                                @empty
                                    <div class="empty">No departments yet.</div>
                                @endforelse
                            </div>
                        @endif
                    </div>
                </section>

                <section class="panel tab-panel" id="roles" role="tabpanel" data-tab-panel hidden>
                    <div class="panel-header">
                        <div>
                            <h2 class="panel-title">User roles</h2>
                            <p class="subtle">List of tenant-level roles used to assign access.</p>
                        </div>
                        @if ($tenant)
                            <button class="btn primary" type="button" data-dialog-open="role-dialog">Add role</button>
                        @endif
                    </div>
                    <div class="panel-body">
                        @if (! $tenant)
                            <div class="empty">Default roles are created after business registration.</div>
                        @else
                            <div class="list">
                                @forelse ($roles as $role)
                                    <div class="item">
                                        <div>
                                            <div class="item-title">{{ $role->name }}</div>
                                            <div class="subtle">{{ $role->slug }}</div>
                                        </div>
                                        <span class="badge {{ $role->is_system ? '' : 'neutral' }}">{{ $role->is_system ? 'System' : 'Custom' }}</span>
                                    </div>
                                @empty
                                    <div class="empty">No roles yet.</div>
                                @endforelse
                            </div>
                        @endif
                    </div>
                </section>

                <section class="panel tab-panel" id="users" role="tabpanel" data-tab-panel hidden>
                    <div class="panel-header">
                        <div>
                            <h2 class="panel-title">Organization users</h2>
                            <p class="subtle">Users are global login identities; membership controls organization access.</p>
                        </div>
                        @if ($tenant)
                            <button class="btn primary" type="button" data-dialog-open="user-dialog">Add user</button>
                        @endif
                    </div>
                    <div class="panel-body">
                        @if (! $tenant)
                            <div class="empty">Create or select an organization first, then add its users.</div>
                        @else
                            <div class="list">
                                @forelse ($memberships as $membership)
                                    <div class="item">
                                        <div>
                                            <div class="item-title">{{ $membership->user->name }}</div>
                                            <div class="subtle">
                                                {{ $membership->user->email }}
                                                @if ($membership->role) · {{ $membership->role->name }} @endif
                                                @if ($membership->branch) · {{ $membership->branch->name }} @endif
                                            </div>
                                        </div>
                                        <span class="badge neutral">{{ $membership->status }}</span>
                                    </div>
                                @empty
                                    <div class="empty">No users belong to this organization yet.</div>
                                @endforelse
                            </div>
                        @endif
                    </div>
                </section>
            </div>
        </div>

        <dialog class="dialog" id="business-profile-dialog">
            <div class="dialog-header">
                <div>
                    <h2 class="panel-title">{{ $tenant ? 'Edit business profile' : 'Create business profile' }}</h2>
                    <p class="subtle">Business identity, branding, operating settings, tax, and subscription plan.</p>
                </div>
                <button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button>
            </div>
            <div class="dialog-body">
                <form method="POST" action="{{ route('admin.business.profile.save') }}" enctype="multipart/form-data">
                    @csrf
                    @if ($tenant)
                        <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                    @endif

                    <div class="form-grid">
                        <div class="field"><label for="name">Business name</label><input id="name" name="name" value="{{ old('name', $tenant?->name) }}" required></div>
                        <div class="field"><label for="slug">Business URL slug</label><input id="slug" name="slug" value="{{ old('slug', $tenant?->slug) }}" placeholder="abc-fashion" @if ($tenant) disabled @endif></div>
                        <div class="field">
                            <label for="business_type">Business type</label>
                            <select id="business_type" name="business_type" required>
                                <option value="">Select type</option>
                                @foreach ($businessTypes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('business_type', $tenant?->business_type) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field"><label for="registration_number">Registration number</label><input id="registration_number" name="registration_number" value="{{ old('registration_number', $tenant?->registration_number) }}"></div>
                        <div class="field"><label for="phone">Phone</label><input id="phone" name="phone" value="{{ old('phone', $tenant?->phone) }}"></div>
                        <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email', $tenant?->email) }}"></div>
                        <div class="field"><label for="website">Website</label><input id="website" name="website" type="url" value="{{ old('website', $tenant?->website) }}" placeholder="https://example.com"></div>
                        <div class="field"><label for="logo">Logo</label><input id="logo" name="logo" type="file" accept="image/*"></div>
                        <div class="field full"><label for="address">Business address</label><textarea id="address" name="address">{{ old('address', $tenant?->address) }}</textarea></div>
                        <div class="field"><label for="country_code">Country</label><input id="country_code" name="country_code" maxlength="2" value="{{ old('country_code', $tenant?->country_code ?? 'NG') }}" required></div>
                        <div class="field"><label for="timezone">Timezone</label><input id="timezone" name="timezone" value="{{ old('timezone', $tenant?->timezone ?? 'Africa/Lagos') }}" required></div>
                        <div class="field"><label for="currency_code">Currency</label><input id="currency_code" name="currency_code" maxlength="3" value="{{ old('currency_code', $tenant?->currency_code ?? 'NGN') }}" required></div>
                        <div class="field"><label for="tax_identifier">Tax identifier</label><input id="tax_identifier" name="tax_identifier" value="{{ old('tax_identifier', $tenant?->tax_identifier) }}"></div>
                        <div class="field"><label for="default_tax_rate">Default tax rate (%)</label><input id="default_tax_rate" name="default_tax_rate" type="number" step="0.01" min="0" max="100" value="{{ old('default_tax_rate', $tenant?->default_tax_rate ?? 0) }}" required></div>
                        <div class="field">
                            <label for="plan_id">Subscription plan</label>
                            <select id="plan_id" name="plan_id">
                                <option value="">No plan selected</option>
                                @foreach ($plans as $plan)
                                    <option value="{{ $plan->id }}" @selected((string) old('plan_id', $selectedPlanId) === (string) $plan->id)>{{ $plan->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field full">
                            <label>Opening and closing hours</label>
                            <div class="hours">
                                @foreach ($days as $day)
                                    @php
                                        $dayHours = $hours[$day] ?? [];
                                        $isOpen = (bool) ($dayHours['is_open'] ?? in_array($day, ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], true));
                                    @endphp
                                    <div class="hour-row">
                                        <strong>{{ ucfirst($day) }}</strong>
                                        <label><input type="checkbox" name="opening_hours[{{ $day }}][is_open]" value="1" @checked($isOpen)> Open</label>
                                        <input name="opening_hours[{{ $day }}][opens_at]" type="time" value="{{ $dayHours['opens_at'] ?? '09:00' }}">
                                        <input name="opening_hours[{{ $day }}][closes_at]" type="time" value="{{ $dayHours['closes_at'] ?? '17:00' }}">
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="button-row">
                        <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                        <button class="btn primary" type="submit">Save business profile</button>
                    </div>
                </form>
            </div>
        </dialog>

        @if ($tenant)
            <dialog class="dialog" id="branch-dialog">
                <div class="dialog-header"><div><h2 class="panel-title">Add branch</h2><p class="subtle">Create a store, outlet, warehouse, or operating branch.</p></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button></div>
                <div class="dialog-body">
                    <form class="mini-form" method="POST" action="{{ route('admin.business.branches.store') }}">
                        @csrf
                        <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                        <div class="form-grid">
                            <div class="field"><label>Name</label><input name="name" required></div>
                            <div class="field"><label>Code</label><input name="code" required placeholder="MAIN"></div>
                        </div>
                        <div class="field"><label>Address</label><textarea name="address"></textarea></div>
                        <div class="form-grid">
                            <div class="field"><label>Phone</label><input name="phone"></div>
                            <div class="field"><label>Email</label><input name="email" type="email"></div>
                            <div class="field"><label>Currency</label><input name="currency_code" maxlength="3" value="{{ $tenant->currency_code }}"></div>
                            <div class="field"><label>Tax rate</label><input name="default_tax_rate" type="number" step="0.01" value="{{ $tenant->default_tax_rate }}"></div>
                        </div>
                        <input type="hidden" name="status" value="active">
                        <label><input type="checkbox" name="is_primary" value="1"> Make primary branch</label>
                        <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Cancel</button><button class="btn primary" type="submit">Add branch</button></div>
                    </form>
                </div>
            </dialog>

            <dialog class="dialog" id="department-dialog">
                <div class="dialog-header"><div><h2 class="panel-title">Add department</h2><p class="subtle">Create an internal unit for this organization.</p></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button></div>
                <div class="dialog-body">
                    <form class="mini-form" method="POST" action="{{ route('admin.business.departments.store') }}">
                        @csrf
                        <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                        <div class="form-grid">
                            <div class="field"><label>Name</label><input name="name" required></div>
                            <div class="field"><label>Code</label><input name="code" required placeholder="SALES"></div>
                        </div>
                        <div class="field">
                            <label>Branch</label>
                            <select name="branch_id">
                                <option value="">All branches</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field"><label>Description</label><textarea name="description"></textarea></div>
                        <input type="hidden" name="status" value="active">
                        <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Cancel</button><button class="btn primary" type="submit">Add department</button></div>
                    </form>
                </div>
            </dialog>

            <dialog class="dialog" id="role-dialog">
                <div class="dialog-header"><div><h2 class="panel-title">Add role</h2><p class="subtle">Create a tenant-level role for organization access.</p></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button></div>
                <div class="dialog-body">
                    <form class="mini-form" method="POST" action="{{ route('admin.access.roles.store') }}">
                        @csrf
                        <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                        <div class="form-grid">
                            <div class="field"><label>Role name</label><input name="name" required placeholder="Store Supervisor"></div>
                            <div class="field"><label>Slug</label><input name="slug" placeholder="store-supervisor"></div>
                        </div>
                        <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Cancel</button><button class="btn primary" type="submit">Add role</button></div>
                    </form>
                </div>
            </dialog>

            <dialog class="dialog" id="user-dialog">
                <div class="dialog-header"><div><h2 class="panel-title">Add organization user</h2><p class="subtle">Attach a login identity to this organization through membership.</p></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button></div>
                <div class="dialog-body">
                    <form class="mini-form" method="POST" action="{{ route('admin.access.tenant-users.store') }}">
                        @csrf
                        <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                        <div class="form-grid">
                            <div class="field"><label>Name</label><input name="name" required placeholder="Jane Manager"></div>
                            <div class="field"><label>Email</label><input name="email" type="email" required placeholder="jane@example.com"></div>
                            <div class="field"><label>Temporary password</label><input name="password" type="password" required minlength="8"></div>
                            <div class="field">
                                <label>Role</label>
                                <select name="role_id">
                                    <option value="">No role yet</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label>Branch access</label>
                                <select name="branch_id">
                                    <option value="">All branches</option>
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Cancel</button><button class="btn primary" type="submit">Add organization user</button></div>
                    </form>
                </div>
            </dialog>
        @endif
    @endif
</x-layouts.admin>
