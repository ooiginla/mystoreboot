<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Access\Enums\MembershipStatus;
use Modules\Catalog\Actions\SaveProductAction;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Enums\TaxBehavior;
use Modules\Catalog\Http\Requests\RawMaterialRequest;
use Modules\Catalog\Models\Product;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Inventory\Models\UnitCategory;
use Modules\Inventory\Support\ReorderLevels;
use Modules\Tenancy\Models\Tenant;

final class RawMaterialController extends Controller
{
    public function index(Request $request): View
    {
        [$tenant, $tenants, $user] = $this->context($request);

        $materials = Product::query()
            ->with(['variants', 'unitCategory.units'])
            ->where('tenant_id', $tenant->id)
            ->where('product_type', ProductType::RawMaterial->value)
            ->orderByDesc('id')
            ->get();

        $onHand = InventoryStockLevel::query()
            ->where('tenant_id', $tenant->id)
            ->get(['product_variant_id', 'quantity_on_hand'])
            ->groupBy('product_variant_id')
            ->map(fn (Collection $rows): float => (float) $rows->sum('quantity_on_hand'));

        return view('catalog::admin.raw-materials.index', [
            'tenant' => $tenant,
            'tenants' => $tenants,
            'isPlatformAdmin' => $user->is_platform_admin,
            'materials' => $materials,
            'onHand' => $onHand,
            'unitCategories' => UnitCategory::query()->where('tenant_id', $tenant->id)->orderByDesc('is_default')->orderBy('name')->get(),
            'reorderLocations' => InventoryLocation::query()->where('tenant_id', $tenant->id)->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'reorderUnits' => ReorderLevels::unitsFor($tenant->id),
            'reorderLevels' => ReorderLevels::levelsFor($tenant->id),
            'canManageReorder' => $user->is_platform_admin || $user->hasPermission($tenant, 'inventory.manage'),
        ]);
    }

    public function store(RawMaterialRequest $request, SaveProductAction $action): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());

        $action->execute($this->data($request));

        return redirect()->to($this->redirectTo($request))->with('status', 'Raw material saved.');
    }

    public function update(RawMaterialRequest $request, Product $product, SaveProductAction $action): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), (string) $product->tenant_id);
        abort_unless($product->product_type === ProductType::RawMaterial, 404);

        $action->execute($this->data($request, $product), $product);

        return redirect()->to($this->redirectTo($request))->with('status', 'Raw material updated.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), (string) $product->tenant_id);
        abort_unless($product->product_type === ProductType::RawMaterial, 404);

        $product->delete();

        return redirect()->to($this->redirectTo($request))->with('status', 'Raw material removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function data(RawMaterialRequest $request, ?Product $product = null): array
    {
        $name = (string) $request->string('name');

        return [
            'tenant_id' => $request->string('tenant_id')->toString(),
            'name' => $name,
            'slug' => $product?->slug ?? Str::slug($name).'-'.Str::lower(Str::random(6)),
            'product_type' => ProductType::RawMaterial->value,
            'has_variants' => false,
            'track_inventory' => true,
            'tax_behavior' => TaxBehavior::Exempt->value,
            'status' => ProductStatus::Active->value,
            'base_price' => 0,
            'sku' => $request->input('sku'),
            'category_id' => $request->input('category_id'),
            'unit_category_id' => $request->input('unit_category_id'),
        ];
    }

    private function redirectTo(Request $request): string
    {
        $tenantId = $request->string('tenant')->toString() ?: $request->string('tenant_id')->toString();

        return route('admin.catalog.raw-materials.index', $tenantId !== '' ? ['tenant' => $tenantId] : []);
    }

    /**
     * @return array{0: Tenant, 1: EloquentCollection<int, Tenant>, 2: User}
     */
    private function context(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();
        $tenants = $this->visibleTenantsFor($user);
        $tenantId = $request->string('tenant')->toString();

        $tenant = $tenantId !== ''
            ? (abort_unless($tenants->contains('id', $tenantId), 403) ?? Tenant::query()->find($tenantId))
            : $tenants->first();

        abort_if(! $tenant, 403);

        return [$tenant, $tenants, $user];
    }

    private function authorizeTenantIdAccess(?User $user, string $tenantId): void
    {
        abort_unless($user instanceof User, 403);

        if ($user->is_platform_admin) {
            return;
        }

        abort_unless(
            Tenant::query()->where('id', $tenantId)
                ->whereHas('memberships', fn ($q) => $q->where('user_id', $user->id)->where('status', MembershipStatus::Active->value))
                ->exists(),
            403,
        );
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
