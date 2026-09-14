@php
    $money = fn (int $minor): string => ($minor > 0 ? '+' : ($minor < 0 ? '−' : '')).number_format(abs($minor) / 100, 2);
    $tenantParam = request('tenant');
    $jsGroups = $groups->mapWithKeys(fn ($g) => [$g->id => [
        'id' => $g->id,
        'name' => $g->name,
        'is_required' => (bool) $g->is_required,
        'min_select' => (int) $g->min_select,
        'max_select' => $g->max_select,
        'product_ids' => $g->products->pluck('id')->values(),
        'options' => $g->options->map(fn ($o) => [
            'id' => $o->id,
            'name' => $o->name,
            'price' => number_format($o->price_delta_minor / 100, 2, '.', ''),
            'component_product_variant_id' => $o->component_product_variant_id,
            'component_quantity' => $o->component_product_variant_id ? rtrim(rtrim(number_format((float) $o->component_quantity, 4, '.', ''), '0'), '.') : '',
            'component_unit_id' => $o->component_unit_id,
        ])->values(),
    ]]);
@endphp

<x-layouts.admin title="Menu Modifiers">
    <style>
        .mod-card { border: 1px solid var(--line); border-radius: 12px; background: #fff; padding: 16px; display: grid; grid-template-columns: minmax(180px, 240px) 1fr minmax(160px, 260px) auto; gap: 16px; align-items: start; margin-bottom: 12px; }
        @media (max-width: 980px) { .mod-card { grid-template-columns: 1fr; } }
        .mod-card h3 { margin: 0 0 4px; font-size: 1.05rem; }
        .rule-pill { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: .74rem; font-weight: 800; background: #f2f4f7; color: #475467; }
        .rule-pill.required { background: #fef0c7; color: #b54708; }
        .opt-chips { display: flex; flex-wrap: wrap; gap: 6px; }
        .opt-chip { padding: 5px 10px; border-radius: 9px; border: 1px solid var(--line); background: #fafbfc; font-size: .83rem; font-weight: 650; }
        .opt-chip .p { color: #067647; font-weight: 800; margin-left: 4px; }
        .opt-chip .p.neg { color: #b42318; }
        .opt-chip .ing { display: block; font-size: .72rem; color: #667085; font-weight: 600; }
        .label-sm { font-size: .7rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: #667085; margin-bottom: 5px; }
        .mod-actions { display: flex; gap: 6px; }

        #modifier-dialog { width: min(920px, 97vw); }
        .opt-table { width: 100%; border-collapse: collapse; }
        .opt-table th { text-align: left; font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: #667085; padding: 6px 4px; }
        .opt-table td { padding: 5px 4px; vertical-align: middle; }
        .opt-table input, .opt-table select { width: 100%; }
        .opt-table .num { width: 96px; }
        .opt-remove { width: 30px; height: 30px; border: 1px solid #f3b7ab; border-radius: 7px; background: #fff; color: #b42318; font-weight: 800; cursor: pointer; }
        .product-picker { max-height: 220px; overflow-y: auto; border: 1px solid var(--line); border-radius: 10px; padding: 8px; display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 4px; }
        .product-picker label { display: flex; gap: 8px; align-items: center; padding: 5px 7px; border-radius: 7px; font-size: .88rem; cursor: pointer; }
        .product-picker label:has(input:checked) { background: #f4ebff; font-weight: 700; }
        .hint { font-size: .78rem; color: #667085; }
    </style>

    <div class="topbar">
        <div>
            <div class="eyebrow"><a href="{{ route('admin.sales.restaurant.floor', array_filter(['tenant' => $tenantParam])) }}">← Floor</a></div>
            <h1>Menu modifiers</h1>
            <p class="subtle">The choices a server offers with a dish — “Spice level”, “Extras”, “Remove”. A choice can change the price, the ingredients used, or both.</p>
        </div>
        <div style="display:flex; gap:10px; align-items:center;">
            @if ($isPlatformAdmin)
                <form method="GET" action="{{ route('admin.sales.restaurant.modifiers.index') }}" style="min-width: 240px;">
                    <select name="tenant" onchange="this.form.submit()">
                        @foreach ($tenants as $visibleTenant)
                            <option value="{{ $visibleTenant->id }}" @selected($visibleTenant->id === $tenant->id)>{{ $visibleTenant->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
            <button class="btn primary" type="button" data-dialog-open="modifier-dialog" data-edit-group="">New modifier group</button>
        </div>
    </div>

    @if (session('status'))<div class="alert success">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="alert errors">{{ $errors->first() }}</div>@endif

    @forelse ($groups as $group)
        <div class="mod-card">
            <div>
                <h3>{{ $group->name }}</h3>
                <span class="rule-pill {{ $group->minimumChoices() > 0 ? 'required' : '' }}">{{ $group->ruleLabel() }}</span>
            </div>
            <div>
                <div class="label-sm">Options</div>
                <div class="opt-chips">
                    @foreach ($group->options as $option)
                        <span class="opt-chip">
                            {{ $option->name }}
                            @if ((int) $option->price_delta_minor !== 0)
                                <span class="p {{ $option->price_delta_minor < 0 ? 'neg' : '' }}">{{ $money((int) $option->price_delta_minor) }}</span>
                            @endif
                            @if ($option->componentVariant)
                                <span class="ing">
                                    {{ (float) $option->component_quantity < 0 ? 'Uses less' : 'Uses more' }}
                                    {{ $option->componentVariant->product?->name }}:
                                    {{ \Modules\Inventory\Support\Quantity::format(abs((float) $option->component_quantity)) }}{{ $option->componentUnit ? ' '.$option->componentUnit->code : '' }}
                                </span>
                            @endif
                        </span>
                    @endforeach
                </div>
            </div>
            <div>
                <div class="label-sm">Offered with</div>
                @if ($group->products->isEmpty())
                    <span class="subtle">No dishes yet — edit to attach it.</span>
                @else
                    <span style="font-size:.88rem;">{{ $group->products->pluck('name')->take(6)->implode(', ') }}{{ $group->products->count() > 6 ? ' +'.($group->products->count() - 6).' more' : '' }}</span>
                @endif
            </div>
            <div class="mod-actions">
                <button class="btn secondary" type="button" data-dialog-open="modifier-dialog" data-edit-group="{{ $group->id }}">Edit</button>
                <form method="POST" action="{{ route('admin.sales.restaurant.modifiers.destroy', $group) }}"
                      onsubmit="return confirm('Remove {{ $group->name }}? Bills already rung up keep their choices.');">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="tenant" value="{{ $tenantParam }}">
                    <button class="btn danger" type="submit">Remove</button>
                </form>
            </div>
        </div>
    @empty
        <div class="panel" style="padding: 34px; text-align:center;">
            <h2 style="margin:0 0 8px;">No modifiers yet</h2>
            <p class="subtle" style="margin:0 0 16px;">
                Start with the questions your servers ask most: “How spicy?”, “Any extras?”, “Anything to leave out?”.
                Mark a group required and the server cannot add the dish until the guest has answered.
            </p>
            <button class="btn primary" type="button" data-dialog-open="modifier-dialog" data-edit-group="">Create the first group</button>
        </div>
    @endforelse

    <dialog class="dialog" id="modifier-dialog">
        <div class="dialog-header">
            <div><h2 class="panel-title" data-dialog-title>New modifier group</h2></div>
            <button class="icon-btn" type="button" data-dialog-close aria-label="Close">x</button>
        </div>
        <div class="dialog-body">
            <form class="mini-form" method="POST" action="{{ route('admin.sales.restaurant.modifiers.store') }}" data-group-form
                  data-store-action="{{ route('admin.sales.restaurant.modifiers.store') }}"
                  data-update-action="{{ route('admin.sales.restaurant.modifiers.update', ['group' => '__ID__']) }}">
                @csrf
                <input type="hidden" name="_method" value="POST" data-method>
                <input type="hidden" name="tenant" value="{{ $tenantParam }}">

                <div class="form-grid">
                    <div class="field">
                        <label for="mg-name">Group name</label>
                        <input id="mg-name" name="name" required maxlength="120" placeholder="e.g. Spice level" data-field="name">
                    </div>
                    <div class="field">
                        <label>&nbsp;</label>
                        <label style="display:flex; gap:8px; align-items:center; font-weight:700;">
                            <input type="hidden" name="is_required" value="0">
                            <input type="checkbox" name="is_required" value="1" data-field="is_required">
                            Required — the server must choose before adding the dish
                        </label>
                    </div>
                    <div class="field">
                        <label for="mg-min">Fewest to pick</label>
                        <input id="mg-min" name="min_select" type="number" min="0" max="20" value="0" data-field="min_select">
                    </div>
                    <div class="field">
                        <label for="mg-max">Most to pick</label>
                        <input id="mg-max" name="max_select" type="number" min="1" max="20" placeholder="No limit" data-field="max_select">
                        <small class="hint">1 makes it a single choice, like a spice level.</small>
                    </div>
                </div>

                <div style="margin-top:14px;">
                    <div class="label-sm">Options</div>
                    <table class="opt-table">
                        <thead>
                            <tr>
                                <th>Option</th>
                                <th>Price change</th>
                                <th>Ingredient it affects (optional)</th>
                                <th>How much</th>
                                <th>Unit</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody data-options></tbody>
                    </table>
                    <button class="btn ghost" type="button" data-add-option style="margin-top:8px;">+ Add option</button>
                    <p class="hint">Price change can be negative (“Half portion −500”). For “How much”, a positive number uses more (“extra chicken: 100 g”), a negative one uses less (“no onions: −30 g”). It is per one dish.</p>
                </div>

                <div style="margin-top:14px;">
                    <div class="label-sm">Offer it with these dishes</div>
                    <input type="search" placeholder="Filter dishes…" data-product-filter style="margin-bottom:8px;">
                    <div class="product-picker">
                        @foreach ($products as $product)
                            <label data-product-name="{{ strtolower($product->name) }}">
                                <input type="checkbox" name="product_ids[]" value="{{ $product->id }}" data-product-box>
                                {{ $product->name }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="button-row">
                    <button class="btn secondary" type="button" data-dialog-close>Cancel</button>
                    <button class="btn primary" type="submit">Save group</button>
                </div>
            </form>
        </div>
    </dialog>

    <template id="option-row">
        <tr>
            <td><input data-o="name" maxlength="120" placeholder="e.g. Extra chicken"></td>
            <td><input class="num" data-o="price" type="number" step="0.01" placeholder="0.00"></td>
            <td>
                <select data-o="component_product_variant_id">
                    <option value="">None — price only</option>
                    @foreach ($ingredients as $ingredient)
                        <option value="{{ $ingredient->id }}">{{ $ingredient->product?->name }}{{ $ingredient->variant_name && strcasecmp($ingredient->variant_name, 'Default') !== 0 ? ' — '.$ingredient->variant_name : '' }}</option>
                    @endforeach
                </select>
            </td>
            <td><input class="num" data-o="component_quantity" type="number" step="any" placeholder="0"></td>
            <td><select data-o="component_unit_id"><option value="">—</option></select></td>
            <td><input type="hidden" data-o="id"><button class="opt-remove" type="button" data-remove-option aria-label="Remove option">✕</button></td>
        </tr>
    </template>

    <script>
        (function () {
            const GROUPS = @json($jsGroups);
            const UNITS = @json($ingredientUnits);
            const form = document.querySelector('[data-group-form]');
            const body = form.querySelector('[data-options]');
            const template = document.getElementById('option-row');

            function reindex() {
                // Inputs are named options[i][field] in their current order.
                body.querySelectorAll('tr').forEach((row, i) => {
                    row.querySelectorAll('[data-o]').forEach((el) => { el.name = `options[${i}][${el.dataset.o}]`; });
                });
            }

            function fillUnits(row, selected) {
                const variant = row.querySelector('[data-o="component_product_variant_id"]').value;
                const units = UNITS[variant] || [];
                const select = row.querySelector('[data-o="component_unit_id"]');
                select.innerHTML = '<option value="">' + (units.length ? 'Base unit' : '—') + '</option>'
                    + units.map((u) => `<option value="${u.id}">${u.code}</option>`).join('');
                if (selected) select.value = String(selected);
                row.querySelector('[data-o="component_quantity"]').disabled = !variant;
                select.disabled = !variant;
            }

            function addRow(option) {
                const row = template.content.firstElementChild.cloneNode(true);
                body.appendChild(row);
                if (option) {
                    row.querySelector('[data-o="id"]').value = option.id || '';
                    row.querySelector('[data-o="name"]').value = option.name || '';
                    row.querySelector('[data-o="price"]').value = option.price || '';
                    row.querySelector('[data-o="component_product_variant_id"]').value = option.component_product_variant_id || '';
                    row.querySelector('[data-o="component_quantity"]').value = option.component_quantity || '';
                }
                fillUnits(row, option ? option.component_unit_id : null);
                reindex();
            }

            body.addEventListener('change', function (e) {
                if (e.target.matches('[data-o="component_product_variant_id"]')) fillUnits(e.target.closest('tr'), null);
            });
            body.addEventListener('click', function (e) {
                if (!e.target.closest('[data-remove-option]')) return;
                e.target.closest('tr').remove();
                if (!body.children.length) addRow(null);
                reindex();
            });
            form.querySelector('[data-add-option]').addEventListener('click', () => addRow(null));

            form.querySelector('[data-product-filter]').addEventListener('input', function () {
                const term = this.value.trim().toLowerCase();
                form.querySelectorAll('[data-product-name]').forEach((label) => {
                    label.hidden = term !== '' && !label.dataset.productName.includes(term);
                });
            });

            function load(group) {
                const title = document.querySelector('[data-dialog-title]');
                body.innerHTML = '';
                form.querySelector('[data-field="name"]').value = group ? group.name : '';
                form.querySelector('[data-field="is_required"]').checked = group ? group.is_required : false;
                form.querySelector('[data-field="min_select"]').value = group ? group.min_select : 0;
                form.querySelector('[data-field="max_select"]').value = group && group.max_select !== null ? group.max_select : '';
                const ids = group ? group.product_ids.map(String) : [];
                form.querySelectorAll('[data-product-box]').forEach((box) => { box.checked = ids.includes(box.value); });

                if (group) {
                    form.action = form.dataset.updateAction.replace('__ID__', group.id);
                    form.querySelector('[data-method]').value = 'PUT';
                    title.textContent = 'Edit ' + group.name;
                    group.options.forEach(addRow);
                } else {
                    form.action = form.dataset.storeAction;
                    form.querySelector('[data-method]').value = 'POST';
                    title.textContent = 'New modifier group';
                    addRow(null);
                    addRow(null);
                }
            }

            document.querySelectorAll('[data-edit-group]').forEach((button) => button.addEventListener('click', function () {
                load(button.dataset.editGroup ? GROUPS[button.dataset.editGroup] : null);
            }));
        })();
    </script>
</x-layouts.admin>
