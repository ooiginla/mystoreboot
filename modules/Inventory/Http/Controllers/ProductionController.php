<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Modules\Access\Enums\MembershipStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Enums\StockPolicy;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Inventory\Actions\RecordProductionAction;
use Modules\Inventory\Enums\UnitDimension;
use Modules\Inventory\Http\Requests\ProductionRequest;
use Modules\Inventory\Http\Requests\RecipeRequest;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Inventory\Models\ProductionOrder;
use Modules\Inventory\Models\Recipe;
use Modules\Inventory\Models\UnitCategory;
use Modules\Inventory\Models\UnitOfMeasure;
use Modules\Inventory\Support\Quantity;
use Modules\Inventory\Support\UnitConverter;
use Modules\Tenancy\Models\Tenant;

final class ProductionController extends Controller
{
    public function __construct(private readonly UnitConverter $converter) {}

    /** Finished-products listing. */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $tenants = $this->visibleTenantsFor($user);
        $tenant = $this->resolveTenant($request, $tenants);
        abort_if(! $tenant, 403);

        $finished = Product::query()
            ->with('variants')
            ->where('tenant_id', $tenant->id)
            ->where('is_finished_product', true)
            ->orderBy('name')
            ->get();

        $variantIds = $finished->map(fn (Product $p): ?int => $p->variants->first()?->id)->filter()->values();
        $recipeVariantIds = Recipe::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->whereIn('output_product_variant_id', $variantIds)
            ->pluck('output_product_variant_id')
            ->all();

        // Items that can be turned into finished products (stockable, not already finished).
        $addable = ProductVariant::query()
            ->with('product')
            ->where('tenant_id', $tenant->id)
            ->whereHas('product', fn ($q) => $q
                ->whereIn('product_type', array_map(fn (ProductType $t) => $t->value, ProductType::stockable()))
                ->where('is_finished_product', false))
            ->orderBy('sku')
            ->get();

