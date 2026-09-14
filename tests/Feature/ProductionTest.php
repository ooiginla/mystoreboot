<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Business\Models\Branch;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Inventory\Actions\PostInventoryMovementAction;
use Modules\Inventory\Enums\InventoryLocationType;
use Modules\Inventory\Enums\InventoryMovementType;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Inventory\Models\ProductionOrder;
use Modules\Inventory\Models\Recipe;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class ProductionTest extends TestCase
{
    use RefreshDatabase;

    public function test_recording_production_consumes_raw_materials_and_creates_finished_goods_at_cost(): void
    {
        [$tenant, $location, $rice, $oil, $jollof] = $this->productionContext();
        $user = User::factory()->create(['is_platform_admin' => true]);

        // Opening stock for the raw materials.
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

        // Create a recipe via HTTP.
        $this->actingAs($user)
            ->post(route('admin.inventory.production.recipes.store'), [
                'tenant' => $tenant->id,
                'name' => 'Jollof Rice (tray)',
                'output_product_variant_id' => $jollof->id,
                'yield_quantity' => 10,
                'items' => [
                    ['component_product_variant_id' => $rice->id, 'quantity' => 20],
                    ['component_product_variant_id' => $oil->id, 'quantity' => 5],
                ],
            ])
            ->assertRedirect();

        $recipe = Recipe::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame(2, $recipe->items()->count());

        // Record a production batch via HTTP.
        $this->actingAs($user)
            ->post(route('admin.inventory.production.record'), [
                'tenant' => $tenant->id,
                'recipe_id' => $recipe->id,
                'source_location_id' => $location->id,
                'actual_yield_quantity' => 10,
                'items' => [
                    ['component_product_variant_id' => $rice->id, 'actual_quantity' => 20, 'planned_quantity' => 20],
                    ['component_product_variant_id' => $oil->id, 'actual_quantity' => 5, 'planned_quantity' => 5],
                ],
            ])
            ->assertRedirect();

        $onHand = fn (int $variantId): float => (float) InventoryStockLevel::query()
            ->where('inventory_location_id', $location->id)
            ->where('product_variant_id', $variantId)
            ->value('quantity_on_hand');

        $this->assertSame(80.0, $onHand($rice->id));
        $this->assertSame(45.0, $onHand($oil->id));
        $this->assertSame(10.0, $onHand($jollof->id));

        $order = ProductionOrder::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame(4500, (int) $order->total_cost_minor); // 20*200 + 5*100
        $this->assertSame(450, (int) $order->unit_cost_minor);   // 4500 / 10

        // Finished good carries the production-derived unit cost.
        $this->assertSame(450, (int) InventoryStockLevel::query()
            ->where('inventory_location_id', $location->id)
            ->where('product_variant_id', $jollof->id)
            ->value('average_cost_minor'));
    }

    /**
     * @return array{0: Tenant, 1: InventoryLocation, 2: ProductVariant, 3: ProductVariant, 4: ProductVariant}
     */
    private function productionContext(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Kitchen Co', 'slug' => 'kitchen-co', 'status' => TenantStatus::Active,
            'business_type' => 'restaurant', 'country_code' => 'NG', 'timezone' => 'Africa/Lagos', 'currency_code' => 'NGN',
        ]);
        $branch = Branch::query()->create([
            'tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'MAIN', 'status' => 'active', 'is_primary' => true,
        ]);
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

        return [$tenant, $location, $make('Rice', 'RICE-1'), $make('Oil', 'OIL-1'), $make('Jollof Cup', 'JOLLOF-1')];
    }
}
