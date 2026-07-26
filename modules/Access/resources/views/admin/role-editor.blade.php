@php
    $isEdit = (bool) $role;
    $levelLabels = ['none' => 'None', 'view' => 'View', 'operate' => 'Operate', 'manage' => 'Manage'];
    $levelHints = [
        'none' => 'No access',
        'view' => 'Read-only',
        'operate' => 'Day-to-day work',
        'manage' => 'Full control',
    ];
    $rank = ['view' => 1, 'operate' => 2, 'manage' => 3];

    // Selectable levels per module: None + each level whose bundle is non-empty and unique
    // (identical bundles collapse to the highest level, matching how levels resolve on load).
    $moduleLevels = [];
    foreach ($modules as $key => $module) {
        $bySig = [];
        foreach (['view', 'operate', 'manage'] as $lv) {
            $bundle = $module['levels'][$lv] ?? [];
            if ($bundle === []) {
                continue;
            }
            $bySig[implode(',', $bundle)] = $lv;
        }
        $levels = array_values($bySig);
        usort($levels, fn ($a, $b) => $rank[$a] <=> $rank[$b]);
        $moduleLevels[$key] = array_merge(['none'], $levels);
    }

    $currency = $currency ?? 'NGN';
    $formatMoney = fn ($minor) => $currency.' '.number_format((float) $minor, 0);
@endphp