        return view('inventory::admin.production.index', [
            'tenant' => $tenant,
            'tenants' => $tenants,
            'isPlatformAdmin' => $user->is_platform_admin,
            'finished' => $finished,
            'recipeVariantIds' => $recipeVariantIds,
            'jsAddable' => $addable->map(fn ($v): array => [
                'id' => $v->id,
                'label' => ($v->product?->name ?? 'Item').' / '.$v->variant_name.' ('.$v->sku.')',
                'type' => $v->product?->product_type?->value,
            ])->values()->all(),
        ]);
    }

    /** Per-finished-product detail: recipe, margins, production history. */
    public function show(Request $request, Product $product): View
    {
        $tenant = $this->tenantFromRequest($request);
        abort_unless($product->tenant_id === $tenant->id && $product->is_finished_product, 404);

        $variant = $product->variants()->oldest('id')->first();
        abort_if(! $variant, 404);

        $recipe = Recipe::query()
            ->with(['items.componentVariant.product', 'items.unit', 'yieldUnit', 'prepStation'])
            ->where('tenant_id', $tenant->id)
            ->where('output_product_variant_id', $variant->id)
            ->where('is_active', true)
            ->latest('version')
            ->first();

        $costMap = InventoryStockLevel::query()
            ->where('tenant_id', $tenant->id)
            ->get(['product_variant_id', 'average_cost_minor'])
            ->groupBy('product_variant_id')
            ->map(fn (Collection $rows): int => (int) $rows->max('average_cost_minor'));

        $recipeCostMinor = $recipe ? $this->recipeCostMinor($recipe, $costMap) : 0;
        $yield = $recipe ? max(0.0001, (float) $recipe->yield_quantity) : 1.0;
        $unitCostMinor = (int) round($recipeCostMinor / $yield);
        $sellMinor = (int) ($variant->selling_price_minor ?: $product->base_price_minor ?: 0);

        $variants = ProductVariant::query()
            ->with('product')
            ->where('tenant_id', $tenant->id)
            ->whereHas('product', fn ($q) => $q->whereIn('product_type', array_map(fn (ProductType $t) => $t->value, ProductType::stockable())))
            ->orderBy('sku')
            ->get();

        $units = UnitOfMeasure::query()->where('tenant_id', $tenant->id)->orderBy('dimension')->orderBy('code')->get();

        return view('inventory::admin.production.show', [
            'tenant' => $tenant,
            'tenants' => $this->visibleTenantsFor($request->user()),
            'isPlatformAdmin' => $request->user()->is_platform_admin,
            'product' => $product,
            'variant' => $variant,
            'recipe' => $recipe,
            'recipeCostMinor' => $recipeCostMinor,
            'margin' => [
                'unit_cost_minor' => $unitCostMinor,
                'sell_minor' => $sellMinor,
                'margin_minor' => $sellMinor - $unitCostMinor,
                'food_cost_pct' => $sellMinor > 0 ? round($unitCostMinor / $sellMinor * 100, 1) : null,
                'margin_pct' => $sellMinor > 0 ? round(($sellMinor - $unitCostMinor) / $sellMinor * 100, 1) : null,
            ],
            'orders' => ProductionOrder::query()
                ->with(['sourceLocation', 'outputLocation'])
                ->where('tenant_id', $tenant->id)
                ->where('output_product_variant_id', $variant->id)
                ->latest('produced_at')
                ->limit(50)
                ->get(),
            'locations' => InventoryLocation::query()->where('tenant_id', $tenant->id)->orderBy('name')->get(),
            'prepStations' => InventoryLocation::query()->where('tenant_id', $tenant->id)->where('is_prep_station', true)->orderBy('name')->get(),
            'unitCategories' => $unitCategories = UnitCategory::query()->with('units')->where('tenant_id', $tenant->id)->orderByDesc('is_default')->orderBy('name')->get(),
            'unitDimensions' => UnitDimension::options(),
            'outputCategoryId' => $product->unit_category_id,
            'jsUnits' => $units->map(fn ($u): array => ['id' => $u->id, 'code' => $u->code, 'cat' => $u->unit_category_id])->values()->all(),
            'jsItems' => $variants->map(fn ($v): array => [
                'id' => $v->id,
                'label' => ($v->product?->name ?? 'Item').' / '.$v->variant_name.' ('.$v->sku.')',
                'type' => $v->product?->product_type?->value,
            ])->values()->all(),
            'jsVariantCat' => $variants->mapWithKeys(fn ($v): array => [$v->id => $v->product?->unit_category_id])->all(),
            // Available stock per location per variant, so the produce dialog can show
            // what the chosen source store actually holds before anyone commits a batch.
            'jsStock' => $this->availabilityMap($tenant->id),
        ]);
    }

    /**
     * @return array<string, array<string, float>>
     */
    private function availabilityMap(string $tenantId): array
    {
        $map = [];

        foreach (InventoryStockLevel::query()->where('tenant_id', $tenantId)->get() as $level) {
            $map[(string) $level->inventory_location_id][(string) $level->product_variant_id] = round($level->quantity_available, 4);
        }

        return $map;
    }

    /**
     * Choose how a finished product is made, which is the single thing that decides how
     * its recipe is consumed:
     *
     *  - tracked → cooked in batches. Production consumes the recipe and the finished
     *    good becomes stock; selling it draws that stock down.
     *  - recipe  → made to order. Nothing is stocked; ordering it takes the ingredients
     *    straight out of the kitchen.
     */
    public function updateStockPolicy(Request $request, Product $product): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request);
        abort_unless($product->tenant_id === $tenant->id && $product->is_finished_product, 404);

        $policy = StockPolicy::tryFrom((string) $request->string('stock_policy'));

        if ($policy === null || $policy === StockPolicy::Untracked) {
            return back()->withErrors(['stock_policy' => 'Choose how this product is made.']);
        }

        $product->update(['stock_policy' => $policy->value]);

        // Switching to made-to-order strands any finished stock: nothing will draw it
        // down again, so say so rather than letting it sit there quietly.
        $onHand = (float) InventoryStockLevel::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('product_variant_id', $product->variants()->pluck('id'))
            ->sum('quantity_on_hand');

        $message = $policy === StockPolicy::Recipe
            ? "{$product->name} is now made to order — ordering it takes the ingredients out of the kitchen."
            : "{$product->name} is now cooked in batches — record production and it becomes stock.";

        if ($policy === StockPolicy::Recipe && $onHand > 0) {
            $message .= ' Note: '.Quantity::format($onHand).' of finished stock is still on hand and will no longer be sold down. Write it off or sell it first.';
        }

        return redirect()->to($this->showUrl($request, $product->id))->with('status', $message);
    }

    public function storeFinishedProduct(Request $request): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request);
        $request->validate(['product_variant_id' => ['required', 'integer', 'exists:product_variants,id']]);

        $variant = ProductVariant::query()->with('product')
            ->where('tenant_id', $tenant->id)->findOrFail((int) $request->integer('product_variant_id'));
        $variant->product?->update(['is_finished_product' => true]);

        return redirect()->to($this->showUrl($request, $variant->product_id))->with('status', 'Finished product added.');
    }

    public function destroyFinishedProduct(Request $request, Product $product): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request);
        abort_unless($product->tenant_id === $tenant->id, 403);

        $product->update(['is_finished_product' => false]);

        return redirect()->to($this->indexUrl($request))->with('status', 'Finished product removed.');
    }

    public function storeRecipe(RecipeRequest $request): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request);

        $recipe = Recipe::query()->create([
            'tenant_id' => $tenant->id,
            'output_product_variant_id' => (int) $request->integer('output_product_variant_id'),
            'yield_unit_id' => $request->input('yield_unit_id') ? (int) $request->integer('yield_unit_id') : null,
            'prep_location_id' => $request->input('prep_station_id') ? (int) $request->integer('prep_station_id') : null,
            'shelf_life_days' => $request->filled('shelf_life_days') ? (int) $request->integer('shelf_life_days') : null,
            'name' => (string) $request->string('name'),
            'yield_quantity' => Quantity::round((float) $request->input('yield_quantity')),
            'version' => 1,
            'is_active' => true,
            'status' => 'active',
        ]);
        $this->syncRecipeItems($recipe, $tenant->id, (array) $request->input('items', []));

        return redirect()->to($this->showUrlForVariant($request, $recipe->output_product_variant_id))->with('status', 'Recipe saved.');
    }

    public function updateRecipe(RecipeRequest $request, Recipe $recipe): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request);
        abort_unless($recipe->tenant_id === $tenant->id, 403);

        $recipe->update([
            'yield_unit_id' => $request->input('yield_unit_id') ? (int) $request->integer('yield_unit_id') : null,
            'prep_location_id' => $request->input('prep_station_id') ? (int) $request->integer('prep_station_id') : null,
            'shelf_life_days' => $request->filled('shelf_life_days') ? (int) $request->integer('shelf_life_days') : null,
            'name' => (string) $request->string('name'),
            'yield_quantity' => Quantity::round((float) $request->input('yield_quantity')),
            'version' => (int) $recipe->version + 1,
        ]);
        $recipe->items()->delete();
        $this->syncRecipeItems($recipe, $tenant->id, (array) $request->input('items', []));

        return redirect()->to($this->showUrlForVariant($request, $recipe->output_product_variant_id))->with('status', 'Recipe updated.');
    }

    public function destroyRecipe(Request $request, Recipe $recipe): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request);
        abort_unless($recipe->tenant_id === $tenant->id, 403);

        $variantId = $recipe->output_product_variant_id;
        $recipe->update(['is_active' => false, 'status' => 'archived']);

        return redirect()->to($this->showUrlForVariant($request, $variantId))->with('status', 'Recipe removed.');
    }

    public function record(ProductionRequest $request, RecordProductionAction $action): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request);
        $recipe = Recipe::query()->where('tenant_id', $tenant->id)->findOrFail((int) $request->integer('recipe_id'));

        $action->execute([
            'tenant_id' => $tenant->id,
            'recipe_id' => $recipe->id,
            'source_location_id' => (int) $request->integer('source_location_id'),
            'output_location_id' => $request->input('output_location_id') ? (int) $request->integer('output_location_id') : null,
            'actual_yield_quantity' => (float) $request->input('actual_yield_quantity'),
            'reference_number' => $request->input('reference_number'),
            'notes' => $request->input('notes'),
            'items' => (array) $request->input('items', []),
        ]);

        return redirect()->to($this->showUrlForVariant($request, $recipe->output_product_variant_id))->with('status', 'Production recorded.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncRecipeItems(Recipe $recipe, string $tenantId, array $items): void
    {
        foreach ($items as $index => $item) {
            if (empty($item['component_product_variant_id'])) {
                continue;
            }
            $recipe->items()->create([
                'tenant_id' => $tenantId,
                'component_product_variant_id' => (int) $item['component_product_variant_id'],
                'unit_id' => ! empty($item['unit_id']) ? (int) $item['unit_id'] : null,
                'quantity' => Quantity::round((float) ($item['quantity'] ?? 0)),
                'wastage_percent' => round((float) ($item['wastage_percent'] ?? 0), 3),
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @param  Collection<int, int>  $costMap
     */
    private function recipeCostMinor(Recipe $recipe, Collection $costMap): int
    {
        $total = 0;
        foreach ($recipe->items as $item) {
            $quantity = (float) $item->quantity;
            $baseQuantity = $item->unit && $item->unit->isConvertible()
                ? $this->converter->toBase($quantity, $item->unit)
                : $quantity;
            $unitCost = (int) ($costMap[$item->component_product_variant_id] ?? 0);
            $total += (int) round($baseQuantity * (1 + ((float) $item->wastage_percent / 100)) * $unitCost);
        }

        return $total;
    }

    private function showUrl(Request $request, int $productId): string
    {
        $tenantId = $request->string('tenant')->toString();

        return route('admin.inventory.production.show', array_filter(['product' => $productId, 'tenant' => $tenantId ?: null]));
    }

    private function showUrlForVariant(Request $request, int $variantId): string
    {
        $productId = (int) ProductVariant::query()->whereKey($variantId)->value('product_id');

        return $productId ? $this->showUrl($request, $productId) : $this->indexUrl($request);
    }

    private function indexUrl(Request $request): string
    {
        $tenantId = $request->string('tenant')->toString();

        return route('admin.inventory.production.index', $tenantId !== '' ? ['tenant' => $tenantId] : []);
    }

    private function tenantFromRequest(Request $request): Tenant
    {
        $tenant = $this->resolveTenant($request, $this->visibleTenantsFor($request->user()));
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
}
