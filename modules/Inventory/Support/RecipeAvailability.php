<?php

declare(strict_types=1);

namespace Modules\Inventory\Support;

use Modules\Catalog\Models\ProductVariant;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Inventory\Models\Recipe;
use Modules\Inventory\Models\RecipeItem;

/**
 * What a recipe needs, and whether the station holding it can cover that.
 *
 * The scaling lives here rather than in the depletion action so a warning shown on the
 * check pad and the movement actually posted can never disagree — one of them being
 * wrong is worse than having no warning at all.
 */
final class RecipeAvailability
{
    public function __construct(private readonly UnitConverter $converter) {}

    /**
     * Ingredient quantities, in base units, for making `$saleQuantity` of the output.
     *
     * @return array<int, array{variant_id: int, quantity: float}>
     */
    public function requirementsFor(Recipe $recipe, float $saleQuantity): array
    {
        $yield = max(0.0001, (float) $recipe->yield_quantity);
        $scale = $saleQuantity / $yield;

        $requirements = [];

        foreach ($recipe->items as $item) {
            /** @var RecipeItem $item */
            $quantity = (float) $item->quantity * $scale * (1 + ((float) $item->wastage_percent / 100));

            $baseQuantity = $item->unit && $item->unit->isConvertible()
                ? $this->converter->toBase($quantity, $item->unit)
                : Quantity::round($quantity);

            if ($baseQuantity <= 0) {
                continue;
            }

            $requirements[] = [
                'variant_id' => (int) $item->component_product_variant_id,
                'quantity' => $baseQuantity,
            ];
        }

        return $requirements;
    }

    /**
     * Fold modifier ingredient changes into a recipe's requirements. Changes are per one
     * of the item sold, in base units: "no onions" takes an ingredient down (never below
     * zero), "extra chicken" adds to it, and an extra the recipe never had becomes a new
     * line. Both the pad warning and the real depletion go through here.
     *
     * @param  array<int, array{variant_id: int, quantity: float}>  $requirements
     * @param  array<int, float>  $adjustments
     * @return array<int, array{variant_id: int, quantity: float}>
     */
    public function applyAdjustments(array $requirements, array $adjustments, float $saleQuantity): array
    {
        if ($adjustments === []) {
            return $requirements;
        }

        $byVariant = [];

        foreach ($requirements as $requirement) {
            $byVariant[$requirement['variant_id']] = ($byVariant[$requirement['variant_id']] ?? 0.0) + $requirement['quantity'];
        }

        foreach ($adjustments as $variantId => $perUnit) {
            $byVariant[(int) $variantId] = ($byVariant[(int) $variantId] ?? 0.0) + ((float) $perUnit * $saleQuantity);
        }

        $adjusted = [];

        foreach ($byVariant as $variantId => $quantity) {
            $quantity = Quantity::round($quantity);

            if ($quantity > 0) {
                $adjusted[] = ['variant_id' => (int) $variantId, 'quantity' => $quantity];
            }
        }

        return $adjusted;
    }

    /**
     * Ingredients the station cannot cover, worst shortfall first.
     *
     * @param  array<int, float>  $adjustments  modifier changes per unit sold, see applyAdjustments()
     * @return array<int, array{name: string, need: float, have: float}>
     */
    public function shortfalls(string $tenantId, int $locationId, ProductVariant $output, float $saleQuantity, array $adjustments = []): array
    {
        $recipe = $this->activeRecipeFor($tenantId, $output);

        if (! $recipe && $adjustments === []) {
            return [];
        }

        $requirements = $this->applyAdjustments(
            $recipe ? $this->requirementsFor($recipe, $saleQuantity) : [],
            $adjustments,
            $saleQuantity,
        );

        $short = [];

        foreach ($requirements as $requirement) {
            $level = InventoryStockLevel::query()
                ->with('variant.product')
                ->where('tenant_id', $tenantId)
                ->where('inventory_location_id', $locationId)
                ->where('product_variant_id', $requirement['variant_id'])
                ->first();

            $have = (float) ($level?->quantity_available ?? 0);

            if ($have >= $requirement['quantity']) {
                continue;
            }

            $short[] = [
                'name' => $level?->variant?->product?->name
                    ?? ProductVariant::query()->find($requirement['variant_id'])?->product?->name
                    ?? 'An ingredient',
                'need' => $requirement['quantity'],
                'have' => max(0, $have),
            ];
        }

        usort($short, fn (array $a, array $b): int => ($b['need'] - $b['have']) <=> ($a['need'] - $a['have']));

        return $short;
    }

    public function activeRecipeFor(string $tenantId, ProductVariant $output): ?Recipe
    {
        return Recipe::query()
            ->with(['items.unit', 'items.componentVariant.product'])
            ->where('tenant_id', $tenantId)
            ->where('output_product_variant_id', $output->id)
            ->where('is_active', true)
            ->latest('version')
            ->first();
    }
}