<x-layouts.admin :title="$isEdit ? 'Edit role · '.$role->name : 'Create custom role'">
    <style>
        .role-editor-grid { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 20px; align-items: start; }
        @media (max-width: 1024px) { .role-editor-grid { grid-template-columns: 1fr; } }
        .perm-module { border: 1px solid var(--line); border-radius: var(--radius-sm); background: #fff; padding: 16px; }
        .perm-module + .perm-module { margin-top: 12px; }
        .perm-module-head { display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap; }
        .perm-module-title { font-weight: 800; font-size: 14.5px; color: var(--ink); }
        .perm-module-sub { color: var(--muted); font-size: 12.5px; margin-top: 2px; }
        .seg { display: inline-flex; border: 1px solid var(--line); border-radius: 999px; background: var(--panel-soft); padding: 3px; gap: 2px; }
        .seg-opt { position: relative; }
        .seg-opt input { position: absolute; opacity: 0; inset: 0; cursor: pointer; }
        .seg-opt span { display: block; padding: 6px 14px; border-radius: 999px; font-size: 12.5px; font-weight: 700; color: var(--ink-soft); cursor: pointer; transition: background .12s, color .12s, box-shadow .12s; white-space: nowrap; }
        .seg-opt input:hover + span { color: var(--ink); }
        .seg-opt input:checked + span { background: var(--brand); color: #fff; box-shadow: 0 2px 8px -2px rgba(6,193,104,.5); }
        .seg-opt input:focus-visible + span { box-shadow: 0 0 0 3px var(--brand-ring); }
        .sensitive-wrap { margin-top: 14px; border-top: 1px dashed var(--line); padding-top: 12px; }
        .sensitive-label { font-size: 11.5px; text-transform: uppercase; letter-spacing: .05em; font-weight: 800; color: #b54708; margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
        .sensitive-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
        @media (max-width: 640px) { .sensitive-grid { grid-template-columns: 1fr; } }
        .check-card { display: flex; align-items: flex-start; gap: 11px; border: 1.5px solid var(--line); border-radius: 11px; background: #fff; padding: 11px 13px; cursor: pointer; color: var(--ink-soft); font-weight: 650; font-size: 13.5px; line-height: 1.3; transition: border-color .15s, background .15s, box-shadow .15s, color .15s; }
        .check-card:hover { border-color: var(--brand-100); background: var(--brand-050); }
        .check-card:has(input:checked) { border-color: var(--brand); background: var(--brand-050); color: var(--brand-strong); box-shadow: inset 0 0 0 1px var(--brand); }
        .check-card input[type="checkbox"] { appearance: none; -webkit-appearance: none; width: 20px; height: 20px; min-width: 20px; margin: 1px 0 0; border: 1.5px solid #cbd5cf; border-radius: 6px; background: #fff; flex: 0 0 auto; position: relative; cursor: pointer; transition: background .15s, border-color .15s; }
        .check-card input:checked { background: var(--brand); border-color: var(--brand); }
        .check-card input[type="checkbox"]:checked::after { content: ''; position: absolute; inset: 0; margin: auto; width: 5px; height: 9px; border: solid #fff; border-width: 0 2.5px 2.5px 0; transform: translateY(-1px) rotate(45deg); }
        .check-card .check-card-text { display: grid; gap: 2px; min-width: 0; }
        .check-card .check-card-text small { font-weight: 500; color: var(--muted); font-size: 11.5px; }
        .role-side { position: sticky; top: 18px; display: grid; gap: 16px; }
        .summary-box { border: 1px solid var(--brand-100); background: var(--brand-050); border-radius: var(--radius-sm); padding: 14px 16px; color: #05603a; font-size: 13.5px; line-height: 1.5; }
        .summary-box .subtle { color: #3f7a5f; }
        .limit-row { display: grid; gap: 6px; }
        .limit-row + .limit-row { margin-top: 14px; }
        .limit-affix { display: grid; grid-template-columns: auto minmax(0, 1fr); align-items: stretch; border: 1px solid #d4ddd8; border-radius: var(--radius-sm); overflow: hidden; }
        .limit-affix span { display: grid; place-items: center; padding: 0 12px; background: var(--panel-soft); color: var(--muted); font-weight: 700; font-size: 13px; border-right: 1px solid #d4ddd8; }
        .limit-affix input { border: 0; border-radius: 0; }
        .limit-affix input:focus { box-shadow: none; }
        .limit-affix:focus-within { border-color: var(--brand); box-shadow: 0 0 0 3.5px var(--brand-ring); }
    </style>

    <div class="topbar">
        <div>
            <div class="eyebrow">Access control</div>
            <h1>{{ $isEdit ? 'Edit role' : 'Create custom role' }}</h1>
            <p class="subtle">
                {{ $isEdit
                    ? "Adjust what “{$role->name}” can do across {$tenant->name}."
                    : "Choose a permission level for each area, then add any sensitive actions." }}
            </p>
        </div>
        <div style="display:flex; gap:10px; align-items:center;">
            <a class="btn secondary" href="{{ route('admin.business.index', ['tenant' => $tenant->id]) }}#roles">Back to roles</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert errors">
            <strong>Check the highlighted fields.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST"
          action="{{ $isEdit ? route('admin.access.roles.update', $role) : route('admin.access.roles.store') }}">
        @csrf
        @if ($isEdit) @method('PUT') @endif
        <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">

        <div class="role-editor-grid">
            <div>
                <section class="panel">
                    <div class="panel-header">
                        <div>
                            <h2 class="panel-title">Access by area</h2>
                            <p class="subtle">Set the base level for each part of the business.</p>
                        </div>
                    </div>
                    <div class="panel-body">
                        @foreach ($modules as $key => $module)
                            <div class="perm-module">
                                <div class="perm-module-head">
                                    <div>
                                        <div class="perm-module-title">{{ $module['label'] }}</div>
                                        <div class="perm-module-sub" data-level-hint="{{ $key }}">{{ $levelHints[$currentLevels[$key] ?? 'none'] ?? '' }}</div>
                                    </div>
                                    <div class="seg" role="radiogroup" aria-label="{{ $module['label'] }} level">
                                        @foreach ($moduleLevels[$key] as $lv)
                                            <label class="seg-opt">
                                                <input type="radio" name="levels[{{ $key }}]" value="{{ $lv }}"
                                                       data-module="{{ $key }}"
                                                       @checked(($currentLevels[$key] ?? 'none') === $lv)>
                                                <span>{{ $levelLabels[$lv] }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>

                                @if (! empty($module['sensitive']))
                                    <div class="sensitive-wrap">
                                        <div class="sensitive-label">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                                            Sensitive actions
                                        </div>
                                        <div class="sensitive-grid">
                                            @foreach ($module['sensitive'] as $slug)
                                                @php $def = $definitions[$slug]; @endphp
                                                <label class="check-card">
                                                    <input type="checkbox" name="sensitive[]" value="{{ $slug }}"
                                                           @checked(in_array($slug, $currentSensitive, true))>
                                                    <span class="check-card-text">
                                                        {{ $def['name'] }}
                                                        <small>{{ $def['description'] }}</small>
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="panel" style="margin-top:20px;">
                    <div class="panel-header">
                        <div>
                            <h2 class="panel-title">Limits</h2>
                            <p class="subtle">Caps that apply before an action needs approval. Leave blank for no limit.</p>
                        </div>
                    </div>
                    <div class="panel-body">
                        @foreach ($catalogueLimits as $limitKey => $limit)
                            <div class="limit-row">
                                <label>{{ $limit['name'] }}</label>
                                @if ($limit['type'] === 'money')
                                    <div class="limit-affix">
                                        <span>{{ $currency }}</span>
                                        <input type="number" min="0" step="1" name="limits[{{ $limitKey }}]"
                                               value="{{ $currentLimitValues[$limitKey] ?? '' }}" placeholder="No limit">
                                    </div>
                                @else
                                    <div class="limit-affix">
                                        <span>%</span>
                                        <input type="number" min="0" max="100" step="0.5" name="limits[{{ $limitKey }}]"
                                               value="{{ $currentLimitValues[$limitKey] ?? '' }}" placeholder="No limit">
                                    </div>
                                @endif
                                <p class="subtle" style="font-size:12px;">{{ $limit['description'] }} Requires the “{{ $definitions[$limit['permission']]['name'] }}” action.</p>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>

            <aside class="role-side">
                <section class="panel">
                    <div class="panel-header">
                        <div><h2 class="panel-title">Role details</h2></div>
                    </div>
                    <div class="panel-body" style="display:grid; gap:14px;">
                        <div class="field">
                            <label>Role name</label>
                            <input name="name" required maxlength="120" value="{{ $roleName }}" placeholder="e.g. Senior Cashier">
                        </div>
                        <div class="field">
                            <label>Description <span class="subtle" style="font-weight:500;">(optional)</span></label>
                            <textarea name="description" rows="3" maxlength="500" placeholder="What is this role for?">{{ $roleDescription }}</textarea>
                        </div>
                        @if ($isEdit && $role->is_system)
                            <div class="badge neutral" style="justify-self:start;">System template · customised</div>
                        @endif
                    </div>
                </section>

                <section class="panel">
                    <div class="panel-header"><div><h2 class="panel-title">Summary</h2></div></div>
                    <div class="panel-body">
                        <div class="summary-box">
                            {{ $isEdit ? ($role->summary ?: 'No permissions assigned yet.') : ($roleDescription ?: 'Set access levels to build this role.') }}
                            <div class="subtle" style="margin-top:8px; font-size:12px;">The plain-language summary is regenerated when you save.</div>
                        </div>
                    </div>
                </section>

                <div class="button-row" style="justify-content:space-between;">
                    @if ($isEdit && ! $role->is_protected)
                        <button class="btn danger" type="submit" form="delete-role-form"
                                onclick="return confirm('Delete this role? Users must be reassigned first.')">Delete</button>
                    @else
                        <span></span>
                    @endif
                    <div style="display:flex; gap:10px;">
                        <a class="btn secondary" href="{{ route('admin.business.index', ['tenant' => $tenant->id]) }}#roles">Cancel</a>
                        <button class="btn primary" type="submit">{{ $isEdit ? 'Save changes' : 'Create role' }}</button>
                    </div>
                </div>
            </aside>
        </div>
    </form>

    @if ($isEdit && ! $role->is_protected)
        <form id="delete-role-form" method="POST" action="{{ route('admin.access.roles.destroy', $role) }}" hidden>
            @csrf @method('DELETE')
        </form>
    @endif

    <script>
        (() => {
            const hints = @json($levelHints);
            document.querySelectorAll('.seg input[type="radio"]').forEach((input) => {
                input.addEventListener('change', () => {
                    const hintEl = document.querySelector('[data-level-hint="' + input.dataset.module + '"]');
                    if (hintEl) hintEl.textContent = hints[input.value] || '';
                });
            });
        })();
    </script>
</x-layouts.admin>
