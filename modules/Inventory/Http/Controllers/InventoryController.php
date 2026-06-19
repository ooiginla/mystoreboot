<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Access\Enums\MembershipStatus;
use Modules\Access\Models\TenantMembership;
use Modules\Business\Models\Branch;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Models\ProductVariant;
use Modules\Inventory\Actions\PostInventoryMovementAction;
use Modules\Inventory\Enums\InventoryLocationType;
use Modules\Inventory\Enums\InventoryMovementType;
use Modules\Inventory\Enums\StockCondition;
use Modules\Inventory\Http\Requests\InventoryLocationRequest;
use Modules\Inventory\Http\Requests\InventoryMovementRequest;
use Modules\Inventory\Http\Requests\ReorderSettingRequest;
use Modules\Inventory\Models\InventoryBatch;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Inventory\Models\InventoryMovement;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Tenancy\Models\Tenant;

final class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $tenants = $this->visibleTenantsFor($user);
        $tenant = $this->resolveTenant($request, $tenants);

        abort_if(! $tenant, 403);

        $this->ensureBranchLocations($tenant);

        $locations = InventoryLocation::query()
            ->with('branch')
            ->where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get();

        $variants = ProductVariant::query()
            ->with(['product.category', 'optionValues.option'])
            ->where('tenant_id', $tenant->id)
            ->whereHas('product', fn ($query) => $query->where('product_type', ProductType::Product->value))
            ->orderBy('sku')
            ->get();

        $stockLevels = InventoryStockLevel::query()
            ->with(['location.branch', 'variant.product.category', 'variant.optionValues.option'])
            ->where('tenant_id', $tenant->id)
            ->latest('last_movement_at')
            ->get();

        $movements = InventoryMovement::query()
            ->with(['location.branch', 'destinationLocation.branch', 'variant.product'])
            ->where('tenant_id', $tenant->id)
            ->latest('occurred_at')
            ->limit(80)
            ->get();

        $batches = InventoryBatch::query()
            ->with(['location.branch', 'variant.product'])
            ->where('tenant_id', $tenant->id)
            ->where('quantity_remaining', '>', 0)
            ->latest()
            ->get();

        $lowStock = $stockLevels->filter(fn (InventoryStockLevel $level): bool => $level->is_low_stock);

        return view('inventory::admin.index', [
            'tenant' => $tenant,
            'tenants' => $tenants,
            'isPlatformAdmin' => $user->is_platform_admin,
            'branches' => Branch::query()->where('tenant_id', $tenant->id)->orderByDesc('is_primary')->orderBy('name')->get(),
            'locations' => $locations,
            'variants' => $variants,
            'stockLevels' => $stockLevels,
            'movements' => $movements,
            'batches' => $batches,
            'lowStock' => $lowStock,
            'expiringBatches' => $batches->filter(fn (InventoryBatch $batch): bool => $batch->expiry_date && $batch->expiry_date->between(now(), now()->addDays(30))),
            'conditionBatches' => $batches->filter(fn (InventoryBatch $batch): bool => $batch->stock_condition !== StockCondition::Sellable),
            'locationTypes' => InventoryLocationType::options(),
            'movementTypes' => InventoryMovementType::options(),
            'stockConditions' => StockCondition::options(),
            'stats' => [
                'on_hand' => $stockLevels->sum('quantity_on_hand'),
                'available' => $stockLevels->sum(fn (InventoryStockLevel $level): int => $level->quantity_available),
                'low_stock' => $lowStock->count(),
                'valuation_minor' => $stockLevels->sum(fn (InventoryStockLevel $level): int => $level->stock_value_minor),
            ],
        ]);
    }

    public function storeLocation(InventoryLocationRequest $request): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());

        $location = InventoryLocation::query()->create($request->validated());

        return redirect()
            ->to(route('admin.inventory.index', ['tenant' => $location->tenant_id]).'#locations')
            ->with('status', "Inventory location {$location->name} created.");
    }

    public function storeMovement(InventoryMovementRequest $request, PostInventoryMovementAction $action): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());

        $action->execute($request->validated());

        return redirect()
            ->to(route('admin.inventory.index', ['tenant' => $request->string('tenant_id')->toString()]).'#movements')
            ->with('status', 'Inventory movement posted.');
    }

    public function saveReorder(ReorderSettingRequest $request): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());

        InventoryStockLevel::query()->firstOrCreate([
            'tenant_id' => $request->string('tenant_id')->toString(),
            'inventory_location_id' => $request->integer('inventory_location_id'),
            'product_variant_id' => $request->integer('product_variant_id'),
        ])->update([
            'reorder_level' => $request->integer('reorder_level'),
            'reorder_quantity' => $request->integer('reorder_quantity'),
        ]);

        return redirect()
            ->to(route('admin.inventory.index', ['tenant' => $request->string('tenant_id')->toString()]).'#reorder')
            ->with('status', 'Reorder settings saved.');
    }

    private function ensureBranchLocations(Tenant $tenant): void
    {
        Branch::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->each(function (Branch $branch) use ($tenant): void {
                InventoryLocation::query()->firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'branch_id' => $branch->id,
                ], [
                    'name' => $branch->name,
                    'code' => $branch->code,
                    'location_type' => InventoryLocationType::Branch->value,
                    'status' => 'active',
                ]);
            });
    }

    /**
     * @return EloquentCollection<int, Tenant>
     */
    private function visibleTenantsFor(User $user): EloquentCollection
    {
        if ($user->is_platform_admin) {
            return Tenant::query()->orderBy('name')->get();
        }

        return Tenant::query()
            ->whereHas('memberships', fn ($query) => $query->where('user_id', $user->id)->where('status', MembershipStatus::Active->value))
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  EloquentCollection<int, Tenant>  $visibleTenants
     */
    private function resolveTenant(Request $request, EloquentCollection $visibleTenants): ?Tenant
    {
        $tenantId = $request->string('tenant')->toString();

        if ($tenantId !== '') {
            abort_unless($visibleTenants->contains('id', $tenantId), 403);

            return Tenant::query()->find($tenantId);
        }

        return $visibleTenants->first();
    }

    private function authorizeTenantIdAccess(?User $user, string $tenantId): void
    {
        abort_unless($user instanceof User, 403);

        if ($user->is_platform_admin) {
            return;
        }

        abort_unless(
            TenantMembership::query()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $user->id)
                ->where('status', MembershipStatus::Active->value)
                ->exists(),
            403,
        );
    }
}
