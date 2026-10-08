<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Access\Enums\MembershipStatus;
use Modules\Access\Models\TenantMembership;
use Modules\Access\Support\ApprovalService;
use Modules\Business\Models\Branch;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Models\ProductVariant;
use Modules\Inventory\Actions\EnsureInventoryLocationsAction;
use Modules\Inventory\Actions\EnsureLocationTypesAction;
use Modules\Inventory\Actions\PostInventoryMovementAction;
use Modules\Inventory\Actions\SaveReorderLevelsAction;
use Modules\Inventory\Enums\InventoryMovementType;
use Modules\Inventory\Enums\StockCondition;
use Modules\Inventory\Http\Requests\InventoryLocationRequest;
use Modules\Inventory\Http\Requests\InventoryMovementRequest;
use Modules\Inventory\Http\Requests\ReorderSettingRequest;
use Modules\Inventory\Models\InventoryBatch;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Inventory\Models\InventoryMovement;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Inventory\Models\LocationType;
use Modules\Inventory\Models\UnitOfMeasure;
use Modules\Inventory\Support\Quantity;
use Modules\Inventory\Support\ReorderLevels;
use Modules\Inventory\Support\UnitConverter;
use Modules\Subscriptions\Support\TenantModuleAccess;
use Modules\Tenancy\Models\Tenant;

final class InventoryController extends Controller
{
    public function index(
        Request $request,
        EnsureInventoryLocationsAction $inventoryLocations,
        TenantModuleAccess $moduleAccess,
    ): View {
        /** @var User $user */
        $user = $request->user();
        $tenants = $this->visibleTenantsFor($user);
        $tenant = $this->resolveTenant($request, $tenants);

        abort_if(! $tenant, 403);

        $inventoryLocations->forTenant($tenant);
        app(EnsureLocationTypesAction::class)->forTenant($tenant->id);

        $locationTypeOptions = LocationType::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->orderByDesc('is_system')
            ->orderBy('label')
            ->get(['id', 'key', 'label', 'is_system']);

        $locations = InventoryLocation::query()
            ->with('branch')
            ->where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get();

        $requestedStockLocationId = $request->integer('stock_location');
        $selectedStockLocationId = $locations->contains(
            fn (InventoryLocation $location): bool => $location->id === $requestedStockLocationId,
        ) ? $requestedStockLocationId : null;
        $stockProductSearch = trim($request->string('stock_product')->toString());

        $variants = ProductVariant::query()
            ->with(['product.category', 'product.unitCategory.units', 'optionValues.option', 'baseUnit'])
            ->where('tenant_id', $tenant->id)
            ->whereHas('product', fn ($query) => $query->whereIn('product_type', array_map(fn (ProductType $t) => $t->value, ProductType::stockable())))
            ->orderBy('sku')
            ->get();

        $allStockLevels = InventoryStockLevel::query()
            ->with(['location.branch', 'variant.baseUnit', 'variant.product.category', 'variant.optionValues.option'])
            ->where('tenant_id', $tenant->id)
            ->latest('last_movement_at')
            ->get();

        $stockLevels = $allStockLevels;

        if ($stockProductSearch !== '') {
            $needle = Str::lower($stockProductSearch);
            $stockLevels = $stockLevels->filter(function (InventoryStockLevel $level) use ($needle): bool {
                $variant = $level->variant;
                $haystack = Str::lower(implode(' ', [
                    $variant?->product?->name,
                    $variant?->variant_name,
                    $variant?->sku,
                    $variant?->barcode,
                    ($variant?->product?->name ?? 'Item').' / '.($variant?->variant_name ?? '').' ('.($variant?->sku ?? '').')',
                ]));

                return Str::contains($haystack, $needle);
            });
        }

        if ($selectedStockLocationId) {
            $stockLevels = $stockLevels->where('inventory_location_id', $selectedStockLocationId);
        }

        $stockLevels = $stockLevels->values();

        $movements = InventoryMovement::query()
            ->with(['location.branch', 'destinationLocation.branch', 'variant.product', 'variant.baseUnit', 'enteredUnit', 'batchAllocations.batch'])
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

        $lowStock = $allStockLevels->filter(fn (InventoryStockLevel $level): bool => $level->is_low_stock);

        return view('inventory::admin.index', [
            'tenant' => $tenant,
            'tenants' => $tenants,
            'isPlatformAdmin' => $user->is_platform_admin,
            'branches' => Branch::query()->where('tenant_id', $tenant->id)->orderByDesc('is_primary')->orderBy('name')->get(),
            'locations' => $locations,
            'selectedStockLocationId' => $selectedStockLocationId,
            'stockProductSearch' => $stockProductSearch,
            'variants' => $variants,
            'stockLevels' => $stockLevels,
            'movements' => $movements,
            'batches' => $batches,
            'lowStock' => $lowStock,
            'expiringBatches' => $batches->filter(fn (InventoryBatch $batch): bool => $batch->expiry_date && $batch->expiry_date->between(now(), now()->addDays(30))),
            'conditionBatches' => $batches->filter(fn (InventoryBatch $batch): bool => $batch->stock_condition !== StockCondition::Sellable),
            'locationTypes' => $locationTypeOptions->pluck('label', 'key')->all(),
            'locationTypeRows' => $locationTypeOptions,
            // Prep stations only mean something to a kitchen, so the panel is shown to
            // tenants on either of the two modules that route by them.
            'hasPrepStations' => $moduleAccess->allows($tenant, 'fnb') || $moduleAccess->allows($tenant, 'restaurant'),
            'prepStations' => $locations->filter(
                fn (InventoryLocation $location): bool => (bool) $location->is_prep_station,
            )->values(),
            'reorderUnits' => $variants->mapWithKeys(fn (ProductVariant $variant): array => [
                $variant->id => ReorderLevels::unitsOf($variant),
            ])->all(),
            'reorderLevels' => ReorderLevels::levelsFor($tenant->id),
            'movementTypes' => InventoryMovementType::options(),
            'stockConditions' => StockCondition::options(),
            'stats' => [
                'on_hand' => $allStockLevels->sum('quantity_on_hand'),
                'available' => $allStockLevels->sum(fn (InventoryStockLevel $level): float => (float) $level->quantity_available),
                'low_stock' => $lowStock->count(),
                'valuation_minor' => $allStockLevels->sum(fn (InventoryStockLevel $level): int => $level->stock_value_minor),
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

    public function updateLocation(Request $request, InventoryLocation $location): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), (string) $location->tenant_id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:140'],
            'code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('inventory_locations', 'code')
                    ->where('tenant_id', $location->tenant_id)
                    ->ignore($location->id),
            ],
            'location_type' => [
                'required', 'string', 'max:40',
                Rule::exists('location_types', 'key')->where('tenant_id', $location->tenant_id),
            ],
        ]);

