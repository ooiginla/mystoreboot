<?php

declare(strict_types=1);

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Sales\Actions\SaveModifierGroupAction;
use Modules\Sales\Http\Controllers\Concerns\ResolvesRestaurantTenant;
use Modules\Sales\Models\ModifierGroup;

/**
 * 6c — modifier groups ("Spice level", "Extras", "Remove") and the menu items that offer
 * them. Set a group up once and attach it to every dish it applies to.
 */
final class ModifierController extends Controller
{
    use ResolvesRestaurantTenant;

    public function index(Request $request): View
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);

        $ingredients = ProductVariant::query()
            ->with('product.unitCategory.units')
            ->where('tenant_id', $tenant->id)
            ->whereHas('product', fn ($q) => $q->whereIn(
                'product_type',
                array_map(fn (ProductType $t): string => $t->value, ProductType::stockable()),
            ))
            ->get()
            ->sortBy(fn (ProductVariant $v): string => strtolower((string) $v->product?->name))
            ->values();

        return view('sales::admin.restaurant.modifiers', [
            'tenant' => $tenant,
            'tenants' => $tenants,
            'isPlatformAdmin' => $user->is_platform_admin,
            'groups' => ModifierGroup::query()
                ->with(['options.componentVariant.product', 'options.componentUnit', 'products'])
                ->where('tenant_id', $tenant->id)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'products' => Product::query()
                ->where('tenant_id', $tenant->id)
                ->where('product_type', ProductType::Product->value)
                ->orderBy('name')
                ->get(['id', 'name']),
            'ingredients' => $ingredients,
            // Units each ingredient can be measured in, for the "how much" field.
            'ingredientUnits' => $ingredients->mapWithKeys(fn (ProductVariant $v): array => [
                $v->id => ($v->product?->unitCategory?->units ?? collect())
                    ->filter(fn ($unit): bool => $unit->isConvertible())
                    ->map(fn ($unit): array => ['id' => $unit->id, 'code' => $unit->code])
                    ->values()
                    ->all(),
            ])->all(),
        ]);
    }

    public function store(Request $request, SaveModifierGroupAction $action): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);

        $group = $action->execute($tenant->id, $this->validated($request));

        return redirect()->to($this->indexUrl($request))->with('status', "{$group->name} saved.");
    }

    public function update(Request $request, ModifierGroup $group, SaveModifierGroupAction $action): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);
        abort_unless($group->tenant_id === $tenant->id, 403);

        $action->execute($tenant->id, $this->validated($request), $group);

        return redirect()->to($this->indexUrl($request))->with('status', "{$group->name} updated.");
    }

    public function destroy(Request $request, ModifierGroup $group): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);
        abort_unless($group->tenant_id === $tenant->id, 403);

        $name = $group->name;
        // Bills keep their own copy of what was chosen, so nothing already rung up changes.
        $group->delete();

        return redirect()->to($this->indexUrl($request))->with('status', "{$name} removed from the menu.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'is_required' => ['nullable', 'boolean'],
            'min_select' => ['nullable', 'integer', 'min:0', 'max:20'],
            'max_select' => ['nullable', 'integer', 'min:1', 'max:20'],
            'options' => ['required', 'array', 'min:1', 'max:40'],
            'options.*.id' => ['nullable', 'integer'],
            'options.*.name' => ['nullable', 'string', 'max:120'],
            'options.*.price' => ['nullable', 'numeric', 'min:-99999999', 'max:99999999'],
            'options.*.component_product_variant_id' => ['nullable', 'integer'],
            'options.*.component_quantity' => ['nullable', 'numeric', 'min:-99999', 'max:99999'],
            'options.*.component_unit_id' => ['nullable', 'integer'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer'],
        ]);
    }

    private function indexUrl(Request $request): string
    {
        $tenantId = $request->string('tenant')->toString();

        return route('admin.sales.restaurant.modifiers.index', $tenantId !== '' ? ['tenant' => $tenantId] : []);
    }
}
