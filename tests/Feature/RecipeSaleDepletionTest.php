<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Business\Models\Branch;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Enums\StockPolicy;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Inventory\Actions\PostInventoryMovementAction;
use Modules\Inventory\Actions\SaleRecipeDepletionAction;
use Modules\Inventory\Enums\InventoryLocationType;
use Modules\Inventory\Enums\InventoryMovementType;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Inventory\Models\Recipe;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class RecipeSaleDepletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_selling_a_recipe_item_depletes_its_ingredients_and_returns_their_cost(): void
    {
        [$tenant, $location, $rice, $oil, $jollof] = $this->context();

        $post = app(PostInventoryMovementAction::class);
        $post->execute([
            'tenant_id' => $tenant->id, 'inventory_location_id' => $location->id,
            'product_variant_id' => $rice->id, 'movement_type' => InventoryMovementType::OpeningStock->value,
            'quantity' => 100, 'unit_cost_minor' => 200,
        ]);
        $post->execute([
            'tenant_id' => $tenant->id, 'inventory_location_id' => $location->id,
            'product_variant_id' => $oil->id, 'movement_type' => InventoryMovementType::OpeningStock->value,
            'quantity' => 50, 'unit_cost_minor' => 100,
        ]);

        // Jollof is a recipe-depleted product: no own stock, deducts ingredients on sale.
        $jollof->product->update(['stock_policy' => StockPolicy::Recipe->value]);
        $recipe = Recipe::query()->create([
            'tenant_id' => $tenant->id, 'output_product_variant_id' => $jollof->id,
            'name' => 'Jollof', 'yield_quantity' => 10, 'version' => 1, 'is_active' => true, 'status' => 'active',
        ]);
        $recipe->items()->create(['tenant_id' => $tenant->id, 'component_product_variant_id' => $rice->id, 'quantity' => 20, 'sort_order' => 1]);
        $recipe->items()->create(['tenant_id' => $tenant->id, 'component_product_variant_id' => $oil->id, 'quantity' => 5, 'sort_order' => 2]);

        // Sell 5 cups → scale 0.5 → 10 rice + 2.5 oil.
        $cost = app(SaleRecipeDepletionAction::class)
            ->deplete($tenant->id, $location->id, $jollof, 5.0, 'SO-1', 1);

        $onHand = fn (int $variantId): float => (float) InventoryStockLevel::query()
            ->where('inventory_location_id', $location->id)
            ->where('product_variant_id', $variantId)
            ->value('quantity_on_hand');

        $this->assertSame(90.0, $onHand($rice->id));  // 100 - 10
        $this->assertSame(47.5, $onHand($oil->id));   // 50 - 2.5
        $this->assertSame(2250, $cost);               // 10*200 + 2.5*100

        // The finished good never held its own stock.
        $this->assertNull(InventoryStockLevel::query()
            ->where('product_variant_id', $jollof->id)->value('quantity_on_hand'));
    }

    /**
     * @return array{0: Tenant, 1: InventoryLocation, 2: ProductVariant, 3: ProductVariant, 4: ProductVariant}
     */
    private function context(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'A La Carte', 'slug' => 'a-la-carte', 'status' => TenantStatus::Active,
            'business_type' => 'restaurant', 'country_code' => 'NG', 'timezone' => 'Africa/Lagos', 'currency_code' => 'NGN',
        ]);
        $branch = Branch::query()->create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'MAIN', 'status' => 'active', 'is_primary' => true]);
        $location = InventoryLocation::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'name' => 'Main', 'code' => 'MAIN',
            'location_type' => InventoryLocationType::Branch->value, 'status' => 'active',
        ]);
        $make = function (string $name, string $sku) use ($tenant): ProductVariant {
            $product = Product::query()->create([
                'tenant_id' => $tenant->id, 'name' => $name, 'slug' => \Illuminate\Support\Str::slug($name),
                'product_type' => ProductType::Product->value, 'status' => ProductStatus::Active->value,
            ]);

            return ProductVariant::query()->create([
                'tenant_id' => $tenant->id, 'product_id' => $product->id, 'variant_name' => 'Default',
                'sku' => $sku, 'status' => ProductStatus::Active->value,
            ]);
        };

        return [$tenant, $location, $make('Rice', 'RICE-A'), $make('Oil', 'OIL-A'), $make('Jollof Cup', 'JOLLOF-A')];
    }
}
