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
use Modules\Inventory\Actions\EnsureInventoryLocationsAction;
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
    public function index(Request $request, EnsureInventoryLocationsAction $inventoryLocations): View
    {
        /** @var User $user */
        $user = $request->user();
        $tenants = $this->visibleTenantsFor($user);
        $tenant = $this->resolveTenant($request, $tenants);

        abort_if(! $tenant, 403);

        $inventoryLocations->forTenant($tenant);

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

    public function storeMovement(
        InventoryMovementRequest $request,
        PostInventoryMovementAction $action,
        \Modules\Access\Support\ApprovalService $approvals,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $tenantId = $request->string('tenant_id')->toString();
        $this->authorizeTenantIdAccess($user, $tenantId);

        $data = $request->validated();
        $redirect = route('admin.inventory.index', ['tenant' => $tenantId]).'#movements';

        $isAdjustment = in_array($data['movement_type'], [
            InventoryMovementType::AdjustmentIn->value,
            InventoryMovementType::AdjustmentOut->value,
        ], true);

        if ($isAdjustment && ! $user->is_platform_admin) {
            $tenant = Tenant::query()->findOrFail($tenantId);

            // Adjustments specifically require the adjust permission (route allows any movement perm).
            abort_unless($user->hasPermission($tenant, 'inventory.adjust'), 403, 'You do not have permission to adjust stock.');

            $valueMinor = $this->estimateAdjustmentValueMinor($tenant, $data);
            $limit = $user->permissionLimit($tenant, 'inventory.adjustment.max_minor');
            $overLimit = $limit !== null && $valueMinor > (int) $limit;

            // Divert to approval when the tenant requires it and the user cannot self-approve,
            // OR when a directly-acting user exceeds their limit and approval is available.
            $mustDivert = $approvals->shouldDivert($tenant, $user, 'inventory_adjustment', 'inventory.adjustments.approve')
                || ($overLimit && $approvals->requiresApproval($tenant, 'inventory_adjustment'));

            if ($mustDivert) {
                $variant = $request->variant();
                $approvals->create($tenant, $user, 'inventory_adjustment', 'Stock adjustment · '.($variant?->name ?? 'item'), [
                    'branch_id' => $this->branchIdForLocation($tenant, (int) $data['inventory_location_id']),
                    'amount_minor' => $valueMinor,
                    'payload' => $data,
                    'description' => sprintf('%s of %d unit(s)', $data['movement_type'] === InventoryMovementType::AdjustmentIn->value ? 'Increase' : 'Decrease', (int) $data['quantity']),
                    'request_note' => $data['notes'] ?? null,
                ]);

                return redirect()->to($redirect)->with('status', 'This stock adjustment has been sent for approval.');
            }

            // Acting directly (can self-approve or approvals off): enforce the limit as a hard cap.
            if ($overLimit) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'quantity' => sprintf('This adjustment is worth %s, above your limit of %s.', $tenant->currency_code.' '.number_format($valueMinor / 100, 2), $tenant->currency_code.' '.number_format((int) $limit / 100, 2)),
                ]);
            }
        }

        $action->execute($data);

        return redirect()->to($redirect)->with('status', 'Inventory movement posted.');
    }

    /**
     * Estimate an adjustment's value from the on-hand average cost (adjustments carry no unit cost).
     */
    private function estimateAdjustmentValueMinor(Tenant $tenant, array $data): int
    {
        $stock = InventoryStockLevel::query()
            ->where('tenant_id', $tenant->id)
            ->where('inventory_location_id', (int) $data['inventory_location_id'])
            ->where('product_variant_id', (int) $data['product_variant_id'])
            ->first();

        $unitCostMinor = (int) ($stock->average_cost_minor ?? 0);

        return abs((int) $data['quantity']) * $unitCostMinor;
    }

    private function branchIdForLocation(Tenant $tenant, int $locationId): ?int
    {
        return InventoryLocation::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($locationId)
            ->value('branch_id');
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
