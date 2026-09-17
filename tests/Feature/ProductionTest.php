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

    public function test_a_one_piece_recipe_can_be_made_fifty_times_and_the_plan_is_recorded(): void
    {
        [$tenant, $location, $flour, $meat, $pie] = $this->productionContext();
        // Only finished products have a production page.
        $pie->product->update(['is_finished_product' => true]);
        $user = User::factory()->create(['is_platform_admin' => true]);

        $post = app(PostInventoryMovementAction::class);
        foreach ([[$flour, 10000, 2], [$meat, 5000, 10]] as [$variant, $quantity, $cost]) {
            $post->execute([
                'tenant_id' => $tenant->id, 'inventory_location_id' => $location->id,
                'product_variant_id' => $variant->id, 'movement_type' => InventoryMovementType::OpeningStock->value,
                'quantity' => $quantity, 'unit_cost_minor' => $cost,
            ]);
        }

        // One meat pie: 80 of flour and 40 of meat.
        $this->actingAs($user)
            ->post(route('admin.inventory.production.recipes.store'), [
                'tenant' => $tenant->id,
                'name' => 'Meat pie',
                'output_product_variant_id' => $pie->id,
                'yield_quantity' => 1,
                'items' => [
                    ['component_product_variant_id' => $flour->id, 'quantity' => 80],
                    ['component_product_variant_id' => $meat->id, 'quantity' => 40],
                ],
            ])
            ->assertRedirect();
        $recipe = Recipe::query()->where('tenant_id', $tenant->id)->firstOrFail();

        // The dialog now starts from "how many to make".
        $this->actingAs($user)
            ->get(route('admin.inventory.production.show', ['product' => $pie->product_id, 'tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('How many to make')
            ->assertSee('name="planned_quantity"', false);

        // Plan 50; the cook used a little more flour than planned and 2 pies burnt.
        $this->actingAs($user)
            ->post(route('admin.inventory.production.record'), [
                'tenant' => $tenant->id,
                'recipe_id' => $recipe->id,
                'source_location_id' => $location->id,
                'planned_quantity' => 50,
                'actual_yield_quantity' => 48,
                'items' => [
                    ['component_product_variant_id' => $flour->id, 'planned_quantity' => 4000, 'actual_quantity' => 4100],
                    ['component_product_variant_id' => $meat->id, 'planned_quantity' => 2000, 'actual_quantity' => 2000],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $order = ProductionOrder::query()->with('items')->where('tenant_id', $tenant->id)->firstOrFail();
        // Variance is against the real plan (50), not the recipe's single piece.
        $this->assertSame(50.0, (float) $order->planned_quantity);
        $this->assertSame(48.0, (float) $order->actual_yield_quantity);
        $this->assertSame(4000.0, (float) $order->items->firstWhere('component_product_variant_id', $flour->id)->planned_quantity);

        $onHand = fn (int $variantId): float => (float) InventoryStockLevel::query()
            ->where('inventory_location_id', $location->id)
            ->where('product_variant_id', $variantId)
            ->value('quantity_on_hand');

        $this->assertSame(5900.0, $onHand($flour->id));
        $this->assertSame(3000.0, $onHand($meat->id));
        $this->assertSame(48.0, $onHand($pie->id));
        // 4100×2 + 2000×10 = 28,200 across 48 pies.
        $this->assertSame(28200, (int) $order->total_cost_minor);
        $this->assertSame(588, (int) $order->unit_cost_minor);
    }

    public function test_starting_holds_the_ingredients_and_completing_deducts_what_was_really_used(): void
    {
        [$tenant, $location, $flour, $meat, $pie, $recipe, $user] = $this->pieRecipe();

        $this->actingAs($user)
            ->post(route('admin.inventory.production.start'), [
                'tenant' => $tenant->id, 'recipe_id' => $recipe->id,
                'source_location_id' => $location->id, 'planned_quantity' => 50,
            ])
            ->assertSessionHasNoErrors();

        $run = ProductionOrder::query()->with('items')->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertTrue($run->isInProgress());
        $this->assertNotNull($run->started_at);

        // Held, not deducted: still on the shelf, but no longer available to anyone else.
        $this->assertSame(10000.0, $this->onHand($location, $flour));
        $this->assertSame(4000.0, $this->reserved($location, $flour));
        $this->assertSame(2000.0, $this->reserved($location, $meat));
        $this->assertSame(0.0, $this->onHand($location, $pie));

        $this->actingAs($user)
            ->get(route('admin.inventory.production.show', ['product' => $pie->product_id, 'tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('In progress')
            ->assertSee('Complete &amp; deduct ingredients', false);

        $flourLine = $run->items->firstWhere('component_product_variant_id', $flour->id);
        $meatLine = $run->items->firstWhere('component_product_variant_id', $meat->id);

        $this->actingAs($user)
            ->post(route('admin.inventory.production.runs.complete', $run), [
                'tenant' => $tenant->id,
                'actual_yield_quantity' => 48,
                'items' => [
                    $flourLine->id => ['actual_quantity' => 4100],
                    $meatLine->id => ['actual_quantity' => 2000],
                ],
            ])
            ->assertSessionHasNoErrors();

        $run->refresh();
        $this->assertTrue($run->isCompleted());
        $this->assertNotNull($run->produced_at);
        $this->assertSame(50.0, (float) $run->planned_quantity);
        $this->assertSame(48.0, (float) $run->actual_yield_quantity);
        $this->assertSame(28200, (int) $run->total_cost_minor);

        // The hold is gone and only what was really used left the shelf.
        $this->assertSame(0.0, $this->reserved($location, $flour));
        $this->assertSame(0.0, $this->reserved($location, $meat));
        $this->assertSame(5900.0, $this->onHand($location, $flour));
        $this->assertSame(3000.0, $this->onHand($location, $meat));
        $this->assertSame(48.0, $this->onHand($location, $pie));

        // Finishing it a second time is refused.
        $this->actingAs($user)
            ->post(route('admin.inventory.production.runs.complete', $run), ['tenant' => $tenant->id, 'actual_yield_quantity' => 48])
            ->assertSessionHasErrors('production');
        $this->assertSame(48.0, $this->onHand($location, $pie));
    }

    public function test_cancelling_a_started_batch_hands_the_ingredients_back(): void
    {
        [$tenant, $location, $flour, $meat, $pie, $recipe, $user] = $this->pieRecipe();

        $this->actingAs($user)->post(route('admin.inventory.production.start'), [
            'tenant' => $tenant->id, 'recipe_id' => $recipe->id,
            'source_location_id' => $location->id, 'planned_quantity' => 50,
        ]);
        $run = ProductionOrder::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->actingAs($user)
            ->post(route('admin.inventory.production.runs.cancel', $run), ['tenant' => $tenant->id])
            ->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $run->refresh()->status);
        $this->assertSame(0.0, $this->reserved($location, $flour));
        $this->assertSame(10000.0, $this->onHand($location, $flour));
        $this->assertSame(0.0, $this->onHand($location, $pie));
    }

    public function test_starting_more_than_the_store_holds_holds_what_it_can_and_says_what_is_short(): void
    {
        [$tenant, $location, $flour, $meat, $pie, $recipe, $user] = $this->pieRecipe();

        // 200 pies want 16,000 of flour; only 10,000 is there.
        $this->actingAs($user)
            ->post(route('admin.inventory.production.start'), [
                'tenant' => $tenant->id, 'recipe_id' => $recipe->id,
                'source_location_id' => $location->id, 'planned_quantity' => 200,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'Not enough in stock to hold all of: Rice'));

        $this->assertSame(10000.0, $this->reserved($location, $flour));
    }

    public function test_without_a_plan_one_batch_of_the_recipe_is_the_plan(): void
    {
        [$tenant, $location, $rice, $oil, $jollof] = $this->productionContext();
        app(PostInventoryMovementAction::class)->execute([
            'tenant_id' => $tenant->id, 'inventory_location_id' => $location->id,
            'product_variant_id' => $rice->id, 'movement_type' => InventoryMovementType::OpeningStock->value,
            'quantity' => 100, 'unit_cost_minor' => 200,
        ]);
        $recipe = Recipe::query()->create([
            'tenant_id' => $tenant->id, 'output_product_variant_id' => $jollof->id, 'name' => 'Jollof tray',
            'yield_quantity' => 10, 'version' => 1, 'is_active' => true, 'status' => 'active',
        ]);

        app(\Modules\Inventory\Actions\RecordProductionAction::class)->execute([
            'tenant_id' => $tenant->id, 'recipe_id' => $recipe->id, 'source_location_id' => $location->id,
            'actual_yield_quantity' => 9,
            'items' => [['component_product_variant_id' => $rice->id, 'actual_quantity' => 20]],
        ]);

        $this->assertSame(10.0, (float) ProductionOrder::query()->firstOrFail()->planned_quantity);
    }

    /**
     * A finished "pie" made from 80 of flour and 40 of meat per piece, with 10,000 flour and
     * 5,000 meat in the store. (The context's variants are named Rice/Oil/Jollof Cup.)
     *
     * @return array{0: Tenant, 1: InventoryLocation, 2: ProductVariant, 3: ProductVariant, 4: ProductVariant, 5: Recipe, 6: User}
     */
    private function pieRecipe(): array
    {
        [$tenant, $location, $flour, $meat, $pie] = $this->productionContext();
        $pie->product->update(['is_finished_product' => true]);
        $user = User::factory()->create(['is_platform_admin' => true]);

        $post = app(PostInventoryMovementAction::class);
        foreach ([[$flour, 10000, 2], [$meat, 5000, 10]] as [$variant, $quantity, $cost]) {
            $post->execute([
                'tenant_id' => $tenant->id, 'inventory_location_id' => $location->id,
                'product_variant_id' => $variant->id, 'movement_type' => InventoryMovementType::OpeningStock->value,
                'quantity' => $quantity, 'unit_cost_minor' => $cost,
            ]);
        }

        $recipe = Recipe::query()->create([
            'tenant_id' => $tenant->id, 'output_product_variant_id' => $pie->id, 'name' => 'Meat pie',
            'yield_quantity' => 1, 'version' => 1, 'is_active' => true, 'status' => 'active',
        ]);
        $recipe->items()->create(['tenant_id' => $tenant->id, 'component_product_variant_id' => $flour->id, 'quantity' => 80, 'sort_order' => 0]);
        $recipe->items()->create(['tenant_id' => $tenant->id, 'component_product_variant_id' => $meat->id, 'quantity' => 40, 'sort_order' => 1]);

        return [$tenant, $location, $flour, $meat, $pie, $recipe, $user];
    }

    private function onHand(InventoryLocation $location, ProductVariant $variant): float
    {
        return (float) InventoryStockLevel::query()
            ->where('inventory_location_id', $location->id)
            ->where('product_variant_id', $variant->id)
            ->value('quantity_on_hand');
    }

    private function reserved(InventoryLocation $location, ProductVariant $variant): float
    {
        return (float) InventoryStockLevel::query()
            ->where('inventory_location_id', $location->id)
            ->where('product_variant_id', $variant->id)
            ->value('quantity_reserved');
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