        $location->update([
            'name' => $validated['name'],
            'code' => filled($validated['code'] ?? null) ? $validated['code'] : null,
            'location_type' => $validated['location_type'],
            'is_sellable_point' => $request->boolean('is_sellable_point'),
            'is_prep_station' => $request->boolean('is_prep_station'),
        ]);

        return redirect()
            ->to(route('admin.inventory.index', ['tenant' => $location->tenant_id]).'#locations')
            ->with('status', "{$location->name} updated.");
    }

    public function storeMovement(
        InventoryMovementRequest $request,
        PostInventoryMovementAction $action,
        ApprovalService $approvals,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $tenantId = $request->string('tenant_id')->toString();
        $this->authorizeTenantIdAccess($user, $tenantId);

        $data = $request->validated();
        $redirect = route('admin.inventory.index', ['tenant' => $tenantId]).'#movements';
        $tenant = Tenant::query()->findOrFail($tenantId);
        $items = $data['items'] ?? [[
            'product_variant_id' => $data['product_variant_id'],
            'movement_type' => $data['movement_type'],
            'quantity' => $data['quantity'],
            'unit_id' => $data['unit_id'] ?? null,
            'total_cost' => $data['total_cost'] ?? null,
        ]];
        unset($data['items'], $data['product_variant_id'], $data['quantity'], $data['unit_id'], $data['total_cost']);

        $movements = collect($items)->map(function (array $item, int $index) use ($data, $tenantId): array {
            $item['movement_type'] ??= $data['movement_type'] ?? null;

            return $this->prepareMovementData($tenantId, [...$data, ...$item], "items.{$index}");
        })->all();

        [$posted, $sentForApproval] = DB::transaction(function () use ($movements, $tenant, $user, $action, $approvals): array {
            $posted = 0;
            $sentForApproval = 0;

            foreach ($movements as $movement) {
                if ($this->divertAdjustmentIfRequired($tenant, $user, $movement, $approvals)) {
                    $sentForApproval++;

                    continue;
                }

                $action->execute($movement);
                $posted++;
            }

            return [$posted, $sentForApproval];
        });

        $status = match (true) {
            $posted > 0 && $sentForApproval > 0 => "{$posted} movement line(s) posted; {$sentForApproval} adjustment line(s) sent for approval.",
            $sentForApproval > 0 => "{$sentForApproval} stock adjustment line(s) sent for approval.",
            default => "{$posted} inventory movement line(s) posted.",
        };

        return redirect()->to($redirect)->with('status', $status);
    }

    /**
     * Convert one user-entered row to the base-unit movement understood by the ledger.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareMovementData(string $tenantId, array $data, string $errorPrefix): array
    {
        $variant = ProductVariant::query()
            ->with(['product.unitCategory.units', 'baseUnit'])
            ->where('tenant_id', $tenantId)
            ->findOrFail((int) $data['product_variant_id']);
        $enteredQuantity = (float) $data['quantity'];
        $enteredUnit = null;

        if (! empty($data['unit_id'])) {
            $enteredUnit = UnitOfMeasure::query()
                ->where('tenant_id', $tenantId)
                ->find((int) $data['unit_id']);
            $allowedUnitIds = collect(ReorderLevels::unitsOf($variant))->pluck('id')->filter()->map(fn ($id): int => (int) $id);

            if (! $enteredUnit || ! $allowedUnitIds->contains($enteredUnit->id)) {
                throw ValidationException::withMessages([
                    "{$errorPrefix}.unit_id" => 'Choose a measurement unit configured for this item.',
                ]);
            }

            if ($enteredUnit->isConvertible()) {
                $data['quantity'] = app(UnitConverter::class)->toBase($enteredQuantity, $enteredUnit);
            }
        }

        $data['entered_quantity'] = $enteredQuantity;
        $data['entered_unit_id'] = $enteredUnit?->id;
        $data['entered_unit_code'] = $enteredUnit?->code ?? $variant->baseUnit?->code ?? 'pc';

        if (array_key_exists('total_cost', $data) && $data['total_cost'] !== null) {
            $movementValueMinor = (int) round((float) $data['total_cost'] * 100);
            $data['movement_value_minor'] = $movementValueMinor;
            $data['unit_cost_minor'] = (int) round($movementValueMinor / (float) $data['quantity']);
        }

        if ($data['movement_type'] === InventoryMovementType::Damaged->value) {
            $data['stock_condition'] = StockCondition::Damaged->value;
        }

        unset($data['total_cost'], $data['unit_id']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function divertAdjustmentIfRequired(
        Tenant $tenant,
        User $user,
        array $data,
        ApprovalService $approvals,
    ): bool {
        $isAdjustment = in_array($data['movement_type'], [
            InventoryMovementType::AdjustmentIn->value,
            InventoryMovementType::AdjustmentOut->value,
        ], true);

        if (! $isAdjustment || $user->is_platform_admin) {
            return false;
        }

        abort_unless($user->hasPermission($tenant, 'inventory.adjust'), 403, 'You do not have permission to adjust stock.');

        $valueMinor = $this->estimateAdjustmentValueMinor($tenant, $data);
        $limit = $user->permissionLimit($tenant, 'inventory.adjustment.max_minor');
        $overLimit = $limit !== null && $valueMinor > (int) $limit;
        $mustDivert = $approvals->shouldDivert($tenant, $user, 'inventory_adjustment', 'inventory.adjustments.approve')
            || ($overLimit && $approvals->requiresApproval($tenant, 'inventory_adjustment'));

        if ($mustDivert) {
            $variant = ProductVariant::query()->with('product')->find((int) $data['product_variant_id']);
            $approvals->create($tenant, $user, 'inventory_adjustment', 'Stock adjustment · '.($variant?->product?->name ?? 'item'), [
                'branch_id' => $this->branchIdForLocation($tenant, (int) $data['inventory_location_id']),
                'amount_minor' => $valueMinor,
                'payload' => $data,
                'description' => sprintf('%s of %s unit(s)', $data['movement_type'] === InventoryMovementType::AdjustmentIn->value ? 'Increase' : 'Decrease', Quantity::format((float) $data['quantity'])),
                'request_note' => $data['notes'] ?? null,
            ]);

            return true;
        }

        if ($overLimit) {
            throw ValidationException::withMessages([
                'quantity' => sprintf('This adjustment is worth %s, above your limit of %s.', $tenant->currency_code.' '.number_format($valueMinor / 100, 2), $tenant->currency_code.' '.number_format((int) $limit / 100, 2)),
            ]);
        }

        return false;
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

        return (int) round(abs((float) $data['quantity']) * $unitCostMinor);
    }

    private function branchIdForLocation(Tenant $tenant, int $locationId): ?int
    {
        return InventoryLocation::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($locationId)
            ->value('branch_id');
    }

    /**
     * Shared by the per-item dialog (Inventory, Raw Materials, Products) and the bulk
     * per-location page, so it returns to whichever screen posted it.
     */
    public function saveReorder(ReorderSettingRequest $request, SaveReorderLevelsAction $action): RedirectResponse
    {
        $tenantId = $request->string('tenant_id')->toString();
        $this->authorizeTenantIdAccess($request->user(), $tenantId);

        $saved = $action->execute($tenantId, $request->validated('rows'));
        $fragment = $request->validated('fragment');

        return redirect()
            ->to(url()->previous(route('admin.inventory.index', ['tenant' => $tenantId])).($fragment ? '#'.$fragment : ''))
            ->with('status', match ($saved) {
                0 => 'No reorder levels changed.',
                1 => 'Reorder level saved.',
                default => "{$saved} reorder levels saved.",
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
