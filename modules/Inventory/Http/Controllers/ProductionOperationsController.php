<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ActiveBranchManager;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Access\Enums\MembershipStatus;
use Modules\Business\Models\Branch;
use Modules\Catalog\Enums\ProductType;
use Modules\HrPayroll\Models\HrStaff;
use Modules\HrPayroll\Models\HrWorkShift;
use Modules\Inventory\Actions\PostInventoryMovementAction;
use Modules\Inventory\Actions\RecordProductionAction;
use Modules\Inventory\Enums\InventoryMovementType;
use Modules\Inventory\Enums\ProductionVarianceReason;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Inventory\Models\ProductionOrder;
use Modules\Inventory\Models\ProductionPlan;
use Modules\Inventory\Models\ProductionWasteRecord;
use Modules\Inventory\Models\ProductionWorkOrder;
use Modules\Inventory\Models\Recipe;
use Modules\Tenancy\Models\Tenant;

final class ProductionOperationsController extends Controller
{
    public function __construct(private readonly ActiveBranchManager $branches) {}

    public function planning(Request $request): View
    {
        [$tenant, $tenants, $user, $branch] = $this->context($request);
        $from = CarbonImmutable::parse($request->string('from')->toString() ?: now()->startOfWeek()->toDateString());
        $to = CarbonImmutable::parse($request->string('to')->toString() ?: $from->addDays(13)->toDateString());
        abort_if($to->lt($from) || $to->diffInDays($from) > 62, 422, 'Choose a valid planning period of 63 days or less.');

        $plans = ProductionPlan::query()
            ->with(['recipe.outputVariant.product', 'station', 'shift', 'lead', 'team', 'productionOrder', 'workOrder'])
            ->where('tenant_id', $tenant->id)->where('branch_id', $branch->id)
            ->whereBetween('production_date', [$from->toDateString(), $to->toDateString()])
            ->orderByDesc('production_date')->orderBy('scheduled_time')->get();

        $recipes = $this->recipes($tenant);

        return view('inventory::admin.production.planning', [
            'tenant' => $tenant, 'tenants' => $tenants, 'isPlatformAdmin' => $user->is_platform_admin,
            'branch' => $branch, 'from' => $from, 'to' => $to, 'plans' => $plans,
            'defaultPlanDate' => $from->isFuture() ? $from : CarbonImmutable::today(),
            'recipes' => $recipes,
            'schedulableProducts' => $recipes->map(fn (Recipe $recipe): array => [
                'recipe_id' => $recipe->id,
                'type' => $recipe->outputVariant?->product?->product_type?->value,
                'label' => ($recipe->outputVariant?->product?->name ?? 'Product')
                    .' / '.($recipe->outputVariant?->variant_name ?? 'Default')
                    .' · '.$recipe->name,
            ])->values(),
            'editablePlans' => $plans->where('status', 'planned')->map(fn (ProductionPlan $plan): array => [
                'id' => $plan->id,
                'update_url' => route('admin.inventory.production.planning.update', $plan),
                'recipe_id' => $plan->recipe_id,
                'product_type' => $plan->recipe?->outputVariant?->product?->product_type?->value,
                'product_label' => ($plan->recipe?->outputVariant?->product?->name ?? 'Product')
                    .' / '.($plan->recipe?->outputVariant?->variant_name ?? 'Default')
                    .' · '.($plan->recipe?->name ?? 'Recipe'),
                'production_date' => $plan->production_date->toDateString(),
                'scheduled_time' => $plan->scheduled_time ? substr($plan->scheduled_time, 0, 5) : '',
                'wave' => $plan->wave,
                'planned_quantity' => $plan->planned_quantity,
                'production_station_id' => $plan->production_station_id,
                'hr_work_shift_id' => $plan->hr_work_shift_id,
                'production_lead_staff_id' => $plan->production_lead_staff_id,
                'notes' => $plan->notes,
            ])->values(),
            'stations' => $this->stations($tenant, $branch),
            'shifts' => $this->shifts($tenant, $branch), 'staff' => $this->staff($tenant, $branch),
        ]);
    }

