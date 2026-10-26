@php
    $qty = fn($value) => \Modules\Inventory\Support\Quantity::format($value);
    $editablePlans = $plans->where('status', 'planned')->map(fn($plan) => [
        'id' => $plan->id,
        'update_url' => route('admin.inventory.production.planning.update', $plan),
        'recipe_id' => $plan->recipe_id,
        'planned_quantity' => $plan->planned_quantity,
        'scheduled_time' => $plan->scheduled_time ? substr($plan->scheduled_time, 0, 5) : '',
        'production_station_id' => $plan->production_station_id,
        'hr_work_shift_id' => $plan->hr_work_shift_id,
        'production_lead_staff_id' => $plan->production_lead_staff_id,
        'wave' => $plan->wave,
        'notes' => $plan->notes,
    ])->values();
@endphp
<x-layouts.admin title="Production plan for {{ $productionDate->format('M j, Y') }}">
    <div class="topbar">
        <div>
            <div class="eyebrow">F&amp;B production</div>
            <h1>{{ $productionDate->format('l, M j, Y') }}</h1>
            <p class="subtle">{{ $plans->count() }} {{ str('scheduled line')->plural($plans->count()) }} for {{ $branch->name }}.</p>
        </div>
        <div class="button-row">
            <button class="btn accent" type="button" data-dialog-open="day-plan-edit-dialog" data-day-plan-create>+ Add product</button>
            <a class="btn primary" href="{{ route('admin.inventory.production.planning', ['tenant' => $tenant->id, 'from' => $productionDate->startOfWeek()->toDateString()]) }}">Back to planning</a>
        </div>
    </div>

    @if(session('status'))<div class="alert">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="alert errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="panel">
        <div @class(['panel-body', 'production-table-scroll' => $plans->isNotEmpty()])>
            @if($plans->isEmpty())
                <div class="empty">Nothing is scheduled for this day.</div>
            @else
                <table class="table production-details-table">
                    <thead><tr><th>Time</th><th>Product</th><th>Quantity</th><th>Station</th><th>Shift</th><th>Lead</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                    @foreach($plans as $plan)
                        <tr>
                            <td><strong>{{ $plan->scheduled_time ? substr($plan->scheduled_time, 0, 5) : 'Any time' }}</strong><br><span class="subtle">{{ $plan->wave ?: 'No wave' }}</span></td>
                            <td><strong>{{ $plan->recipe?->outputVariant?->product?->name }}</strong><br><span class="subtle">{{ $plan->recipe?->name }}@if($plan->workOrder) · {{ $plan->workOrder->order_number }}@endif</span></td>
                            <td>{{ $qty($plan->planned_quantity) }}</td>
                            <td>{{ $plan->station?->name ?? '—' }}</td>
                            <td>{{ $plan->shift?->name ?? '—' }}</td>
                            <td>{{ $plan->lead?->name ?? '—' }}</td>
                            <td><span class="badge {{ $plan->status === 'completed' ? 'success' : ($plan->status === 'cancelled' ? 'neutral' : '') }}">{{ str($plan->status)->headline() }}</span></td>
                            <td>
                                <details class="table-action-menu">
                                    <summary class="btn primary">Action <span aria-hidden="true">⌄</span></summary>
                                    <div class="table-action-dropdown">
                                        <div class="table-action-heading">Actions</div>
                                        @if($plan->status === 'planned')
                                            <button class="table-action-option edit" type="button" data-dialog-open="day-plan-edit-dialog" data-day-plan-edit="{{ $plan->id }}">Edit Schedule</button>
                                            <form method="POST" action="{{ route('admin.inventory.production.planning.start', $plan) }}">@csrf<input type="hidden" name="tenant" value="{{ $tenant->id }}"><button class="table-action-option start" type="submit">Start Production</button></form>
                                            <form method="POST" action="{{ route('admin.inventory.production.planning.cancel', $plan) }}">@csrf<input type="hidden" name="tenant" value="{{ $tenant->id }}"><button class="table-action-option cancel" type="submit">Cancel Schedule</button></form>
                                        @elseif($plan->productionOrder)
                                            <a class="table-action-option open" href="{{ route('admin.inventory.production.runs.index', ['tenant' => $tenant->id]).'#run-'.$plan->production_order_id }}">Production Run</a>
                                        @endif
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </section>

    <dialog class="dialog" id="day-plan-edit-dialog">
        <div class="dialog-header"><h2 class="panel-title" data-day-plan-dialog-title>Edit scheduled product</h2><button class="icon-btn" type="button" data-dialog-close>✕</button></div>
        <div class="dialog-body">
            <form class="mini-form" method="POST" action="{{ route('admin.inventory.production.planning.store') }}" data-day-plan-edit-form data-create-action="{{ route('admin.inventory.production.planning.store') }}">
                @csrf
                <input type="hidden" name="_method" value="PUT" disabled data-day-plan-method>
                <input type="hidden" name="tenant" value="{{ $tenant->id }}">
                <input type="hidden" name="production_date" value="{{ $productionDate->toDateString() }}">
                <div class="form-grid">
                    <div class="field full">
                        <label>Finished product</label>
                        <select name="items[0][recipe_id]" required>
                            <option value="">Select finished product</option>
                            @foreach($recipes as $recipe)
                                <option value="{{ $recipe->id }}">{{ $recipe->outputVariant?->product?->name }} / {{ $recipe->outputVariant?->variant_name ?? 'Default' }} · {{ $recipe->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field"><label>Quantity</label><input type="number" min="0.0001" step="any" name="items[0][planned_quantity]" required></div>
                    <div class="field"><label>Time <span class="subtle">(optional)</span></label><input type="time" name="items[0][scheduled_time]"></div>
                    <div class="field"><label>Station</label><select name="production_station_id" required>@foreach($stations as $station)<option value="{{ $station->id }}">{{ $station->name }}</option>@endforeach</select></div>
                    <div class="field"><label>Work shift</label><select name="hr_work_shift_id"><option value="">No shift</option>@foreach($shifts as $shift)<option value="{{ $shift->id }}">{{ $shift->name }} · {{ substr($shift->starts_at, 0, 5) }}</option>@endforeach</select></div>
                    <div class="field"><label>Head baker / lead</label><select name="production_lead_staff_id" required><option value="">Select</option>@foreach($staff as $employee)<option value="{{ $employee->id }}">{{ $employee->name }}</option>@endforeach</select></div>
                    <div class="field"><label>Wave</label><input name="wave" placeholder="Morning wave"></div>
                    <div class="field full"><label>Notes</label><textarea name="notes"></textarea></div>
                </div>
                <div class="dialog-actions" style="display:flex; gap:10px; flex-wrap:wrap; margin-top:24px;"><button class="btn danger" type="button" data-dialog-close>Cancel</button><button class="btn primary" type="submit" data-day-plan-submit>Save changes</button></div>
            </form>
        </div>
    </dialog>

    <script>
        (() => {
            const plans = @json($editablePlans);
            const form = document.querySelector('[data-day-plan-edit-form]');
            const method = document.querySelector('[data-day-plan-method]');
            const title = document.querySelector('[data-day-plan-dialog-title]');
            const submit = document.querySelector('[data-day-plan-submit]');
            if (!form) return;

            function setValue(name, value) {
                const field = form.elements.namedItem(name);
                if (field) field.value = value ?? '';
            }

            document.querySelector('[data-day-plan-create]')?.addEventListener('click', () => {
                form.reset();
                form.action = form.dataset.createAction;
                method.disabled = true;
                title.textContent = 'Add scheduled product';
                submit.textContent = 'Add product';
            });

            document.querySelectorAll('[data-day-plan-edit]').forEach((button) => {
                button.addEventListener('click', () => {
                    const plan = plans.find((item) => String(item.id) === button.dataset.dayPlanEdit);
                    if (!plan) return;
                    form.reset();
                    form.action = plan.update_url;
                    method.disabled = false;
                    title.textContent = 'Edit scheduled product';
                    submit.textContent = 'Save changes';
                    setValue('items[0][recipe_id]', plan.recipe_id);
                    setValue('items[0][planned_quantity]', plan.planned_quantity);
                    setValue('items[0][scheduled_time]', plan.scheduled_time);
                    setValue('production_station_id', plan.production_station_id);
                    setValue('hr_work_shift_id', plan.hr_work_shift_id);
                    setValue('production_lead_staff_id', plan.production_lead_staff_id);
                    setValue('wave', plan.wave);
                    setValue('notes', plan.notes);
                });
            });

            const requestedEdit = @json(request()->integer('edit'));
            if (requestedEdit) {
                document.addEventListener('DOMContentLoaded', () => {
                    window.setTimeout(() => document.querySelector(`[data-day-plan-edit="${requestedEdit}"]`)?.click(), 0);
                });
            }
        })();
    </script>
</x-layouts.admin>
