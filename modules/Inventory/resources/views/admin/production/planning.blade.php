<x-layouts.admin title="Production Planning">
    <style>
        .scheduled-items-empty { padding: 18px; border: 1px dashed var(--line); border-radius: 10px; color: var(--muted); text-align: center; }
        .scheduled-item-remove { width: 32px; height: 32px; padding: 0; border-radius: 50%; }
        #plan-product-dialog { width: min(520px, calc(100vw - 28px)); }
        #plan-product-dialog .dialog-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 24px; }
        .planning-date-filter { display: grid; grid-template-columns: minmax(170px, 1fr) minmax(170px, 1fr) auto; gap: 16px; align-items: end; }
        @media (max-width: 640px) { .planning-date-filter { grid-template-columns: 1fr; } }
    </style>
    <div class="topbar">
        <div>
            <div class="eyebrow">F&amp;B production</div>
            <h1>Production planning</h1>
            <p class="subtle">Schedule what will be produced, when, where and by which team at {{ $branch->name }}.</p>
        </div>
        <div class="button-row">
            <a class="btn secondary" href="{{ route('admin.inventory.production.index', ['tenant' => $tenant->id]) }}">Recipes</a>
            <a class="btn secondary" href="{{ route('admin.inventory.production.waste', ['tenant' => $tenant->id]) }}">Waste</a>
            <a class="btn secondary" href="{{ route('admin.inventory.production.reports', ['tenant' => $tenant->id]) }}" target="_blank" rel="noopener">Reports</a>
            <button class="btn accent" type="button" data-dialog-open="plan-dialog" data-plan-create>Schedule production</button>
        </div>
    </div>

    <nav class="pill-nav" aria-label="Production planning sections">
        <a class="active" href="{{ route('admin.inventory.production.planning', ['tenant' => $tenant->id]) }}" aria-current="page">Planning</a>
        <a href="{{ route('admin.inventory.production.orders', ['tenant' => $tenant->id]) }}">Scheduled Orders</a>
    </nav>

    @if(session('status'))<div class="alert">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="alert errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <form class="planning-date-filter" method="GET">
        <input type="hidden" name="tenant" value="{{ $tenant->id }}">
        <div class="field"><label>From</label><input type="date" name="from" value="{{ $from->toDateString() }}"></div>
        <div class="field"><label>To</label><input type="date" name="to" value="{{ $to->toDateString() }}"></div>
        <div class="field"><button class="btn primary">Show calendar</button></div>
    </form>

    <section class="panel" style="margin-top:16px">
        <div class="panel-body" style="overflow-x:auto">
            @if($plans->isEmpty())
                <div class="empty">Nothing is scheduled for this period.</div>
            @else
                <table class="table">
                    <thead><tr><th>Day</th><th>Date</th><th>Scheduled lines</th><th></th></tr></thead>
                    <tbody>
                    @foreach($plans->groupBy(fn($plan) => $plan->production_date->toDateString()) as $date => $dayPlans)
                        <tr>
                            <td><strong>{{ $dayPlans->first()->production_date->format('l') }}</strong></td>
                            <td>{{ $dayPlans->first()->production_date->format('M j, Y') }}</td>
                            <td>{{ $dayPlans->count() }} {{ str('scheduled line')->plural($dayPlans->count()) }}</td>
                            <td style="text-align:right"><a class="btn primary" href="{{ route('admin.inventory.production.planning.day', ['date' => $date, 'tenant' => $tenant->id]) }}">Open order</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </section>

    <dialog class="dialog" id="plan-dialog">
        <div class="dialog-header"><h2 class="panel-title" data-plan-dialog-title>Schedule production</h2><button class="icon-btn" type="button" data-dialog-close>✕</button></div>
        <div class="dialog-body">
            <form class="mini-form" method="POST" action="{{ route('admin.inventory.production.planning.store') }}" data-plan-form data-create-action="{{ route('admin.inventory.production.planning.store') }}">
                @csrf
                <input type="hidden" name="_method" value="PUT" disabled data-plan-method>
                <input type="hidden" name="tenant" value="{{ $tenant->id }}">
                <div class="form-grid">
                    <div class="field"><label>Date</label><input type="date" name="production_date" value="{{ $defaultPlanDate->toDateString() }}" required></div>
                    <div class="field"><label>Wave</label><input name="wave" placeholder="Morning wave"></div>
                    <div class="field"><label>Production station</label><select name="production_station_id" required>@foreach($stations as $station)<option value="{{ $station->id }}">{{ $station->name }}</option>@endforeach</select></div>
                    <div class="field"><label>Work shift</label><select name="hr_work_shift_id"><option value="">No shift</option>@foreach($shifts as $shift)<option value="{{ $shift->id }}">{{ $shift->name }} · {{ substr($shift->starts_at, 0, 5) }}</option>@endforeach</select></div>
                    <div class="field"><label>Head baker / lead</label><select name="production_lead_staff_id" required><option value="">Select</option>@foreach($staff as $employee)<option value="{{ $employee->id }}">{{ $employee->name }}</option>@endforeach</select></div>
                    <div class="field full">
                        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:8px;">
                            <label>Products to produce</label>
                            <button class="btn primary" type="button" data-add-product>+ Add product to this order</button>
                        </div>
                        <div data-scheduled-products></div>
                        <small class="subtle">Add one or more products. Each product can have its own quantity and time.</small>
                        <div class="subtle" data-scheduled-products-error hidden style="color:var(--danger); margin-top:6px;">Add at least one product before saving.</div>
                    </div>
                    <div class="field full"><label>Notes</label><textarea name="notes"></textarea></div>
                </div>
                @if($schedulableProducts->isEmpty())
                    <div class="alert">No finished product has an active recipe yet. <a href="{{ route('admin.inventory.production.index', ['tenant' => $tenant->id]) }}">Set up a finished product and recipe first.</a></div>
                @endif
                <div class="dialog-actions"><button class="btn danger" type="button" data-dialog-close>Cancel</button><button class="btn primary" data-plan-submit @disabled($schedulableProducts->isEmpty())>Add to plan</button></div>
            </form>
        </div>
    </dialog>

    <dialog class="dialog" id="plan-product-dialog">
        <div class="dialog-header"><h2 class="panel-title" data-product-dialog-title>Add product</h2><button class="icon-btn" type="button" data-dialog-close>✕</button></div>
        <div class="dialog-body">
            <div class="mini-form form-grid">
                <div class="field full">
                    <label>Product type</label>
                    <select data-item-product-type>
                        <option value="product">Product</option>
                        <option value="raw_material">Raw material</option>
                    </select>
                </div>
                <div class="field full">
                    <label>Finished product</label>
                    <select data-item-product required></select>
                    <small class="subtle" data-item-product-help></small>
                </div>
                <div class="field"><label>Quantity</label><input type="number" min="0.0001" step="any" data-item-quantity required></div>
                <div class="field"><label>Time <span class="subtle">(optional)</span></label><input type="time" data-item-time></div>
            </div>
            <div class="dialog-actions"><button class="btn danger" type="button" data-dialog-close>Cancel</button><button class="btn primary" type="button" data-save-product>OK</button></div>
        </div>
    </dialog>

    <script>
        (function () {
            const products = @json($schedulableProducts);
            const plans = @json($editablePlans);
            const form = document.querySelector('[data-plan-form]');
            const methodInput = document.querySelector('[data-plan-method]');
            const title = document.querySelector('[data-plan-dialog-title]');
            const submit = document.querySelector('[data-plan-submit]');
            const addProductButton = document.querySelector('[data-add-product]');
            const scheduledProducts = document.querySelector('[data-scheduled-products]');
            const productsError = document.querySelector('[data-scheduled-products-error]');
            const productDialog = document.getElementById('plan-product-dialog');
            const productDialogTitle = document.querySelector('[data-product-dialog-title]');
            const itemType = document.querySelector('[data-item-product-type]');
            const itemProduct = document.querySelector('[data-item-product]');
            const itemQuantity = document.querySelector('[data-item-quantity]');
            const itemTime = document.querySelector('[data-item-time]');
            const itemHelp = document.querySelector('[data-item-product-help]');
            let items = [];
            let editingItemIndex = null;
            let editingSinglePlan = false;
            if (!form || !productDialog || !itemType || !itemProduct) return;

            function escapeHtml(value) {
                return String(value).replace(/[&<>'"]/g, (character) => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;',
                })[character]);
            }

            function fillItemProducts(selectedRecipe = '') {
                const matches = products.filter((product) => String(product.type) === itemType.value);
                itemProduct.innerHTML = '';
                itemProduct.add(new Option(matches.length ? 'Select finished product…' : 'No products with an active recipe', ''));
                matches.forEach((product) => itemProduct.add(new Option(product.label, product.recipe_id)));
                itemProduct.disabled = matches.length === 0;
                itemHelp.textContent = matches.length ? 'Only products with an active recipe are listed.' : 'Create an active recipe for this product type before scheduling it.';
                if (selectedRecipe) itemProduct.value = String(selectedRecipe);
            }

            function renderItems() {
                scheduledProducts.innerHTML = '';
                productsError.hidden = true;
                if (!items.length) {
                    scheduledProducts.innerHTML = '<div class="scheduled-items-empty">No products added yet.</div>';
                    addProductButton.textContent = '+ Add product to this order';
                    return;
                }

                const table = document.createElement('table');
                table.className = 'table';
                table.innerHTML = '<thead><tr><th aria-label="Remove"></th><th>Product</th><th>Quantity</th><th>Time</th><th></th></tr></thead>';
                const body = document.createElement('tbody');
                items.forEach((item, index) => {
                    const row = document.createElement('tr');
                    row.innerHTML = `<td><button class="btn danger scheduled-item-remove" type="button" data-remove-item="${index}" aria-label="Remove ${escapeHtml(item.label)}" title="Remove">×</button></td><td><strong>${escapeHtml(item.label)}</strong><input type="hidden" name="items[${index}][recipe_id]" value="${escapeHtml(item.recipe_id)}"></td><td>${escapeHtml(item.quantity)}<input type="hidden" name="items[${index}][planned_quantity]" value="${escapeHtml(item.quantity)}"></td><td>${escapeHtml(item.time || 'Any time')}<input type="hidden" name="items[${index}][scheduled_time]" value="${escapeHtml(item.time || '')}"></td><td><button class="btn secondary" type="button" data-edit-item="${index}">Edit</button></td>`;
                    body.appendChild(row);
                });
                table.appendChild(body);
                scheduledProducts.appendChild(table);
                addProductButton.textContent = editingSinglePlan ? 'Change product details' : '+ Add another product';
            }

            function openItemDialog(index = null) {
                editingItemIndex = index;
                const item = index === null ? null : items[index];
                productDialogTitle.textContent = item ? 'Edit product' : 'Add product';
                itemType.value = item?.type || 'product';
                fillItemProducts(item?.recipe_id || '');
                itemQuantity.value = item?.quantity || '';
                itemTime.value = item?.time || '';
                window.sbOpenDialog?.(productDialog);
            }

            function setValue(name, value) {
                const field = form.elements.namedItem(name);
                if (field) field.value = value ?? '';
            }

            function resetForCreate() {
                form.reset();
                items = [];
                editingSinglePlan = false;
                form.action = form.dataset.createAction;
                methodInput.disabled = true;
                title.textContent = 'Schedule production';
                submit.textContent = 'Add to plan';
                setValue('production_date', @json($defaultPlanDate->toDateString()));
                renderItems();
            }

            function editPlan(plan) {
                form.reset();
                editingSinglePlan = true;
                items = [{
                    recipe_id: String(plan.recipe_id),
                    type: plan.product_type || 'product',
                    label: plan.product_label,
                    quantity: String(plan.planned_quantity),
                    time: plan.scheduled_time || '',
                }];
                form.action = plan.update_url;
                methodInput.disabled = false;
                title.textContent = 'Edit production plan';
                submit.textContent = 'Save changes';
                setValue('production_date', plan.production_date);
                setValue('wave', plan.wave);
                setValue('production_station_id', plan.production_station_id);
                setValue('hr_work_shift_id', plan.hr_work_shift_id);
                setValue('production_lead_staff_id', plan.production_lead_staff_id);
                setValue('notes', plan.notes);
                renderItems();
            }

            itemType.addEventListener('change', () => fillItemProducts());
            addProductButton.addEventListener('click', () => openItemDialog(editingSinglePlan && items.length ? 0 : null));
            document.querySelector('[data-save-product]')?.addEventListener('click', () => {
                if (!itemProduct.reportValidity() || !itemQuantity.reportValidity()) return;
                const selected = products.find((product) => String(product.recipe_id) === itemProduct.value);
                if (!selected) return;
                const item = {
                    recipe_id: String(selected.recipe_id),
                    type: selected.type,
                    label: selected.label,
                    quantity: itemQuantity.value,
                    time: itemTime.value,
                };
                if (editingItemIndex === null) items.push(item);
                else items[editingItemIndex] = item;
                renderItems();
                window.sbCloseDialog?.(productDialog);
            });
            scheduledProducts.addEventListener('click', (event) => {
                const remove = event.target.closest('[data-remove-item]');
                const edit = event.target.closest('[data-edit-item]');
                if (remove) {
                    items.splice(Number(remove.dataset.removeItem), 1);
                    renderItems();
                } else if (edit) {
                    openItemDialog(Number(edit.dataset.editItem));
                }
            });
            form.addEventListener('submit', (event) => {
                if (items.length) return;
                event.preventDefault();
                productsError.hidden = false;
                addProductButton.focus();
            });
            document.querySelector('[data-plan-create]')?.addEventListener('click', resetForCreate);
            document.querySelectorAll('[data-plan-edit]').forEach((button) => {
                button.addEventListener('click', () => {
                    const plan = plans.find((item) => String(item.id) === button.dataset.planEdit);
                    if (plan) editPlan(plan);
                });
            });
            resetForCreate();
            const requestedEdit = @json(request()->integer('edit'));
            if (requestedEdit) {
                const plan = plans.find((item) => Number(item.id) === Number(requestedEdit));
                if (plan) {
                    editPlan(plan);
                    window.setTimeout(() => window.sbOpenDialog?.(document.getElementById('plan-dialog')), 0);
                }
            }
        })();
    </script>
</x-layouts.admin>
