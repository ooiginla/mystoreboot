<x-layouts.admin title="Plans">
    <style>
        .plan-list { display: grid; gap: 16px; }
        .plan-card { border: 1px solid var(--border); border-radius: 16px; background: #fff; overflow: hidden; }
        .plan-summary { display: flex; align-items: center; justify-content: space-between; gap: 18px; padding: 20px; }
        .plan-summary-main { display: flex; align-items: center; gap: 14px; min-width: 0; }
        .plan-icon { display: grid; place-items: center; width: 44px; height: 44px; border-radius: 12px; background: var(--brand-soft); color: var(--brand); font-weight: 800; }
        .plan-summary h2 { margin: 0 0 3px; font-size: 18px; }
        .plan-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .plan-actions { display: flex; align-items: center; gap: 16px; }
        .plan-price { text-align: right; white-space: nowrap; }
        .plan-price strong { display: block; font-size: 16px; }
        .module-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .module-option { display: flex; align-items: flex-start; gap: 10px; padding: 12px; border: 1px solid var(--border); border-radius: 12px; cursor: pointer; }
        .module-option:has(input:checked) { border-color: var(--brand); background: var(--brand-soft); }
        .module-option input { margin-top: 3px; }
        .module-copy { display: grid; gap: 2px; }
        .module-copy small { color: var(--muted); }
        .module-flags { display: flex; gap: 6px; margin-top: 4px; }
        .limits-field textarea { min-height: 130px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px; }
        @media (max-width: 720px) {
            .plan-summary { align-items: flex-start; }
            .plan-actions { align-items: flex-end; flex-direction: column; }
            .plan-price { font-size: 13px; }
            .module-grid { grid-template-columns: 1fr; }
        }
    </style>

    <div class="topbar">
        <div>
            <div class="eyebrow">Platform administration</div>
            <h1>Plans</h1>
            <p class="subtle">Manage subscription pricing and choose the modules included with each plan.</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert error" role="alert">
            <strong>The plan could not be saved.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="plan-list">
        @forelse ($plans as $plan)
            @php
                $enabledModuleIds = $plan->modules
                    ->filter(fn ($module) => (bool) ($module->pivot->is_enabled ?? true))
                    ->pluck('id');
                $monthlyPrice = number_format($plan->monthly_price_minor / 100, 2, '.', '');
                $yearlyPrice = number_format($plan->yearly_price_minor / 100, 2, '.', '');
            @endphp
            <section class="plan-card">
                <div class="plan-summary">
                    <div class="plan-summary-main">
                        <div class="plan-icon">{{ strtoupper(substr($plan->name, 0, 1)) }}</div>
                        <div>
                            <h2>{{ $plan->name }}</h2>
                            <div class="plan-meta">
                                <span class="badge {{ $plan->is_active ? 'success' : 'neutral' }}">{{ $plan->is_active ? 'Active' : 'Inactive' }}</span>
                                <span class="subtle">{{ $enabledModuleIds->count() }} modules · {{ $plan->slug }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="plan-actions">
                        <div class="plan-price">
                            <strong>{{ $plan->currency_code }} {{ number_format($plan->monthly_price_minor / 100, 2) }}</strong>
                            <span class="subtle">per month</span>
                        </div>
                        <button class="btn secondary" type="button" data-dialog-open="plan-edit-{{ $plan->id }}">Edit plan</button>
                    </div>
                </div>
            </section>

            <dialog class="dialog" id="plan-edit-{{ $plan->id }}">
                <form method="POST" action="{{ route('admin.subscriptions.plans.update', $plan) }}">
                    @csrf
                    @method('PUT')
                    <div class="dialog-header">
                        <div>
                            <div class="eyebrow">Subscription plan</div>
                            <h2 class="panel-title">Edit {{ $plan->name }}</h2>
                            <p class="subtle">Changes affect the access supplied by this plan.</p>
                        </div>
                        <button class="btn ghost" type="button" data-dialog-close aria-label="Close">Close</button>
                    </div>
                    <div class="dialog-body">
                        <div class="form-grid">
                            <div class="field">
                                <label for="plan-name-{{ $plan->id }}">Plan name</label>
                                <input id="plan-name-{{ $plan->id }}" name="name" value="{{ $plan->name }}" required maxlength="120">
                            </div>
                            <div class="field">
                                <label for="plan-slug-{{ $plan->id }}">Slug</label>
                                <input id="plan-slug-{{ $plan->id }}" name="slug" value="{{ $plan->slug }}" required maxlength="120">
                            </div>
                            <div class="field">
                                <label for="plan-monthly-{{ $plan->id }}">Monthly price</label>
                                <input id="plan-monthly-{{ $plan->id }}" name="monthly_price" type="number" inputmode="decimal" min="0" step="0.01" value="{{ $monthlyPrice }}" required>
                            </div>
                            <div class="field">
                                <label for="plan-yearly-{{ $plan->id }}">Yearly price</label>
                                <input id="plan-yearly-{{ $plan->id }}" name="yearly_price" type="number" inputmode="decimal" min="0" step="0.01" value="{{ $yearlyPrice }}" required>
                            </div>
                            <div class="field">
                                <label for="plan-currency-{{ $plan->id }}">Currency</label>
                                <input id="plan-currency-{{ $plan->id }}" name="currency_code" value="{{ $plan->currency_code }}" minlength="3" maxlength="3" required>
                            </div>
                            <div class="field">
                                <label for="plan-sort-{{ $plan->id }}">Display order</label>
                                <input id="plan-sort-{{ $plan->id }}" name="sort_order" type="number" min="0" step="1" value="{{ $plan->sort_order }}" required>
                            </div>
                            <div class="field full">
                                <label class="inline-check">
                                    <input type="checkbox" name="is_active" value="1" @checked($plan->is_active)>
                                    Make this plan available for selection
                                </label>
                            </div>
                            <div class="field full">
                                <label>Included modules</label>
                                <p class="subtle">Core modules are required on every plan and cannot be removed.</p>
                                <div class="module-grid">
                                    @foreach ($modules as $module)
                                        <label class="module-option">
                                            <input type="checkbox" name="module_ids[]" value="{{ $module->id }}"
                                                @checked($module->is_core || $enabledModuleIds->contains($module->id))
                                                @disabled($module->is_core)>
                                            <span class="module-copy">
                                                <strong>{{ $module->name }}</strong>
                                                @if ($module->description)<small>{{ $module->description }}</small>@endif
                                                <span class="module-flags">
                                                    @if ($module->is_core)<span class="badge neutral">Core</span>@endif
                                                    @unless ($module->is_active)<span class="badge neutral">Globally inactive</span>@endunless
                                                </span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            <div class="field full limits-field">
                                <label for="plan-limits-{{ $plan->id }}">Plan limits <span class="subtle">(advanced JSON)</span></label>
                                <textarea id="plan-limits-{{ $plan->id }}" name="limits" placeholder='{"branches": 1, "users": 2}'>{{ $plan->limits ? json_encode($plan->limits, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '' }}</textarea>
                            </div>
                        </div>
                    </div>
                    <div class="dialog-footer">
                        <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                        <button class="btn accent" type="submit">Save plan</button>
                    </div>
                </form>
            </dialog>
        @empty
            <section class="panel"><div class="empty">No subscription plans have been created yet.</div></section>
        @endforelse
    </div>
</x-layouts.admin>
