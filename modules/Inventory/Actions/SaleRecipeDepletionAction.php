<?php

declare(strict_types=1);

namespace Modules\Inventory\Actions;

use Modules\Catalog\Models\ProductVariant;
use Modules\Inventory\Enums\InventoryMovementType;
use Modules\Inventory\Enums\StockCondition;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Inventory\Models\Recipe;
use Modules\Inventory\Support\Quantity;
use Modules\Inventory\Support\RecipeAvailability;

/**
 * Model A depletion: when an à la carte item sells, deduct its recipe's ingredients
 * directly instead of any finished-goods stock. The ingredient movements are posted
 * as sale-sourced (accounting handled by the sale), so the caller books COGS once
 * from the returned total cost. Returns the total ingredient cost in minor units for
 * the sold quantity.
 */
final class SaleRecipeDepletionAction
{
    public function __construct(
        private readonly PostInventoryMovementAction $postInventoryMovement,
        private readonly RecipeAvailability $availability,
    ) {}

    public function deplete(
        string $tenantId,
        int $locationId,
        ProductVariant $saleVariant,
        float $saleQuantity,
        ?string $referenceNumber,
        int $orderId,
        bool $allowNegative = false,
        array $adjustments = [],
    ): int {
        $recipe = Recipe::query()
            ->with('items.unit')
            ->where('tenant_id', $tenantId)
            ->where('output_product_variant_id', $saleVariant->id)
            ->where('is_active', true)
            ->latest('version')
            ->first();

        // No recipe, but a modifier may still consume something ("extra shot" on a
        // bottled drink) — only then is there work to do.
        if (! $recipe && $adjustments === []) {
            return 0;
        }

        $totalCostMinor = 0;

        // Shared with the pad's shortfall warning, so the two can never disagree.
        $requirements = $this->availability->applyAdjustments(
            $recipe ? $this->availability->requirementsFor($recipe, $saleQuantity) : [],
            $adjustments,
            $saleQuantity,
        );

        foreach ($requirements as $requirement) {
            $baseQuantity = $requirement['quantity'];

            $unitCostMinor = (int) (InventoryStockLevel::query()
                ->where('tenant_id', $tenantId)
                ->where('inventory_location_id', $locationId)
                ->where('product_variant_id', $requirement['variant_id'])
                ->value('average_cost_minor') ?? 0);

            $totalCostMinor += (int) round($baseQuantity * $unitCostMinor);

            $this->postInventoryMovement->executeFromSource([
                'tenant_id' => $tenantId,
                'inventory_location_id' => $locationId,
                'product_variant_id' => $requirement['variant_id'],
                'movement_type' => InventoryMovementType::StockOut->value,
                'stock_condition' => StockCondition::Sellable->value,
                'quantity' => $baseQuantity,
                'unit_cost_minor' => $unitCostMinor,
                'reference_number' => $referenceNumber,
                'notes' => 'Recipe ingredient consumed by sale.',
                'occurred_at' => now(),
                'allow_negative' => $allowNegative,
            ], 'sales_order', $orderId);
        }

        return $totalCostMinor;
    }
}
