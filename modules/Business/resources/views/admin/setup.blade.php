@php
    $publicImageUrl = fn (?string $path): ?string => $path ? '/storage/'.ltrim($path, '/') : null;
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    $hours = old('opening_hours', $tenant?->opening_hours ?? []);
    $rawPaymentMethods = old('payment_methods');
    $paymentMethods = is_array($rawPaymentMethods)
        ? implode(', ', $rawPaymentMethods)
        : ($rawPaymentMethods ?? implode(', ', $tenant?->settings['payment_methods'] ?? ['Cash', 'Bank transfer', 'POS/Card', 'Cheque']));
    $bankDetails = collect(old('bank_details', $tenant?->settings['bank_details'] ?? []));
    $bankDetails = $bankDetails->isNotEmpty() ? $bankDetails : collect([null]);
    $maintenanceMode = (bool) old('maintenance_mode', $tenant?->settings['maintenance_mode'] ?? false);
    $useEstimatedCostForCogs = (bool) old('use_estimated_cost_for_cogs', $tenant?->settings['use_estimated_cost_for_cogs'] ?? false);
    $selectedPlan = $plans->firstWhere('id', (int) old('plan_id', $selectedPlanId));
    $selectedPlanHasInventory = $selectedPlan?->modules?->contains(fn ($module) => $module->slug === 'inventory' && (bool) ($module->pivot->is_enabled ?? true)) ?? false;
    $rawOnlinePaymentMethods = old('payment_methods', $onlineStore?->payment_methods ?? []);
    $onlinePaymentMethods = is_array($rawOnlinePaymentMethods)
        ? $rawOnlinePaymentMethods
        : array_values(array_filter(array_map('trim', explode(',', (string) $rawOnlinePaymentMethods))));
    $bankAccountKey = fn (array $account): string => sha1(implode('|', [
        trim((string) ($account['bank_name'] ?? '')),
        trim((string) ($account['account_name'] ?? '')),
        trim((string) ($account['account_number'] ?? '')),
    ]));
    $businessBankAccountOptions = collect($tenant?->settings['bank_details'] ?? [])
        ->filter(fn ($account) => is_array($account) && ($account['status'] ?? 'active') === 'active')
        ->map(fn (array $account) => [
            'key' => $bankAccountKey($account),
            'bank_name' => trim((string) ($account['bank_name'] ?? '')),
            'account_name' => trim((string) ($account['account_name'] ?? '')),
            'account_number' => trim((string) ($account['account_number'] ?? '')),
        ])
        ->filter(fn (array $account) => $account['bank_name'] !== '' && $account['account_number'] !== '')
        ->values();
    $storedOnlineBankAccount = collect($onlineStore?->bank_accounts ?? [])->first();
    $storedOnlineBankAccountKey = is_array($storedOnlineBankAccount) ? $bankAccountKey($storedOnlineBankAccount) : null;
    $selectedOnlineBankAccountKey = old('bank_account_key', $onlineStore?->payment_settings['bank_account_key'] ?? $storedOnlineBankAccountKey);
    $onlineShippingOptions = collect(old('shipping_options', $onlineStore?->shipping_options ?? []));
    $onlineShippingOptions = $onlineShippingOptions->isNotEmpty() ? $onlineShippingOptions : collect([null]);
    $onlineFaqs = collect(old('faqs', $onlineStore?->faqs ?? []));
    $onlineFaqs = $onlineFaqs->isNotEmpty() ? $onlineFaqs : collect([null]);
    $onlineSlides = collect(old('slides', $onlineStore?->slides ?? []))
        ->filter(fn ($slide) => is_array($slide));

    if ($onlineSlides->isEmpty() && ($onlineStore?->hero_image_path || $onlineStore?->hero_image_text || $onlineStore?->hero_image_description || $onlineStore?->hero_image_tag)) {
        $onlineSlides = collect([[
            'image_path' => $onlineStore?->hero_image_path,
            'hero_image_tag' => $onlineStore?->hero_image_tag,
            'hero_image_text' => $onlineStore?->hero_image_text,
            'hero_image_description' => $onlineStore?->hero_image_description,
        ]]);
    }

    $onlinePages = old('pages', $onlineStore?->pages ?? []);
    $onlineSocials = old('socials', $onlineStore?->social_accounts ?? []);
    $onlinePaystack = old('paystack', $onlineStore?->payment_settings['paystack'] ?? []);
    $onlineSettlementBank = old('settlement_bank_account', $onlineStore?->payment_settings['settlement_bank_account'] ?? []);
    $onlinePaystackMethod = old('paystack_method', in_array('self_hosted_paystack', $onlinePaymentMethods, true) ? 'self_hosted_paystack' : (in_array('storeboot_paystack', $onlinePaymentMethods, true) ? 'storeboot_paystack' : 'none'));
    $onlineMaintenanceMode = (bool) old('maintenance_mode', $onlineStore?->maintenance_mode ?? false);
    $selectedOnlineCategories = collect(old('category_ids', $onlineStore?->categories?->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id);
    $onlinePaymentOptions = [
        'pay_on_delivery' => 'Pay On Delivery',
        'bank_account' => 'Pay via Transfer',
        'place_order' => 'Place Order',
    ];
    $businessPaymentMethods = collect($tenant?->settings['payment_methods'] ?? []);
    $openBusinessDays = collect($tenant?->opening_hours ?? [])->filter(fn ($hours) => (bool) ($hours['is_open'] ?? false));
@endphp

<x-layouts.admin title="Organization & Branch Management">
    <style>
        .setup-line-card { border: 1px solid var(--line); border-radius: 8px; background: #fff; padding: 14px; display: grid; gap: 12px; }
        .setup-line-card + .setup-line-card { margin-top: 12px; }
        .setup-line-header { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
        .setup-section { border: 1px solid var(--line); border-radius: 8px; padding: 16px; display: grid; gap: 14px; background: #fff; }
        .setup-section-title { margin: 0; color: #111827; font-size: 16px; font-weight: 900; }
        .theme-color-input { min-height: 44px; }
        .check-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .check-list { display: grid; grid-template-columns: 1fr; gap: 10px; }
        .inline-check { display: inline-flex; gap: 8px; align-items: center; color: #344054; font-weight: 750; }
        .inline-check input { width: auto; min-width: 16px; height: 16px; }
        .nested-check-list { margin: 10px 0 0 24px; display: grid; grid-template-columns: 1fr; gap: 8px; }
        .nested-check-list[hidden] { display: none; }
        .form-grid[hidden] { display: none !important; }
        .online-store-section-nav { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 4px; }
        .online-store-section-nav button { white-space: nowrap; }
        .online-store-section-nav button.active { background: #111827; color: #fff; border-color: #111827; }
        .online-store-section-panel[hidden] { display: none; }
        .online-store-section-panel { display: grid; gap: 14px; }
        .upload-preview { margin-top: 8px; display: grid; gap: 6px; }
        .upload-preview img { width: 100%; max-width: 220px; height: 96px; border: 1px solid var(--line); border-radius: 8px; object-fit: contain; background: #f8fafc; padding: 6px; }
        .upload-preview.hero img { max-width: 360px; height: 120px; object-fit: cover; }
        .setup-empty-line { border: 1px dashed var(--line); border-radius: 8px; padding: 14px; color: #667085; background: #f8fafc; }
        .setup-row-actions { display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end; }
        .setup-list-toolbar { margin-bottom: 12px; }
        .setup-accordion { border: 1px solid #c7d7fe; border-radius: 8px; background: #fff; overflow: hidden; }
        .setup-accordion + .setup-accordion { margin-top: 12px; }
        .setup-accordion summary { cursor: pointer; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 16px; font-weight: 900; color: #111827; list-style: none; background: #eef4ff; border-bottom: 1px solid #c7d7fe; }
        .setup-accordion summary::-webkit-details-marker { display: none; }
        .setup-accordion[open] summary { background: #e0ecff; }
        .setup-accordion-toggle { display: inline-flex; align-items: center; gap: 10px; min-width: 0; }
        .setup-accordion-icon { display: inline-flex; width: 24px; height: 24px; align-items: center; justify-content: center; border-radius: 999px; background: #101828; color: #fff; transition: transform .16s ease; }
        .setup-accordion-icon svg { width: 14px; height: 14px; }
        .setup-accordion[open] .setup-accordion-icon { transform: rotate(90deg); }
        .setup-accordion-body { border-top: 1px solid var(--line); padding: 16px; display: grid; gap: 14px; }
        .online-slide-card { border: 1px solid var(--line); border-radius: 8px; background: #fff; padding: 14px; display: grid; grid-template-columns: minmax(180px, 30%) 1fr; gap: 16px; }
        .online-slide-card + .online-slide-card { margin-top: 12px; }
        .online-slide-photo { min-height: 180px; border: 1px dashed var(--line); border-radius: 8px; background: #f8fafc; display: flex; align-items: center; justify-content: center; overflow: hidden; cursor: pointer; color: #667085; font-weight: 800; text-align: center; padding: 12px; }
        .online-slide-photo img { width: 100%; height: 100%; min-height: 160px; object-fit: cover; border-radius: 6px; }
        .online-slide-photo input { display: none; }
        .online-slide-form { display: grid; gap: 12px; }
        @media (max-width: 700px) { .check-grid, .online-slide-card { grid-template-columns: 1fr; } }
    </style>

    <div class="topbar">
        <div>
            <div class="eyebrow">Business administration</div>
            <h1>Business Setup</h1>
            <p class="subtle">{{ $tenant ? "Managing {$tenant->name}." : 'Register a new organization.' }}</p>
        </div>

        <div style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end; align-items: center;">
            @if ($tenants->count() > 1)
                <form method="GET" action="{{ route('admin.business.index') }}" style="min-width: 260px;">
                    <select name="tenant" onchange="this.form.submit()" aria-label="Switch organization">
                        @foreach ($tenants as $visibleTenant)
                            <option value="{{ $visibleTenant->id }}" @selected($tenant?->id === $visibleTenant->id)>{{ $visibleTenant->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif

            @if ($isPlatformAdmin)
                <a class="btn accent" href="{{ route('admin.business.index', ['new' => 1]) }}">Create new organization</a>
            @elseif ($tenant)
                <span class="badge">{{ $tenant->status->label() }}</span>
            @endif
        </div>
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
                <a href="#subscriptions" role="tab" data-tab-target="subscriptions">Subscriptions <span class="badge neutral">{{ $tenantSubscriptions->count() }}</span></a>
                <a href="#online-store" role="tab" data-tab-target="online-store">Online Store</a>
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
                                <div class="summary-item"><span>Status</span><strong>{{ $tenant->status->label() }}</strong></div>
                                <div class="summary-item"><span>Registration number</span><strong>{{ $tenant->registration_number ?: 'Not set' }}</strong></div>
                                <div class="summary-item"><span>Email</span><strong>{{ $tenant->email ?: 'Not set' }}</strong></div>
                                <div class="summary-item"><span>Phone</span><strong>{{ $tenant->phone ?: 'Not set' }}</strong></div>
                                <div class="summary-item"><span>Website</span><strong>{{ $tenant->website ?: 'Not set' }}</strong></div>
                                <div class="summary-item"><span>Country</span><strong>{{ $tenant->country_code }}</strong></div>
                                <div class="summary-item"><span>Currency</span><strong>{{ $tenant->currency_code }}</strong></div>
                                <div class="summary-item"><span>Tax identifier</span><strong>{{ $tenant->tax_identifier ?: 'Not set' }}</strong></div>
                                <div class="summary-item"><span>Tax rate</span><strong>{{ $tenant->default_tax_rate }}%</strong></div>
                                <div class="summary-item"><span>Timezone</span><strong>{{ $tenant->timezone }}</strong></div>
                                <div class="summary-item"><span>Subscription plan</span><strong>{{ $selectedPlan?->name ?? 'None' }}</strong></div>
                                <div class="summary-item"><span>Logo</span><strong>{{ $tenant->logo_path ? 'Uploaded' : 'Not set' }}</strong></div>
                                <div class="summary-item"><span>Opening days</span><strong>{{ $openBusinessDays->count() }} of 7 days</strong></div>
                                <div class="summary-item"><span>Payment methods</span><strong>{{ $businessPaymentMethods->isNotEmpty() ? $businessPaymentMethods->join(', ') : 'Not set' }}</strong></div>
                                <div class="summary-item" style="grid-column: 1 / -1;"><span>Address</span><strong>{{ $tenant->address ?: 'Not set' }}</strong></div>
                                <div class="summary-item"><span>Maintenance mode</span><strong>{{ ($tenant->settings['maintenance_mode'] ?? false) ? 'Enabled' : 'Disabled' }}</strong></div>
                                <div class="summary-item"><span>Bank accounts</span><strong>{{ count($tenant->settings['bank_details'] ?? []) }}</strong></div>
                                <div class="summary-item"><span>Estimated cost COGS</span><strong>{{ ($tenant->settings['use_estimated_cost_for_cogs'] ?? false) ? 'Enabled' : 'Disabled' }}</strong></div>
                            </div>
                        @endif
                    </div>
                </section>

                <section class="panel tab-panel" id="subscriptions" role="tabpanel" data-tab-panel hidden>
                    <div class="panel-header">
                        <div>
                            <h2 class="panel-title">Tenant subscriptions</h2>
                            <p class="subtle">Plans and billing periods assigned to this organization.</p>
                        </div>
                        @if ($tenant && $isPlatformAdmin)
                            <button class="btn primary" type="button" data-dialog-open="subscription-dialog">Add subscription</button>
                        @endif
                    </div>
                    <div class="panel-body">
                        @if (! $tenant)
                            <div class="empty">Save the business profile first, then add subscriptions.</div>
                        @else
                            <div class="list">
                                @forelse ($tenantSubscriptions as $subscription)
                                    <div class="item">
                                        <div>
                                            <div class="item-title">{{ $subscription->plan?->name ?? 'Plan not found' }}</div>
                                            <div class="subtle">
                                                {{ ucfirst($subscription->billing_interval) }}
                                                @if ($subscription->current_period_starts_at || $subscription->current_period_ends_at)
                                                    · Period:
                                                    {{ $subscription->current_period_starts_at?->format('M j, Y') ?? 'Not set' }}
                                                    to
                                                    {{ $subscription->current_period_ends_at?->format('M j, Y') ?? 'Not set' }}
                                                @endif
                                            </div>
                                            @if ($subscription->trial_ends_at || $subscription->cancelled_at)
                                                <div class="subtle">
                                                    @if ($subscription->trial_ends_at)
                                                        Trial ends {{ $subscription->trial_ends_at->format('M j, Y') }}
                                                    @endif
                                                    @if ($subscription->trial_ends_at && $subscription->cancelled_at)
                                                        ·
                                                    @endif
                                                    @if ($subscription->cancelled_at)
                                                        Cancelled {{ $subscription->cancelled_at->format('M j, Y') }}
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                        <div style="display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end;">
                                            <span class="badge neutral">{{ $subscription->status->label() }}</span>
                                            @if ($isPlatformAdmin)
                                                <button class="btn secondary" type="button" data-dialog-open="subscription-edit-{{ $subscription->id }}">Edit</button>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <div class="empty">No subscriptions have been added for this organization yet.</div>
                                @endforelse
                            </div>
                        @endif
                    </div>
                </section>

                <section class="panel tab-panel" id="online-store" role="tabpanel" data-tab-panel hidden>
                    <div class="panel-header">
                        <div>
                            <h2 class="panel-title">Online Store</h2>
                            <p class="subtle">Collect storefront identity, menu categories, payment, shipping, socials, and page content for the customer-facing shop.</p>
                        </div>
                        @if ($onlineStore)
                            <span class="badge">Saved</span>
                        @endif
                    </div>
                    <div class="panel-body">
                        @if (! $tenant)
                            <div class="empty">Save the business profile first, then set up the online store.</div>
                        @else
                            <form class="mini-form" method="POST" action="{{ route('admin.business.online-store.save') }}" enctype="multipart/form-data" data-online-store-form>
                                @csrf
                                <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                                <input type="hidden" name="online_store_section" value="{{ old('online_store_section', request('online_store_section', 'online-store-basics')) }}" data-online-store-active-section>

                                <div class="online-store-section-nav" role="tablist" aria-label="Online store setup sections">
                                    <button class="btn secondary active" type="button" role="tab" aria-selected="true" data-online-store-section-target="online-store-basics">Basics</button>
                                    <button class="btn secondary" type="button" role="tab" aria-selected="false" data-online-store-section-target="online-store-contact">Contact</button>
                                    <button class="btn secondary" type="button" role="tab" aria-selected="false" data-online-store-section-target="online-store-theme">Theme</button>
                                    <button class="btn secondary" type="button" role="tab" aria-selected="false" data-online-store-section-target="online-store-payments">Payment</button>
                                    <button class="btn secondary" type="button" role="tab" aria-selected="false" data-online-store-section-target="online-store-shipping">Shipping</button>
                                    <button class="btn secondary" type="button" role="tab" aria-selected="false" data-online-store-section-target="online-store-socials">Socials</button>
                                    <button class="btn secondary" type="button" role="tab" aria-selected="false" data-online-store-section-target="online-store-pages">Pages</button>
                                    <button class="btn secondary" type="button" role="tab" aria-selected="false" data-online-store-section-target="online-store-faq">FAQ</button>
                                </div>

                                <div class="setup-section online-store-section-panel" id="online-store-basics" role="tabpanel" data-online-store-section-panel>
                                    <h3 class="setup-section-title">Online Store Basics</h3>
                                    <div class="form-grid">
                                        <div class="field"><label>Store username</label><input name="username" value="{{ old('username', $onlineStore?->username) }}" placeholder="abc-fashion" required></div>
                                        <div class="field"><label>Name of Store</label><input name="store_name" value="{{ old('store_name', $onlineStore?->store_name ?? $tenant->name) }}" required></div>
                                        <div class="field full"><label>Description of Store</label><textarea name="description" rows="3" placeholder="A short description customers will see on your online store.">{{ old('description', $onlineStore?->description) }}</textarea></div>
                                        <div class="field">
                                            <label>Image Logo of the Store</label>
                                            <input name="logo" type="file" accept="image/*">
                                            @if ($onlineStore?->logo_path)
                                                <div class="upload-preview">
                                                    <img src="{{ $publicImageUrl($onlineStore->logo_path) }}" alt="{{ $onlineStore->store_name }} logo preview">
                                                    <span class="subtle">Current logo uploaded.</span>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="field"><label>Theme primary color</label><input class="theme-color-input" name="theme_primary_color" type="color" value="{{ old('theme_primary_color', $onlineStore?->theme_primary_color ?? '#006554') }}" style="background-color: {{ old('theme_primary_color', $onlineStore?->theme_primary_color ?? '#006554') }};" data-theme-color-field></div>
                                        <div class="field"><label>Theme secondary color</label><input class="theme-color-input" name="theme_secondary_color" type="color" value="{{ old('theme_secondary_color', $onlineStore?->theme_secondary_color ?? '#f59e0b') }}" style="background-color: {{ old('theme_secondary_color', $onlineStore?->theme_secondary_color ?? '#f59e0b') }};" data-theme-color-field></div>
                                        <div class="field"><label>Fulfilment branch</label><select name="fulfilment_branch_id"><option value="">Select branch</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected((string) old('fulfilment_branch_id', $onlineStore?->fulfilment_branch_id) === (string) $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
                                        <div class="field"><label>Store status</label><label class="inline-check"><input type="checkbox" name="maintenance_mode" value="1" @checked($onlineMaintenanceMode)> Enable maintenance mode</label></div>
                                        <div class="field full"><label>Announcement</label><textarea name="announcement" rows="2">{{ old('announcement', $onlineStore?->announcement) }}</textarea></div>
                                    </div>
                                    <div class="button-row"><button class="btn primary" type="submit">Save basics</button></div>
                                </div>

                                <div class="setup-section online-store-section-panel" id="online-store-contact" role="tabpanel" data-online-store-section-panel hidden>
                                    <h3 class="setup-section-title">Contact</h3>
                                    <div class="form-grid">
                                        <div class="field full"><label>Address</label><textarea name="address" rows="2">{{ old('address', $onlineStore?->address ?? $tenant->address) }}</textarea></div>
                                        <div class="field"><label>City</label><input name="city" value="{{ old('city', $onlineStore?->city) }}"></div>
                                        <div class="field"><label>State</label><input name="state" value="{{ old('state', $onlineStore?->state) }}"></div>
                                        <div class="field"><label>Country</label><input name="country" value="{{ old('country', $onlineStore?->country ?? $tenant->country_code) }}"></div>
                                        <div class="field"><label>Site Email</label><input name="site_email" type="email" value="{{ old('site_email', $onlineStore?->site_email ?? $tenant->email) }}"></div>
                                        <div class="field"><label>Store Phone number</label><input name="store_phone" value="{{ old('store_phone', $onlineStore?->store_phone ?? $tenant->phone) }}"></div>
                                        <div class="field"><label>Store WhatsApp Number</label><input name="store_whatsapp" value="{{ old('store_whatsapp', $onlineStore?->store_whatsapp) }}"></div>
                                    </div>
                                    <div class="button-row"><button class="btn primary" type="submit">Save contact</button></div>
                                </div>

                                <div class="setup-section online-store-section-panel" id="online-store-theme" role="tabpanel" data-online-store-section-panel hidden>
                                    <h3 class="setup-section-title">Theme</h3>

                                    <details class="setup-accordion" open>
                                        <summary>
                                            <span class="setup-accordion-toggle">
                                                <span class="setup-accordion-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M7 4.5 13 10l-6 5.5v-11Z"/></svg></span>
                                                <span>Section A: Menu Setup</span>
                                            </span>
                                            <span class="subtle">Storefront category menu</span>
                                        </summary>
                                        <div class="setup-accordion-body">
                                            <div class="check-grid">
                                                @forelse ($productCategories as $category)
                                                    <label class="inline-check"><input type="checkbox" name="category_ids[]" value="{{ $category->id }}" @checked($selectedOnlineCategories->contains($category->id))> {{ $category->name }}</label>
                                                @empty
                                                    <span class="subtle">No product categories yet.</span>
                                                @endforelse
                                            </div>
                                        </div>
                                    </details>

                                    <details class="setup-accordion" open>
                                        <summary>
                                            <span class="setup-accordion-toggle">
                                                <span class="setup-accordion-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M7 4.5 13 10l-6 5.5v-11Z"/></svg></span>
                                                <span>Section B: Slides</span>
                                            </span>
                                            <span class="subtle">Hero section slider</span>
                                        </summary>
                                        <div class="setup-accordion-body">
                                            <div class="setup-line-header">
                                                <label>Slides</label>
                                                <button class="btn primary" type="button" data-add-online-slide>Add Slide</button>
                                            </div>
                                            <div data-online-slides>
                                                @foreach ($onlineSlides as $index => $slide)
                                                    @php
                                                        $slideImagePath = (string) ($slide['image_path'] ?? $slide['existing_image_path'] ?? '');
                                                        $slideTag = (string) ($slide['hero_image_tag'] ?? '');
                                                        $slideText = (string) ($slide['hero_image_text'] ?? '');
                                                        $slideDescription = (string) ($slide['hero_image_description'] ?? '');
                                                    @endphp
                                                    <div class="online-slide-card" data-online-slide>
                                                        <label class="online-slide-photo">
                                                            <input type="file" name="slides[{{ $index }}][image]" accept="image/*" data-slide-image-input>
                                                            <input type="hidden" name="slides[{{ $index }}][existing_image_path]" value="{{ $slideImagePath }}" data-slide-field="existing_image_path">
                                                            @if ($slideImagePath !== '')
                                                                <img src="{{ $publicImageUrl($slideImagePath) }}" alt="Slide {{ $index + 1 }} image" data-slide-preview>
                                                            @else
                                                                <span data-slide-placeholder>Upload Banner / Hero Image<br><small>(recommended 1600 x 600px)</small></span>
                                                            @endif
                                                        </label>
                                                        <div class="online-slide-form">
                                                            <div class="setup-line-header">
                                                                <strong data-slide-title>Slide {{ $index + 1 }}</strong>
                                                                <button class="btn danger" type="button" data-remove-online-slide>Remove</button>
                                                            </div>
                                                            <div class="field"><label>Banner Image Tag</label><input name="slides[{{ $index }}][hero_image_tag]" value="{{ $slideTag }}" data-slide-field="hero_image_tag"></div>
                                                            <div class="field"><label>Banner Image Text</label><input name="slides[{{ $index }}][hero_image_text]" value="{{ $slideText }}" data-slide-field="hero_image_text"></div>
                                                            <div class="field"><label>Banner Image Description</label><textarea name="slides[{{ $index }}][hero_image_description]" rows="3" data-slide-field="hero_image_description">{{ $slideDescription }}</textarea></div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                                <div class="setup-empty-line" data-online-slides-empty @if ($onlineSlides->isNotEmpty()) hidden @endif>No Slides Added Yet.</div>
                                            </div>
                                        </div>
                                    </details>

                                    <div class="button-row"><button class="btn primary" type="submit">Save theme</button></div>
                                </div>

                                <div class="setup-section online-store-section-panel" id="online-store-payments" role="tabpanel" data-online-store-section-panel hidden>
                                    <h3 class="setup-section-title">Payment Method</h3>
                                    <div class="check-list">
                                        @foreach ($onlinePaymentOptions as $value => $label)
                                            @if ($value === 'bank_account')
                                                <div>
                                                    <label class="inline-check"><input type="checkbox" name="payment_methods[]" value="{{ $value }}" @checked(in_array($value, $onlinePaymentMethods, true))> {{ $label }}</label>
                                                    <div class="nested-check-list" data-transfer-bank-selector @if (! in_array('bank_account', $onlinePaymentMethods, true)) hidden @endif>
                                                        <div class="field">
                                                            <label>Bank account</label>
                                                            @if ($businessBankAccountOptions->isNotEmpty())
                                                                <select name="bank_account_key">
                                                                    <option value="">Select a bank account</option>
                                                                    @foreach ($businessBankAccountOptions as $account)
                                                                        <option value="{{ $account['key'] }}" @selected($selectedOnlineBankAccountKey === $account['key'])>
                                                                            {{ $account['bank_name'] }} · {{ $account['account_name'] ?: 'Account name not set' }} · {{ $account['account_number'] }}
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                                <span class="subtle">Manage bank accounts from Business Profile.</span>
                                                            @else
                                                                <select name="bank_account_key" disabled>
                                                                    <option value="">No active business bank accounts available</option>
                                                                </select>
                                                                <span class="subtle">Add an active bank account in Business Profile to use Pay via Transfer.</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            @else
                                                <label class="inline-check"><input type="checkbox" name="payment_methods[]" value="{{ $value }}" @checked(in_array($value, $onlinePaymentMethods, true))> {{ $label }}</label>
                                            @endif
                                        @endforeach
                                        <div>
                                            <label class="inline-check"><input type="checkbox" data-paystack-toggle @checked($onlinePaystackMethod !== 'none')> Paystack</label>
                                            <div class="nested-check-list" data-paystack-options @if ($onlinePaystackMethod === 'none') hidden @endif>
                                                <input type="hidden" name="paystack_method" value="none" data-paystack-method-fallback @disabled($onlinePaystackMethod !== 'none')>
                                                <label class="inline-check"><input type="radio" name="paystack_method" value="storeboot_paystack" @checked($onlinePaystackMethod === 'storeboot_paystack') @disabled($onlinePaystackMethod === 'none') data-paystack-method-option> Storeboot Paystack</label>
                                                <label class="inline-check"><input type="radio" name="paystack_method" value="self_hosted_paystack" @checked($onlinePaystackMethod === 'self_hosted_paystack') @disabled($onlinePaystackMethod === 'none') data-paystack-method-option> Self Hosted Paystack</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-grid" data-self-hosted-paystack-fields @if ($onlinePaystackMethod !== 'self_hosted_paystack') hidden @endif>
                                        <div class="field"><label>Self Hosted Paystack Live Public Key</label><input name="paystack[public_key]" value="{{ $onlinePaystack['public_key'] ?? '' }}"><span class="subtle">Required only when Self Hosted Paystack is selected.</span></div>
                                        <div class="field"><label>Self Hosted Paystack Live Private Key</label><input name="paystack[private_key]" value="{{ $onlinePaystack['private_key'] ?? '' }}"><span class="subtle">Required only when Self Hosted Paystack is selected.</span></div>
                                    </div>
                                    <div class="form-grid" data-paystack-settlement-bank-fields @if ($onlinePaystackMethod !== 'storeboot_paystack') hidden @endif>
                                        <div class="field"><label>Settlement Bank Name</label><input name="settlement_bank_account[bank_name]" value="{{ $onlineSettlementBank['bank_name'] ?? '' }}" placeholder="Bank name"></div>
                                        <div class="field"><label>Settlement Bank Account</label><input name="settlement_bank_account[account_number]" value="{{ $onlineSettlementBank['account_number'] ?? '' }}" placeholder="Account number"></div>
                                        <div class="field"><label>Settlement Account Name</label><input name="settlement_bank_account[account_name]" value="{{ $onlineSettlementBank['account_name'] ?? '' }}" placeholder="Account name"></div>
                                    </div>
                                    <div class="button-row"><button class="btn primary" type="submit">Save payment method</button></div>
                                </div>

                                <div class="setup-section online-store-section-panel" id="online-store-shipping" role="tabpanel" data-online-store-section-panel hidden>
                                    <h3 class="setup-section-title">Shipping Details</h3>
                                    <div class="setup-line-header">
                                        <label>Shipping Options</label>
                                        <button class="btn primary" type="button" data-open-online-shipping-dialog>Add shipping option</button>
                                    </div>
                                    <div data-online-shipping-options>
                                        @foreach ($onlineShippingOptions as $index => $option)
                                            @php
                                                $location = trim((string) ($option['location'] ?? ''));
                                                $description = trim((string) ($option['description'] ?? ''));
                                                $price = (string) ($option['price'] ?? 0);
                                            @endphp
                                            @continue($location === '')
                                            <div class="setup-line-card" data-online-shipping-option>
                                                <div class="setup-line-header">
                                                    <div>
                                                        <strong data-shipping-location>{{ $location }}</strong>
                                                        <div class="subtle" data-shipping-description @if ($description === '') hidden @endif>{{ $description }}</div>
                                                        <div class="subtle">Price: <span data-shipping-price>{{ $price }}</span></div>
                                                    </div>
                                                    <div class="setup-row-actions">
                                                        <button class="btn secondary" type="button" data-edit-online-shipping-option>Edit</button>
                                                        <button class="btn danger" type="button" data-delete-online-shipping-option>Delete</button>
                                                    </div>
                                                </div>
                                                <input type="hidden" data-shipping-field="location" name="shipping_options[{{ $index }}][location]" value="{{ $location }}">
                                                <input type="hidden" data-shipping-field="description" name="shipping_options[{{ $index }}][description]" value="{{ $description }}">
                                                <input type="hidden" data-shipping-field="price" name="shipping_options[{{ $index }}][price]" value="{{ $price }}">
                                            </div>
                                        @endforeach
                                        <div class="setup-empty-line" data-online-shipping-empty @if ($onlineShippingOptions->filter(fn ($option) => trim((string) ($option['location'] ?? '')) !== '')->isNotEmpty()) hidden @endif>No shipping options added yet.</div>
                                    </div>
                                    <div class="button-row"><button class="btn primary" type="submit">Save shipping details</button></div>
                                </div>

                                <div class="setup-section online-store-section-panel" id="online-store-socials" role="tabpanel" data-online-store-section-panel hidden>
                                    <h3 class="setup-section-title">Social Accounts</h3>
                                    <div class="form-grid">
                                        <div class="field"><label>Instagram</label><input name="socials[instagram]" value="{{ $onlineSocials['instagram'] ?? '' }}"></div>
                                        <div class="field"><label>TikTok</label><input name="socials[tiktok]" value="{{ $onlineSocials['tiktok'] ?? '' }}"></div>
                                        <div class="field"><label>Facebook</label><input name="socials[facebook]" value="{{ $onlineSocials['facebook'] ?? '' }}"></div>
                                        <div class="field"><label>Twitter / X</label><input name="socials[twitter]" value="{{ $onlineSocials['twitter'] ?? '' }}"></div>
                                        <div class="field"><label>YouTube</label><input name="socials[youtube]" value="{{ $onlineSocials['youtube'] ?? '' }}"></div>
                                        <div class="field"><label>WhatsApp</label><input name="socials[whatsapp]" value="{{ $onlineSocials['whatsapp'] ?? '' }}"></div>
                                    </div>
                                    <div class="button-row"><button class="btn primary" type="submit">Save social accounts</button></div>
                                </div>

                                <div class="setup-section online-store-section-panel" id="online-store-pages" role="tabpanel" data-online-store-section-panel hidden>
                                    <h3 class="setup-section-title">Additional Pages</h3>
                                    <div class="form-grid">
                                        <div class="field full"><label>About Us Page</label><textarea name="pages[about_us]" rows="4">{{ $onlinePages['about_us'] ?? '' }}</textarea></div>
                                        <div class="field full"><label>Terms of Use</label><textarea name="pages[terms_of_use]" rows="4">{{ $onlinePages['terms_of_use'] ?? '' }}</textarea></div>
                                        <div class="field full"><label>Return Policy</label><textarea name="pages[return_policy]" rows="4">{{ $onlinePages['return_policy'] ?? '' }}</textarea></div>
                                        <div class="field full"><label>Privacy Policy</label><textarea name="pages[privacy_policy]" rows="4">{{ $onlinePages['privacy_policy'] ?? '' }}</textarea></div>
                                        <div class="field full"><label>Shipping Information</label><textarea name="pages[shipping_information]" rows="4">{{ $onlinePages['shipping_information'] ?? '' }}</textarea></div>
                                    </div>
                                    <div class="button-row"><button class="btn primary" type="submit">Save pages</button></div>
                                </div>

                                <div class="setup-section online-store-section-panel" id="online-store-faq" role="tabpanel" data-online-store-section-panel hidden>
                                    <h3 class="setup-section-title">FAQ</h3>
                                    <div class="setup-line-header setup-list-toolbar">
                                        <label>FAQ</label>
                                        <button class="btn primary" type="button" data-add-online-faq>Add FAQ</button>
                                    </div>
                                    <div data-online-faqs>
                                        @foreach ($onlineFaqs as $index => $faq)
                                            @php
                                                $question = trim((string) ($faq['question'] ?? ''));
                                                $answer = trim((string) ($faq['answer'] ?? ''));
                                            @endphp
                                            @continue($question === '' && $answer === '')
                                            <div class="setup-line-card" data-online-faq>
                                                <div class="setup-line-header">
                                                    <div>
                                                        <strong data-faq-question>{{ $question ?: 'FAQ item' }}</strong>
                                                        <div class="subtle" data-faq-answer>{{ $answer ?: 'Answer not set' }}</div>
                                                    </div>
                                                    <div class="setup-row-actions">
                                                        <button class="btn secondary" type="button" data-edit-online-faq>Edit</button>
                                                        <button class="btn danger" type="button" data-delete-online-faq>Delete</button>
                                                    </div>
                                                </div>
                                                <input type="hidden" data-faq-field="question" name="faqs[{{ $index }}][question]" value="{{ $question }}">
                                                <input type="hidden" data-faq-field="answer" name="faqs[{{ $index }}][answer]" value="{{ $answer }}">
                                            </div>
                                        @endforeach
                                        <div class="setup-empty-line" data-online-faq-empty @if ($onlineFaqs->filter(fn ($faq) => trim((string) ($faq['question'] ?? '')) !== '' || trim((string) ($faq['answer'] ?? '')) !== '')->isNotEmpty()) hidden @endif>No FAQ items added yet.</div>
                                    </div>
                                    <div class="button-row"><button class="btn primary" type="submit">Save FAQ</button></div>
                                </div>

                                <dialog class="dialog" id="online-shipping-dialog" data-online-shipping-dialog>
                                    <div class="dialog-header">
                                        <div>
                                            <h2 class="panel-title" data-online-shipping-dialog-title>Shipping option</h2>
                                            <p class="subtle">Add or update a delivery location and price.</p>
                                        </div>
                                        <button class="icon-btn" type="button" data-online-shipping-cancel aria-label="Close">x</button>
                                    </div>
                                    <div class="dialog-body">
                                        <input type="hidden" data-online-shipping-edit-index>
                                        <div class="form-grid">
                                            <div class="field"><label>Location</label><input data-online-shipping-input="location"></div>
                                            <div class="field"><label>Description</label><textarea data-online-shipping-input="description" rows="2" placeholder="e.g 3-5 days"></textarea></div>
                                            <div class="field"><label>Price</label><input data-online-shipping-input="price" type="number" min="0" step="0.01" value="0"></div>
                                        </div>
                                        <div class="button-row">
                                            <button class="btn secondary" type="button" data-online-shipping-cancel>Cancel</button>
                                            <button class="btn primary" type="button" data-save-online-shipping-option>Save shipping option</button>
                                        </div>
                                    </div>
                                </dialog>

                                <dialog class="dialog" id="online-faq-dialog" data-online-faq-dialog>
                                    <div class="dialog-header">
                                        <div>
                                            <h2 class="panel-title" data-online-faq-dialog-title>FAQ item</h2>
                                            <p class="subtle">Add or update a question and answer for the storefront FAQ page.</p>
                                        </div>
                                        <button class="icon-btn" type="button" data-online-faq-cancel aria-label="Close">x</button>
                                    </div>
                                    <div class="dialog-body">
                                        <input type="hidden" data-online-faq-edit-index>
                                        <div class="form-grid">
                                            <div class="field"><label>Question</label><input data-online-faq-input="question"></div>
                                            <div class="field full"><label>Answer</label><textarea data-online-faq-input="answer" rows="4"></textarea></div>
                                        </div>
                                        <div class="button-row">
                                            <button class="btn secondary" type="button" data-online-faq-cancel>Cancel</button>
                                            <button class="btn primary" type="button" data-save-online-faq>Save FAQ item</button>
                                        </div>
                                    </div>
                                </dialog>
                            </form>
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
                                            @if (collect($branch->settings['delivery_methods'] ?? [])->isNotEmpty())
                                                <div class="subtle">Delivery: {{ collect($branch->settings['delivery_methods'])->pluck('name')->join(', ') }}</div>
                                            @endif
                                        </div>
                                        <div style="display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end;">
                                            @if ($branch->is_primary)
                                                <span class="badge">Primary</span>
                                            @endif
                                            <button class="btn secondary" type="button" data-dialog-open="branch-edit-{{ $branch->id }}">Edit</button>
                                        </div>
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
                                        <span class="badge neutral">{{ $membership->status->label() }}</span>
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
                        <div class="field full"><label for="payment_methods">Payment methods</label><input id="payment_methods" name="payment_methods" value="{{ $paymentMethods }}" placeholder="Cash, Bank transfer, POS/Card, Cheque"></div>
                        <div class="field full">
                            <label>Bank details</label>
                            <div data-business-bank-details>
                                @foreach ($bankDetails as $index => $account)
                                    <div class="setup-line-card" data-business-bank-detail>
                                        <div class="setup-line-header"><strong>Bank account</strong><button class="btn danger" type="button" data-remove-business-bank-detail>Remove</button></div>
                                        <div class="form-grid">
                                            <div class="field"><label>Bank name</label><input name="bank_details[{{ $index }}][bank_name]" value="{{ $account['bank_name'] ?? '' }}"></div>
                                            <div class="field"><label>Bank account name</label><input name="bank_details[{{ $index }}][account_name]" value="{{ $account['account_name'] ?? '' }}"></div>
                                            <div class="field"><label>Bank account number</label><input name="bank_details[{{ $index }}][account_number]" value="{{ $account['account_number'] ?? '' }}"></div>
                                            <div class="field"><label>Status</label><select name="bank_details[{{ $index }}][status]"><option value="active" @selected(($account['status'] ?? 'active') === 'active')>Active</option><option value="inactive" @selected(($account['status'] ?? 'active') === 'inactive')>Inactive</option></select></div>
                                            <div class="field">
                                                <label>Asset account code</label>
                                                <input type="hidden" name="bank_details[{{ $index }}][asset_account_code]" value="{{ $account['asset_account_code'] ?? '' }}">
                                                <span class="subtle" data-bank-asset-code>{{ $account['asset_account_code'] ?? 'Created on save' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <button class="btn primary" type="button" data-add-business-bank-detail style="margin-top: 10px;">Add bank account</button>
                        </div>
                        <div class="field full"><label><input type="checkbox" name="maintenance_mode" value="1" @checked($maintenanceMode)> Enable maintenance mode</label></div>
                        <div class="field">
                            <label for="plan_id">Subscription plan</label>
                            <select id="plan_id" name="plan_id">
                                <option value="">No plan selected</option>
                                @foreach ($plans as $plan)
                                    <option value="{{ $plan->id }}" data-has-inventory="{{ $plan->modules->contains(fn ($module) => $module->slug === 'inventory' && (bool) ($module->pivot->is_enabled ?? true)) ? '1' : '0' }}" @selected((string) old('plan_id', $selectedPlanId) === (string) $plan->id)>{{ $plan->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field full" data-estimated-cost-cogs-setting @if ($selectedPlanHasInventory) hidden @endif>
                            <label><input type="hidden" name="use_estimated_cost_for_cogs" value="0" @disabled($selectedPlanHasInventory)><input type="checkbox" name="use_estimated_cost_for_cogs" value="1" @checked($useEstimatedCostForCogs) @disabled($selectedPlanHasInventory)> Use Estimated cost for COGS</label>
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
            @if ($isPlatformAdmin)
                <dialog class="dialog" id="subscription-dialog">
                    <div class="dialog-header"><div><h2 class="panel-title">Add subscription</h2><p class="subtle">Assign a plan and billing period to this organization.</p></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button></div>
                    <div class="dialog-body">
                        <form class="mini-form" method="POST" action="{{ route('admin.business.subscriptions.store') }}">
                            @csrf
                            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                            <div class="form-grid">
                                <div class="field">
                                    <label>Plan</label>
                                    <select name="plan_id" required>
                                        <option value="">Select plan</option>
                                        @foreach ($plans as $plan)
                                            <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label>Status</label>
                                    <select name="status" required>
                                        @foreach ($subscriptionStatuses as $status)
                                            <option value="{{ $status->value }}" @selected($status->value === 'active')>{{ $status->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label>Billing interval</label>
                                    <select name="billing_interval" required>
                                        <option value="monthly">Monthly</option>
                                        <option value="yearly">Yearly</option>
                                    </select>
                                </div>
                                <div class="field"><label>Trial ends</label><input name="trial_ends_at" type="date"></div>
                                <div class="field"><label>Period starts</label><input name="current_period_starts_at" type="date"></div>
                                <div class="field"><label>Period ends</label><input name="current_period_ends_at" type="date"></div>
                                <div class="field"><label>Cancelled at</label><input name="cancelled_at" type="date"></div>
                            </div>
                            <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Cancel</button><button class="btn primary" type="submit">Add subscription</button></div>
                        </form>
                    </div>
                </dialog>

                @foreach ($tenantSubscriptions as $subscription)
                    <dialog class="dialog" id="subscription-edit-{{ $subscription->id }}">
                        <div class="dialog-header"><div><h2 class="panel-title">Edit subscription</h2><p class="subtle">Update the plan, status, and billing period.</p></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button></div>
                        <div class="dialog-body">
                            <form class="mini-form" method="POST" action="{{ route('admin.business.subscriptions.update', $subscription) }}">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                                <div class="form-grid">
                                    <div class="field">
                                        <label>Plan</label>
                                        <select name="plan_id" required>
                                            @foreach ($plans as $plan)
                                                <option value="{{ $plan->id }}" @selected((int) $subscription->plan_id === (int) $plan->id)>{{ $plan->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label>Status</label>
                                        <select name="status" required>
                                            @foreach ($subscriptionStatuses as $status)
                                                <option value="{{ $status->value }}" @selected($subscription->status->value === $status->value)>{{ $status->label() }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label>Billing interval</label>
                                        <select name="billing_interval" required>
                                            <option value="monthly" @selected($subscription->billing_interval === 'monthly')>Monthly</option>
                                            <option value="yearly" @selected($subscription->billing_interval === 'yearly')>Yearly</option>
                                        </select>
                                    </div>
                                    <div class="field"><label>Trial ends</label><input name="trial_ends_at" type="date" value="{{ $subscription->trial_ends_at?->format('Y-m-d') }}"></div>
                                    <div class="field"><label>Period starts</label><input name="current_period_starts_at" type="date" value="{{ $subscription->current_period_starts_at?->format('Y-m-d') }}"></div>
                                    <div class="field"><label>Period ends</label><input name="current_period_ends_at" type="date" value="{{ $subscription->current_period_ends_at?->format('Y-m-d') }}"></div>
                                    <div class="field"><label>Cancelled at</label><input name="cancelled_at" type="date" value="{{ $subscription->cancelled_at?->format('Y-m-d') }}"></div>
                                </div>
                                <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Cancel</button><button class="btn primary" type="submit">Save subscription</button></div>
                            </form>
                        </div>
                    </dialog>
                @endforeach
            @endif

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
                        <div class="field">
                            <label>Delivery methods</label>
                            <div data-delivery-methods>
                                <div class="setup-line-card" data-delivery-method>
                                    <div class="setup-line-header"><strong>Delivery option</strong><button class="btn danger" type="button" data-remove-delivery-method>Remove</button></div>
                                    <div class="form-grid">
                                        <div class="field"><label>Method</label><input name="delivery_methods[0][name]" placeholder="Standard delivery"></div>
                                        <div class="field"><label>Price</label><input name="delivery_methods[0][price]" type="number" min="0" step="0.01" value="0"></div>
                                        <div class="field"><label>Status</label><select name="delivery_methods[0][status]"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                                    </div>
                                </div>
                            </div>
                            <button class="btn primary" type="button" data-add-delivery-method style="margin-top: 10px;">Add delivery method</button>
                        </div>
                        <input type="hidden" name="status" value="active">
                        <label><input type="checkbox" name="is_primary" value="1"> Make primary branch</label>
                        <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Cancel</button><button class="btn primary" type="submit">Add branch</button></div>
                    </form>
                </div>
            </dialog>

            @foreach ($branches as $branch)
                @php
                    $branchDeliveryMethods = collect($branch->settings['delivery_methods'] ?? []);
                    $branchDeliveryMethods = $branchDeliveryMethods->isNotEmpty() ? $branchDeliveryMethods : collect([null]);
                @endphp
                <dialog class="dialog" id="branch-edit-{{ $branch->id }}">
                    <div class="dialog-header"><div><h2 class="panel-title">Edit branch</h2><p class="subtle">Update branch identity, tax, and delivery options.</p></div><button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button></div>
                    <div class="dialog-body">
                        <form class="mini-form" method="POST" action="{{ route('admin.business.branches.update', $branch) }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                            <div class="form-grid">
                                <div class="field"><label>Name</label><input name="name" value="{{ $branch->name }}" required></div>
                                <div class="field"><label>Code</label><input name="code" value="{{ $branch->code }}" required></div>
                            </div>
                            <div class="field"><label>Address</label><textarea name="address">{{ $branch->address }}</textarea></div>
                            <div class="form-grid">
                                <div class="field"><label>Phone</label><input name="phone" value="{{ $branch->phone }}"></div>
                                <div class="field"><label>Email</label><input name="email" type="email" value="{{ $branch->email }}"></div>
                                <div class="field"><label>Currency</label><input name="currency_code" maxlength="3" value="{{ $branch->currency_code ?: $tenant->currency_code }}"></div>
                                <div class="field"><label>Tax rate</label><input name="default_tax_rate" type="number" step="0.01" value="{{ $branch->default_tax_rate ?? $tenant->default_tax_rate }}"></div>
                                <div class="field"><label>Status</label><select name="status"><option value="active" @selected($branch->status === 'active')>Active</option><option value="inactive" @selected($branch->status === 'inactive')>Inactive</option></select></div>
                            </div>
                            <div class="field">
                                <label>Delivery methods</label>
                                <div data-delivery-methods>
                                    @foreach ($branchDeliveryMethods as $index => $method)
                                        <div class="setup-line-card" data-delivery-method>
                                            <div class="setup-line-header"><strong>Delivery option</strong><button class="btn danger" type="button" data-remove-delivery-method>Remove</button></div>
                                            <div class="form-grid">
                                                <div class="field"><label>Method</label><input name="delivery_methods[{{ $index }}][name]" value="{{ $method['name'] ?? '' }}" placeholder="Standard delivery"></div>
                                                <div class="field"><label>Price</label><input name="delivery_methods[{{ $index }}][price]" type="number" min="0" step="0.01" value="{{ $method['price'] ?? 0 }}"></div>
                                                <div class="field"><label>Status</label><select name="delivery_methods[{{ $index }}][status]"><option value="active" @selected(($method['status'] ?? 'active') === 'active')>Active</option><option value="inactive" @selected(($method['status'] ?? 'active') === 'inactive')>Inactive</option></select></div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <button class="btn primary" type="button" data-add-delivery-method style="margin-top: 10px;">Add delivery method</button>
                            </div>
                            <label><input type="checkbox" name="is_primary" value="1" @checked($branch->is_primary)> Make primary branch</label>
                            <div class="button-row"><button class="btn secondary" type="button" data-dialog-close>Cancel</button><button class="btn primary" type="submit">Save branch</button></div>
                        </form>
                    </div>
                </dialog>
            @endforeach

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

        <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (window.storebootBusinessSetupBound) return;
            window.storebootBusinessSetupBound = true;

            const paintThemeColorField = (field) => {
                field.style.backgroundColor = field.value;
            };

            document.querySelectorAll('[data-theme-color-field]').forEach(paintThemeColorField);

            const planSelect = document.querySelector('#plan_id');
            const estimatedCostCogsSetting = document.querySelector('[data-estimated-cost-cogs-setting]');
            const syncEstimatedCostCogsSetting = () => {
                if (!planSelect || !estimatedCostCogsSetting) return;

                const selectedOption = planSelect.options[planSelect.selectedIndex];
                const hasInventory = selectedOption?.dataset.hasInventory === '1';

                estimatedCostCogsSetting.hidden = hasInventory;
                estimatedCostCogsSetting.querySelectorAll('input').forEach((input) => {
                    input.disabled = hasInventory;
                });
            };

            planSelect?.addEventListener('change', syncEstimatedCostCogsSetting);
            syncEstimatedCostCogsSetting();

            const onlineStoreSectionPanels = Array.from(document.querySelectorAll('[data-online-store-section-panel]'));
            const onlineStoreSectionTabs = Array.from(document.querySelectorAll('[data-online-store-section-target]'));
            const onlineStoreActiveSection = document.querySelector('[data-online-store-active-section]');

            const activateOnlineStoreSection = (id) => {
                if (!onlineStoreSectionPanels.length) return;

                const panelId = onlineStoreSectionPanels.some((panel) => panel.id === id)
                    ? id
                    : onlineStoreSectionPanels[0].id;

                onlineStoreSectionPanels.forEach((panel) => {
                    panel.hidden = panel.id !== panelId;
                });

                onlineStoreSectionTabs.forEach((tab) => {
                    const isActive = tab.dataset.onlineStoreSectionTarget === panelId;
                    tab.classList.toggle('active', isActive);
                    tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });

                if (onlineStoreActiveSection) {
                    onlineStoreActiveSection.value = panelId;
                }
            };

            onlineStoreSectionTabs.forEach((tab) => {
                tab.addEventListener('click', () => {
                    activateOnlineStoreSection(tab.dataset.onlineStoreSectionTarget);
                });
            });

            activateOnlineStoreSection(onlineStoreActiveSection?.value ?? new URLSearchParams(window.location.search).get('online_store_section') ?? 'online-store-basics');

            const syncPaystackOptions = () => {
                const toggle = document.querySelector('[data-paystack-toggle]');
                const options = document.querySelector('[data-paystack-options]');
                const fallback = document.querySelector('[data-paystack-method-fallback]');
                const selfHostedFields = document.querySelector('[data-self-hosted-paystack-fields]');
                const settlementBankFields = document.querySelector('[data-paystack-settlement-bank-fields]');
                const radios = Array.from(document.querySelectorAll('[data-paystack-method-option]'));
                if (!toggle || !options || !fallback) return;

                options.hidden = !toggle.checked;
                fallback.disabled = toggle.checked;
                radios.forEach((radio) => {
                    radio.disabled = !toggle.checked;
                });

                if (toggle.checked && !radios.some((radio) => radio.checked)) {
                    const storebootPaystack = radios.find((radio) => radio.value === 'storeboot_paystack');
                    if (storebootPaystack) storebootPaystack.checked = true;
                }

                const selectedMethod = radios.find((radio) => radio.checked && !radio.disabled)?.value ?? 'none';
                if (selfHostedFields) {
                    selfHostedFields.hidden = !toggle.checked || selectedMethod !== 'self_hosted_paystack';
                }
                if (settlementBankFields) {
                    settlementBankFields.hidden = !toggle.checked || selectedMethod !== 'storeboot_paystack';
                }
            };

            document.querySelector('[data-paystack-toggle]')?.addEventListener('change', syncPaystackOptions);
            document.querySelectorAll('[data-paystack-method-option]').forEach((radio) => {
                radio.addEventListener('change', syncPaystackOptions);
            });
            syncPaystackOptions();

            const onlineShippingDialog = document.querySelector('[data-online-shipping-dialog]');
            const onlineFaqDialog = document.querySelector('[data-online-faq-dialog]');
            const onlineShippingList = document.querySelector('[data-online-shipping-options]');
            const onlineFaqList = document.querySelector('[data-online-faqs]');
            const onlineSlidesList = document.querySelector('[data-online-slides]');
            const transferBankSelector = document.querySelector('[data-transfer-bank-selector]');
            const transferMethodCheckbox = document.querySelector('input[name="payment_methods[]"][value="bank_account"]');

            const setDialogInputValues = (dialog, selector, values) => {
                Object.entries(values).forEach(([key, value]) => {
                    const input = dialog?.querySelector(`${selector}="${key}"]`);
                    if (input) input.value = value ?? '';
                });
            };

            const updateOnlineShippingEmptyState = () => {
                const empty = onlineShippingList?.querySelector('[data-online-shipping-empty]');
                if (empty) {
                    empty.hidden = Boolean(onlineShippingList?.querySelector('[data-online-shipping-option]'));
                }
            };

            const updateOnlineFaqEmptyState = () => {
                const empty = onlineFaqList?.querySelector('[data-online-faq-empty]');
                if (empty) {
                    empty.hidden = Boolean(onlineFaqList?.querySelector('[data-online-faq]'));
                }
            };

            const updateOnlineSlidesEmptyState = () => {
                const empty = onlineSlidesList?.querySelector('[data-online-slides-empty]');
                if (empty) {
                    empty.hidden = Boolean(onlineSlidesList?.querySelector('[data-online-slide]'));
                }
            };

            const renumberOnlineShippingOptions = () => {
                onlineShippingList?.querySelectorAll('[data-online-shipping-option]').forEach((row, index) => {
                    row.querySelector('[data-shipping-field="location"]').name = `shipping_options[${index}][location]`;
                    row.querySelector('[data-shipping-field="description"]').name = `shipping_options[${index}][description]`;
                    row.querySelector('[data-shipping-field="price"]').name = `shipping_options[${index}][price]`;
                });
                updateOnlineShippingEmptyState();
            };

            const renumberOnlineFaqs = () => {
                onlineFaqList?.querySelectorAll('[data-online-faq]').forEach((row, index) => {
                    row.querySelector('[data-faq-field="question"]').name = `faqs[${index}][question]`;
                    row.querySelector('[data-faq-field="answer"]').name = `faqs[${index}][answer]`;
                });
                updateOnlineFaqEmptyState();
            };

            const renumberOnlineSlides = () => {
                onlineSlidesList?.querySelectorAll('[data-online-slide]').forEach((row, index) => {
                    const title = row.querySelector('[data-slide-title]');
                    const image = row.querySelector('[data-slide-image-input]');
                    const existingPath = row.querySelector('[data-slide-field="existing_image_path"]');
                    const tag = row.querySelector('[data-slide-field="hero_image_tag"]');
                    const text = row.querySelector('[data-slide-field="hero_image_text"]');
                    const description = row.querySelector('[data-slide-field="hero_image_description"]');

                    if (title) title.textContent = `Slide ${index + 1}`;
                    if (image) image.name = `slides[${index}][image]`;
                    if (existingPath) existingPath.name = `slides[${index}][existing_image_path]`;
                    if (tag) tag.name = `slides[${index}][hero_image_tag]`;
                    if (text) text.name = `slides[${index}][hero_image_text]`;
                    if (description) description.name = `slides[${index}][hero_image_description]`;
                });
                updateOnlineSlidesEmptyState();
            };

            const updateOnlineShippingRow = (row, values) => {
                const location = (values.location ?? '').trim();
                const description = (values.description ?? '').trim();
                const price = `${values.price ?? 0}`.trim() || '0';
                const descriptionNode = row.querySelector('[data-shipping-description]');

                row.querySelector('[data-shipping-location]').textContent = location || 'Shipping option';
                if (descriptionNode) {
                    descriptionNode.textContent = description;
                    descriptionNode.hidden = description === '';
                }
                row.querySelector('[data-shipping-price]').textContent = price;
                row.querySelector('[data-shipping-field="location"]').value = location;
                row.querySelector('[data-shipping-field="description"]').value = description;
                row.querySelector('[data-shipping-field="price"]').value = price;
            };

            const createOnlineShippingRow = (values) => {
                const row = document.createElement('div');
                row.className = 'setup-line-card';
                row.dataset.onlineShippingOption = '';
                row.innerHTML = `
                    <div class="setup-line-header">
                        <div>
                            <strong data-shipping-location>Shipping option</strong>
                            <div class="subtle" data-shipping-description hidden></div>
                            <div class="subtle">Price: <span data-shipping-price>0</span></div>
                        </div>
                        <div class="setup-row-actions">
                            <button class="btn secondary" type="button" data-edit-online-shipping-option>Edit</button>
                            <button class="btn danger" type="button" data-delete-online-shipping-option>Delete</button>
                        </div>
                    </div>
                    <input type="hidden" data-shipping-field="location">
                    <input type="hidden" data-shipping-field="description">
                    <input type="hidden" data-shipping-field="price">
                `;
                updateOnlineShippingRow(row, values);
                return row;
            };

            const openOnlineShippingDialog = (values = {}, index = '') => {
                if (!onlineShippingDialog) return;
                onlineShippingDialog.querySelector('[data-online-shipping-dialog-title]').textContent = index === '' ? 'Add shipping option' : 'Edit shipping option';
                onlineShippingDialog.querySelector('[data-online-shipping-edit-index]').value = index;
                setDialogInputValues(onlineShippingDialog, '[data-online-shipping-input', {
                    location: values.location ?? '',
                    description: values.description ?? '',
                    price: values.price ?? '0',
                });
                onlineShippingDialog.showModal();
            };

            const onlineShippingValuesFromRow = (row) => ({
                location: row.querySelector('[data-shipping-field="location"]')?.value ?? '',
                description: row.querySelector('[data-shipping-field="description"]')?.value ?? '',
                price: row.querySelector('[data-shipping-field="price"]')?.value ?? '0',
            });

            const updateOnlineFaqRow = (row, values) => {
                const question = (values.question ?? '').trim();
                const answer = (values.answer ?? '').trim();

                row.querySelector('[data-faq-question]').textContent = question || 'FAQ item';
                row.querySelector('[data-faq-answer]').textContent = answer || 'Answer not set';
                row.querySelector('[data-faq-field="question"]').value = question;
                row.querySelector('[data-faq-field="answer"]').value = answer;
            };

            const createOnlineFaqRow = (values) => {
                const row = document.createElement('div');
                row.className = 'setup-line-card';
                row.dataset.onlineFaq = '';
                row.innerHTML = `
                    <div class="setup-line-header">
                        <div>
                            <strong data-faq-question>FAQ item</strong>
                            <div class="subtle" data-faq-answer>Answer not set</div>
                        </div>
                        <div class="setup-row-actions">
                            <button class="btn secondary" type="button" data-edit-online-faq>Edit</button>
                            <button class="btn danger" type="button" data-delete-online-faq>Delete</button>
                        </div>
                    </div>
                    <input type="hidden" data-faq-field="question">
                    <input type="hidden" data-faq-field="answer">
                `;
                updateOnlineFaqRow(row, values);
                return row;
            };

            const openOnlineFaqDialog = (values = {}, index = '') => {
                if (!onlineFaqDialog) return;
                onlineFaqDialog.querySelector('[data-online-faq-dialog-title]').textContent = index === '' ? 'Add FAQ item' : 'Edit FAQ item';
                onlineFaqDialog.querySelector('[data-online-faq-edit-index]').value = index;
                setDialogInputValues(onlineFaqDialog, '[data-online-faq-input', {
                    question: values.question ?? '',
                    answer: values.answer ?? '',
                });
                onlineFaqDialog.showModal();
            };

            const onlineFaqValuesFromRow = (row) => ({
                question: row.querySelector('[data-faq-field="question"]')?.value ?? '',
                answer: row.querySelector('[data-faq-field="answer"]')?.value ?? '',
            });

            const createOnlineSlideRow = () => {
                const row = document.createElement('div');
                row.className = 'online-slide-card';
                row.dataset.onlineSlide = '';
                row.innerHTML = `
                    <label class="online-slide-photo">
                        <input type="file" accept="image/*" data-slide-image-input>
                        <input type="hidden" value="" data-slide-field="existing_image_path">
                        <span data-slide-placeholder>Upload Banner / Hero Image<br><small>(recommended 1600 x 600px)</small></span>
                    </label>
                    <div class="online-slide-form">
                        <div class="setup-line-header">
                            <strong data-slide-title>Slide</strong>
                            <button class="btn danger" type="button" data-remove-online-slide>Remove</button>
                        </div>
                        <div class="field"><label>Banner Image Tag</label><input data-slide-field="hero_image_tag"></div>
                        <div class="field"><label>Banner Image Text</label><input data-slide-field="hero_image_text"></div>
                        <div class="field"><label>Banner Image Description</label><textarea rows="3" data-slide-field="hero_image_description"></textarea></div>
                    </div>
                `;

                return row;
            };

            const syncTransferBankSelector = () => {
                if (!transferBankSelector || !transferMethodCheckbox) return;
                transferBankSelector.hidden = !transferMethodCheckbox.checked;
            };

            transferMethodCheckbox?.addEventListener('change', syncTransferBankSelector);
            syncTransferBankSelector();

            renumberOnlineShippingOptions();
            renumberOnlineFaqs();
            renumberOnlineSlides();

            document.addEventListener('input', (event) => {
                const colorField = event.target.closest('[data-theme-color-field]');
                if (colorField) {
                    paintThemeColorField(colorField);
                }

                const slideImageInput = event.target.closest('[data-slide-image-input]');
                if (slideImageInput?.files?.[0]) {
                    const row = slideImageInput.closest('[data-online-slide]');
                    const photo = slideImageInput.closest('.online-slide-photo');
                    const previousPreview = photo?.querySelector('[data-slide-preview]');
                    const placeholder = photo?.querySelector('[data-slide-placeholder]');
                    const preview = previousPreview ?? document.createElement('img');
                    preview.dataset.slidePreview = '';
                    preview.alt = 'Selected slide image preview';
                    preview.src = URL.createObjectURL(slideImageInput.files[0]);
                    placeholder?.remove();
                    if (!previousPreview) photo?.appendChild(preview);
                    const existingPath = row?.querySelector('[data-slide-field="existing_image_path"]');
                    if (existingPath) existingPath.value = '';
                }
            });

            document.addEventListener('click', (event) => {
                const addDelivery = event.target.closest('[data-add-delivery-method]');
                if (addDelivery) {
                    const list = addDelivery.closest('form')?.querySelector('[data-delivery-methods]');
                    const first = list?.querySelector('[data-delivery-method]');
                    if (!list || !first) return;
                    const index = list.querySelectorAll('[data-delivery-method]').length;
                    const row = first.cloneNode(true);
                    row.querySelectorAll('[name]').forEach((field) => {
                        field.name = field.name.replace(/delivery_methods\[\d+\]/, `delivery_methods[${index}]`);
                        if (field.tagName === 'SELECT') field.value = 'active';
                        else field.value = field.name.endsWith('[price]') ? '0' : '';
                    });
                    list.appendChild(row);
                    return;
                }

                const removeDelivery = event.target.closest('[data-remove-delivery-method]');
                if (removeDelivery) {
                    const row = removeDelivery.closest('[data-delivery-method]');
                    const list = removeDelivery.closest('[data-delivery-methods]');
                    if (row && list?.querySelectorAll('[data-delivery-method]').length > 1) {
                        row.remove();
                        return;
                    }
                    row?.querySelectorAll('input').forEach((field) => {
                        field.value = field.name.endsWith('[price]') ? '0' : '';
                    });
                    row?.querySelectorAll('select').forEach((field) => {
                        field.value = 'active';
                    });
                    return;
                }

                const addBank = event.target.closest('[data-add-business-bank-detail]');
                if (addBank) {
                    const list = addBank.closest('.field')?.querySelector('[data-business-bank-details]');
                    const first = list?.querySelector('[data-business-bank-detail]');
                    if (!list || !first) return;
                    const index = list.querySelectorAll('[data-business-bank-detail]').length;
                    const row = first.cloneNode(true);
                    row.querySelectorAll('[name]').forEach((field) => {
                        field.name = field.name.replace(/bank_details\[\d+\]/, `bank_details[${index}]`);
                        if (field.tagName === 'SELECT') field.value = 'active';
                        else field.value = '';
                    });
                    const assetCode = row.querySelector('[data-bank-asset-code]');
                    if (assetCode) assetCode.textContent = 'Created on save';
                    list.appendChild(row);
                    return;
                }

                const addSlide = event.target.closest('[data-add-online-slide]');
                if (addSlide) {
                    event.preventDefault();
                    const row = createOnlineSlideRow();
                    const empty = onlineSlidesList?.querySelector('[data-online-slides-empty]');
                    if (empty) {
                        onlineSlidesList?.insertBefore(row, empty);
                    } else {
                        onlineSlidesList?.appendChild(row);
                    }
                    renumberOnlineSlides();
                    return;
                }

                const removeSlide = event.target.closest('[data-remove-online-slide]');
                if (removeSlide) {
                    removeSlide.closest('[data-online-slide]')?.remove();
                    renumberOnlineSlides();
                    return;
                }

                const openShippingDialog = event.target.closest('[data-open-online-shipping-dialog]');
                if (openShippingDialog) {
                    openOnlineShippingDialog();
                    return;
                }

                const editOnlineShipping = event.target.closest('[data-edit-online-shipping-option]');
                if (editOnlineShipping) {
                    const row = editOnlineShipping.closest('[data-online-shipping-option]');
                    const rows = Array.from(onlineShippingList?.querySelectorAll('[data-online-shipping-option]') ?? []);
                    if (row) openOnlineShippingDialog(onlineShippingValuesFromRow(row), rows.indexOf(row));
                    return;
                }

                const deleteOnlineShipping = event.target.closest('[data-delete-online-shipping-option]');
                if (deleteOnlineShipping) {
                    const row = deleteOnlineShipping.closest('[data-online-shipping-option]');
                    if (row) {
                        row.remove();
                        renumberOnlineShippingOptions();
                    }
                    return;
                }

                const saveOnlineShipping = event.target.closest('[data-save-online-shipping-option]');
                if (saveOnlineShipping) {
                    const values = {
                        location: onlineShippingDialog?.querySelector('[data-online-shipping-input="location"]')?.value ?? '',
                        description: onlineShippingDialog?.querySelector('[data-online-shipping-input="description"]')?.value ?? '',
                        price: onlineShippingDialog?.querySelector('[data-online-shipping-input="price"]')?.value ?? '0',
                    };
                    const index = onlineShippingDialog?.querySelector('[data-online-shipping-edit-index]')?.value ?? '';
                    const rows = Array.from(onlineShippingList?.querySelectorAll('[data-online-shipping-option]') ?? []);
                    const row = index === '' ? createOnlineShippingRow(values) : rows[Number(index)];

                    if (index === '') {
                        onlineShippingList?.appendChild(row);
                    } else if (row) {
                        updateOnlineShippingRow(row, values);
                    }

                    renumberOnlineShippingOptions();
                    onlineShippingDialog?.close();
                    return;
                }

                const cancelOnlineShipping = event.target.closest('[data-online-shipping-cancel]');
                if (cancelOnlineShipping) {
                    onlineShippingDialog?.close();
                    return;
                }

                const addFaq = event.target.closest('[data-add-online-faq]');
                if (addFaq) {
                    openOnlineFaqDialog();
                    return;
                }

                const editOnlineFaq = event.target.closest('[data-edit-online-faq]');
                if (editOnlineFaq) {
                    const row = editOnlineFaq.closest('[data-online-faq]');
                    const rows = Array.from(onlineFaqList?.querySelectorAll('[data-online-faq]') ?? []);
                    if (row) openOnlineFaqDialog(onlineFaqValuesFromRow(row), rows.indexOf(row));
                    return;
                }

                const deleteOnlineFaq = event.target.closest('[data-delete-online-faq]');
                if (deleteOnlineFaq) {
                    const row = deleteOnlineFaq.closest('[data-online-faq]');
                    if (row) {
                        row.remove();
                        renumberOnlineFaqs();
                    }
                    return;
                }

                const saveOnlineFaq = event.target.closest('[data-save-online-faq]');
                if (saveOnlineFaq) {
                    const values = {
                        question: onlineFaqDialog?.querySelector('[data-online-faq-input="question"]')?.value ?? '',
                        answer: onlineFaqDialog?.querySelector('[data-online-faq-input="answer"]')?.value ?? '',
                    };
                    const index = onlineFaqDialog?.querySelector('[data-online-faq-edit-index]')?.value ?? '';
                    const rows = Array.from(onlineFaqList?.querySelectorAll('[data-online-faq]') ?? []);
                    const row = index === '' ? createOnlineFaqRow(values) : rows[Number(index)];

                    if (index === '') {
                        onlineFaqList?.appendChild(row);
                    } else if (row) {
                        updateOnlineFaqRow(row, values);
                    }

                    renumberOnlineFaqs();
                    onlineFaqDialog?.close();
                    return;
                }

                const cancelOnlineFaq = event.target.closest('[data-online-faq-cancel]');
                if (cancelOnlineFaq) {
                    onlineFaqDialog?.close();
                    return;
                }

                const removeBank = event.target.closest('[data-remove-business-bank-detail]');
                if (!removeBank) return;
                const row = removeBank.closest('[data-business-bank-detail]');
                const list = removeBank.closest('[data-business-bank-details]');
                if (row && list?.querySelectorAll('[data-business-bank-detail]').length > 1) {
                    row.remove();
                    return;
                }
                row?.querySelectorAll('input').forEach((field) => {
                    field.value = '';
                });
                row?.querySelectorAll('select').forEach((field) => {
                    field.value = 'active';
                });
            });
        });
        </script>
    @endif
</x-layouts.admin>
