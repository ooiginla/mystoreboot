<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Access\Enums\MembershipStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Models\ProductVariant;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Inventory\Support\ReorderLevels;
use Modules\Tenancy\Models\Tenant;

/**
 * Bulk reorder-level setup for one location: every stockable item in one table, so
 * a store can be configured in one sitting instead of one dialog per item. Saving
 * posts to the same endpoint as the per-item dialog.
 */
final class ReorderLevelController extends Controller
{
    public function index(Request $request): View
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);

        $locations = InventoryLocation::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        $location = $locations->firstWhere('id', $request->integer('location')) ?? $locations->first();

        $typeOptions = collect(ProductType::stockable())
            ->mapWithKeys(fn (ProductType $type): array => [$type->value => $type->label()])
            ->all();
        $type = $request->string('type')->toString();
        $type = array_key_exists($type, $typeOptions) ? $type : '';

        $status = $request->string('status')->toString();
        $status = in_array($status, ['low', 'monitored', 'unset'], true) ? $status : '';

        $search = trim($request->string('q')->toString());

        $levels = $location
            ? InventoryStockLevel::query()
                ->where('tenant_id', $tenant->id)
                ->where('inventory_location_id', $location->id)
                ->get()
                ->keyBy('product_variant_id')
            : collect();

        $rows = ProductVariant::query()
            ->with(['product.unitCategory.units', 'baseUnit'])
            ->where('tenant_id', $tenant->id)
            ->whereHas('product', fn ($query) => $query->whereIn(
                'product_type',
                $type !== '' ? [$type] : array_keys($typeOptions),
            ))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('sku', 'like', "%{$search}%")
                ->orWhere('variant_name', 'like', "%{$search}%")
                ->orWhereHas('product', fn ($product) => $product->where('name', 'like', "%{$search}%"))))
            ->get()
            ->map(function (ProductVariant $variant) use ($levels): array {
                /** @var InventoryStockLevel|null $stock */
                $stock = $levels->get($variant->id);
                $level = (float) ($stock?->reorder_level ?? 0);

                return [
                    'variant' => $variant,
                    'units' => ReorderLevels::unitsOf($variant),
                    'level' => $level,
                    'qty' => (float) ($stock?->reorder_quantity ?? 0),
                    'available' => (float) ($stock?->quantity_available ?? 0),
                    'status' => $level <= 0 ? 'unset' : ($stock->is_low_stock ? 'low' : 'ok'),
                ];
            })
            ->when($status !== '', fn (Collection $rows) => $rows->filter(
                fn (array $row): bool => $status === 'monitored' ? $row['status'] !== 'unset' : $row['status'] === $status,
            ))
            ->sortBy(fn (array $row): string => Str::lower(($row['variant']->product?->name ?? '').' '.$row['variant']->variant_name))
            ->values();

        return view('inventory::admin.reorder-levels.index', [
            'tenant' => $tenant,
            'tenants' => $tenants,
            'isPlatformAdmin' => $user->is_platform_admin,
            'locations' => $locations,
            'location' => $location,
            'typeOptions' => $typeOptions,
            'type' => $type,
            'status' => $status,
            'search' => $search,
            'rows' => $rows,
            'lowCount' => $levels->filter(fn (InventoryStockLevel $stock): bool => $stock->is_low_stock)->count(),
        ]);
    }

    private function tenantFromRequest(Request $request, ?EloquentCollection &$tenants, ?User &$user): Tenant
    {
        /** @var User $user */
        $user = $request->user();
        $tenants = $this->visibleTenantsFor($user);

        $tenantId = $request->string('tenant')->toString();
        if ($tenantId !== '') {
            abort_unless($tenants->contains('id', $tenantId), 403);
            $tenant = Tenant::query()->find($tenantId);
        } else {
            $tenant = $tenants->first();
        }

        abort_if(! $tenant, 403);

        return $tenant;
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
}