    public function planningDay(Request $request, string $date): View
    {
        [$tenant, $tenants, $user, $branch] = $this->context($request);
        try {
            $productionDate = CarbonImmutable::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Throwable) {
            abort(404);
        }
        abort_unless($productionDate->format('Y-m-d') === $date, 404);

        $plans = ProductionPlan::query()
            ->with(['recipe.outputVariant.product', 'station', 'shift', 'lead', 'productionOrder', 'workOrder'])
            ->where('tenant_id', $tenant->id)->where('branch_id', $branch->id)
            ->whereDate('production_date', $date)
            ->orderByRaw('scheduled_time is null')->orderBy('scheduled_time')->orderBy('id')->get();
        $recipes = $this->recipes($tenant);

        return view('inventory::admin.production.planning-day', [
            'tenant' => $tenant, 'tenants' => $tenants, 'isPlatformAdmin' => $user->is_platform_admin,
            'branch' => $branch, 'productionDate' => $productionDate, 'plans' => $plans,
            'recipes' => $recipes, 'stations' => $this->stations($tenant, $branch),
            'shifts' => $this->shifts($tenant, $branch), 'staff' => $this->staff($tenant, $branch),
        ]);
    }

    public function storePlan(Request $request): RedirectResponse
    {
        [$tenant, , $user, $branch] = $this->context($request);
        $data = $request->validate([
            'production_station_id' => ['required', 'integer'],
            'hr_work_shift_id' => ['nullable', 'integer'],
            'production_lead_staff_id' => ['required', 'integer'],
            'production_date' => ['required', 'date'],
            'wave' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.recipe_id' => ['required', 'integer'],
            'items.*.planned_quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'items.*.scheduled_time' => ['nullable', 'date_format:H:i'],
        ]);
        $station = InventoryLocation::query()->where('tenant_id', $tenant->id)->where('branch_id', $branch->id)->where('status', 'active')->findOrFail($data['production_station_id']);
        $shift = filled($data['hr_work_shift_id'] ?? null)
            ? HrWorkShift::query()->where('tenant_id', $tenant->id)->where('branch_id', $branch->id)->where('is_active', true)->findOrFail($data['hr_work_shift_id'])
            : null;
        $teamIds = $shift?->assignments()->whereDate('work_date', $data['production_date'])
            ->where('status', '!=', 'cancelled')->pluck('hr_staff_id') ?? collect();
        $teamIds->push((int) $data['production_lead_staff_id'])->unique()->values();
        $staff = $this->staff($tenant, $branch)->whereIn('id', $teamIds);
        if (! $staff->contains('id', (int) $data['production_lead_staff_id'])) {
            throw ValidationException::withMessages(['production_lead_staff_id' => 'The production lead must be active staff in this branch.']);
        }

        $lines = collect($data['items'])->map(fn (array $line): array => [
            'recipe_id' => (int) $line['recipe_id'],
            'planned_quantity' => (float) $line['planned_quantity'],
            'scheduled_time' => $line['scheduled_time'] ?? null,
        ])->values();
        $recipeIds = $lines->pluck('recipe_id')->unique()->values();
        $validRecipes = $this->recipes($tenant)->whereIn('id', $recipeIds);
        if ($validRecipes->count() !== $recipeIds->count()) {
            throw ValidationException::withMessages(['items' => 'Every product must have an active recipe for this business.']);
        }

        if ($lines->count() > 1) {
            DB::transaction(function () use ($tenant, $branch, $user, $data, $station, $shift, $staff, $lines): void {
                $scheduledTime = $lines->pluck('scheduled_time')->unique()->count() === 1
                    ? $lines->first()['scheduled_time']
                    : null;
                $workOrder = ProductionWorkOrder::query()->create([
                    'tenant_id' => $tenant->id,
                    'branch_id' => $branch->id,
                    'order_number' => 'PWO-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                    'production_date' => $data['production_date'],
                    'scheduled_time' => $scheduledTime,
                    'wave' => $data['wave'] ?? null,
                    'production_station_id' => $station->id,
                    'hr_work_shift_id' => $shift?->id,
                    'production_lead_staff_id' => (int) $data['production_lead_staff_id'],
                    'status' => 'planned',
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $user->id,
                ]);

                foreach ($lines as $line) {
                    $plan = ProductionPlan::query()->create([
                        'tenant_id' => $tenant->id, 'branch_id' => $branch->id,
                        'production_work_order_id' => $workOrder->id, 'recipe_id' => $line['recipe_id'],
                        'production_station_id' => $station->id, 'hr_work_shift_id' => $shift?->id,
                        'production_lead_staff_id' => (int) $data['production_lead_staff_id'],
                        'production_date' => $data['production_date'], 'scheduled_time' => $line['scheduled_time'],
                        'wave' => $data['wave'] ?? null, 'planned_quantity' => $line['planned_quantity'],
                        'status' => 'planned', 'notes' => $data['notes'] ?? null, 'created_by' => $user->id,
                    ]);
                    $plan->team()->sync($staff->mapWithKeys(fn (HrStaff $member): array => [
                        $member->id => ['is_lead' => $member->id === (int) $data['production_lead_staff_id']],
                    ])->all());
                }
            });

            return back()->with('status', $lines->count().' products scheduled in one production order.');
        }

        $line = $lines->first();

        $plan = ProductionPlan::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'recipe_id' => $line['recipe_id'],
            'production_station_id' => $station->id, 'hr_work_shift_id' => $shift?->id,
            'production_lead_staff_id' => (int) $data['production_lead_staff_id'],
            'production_date' => $data['production_date'], 'scheduled_time' => $line['scheduled_time'],
            'wave' => $data['wave'] ?? null, 'planned_quantity' => $line['planned_quantity'],
            'status' => 'planned', 'notes' => $data['notes'] ?? null, 'created_by' => $user->id,
        ]);
        $plan->team()->sync($staff->mapWithKeys(fn (HrStaff $member): array => [
            $member->id => ['is_lead' => $member->id === (int) $data['production_lead_staff_id']],
        ])->all());

        return back()->with('status', 'Production scheduled.');
    }

    public function startPlan(Request $request, ProductionPlan $plan, RecordProductionAction $action): RedirectResponse
    {
        [$tenant, , $user, $branch] = $this->context($request);
        abort_unless($plan->tenant_id === $tenant->id && $plan->branch_id === $branch->id, 404);
        if ($plan->status !== 'planned') {
            throw ValidationException::withMessages(['production_plan' => 'Only a planned production item can be started.']);
        }
        $plan->load(['recipe', 'team', 'workOrder']);
        $result = $action->start([
            'tenant_id' => $tenant->id, 'recipe_id' => $plan->recipe_id,
            'source_location_id' => $plan->production_station_id, 'output_location_id' => $plan->production_station_id,
            'production_lead_user_id' => $user->id, 'planned_quantity' => (float) $plan->planned_quantity,
            'reference_number' => $plan->workOrder?->order_number, 'notes' => $plan->notes,
        ]);
        $order = $result['order'];
        $order->update([
            'production_work_order_id' => $plan->production_work_order_id,
            'hr_work_shift_id' => $plan->hr_work_shift_id,
            'production_lead_staff_id' => $plan->production_lead_staff_id,
        ]);
        $order->team()->sync($plan->team->mapWithKeys(fn (HrStaff $member): array => [
            $member->id => ['is_lead' => (bool) $member->pivot->is_lead],
        ])->all());
        $plan->update(['status' => 'in_progress', 'production_order_id' => $order->id]);
        $plan->workOrder?->update(['status' => 'in_progress']);

        return back()->with('status', 'Scheduled production started. Its ingredients are now held.');
    }

    public function updatePlan(Request $request, ProductionPlan $plan): RedirectResponse
    {
        [$tenant, , , $branch] = $this->context($request);
        abort_unless($plan->tenant_id === $tenant->id && $plan->branch_id === $branch->id, 404);
        if ($plan->status !== 'planned') {
            throw ValidationException::withMessages(['production_plan' => 'Only production that is still planned can be edited.']);
        }

        $data = $request->validate([
            'production_station_id' => ['required', 'integer'],
            'hr_work_shift_id' => ['nullable', 'integer'],
            'production_lead_staff_id' => ['required', 'integer'],
            'production_date' => ['required', 'date'],
            'wave' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'size:1'],
            'items.*.recipe_id' => ['required', 'integer'],
            'items.*.planned_quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'items.*.scheduled_time' => ['nullable', 'date_format:H:i'],
        ]);
        $line = $data['items'][array_key_first($data['items'])];
        $recipe = Recipe::query()->where('tenant_id', $tenant->id)->where('is_active', true)->findOrFail($line['recipe_id']);
        $station = InventoryLocation::query()->where('tenant_id', $tenant->id)->where('branch_id', $branch->id)->where('status', 'active')->findOrFail($data['production_station_id']);
        $shift = filled($data['hr_work_shift_id'] ?? null)
            ? HrWorkShift::query()->where('tenant_id', $tenant->id)->where('branch_id', $branch->id)->where('is_active', true)->findOrFail($data['hr_work_shift_id'])
            : null;
        $teamIds = $shift?->assignments()->whereDate('work_date', $data['production_date'])
            ->where('status', '!=', 'cancelled')->pluck('hr_staff_id') ?? collect();
        $teamIds->push((int) $data['production_lead_staff_id'])->unique()->values();
        $staff = $this->staff($tenant, $branch)->whereIn('id', $teamIds);
        if (! $staff->contains('id', (int) $data['production_lead_staff_id'])) {
            throw ValidationException::withMessages(['production_lead_staff_id' => 'The production lead must be active staff in this branch.']);
        }

        DB::transaction(function () use ($plan, $data, $line, $recipe, $station, $shift, $staff): void {
            $plan->update([
                'recipe_id' => $recipe->id,
                'production_station_id' => $station->id,
                'hr_work_shift_id' => $shift?->id,
                'production_lead_staff_id' => (int) $data['production_lead_staff_id'],
                'production_date' => $data['production_date'],
                'scheduled_time' => $line['scheduled_time'] ?? null,
                'wave' => $data['wave'] ?? null,
                'planned_quantity' => $line['planned_quantity'],
                'notes' => $data['notes'] ?? null,
            ]);
            $plan->team()->sync($staff->mapWithKeys(fn (HrStaff $member): array => [
                $member->id => ['is_lead' => $member->id === (int) $data['production_lead_staff_id']],
            ])->all());
        });

        return back()->with('status', 'Scheduled production item updated.');
    }

    public function cancelPlan(Request $request, ProductionPlan $plan): RedirectResponse
    {
        [$tenant, , , $branch] = $this->context($request);
        abort_unless($plan->tenant_id === $tenant->id && $plan->branch_id === $branch->id, 404);
        abort_unless($plan->status === 'planned', 422, 'A started production must be cancelled from its production run.');
        $plan->update(['status' => 'cancelled']);
        $this->syncWorkOrderStatus($plan);

        return back()->with('status', 'Scheduled production item cancelled.');
    }

    public function workOrders(Request $request): View
    {
        [$tenant, $tenants, $user, $branch] = $this->context($request);
        $from = CarbonImmutable::parse($request->string('from')->toString() ?: now()->toDateString());
        $to = CarbonImmutable::parse($request->string('to')->toString() ?: now()->toDateString());
        abort_if($to->lt($from) || $to->diffInDays($from) > 366, 422, 'Choose a valid production period of one year or less.');
        $plans = ProductionPlan::query()
            ->with(['recipe.outputVariant.product', 'station', 'shift', 'lead', 'team', 'productionOrder', 'workOrder'])
            ->where('tenant_id', $tenant->id)->where('branch_id', $branch->id)
            ->whereDate('production_date', '>=', $from->toDateString())
            ->whereDate('production_date', '<=', $to->toDateString())
            ->orderByDesc('production_date')->orderByDesc('scheduled_time')->latest('id')->get();

        return view('inventory::admin.production.orders', compact(
            'tenant', 'tenants', 'user', 'branch', 'from', 'to', 'plans',
        ));
    }

    public function startWorkOrder(Request $request, ProductionWorkOrder $workOrder, RecordProductionAction $action): RedirectResponse
    {
        [$tenant, , $user, $branch] = $this->context($request);
        abort_unless($workOrder->tenant_id === $tenant->id && $workOrder->branch_id === $branch->id, 404);
        if ($workOrder->status !== 'planned') {
            throw ValidationException::withMessages(['production_work_order' => 'Only a planned production order can be started.']);
        }
        DB::transaction(function () use ($workOrder, $tenant, $user, $action): void {
            $lockedWorkOrder = ProductionWorkOrder::query()->lockForUpdate()->findOrFail($workOrder->id);
            if ($lockedWorkOrder->status !== 'planned') {
                throw ValidationException::withMessages(['production_work_order' => 'Only a planned production order can be started.']);
            }
            $lockedWorkOrder->load('plans.team');

            foreach ($lockedWorkOrder->plans as $plan) {
                $result = $action->start([
                    'tenant_id' => $tenant->id, 'recipe_id' => $plan->recipe_id,
                    'source_location_id' => $plan->production_station_id, 'output_location_id' => $plan->production_station_id,
                    'production_lead_user_id' => $user->id, 'planned_quantity' => (float) $plan->planned_quantity,
                    'reference_number' => $lockedWorkOrder->order_number, 'notes' => $plan->notes,
                ]);
                $order = $result['order'];
                $order->update([
                    'production_work_order_id' => $lockedWorkOrder->id,
                    'hr_work_shift_id' => $plan->hr_work_shift_id,
                    'production_lead_staff_id' => $plan->production_lead_staff_id,
                ]);
                $order->team()->sync($plan->team->mapWithKeys(fn (HrStaff $member): array => [
                    $member->id => ['is_lead' => (bool) $member->pivot->is_lead],
                ])->all());
                $plan->update(['status' => 'in_progress', 'production_order_id' => $order->id]);
            }
            $lockedWorkOrder->update(['status' => 'in_progress']);
        });

        return redirect()->route('admin.inventory.production.orders', ['tenant' => $tenant->id])
            ->with('status', 'Multi-product production order started. All ingredients are now held.');
    }

    public function cancelWorkOrder(Request $request, ProductionWorkOrder $workOrder): RedirectResponse
    {
        [$tenant, , , $branch] = $this->context($request);
        abort_unless($workOrder->tenant_id === $tenant->id && $workOrder->branch_id === $branch->id, 404);
        abort_unless($workOrder->status === 'planned', 422, 'A started production order must be managed through its individual runs.');
        DB::transaction(function () use ($workOrder): void {
            $workOrder->plans()->update(['status' => 'cancelled']);
            $workOrder->update(['status' => 'cancelled']);
        });

        return back()->with('status', 'Production order cancelled.');
    }

    public function waste(Request $request): View
    {
        [$tenant, $tenants, $user, $branch] = $this->context($request);
        [$from, $to] = $this->dateRange($request);
        $records = ProductionWasteRecord::query()->with(['variant.product', 'location', 'staff', 'productionOrder'])
            ->where('tenant_id', $tenant->id)->where('branch_id', $branch->id)
            ->whereBetween('waste_date', [$from->toDateString(), $to->toDateString()])
            ->latest('waste_date')->latest('id')->get();

        return view('inventory::admin.production.waste', [
            'tenant' => $tenant, 'tenants' => $tenants, 'isPlatformAdmin' => $user->is_platform_admin,
            'branch' => $branch, 'from' => $from, 'to' => $to, 'records' => $records,
            'wasteStockLevels' => $this->wasteStockLevels($tenant, $branch),
            'locations' => $this->stations($tenant, $branch, false),
            'staff' => $this->staff($tenant, $branch), 'reasons' => ProductionVarianceReason::options(),
            'orders' => ProductionOrder::query()->with('outputVariant.product')
                ->where('tenant_id', $tenant->id)
                ->where('status', ProductionOrder::STATUS_COMPLETED)
                ->whereHas('sourceLocation', fn ($query) => $query
                    ->where('tenant_id', $tenant->id)->where('branch_id', $branch->id))
                ->whereHas('outputLocation', fn ($query) => $query
                    ->where('tenant_id', $tenant->id)->where('branch_id', $branch->id))
                ->latest('produced_at')->latest('id')->limit(100)->get(),
        ]);
    }

    public function storeWaste(Request $request, PostInventoryMovementAction $movements): RedirectResponse
    {
        [$tenant, , $user, $branch] = $this->context($request);
        $data = $request->validate([
            'product_variant_id' => ['required', Rule::exists('product_variants', 'id')->where('tenant_id', $tenant->id)],
            'inventory_location_id' => ['required', 'integer'],
            'production_order_id' => ['nullable', Rule::exists('production_orders', 'id')->where('tenant_id', $tenant->id)],
            'hr_staff_id' => ['nullable', 'integer'],
            'waste_date' => ['required', 'date'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'reason' => ['required', Rule::enum(ProductionVarianceReason::class)],
            'deduct_from_stock' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $deduct = $request->boolean('deduct_from_stock');
        $location = InventoryLocation::query()->where('tenant_id', $tenant->id)
            ->where('branch_id', $branch->id)->findOrFail($data['inventory_location_id']);
        $stockLevel = InventoryStockLevel::query()
            ->where('tenant_id', $tenant->id)
            ->where('inventory_location_id', $location->id)
            ->where('product_variant_id', $data['product_variant_id'])
            ->where('quantity_on_hand', '>', 0)
            ->whereHas('variant', fn ($query) => $query
                ->where('tenant_id', $tenant->id)
                ->whereHas('product', fn ($product) => $product
                    ->where('tenant_id', $tenant->id)
                    ->whereIn('product_type', array_map(fn (ProductType $type): string => $type->value, ProductType::stockable()))
                    ->where('is_finished_product', true)))
            ->first();
        if (! $stockLevel) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'Select a finished product currently stocked at the selected location.',
            ]);
        }
        $productionOrder = null;
        if (filled($data['production_order_id'] ?? null)) {
            $productionOrder = ProductionOrder::query()
                ->where('tenant_id', $tenant->id)
                ->where('status', ProductionOrder::STATUS_COMPLETED)
                ->where('output_product_variant_id', $data['product_variant_id'])
                ->whereHas('sourceLocation', fn ($query) => $query
                    ->where('tenant_id', $tenant->id)->where('branch_id', $branch->id))
                ->whereHas('outputLocation', fn ($query) => $query
                    ->where('tenant_id', $tenant->id)->where('branch_id', $branch->id))
                ->find($data['production_order_id']);

            if (! $productionOrder) {
                throw ValidationException::withMessages([
                    'production_order_id' => 'Select a completed production run for the chosen finished product.',
                ]);
            }
        }
        $staff = filled($data['hr_staff_id'] ?? null) ? $this->staff($tenant, $branch)->firstWhere('id', (int) $data['hr_staff_id']) : null;
        if (filled($data['hr_staff_id'] ?? null) && ! $staff) {
            throw ValidationException::withMessages(['hr_staff_id' => 'Select an active staff member in this branch.']);
        }
        $unitCost = (int) $stockLevel->average_cost_minor;

        DB::transaction(function () use ($tenant, $branch, $location, $data, $productionOrder, $staff, $unitCost, $deduct, $user, $movements): void {
            $record = ProductionWasteRecord::query()->create([
                'tenant_id' => $tenant->id, 'branch_id' => $branch->id,
                'inventory_location_id' => $location->id, 'product_variant_id' => $data['product_variant_id'],
                'production_order_id' => $productionOrder?->id, 'hr_staff_id' => $staff?->id,
                'waste_date' => $data['waste_date'], 'quantity' => $data['quantity'], 'reason' => $data['reason'],
                'unit_cost_minor' => $unitCost, 'value_minor' => (int) round((float) $data['quantity'] * $unitCost),
                'deducted_from_stock' => $deduct, 'notes' => $data['notes'] ?? null, 'recorded_by' => $user->id,
            ]);

            if ($deduct) {
                $movement = $movements->execute([
                    'tenant_id' => $tenant->id, 'inventory_location_id' => $location->id,
                    'product_variant_id' => (int) $data['product_variant_id'],
                    'movement_type' => InventoryMovementType::Damaged->value, 'quantity' => (float) $data['quantity'],
                    'unit_cost_minor' => $unitCost, 'reference_number' => 'WASTE-'.$record->id,
                    'notes' => ProductionVarianceReason::from($data['reason'])->label().'. '.($data['notes'] ?? ''),
                    'occurred_at' => CarbonImmutable::parse($data['waste_date'])->endOfDay(),
                ]);
                $record->update(['inventory_movement_id' => $movement->id]);
            }
        });

        return redirect()->route('admin.inventory.production.waste', [
            'tenant' => $tenant->id, 'from' => $data['waste_date'], 'to' => $data['waste_date'],
        ])->with('status', 'Waste recorded'.($deduct ? ' and stock deducted.' : '.'));
    }

    public function reports(Request $request): View
    {
        return view('inventory::admin.production.reports', $this->reportData($request));
    }

    public function downloadReport(Request $request): Response
    {
        $format = $request->string('format')->toString();
        abort_unless(in_array($format, ['pdf', 'excel', 'word'], true), 404);
        $data = $this->reportData($request);
        $filename = 'production-report-'.$data['tenant']->slug.'-'.$data['from']->format('Ymd').'-'.$data['to']->format('Ymd');

        if ($format === 'pdf') {
            return response($this->reportPdf($data), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"',
            ]);
        }

        $html = view('inventory::admin.production.report-export', $data)->render();

        return response($html, 200, [
            'Content-Type' => $format === 'word'
                ? 'application/msword; charset=UTF-8'
                : 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.'.($format === 'word' ? 'doc' : 'xls').'"',
        ]);
    }

    /** @return array<string, mixed> */
    private function reportData(Request $request): array
    {
        [$tenant, $tenants, $user, $branch] = $this->context($request);
        [$from, $to] = $this->dateRange($request);
        $orders = ProductionOrder::query()
            ->with(['outputVariant.product', 'productionLead', 'productionLeadStaff', 'team', 'workShift', 'items.componentVariant.product', 'items.unit'])
            ->where('tenant_id', $tenant->id)->where('status', ProductionOrder::STATUS_COMPLETED)
            ->whereHas('sourceLocation', fn ($q) => $q->where('branch_id', $branch->id))
            ->whereBetween('produced_at', [$from->startOfDay(), $to->endOfDay()])->get();
        $waste = ProductionWasteRecord::query()->with(['variant.product', 'staff', 'productionOrder'])
            ->where('tenant_id', $tenant->id)->where('branch_id', $branch->id)
            ->whereBetween('waste_date', [$from->toDateString(), $to->toDateString()])->get();
        $surplusValueMinor = (int) $orders
            ->filter(fn (ProductionOrder $order): bool => (float) $order->actual_yield_quantity > (float) $order->planned_quantity)
            ->sum('variance_value_minor');
        $yieldLossValueMinor = (int) $orders
            ->filter(fn (ProductionOrder $order): bool => (float) $order->actual_yield_quantity < (float) $order->planned_quantity)
            ->sum('variance_value_minor');

        $ingredientVariance = $orders->flatMap(fn (ProductionOrder $order) => $order->items->map(function ($item) use ($order): array {
            $planned = (float) $item->planned_quantity;
            $actual = (float) $item->actual_quantity;

            $baseVariance = abs($actual - $planned) * (float) ($item->unit?->to_base_factor ?? 1);

            return [
                'order' => $order, 'item' => $item, 'planned' => $planned, 'actual' => $actual,
                'variance' => $actual - $planned,
                'value_minor' => (int) round($baseVariance * (int) $item->unit_cost_minor),
            ];
        }));
        $staff = $this->staff($tenant, $branch);
        $productivity = $staff->map(function (HrStaff $employee) use ($orders, $waste): array {
            $runs = $orders->filter(fn (ProductionOrder $order): bool => $order->team->contains('id', $employee->id));

            return [
                'staff' => $employee, 'runs' => $runs->count(),
                'led' => $runs->where('production_lead_staff_id', $employee->id)->count(),
                'planned' => (float) $runs->sum('planned_quantity'), 'actual' => (float) $runs->sum('actual_yield_quantity'),
                'surplus_value_minor' => (int) $runs
                    ->filter(fn (ProductionOrder $order): bool => (float) $order->actual_yield_quantity > (float) $order->planned_quantity)
                    ->sum('variance_value_minor'),
                'yield_loss_value_minor' => (int) $runs
                    ->filter(fn (ProductionOrder $order): bool => (float) $order->actual_yield_quantity < (float) $order->planned_quantity)
                    ->sum('variance_value_minor'),
                'waste_value_minor' => (int) $waste->where('hr_staff_id', $employee->id)->sum('value_minor'),
            ];
        })->filter(fn (array $row): bool => $row['runs'] > 0 || $row['waste_value_minor'] > 0)->values();

        return compact(
            'tenant', 'tenants', 'user', 'branch', 'from', 'to', 'orders', 'waste', 'ingredientVariance', 'productivity',
            'surplusValueMinor', 'yieldLossValueMinor',
        );
    }

    /** @param array<string, mixed> $data */
    private function reportPdf(array $data): string
    {
        $money = fn (int $minor): string => $data['tenant']->currency_code.' '.number_format($minor / 100, 2);
        $lines = [
            'Company: '.$data['tenant']->name,
            'Branch: '.$data['branch']->name,
            'Period: '.$data['from']->format('M j, Y').' to '.$data['to']->format('M j, Y'),
            'Completed runs: '.$data['orders']->count(),
            'Production surplus value: '.$money($data['surplusValueMinor']),
            'Yield loss value: '.$money($data['yieldLossValueMinor']),
            'Waste value: '.$money((int) $data['waste']->sum('value_minor')),
            '',
            'Production and yield',
        ];
        foreach ($data['orders']->take(18) as $order) {
            $lines[] = $order->runNumber().' | '.$order->produced_at?->format('M j, Y').' | '.($order->outputVariant?->product?->name ?? 'Product').' | planned '.$order->planned_quantity.' | actual '.$order->actual_yield_quantity;
        }
        $lines[] = '';
        $lines[] = 'Staff productivity';
        foreach ($data['productivity']->take(14) as $row) {
            $lines[] = $row['staff']->name.' | runs '.$row['runs'].' | led '.$row['led'].' | actual '.$row['actual'].' | surplus '.$money($row['surplus_value_minor']).' | yield loss '.$money($row['yield_loss_value_minor']).' | waste '.$money($row['waste_value_minor']);
        }

        $content = "BT\n/F1 18 Tf\n50 780 Td\n(Production Report) Tj\n/F1 9 Tf\n0 -26 Td\n";
        foreach ($lines as $line) {
            $content .= '('.$this->pdfText($line).") Tj\n0 -14 Td\n";
        }
        $content .= "ET\n";
        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            "5 0 obj\n<< /Length ".strlen($content)." >>\nstream\n".$content."endstream\nendobj\n",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";

        return $pdf;
    }

    private function pdfText(string $text): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $ascii);
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function dateRange(Request $request): array
    {
        $from = CarbonImmutable::parse($request->string('from')->toString() ?: now()->startOfMonth()->toDateString());
        $to = CarbonImmutable::parse($request->string('to')->toString() ?: now()->toDateString());
        abort_if($to->lt($from) || $to->diffInDays($from) > 366, 422, 'Choose a valid reporting period of one year or less.');

        return [$from, $to];
    }

    /** @return array{Tenant, EloquentCollection<int, Tenant>, User, Branch} */
    private function context(Request $request): array
    {
        /** @var User $user */ $user = $request->user();
        $tenants = $user->is_platform_admin ? Tenant::query()->orderBy('name')->get()
            : Tenant::query()->whereHas('memberships', fn ($q) => $q->where('user_id', $user->id)->where('status', MembershipStatus::Active->value))->orderBy('name')->get();
        $tenantId = $request->string('tenant')->toString();
        $tenant = $tenantId !== '' ? $tenants->firstWhere('id', $tenantId) : $tenants->first();
        abort_if(! $tenant, 403);
        $state = $this->branches->stateForRequest($request, $user);
        $branch = $state['tenant']?->id === $tenant->id ? $state['activeBranch'] : null;
        abort_unless($branch instanceof Branch, 403, 'Select an active branch first.');

        return [$tenant, $tenants, $user, $branch];
    }

    private function recipes(Tenant $tenant): Collection
    {
        return Recipe::query()->with('outputVariant.product')
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->whereHas('outputVariant.product', fn ($query) => $query->whereIn(
                'product_type',
                array_map(fn (ProductType $type): string => $type->value, ProductType::stockable()),
            ))
            ->orderBy('name')->get();
    }

    private function stations(Tenant $tenant, Branch $branch, bool $preferPrep = true): Collection
    {
        return InventoryLocation::query()->where('tenant_id', $tenant->id)->where('branch_id', $branch->id)->where('status', 'active')
            ->when($preferPrep, fn ($q) => $q->orderByDesc('is_prep_station'))->orderBy('name')->get();
    }

    private function shifts(Tenant $tenant, Branch $branch): Collection
    {
        return HrWorkShift::query()->where('tenant_id', $tenant->id)->where('branch_id', $branch->id)->where('is_active', true)->orderBy('starts_at')->get();
    }

    private function staff(Tenant $tenant, Branch $branch): Collection
    {
        return HrStaff::query()->where('tenant_id', $tenant->id)->where('branch_id', $branch->id)->where('status', 'active')->orderBy('first_name')->orderBy('last_name')->get();
    }

    private function wasteStockLevels(Tenant $tenant, Branch $branch): Collection
    {
        return InventoryStockLevel::query()
            ->with('variant.product')
            ->where('tenant_id', $tenant->id)
            ->where('quantity_on_hand', '>', 0)
            ->whereHas('location', fn ($query) => $query
                ->where('tenant_id', $tenant->id)
                ->where('branch_id', $branch->id)
                ->where('status', 'active'))
            ->whereHas('variant', fn ($query) => $query
                ->where('tenant_id', $tenant->id)
                ->whereHas('product', fn ($product) => $product
                    ->where('tenant_id', $tenant->id)
                    ->whereIn('product_type', array_map(fn (ProductType $type): string => $type->value, ProductType::stockable()))
                    ->where('is_finished_product', true)))
            ->get()
            ->sortBy(fn (InventoryStockLevel $level): string => strtolower(
                ($level->variant?->product?->name ?? '').' '.($level->variant?->variant_name ?? ''),
            ))
            ->values();
    }

    private function syncWorkOrderStatus(ProductionPlan $plan): void
    {
        if (! $plan->production_work_order_id) {
            return;
        }

        $workOrder = ProductionWorkOrder::query()->with('plans')->find($plan->production_work_order_id);
        if (! $workOrder) {
            return;
        }

        $statuses = $workOrder->plans->pluck('status');
        $status = $statuses->contains('in_progress') ? 'in_progress'
            : ($statuses->contains('planned') ? 'planned'
                : ($statuses->contains('completed') ? 'completed' : 'cancelled'));
        $workOrder->update(['status' => $status]);
    }

    private function planningRedirect(Tenant $tenant, string $date, string $status): RedirectResponse
    {
        return redirect()->route('admin.inventory.production.planning', [
            'tenant' => $tenant->id, 'from' => CarbonImmutable::parse($date)->startOfWeek()->toDateString(),
        ])->with('status', $status);
    }
}
