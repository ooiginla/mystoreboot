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
use Modules\Inventory\Models\InventoryBatch;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Inventory\Models\ProductionOrder;
use Modules\Inventory\Models\Recipe;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

/**
 * Production output used to enter stock with no lot and no expiry — backwards for a
 * kitchen, where a cooked batch is the shortest-lived stock in the building.
 */
final class ProductionLotTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private InventoryLocation $location;

    private ProductVariant $rice;

    private ProductVariant $jollof;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::query()->create([
            'name' => 'Lot Kitchen', 'slug' => 'lot-kitchen', 'status' => TenantStatus::Active,
            'business_type' => 'restaurant', 'country_code' => 'NG', 'timezone' => 'Africa/Lagos', 'currency_code' => 'NGN',
        ]);
        $branch = Branch::query()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Main', 'code' => 'MAIN', 'status' => 'active', 'is_primary' => true,
        ]);
        $this->location = InventoryLocation::query()->create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $branch->id, 'name' => 'Kitchen', 'code' => 'KITCHEN',
            'location_type' => InventoryLocationType::StoreRoom->value, 'status' => 'active',
            // The kitchen both cooks and holds its line stock — one row for both.
            'is_prep_station' => true,
        ]);

        // A plain store alongside the kitchen: ingredients can legitimately come from
        // either, which is why the source picker is not restricted to stations.
        InventoryLocation::query()->create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $branch->id, 'name' => 'Central Store', 'code' => 'CENTRAL',
            'location_type' => InventoryLocationType::Warehouse->value, 'status' => 'active',
        ]);

        $this->rice = $this->makeVariant('Rice', 'RICE-L', ProductType::RawMaterial);
        $this->jollof = $this->makeVariant('Jollof Rice', 'JOLLOF-L', ProductType::Product);
        // The production page only opens for products flagged as finished goods.
        $this->jollof->product->update(['is_finished_product' => true]);

        app(PostInventoryMovementAction::class)->execute([
            'tenant_id' => $this->tenant->id, 'inventory_location_id' => $this->location->id,
            'product_variant_id' => $this->rice->id, 'movement_type' => InventoryMovementType::OpeningStock->value,
            'quantity' => 100, 'unit_cost_minor' => 200,
        ]);

        $this->user = User::factory()->create(['is_platform_admin' => true]);
    }

    public function test_a_recipe_stores_its_shelf_life(): void
    {
        $recipe = $this->createRecipe(shelfLifeDays: 2);

        $this->assertSame(2, $recipe->shelf_life_days);
    }

    public function test_every_production_batch_creates_a_lot_named_after_the_order(): void
    {
        $recipe = $this->createRecipe(shelfLifeDays: 2);
        $this->produce($recipe, 10);

        $order = ProductionOrder::query()->where('tenant_id', $this->tenant->id)->firstOrFail();
        $batch = $this->finishedLot();

        $this->assertSame('PRD-'.$order->produced_at->format('Ymd').'-'.$order->id, $batch->batch_number);
        $this->assertSame(10.0, (float) $batch->quantity_remaining);
        $this->assertSame($this->location->id, $batch->inventory_location_id);
    }

    public function test_shelf_life_becomes_the_lot_expiry_date(): void
    {
        $recipe = $this->createRecipe(shelfLifeDays: 2);
        $this->produce($recipe, 10);

        $order = ProductionOrder::query()->where('tenant_id', $this->tenant->id)->firstOrFail();
        $batch = $this->finishedLot();

        $this->assertSame(
            $order->produced_at->copy()->addDays(2)->toDateString(),
            $batch->expiry_date->toDateString(),
        );
    }

    public function test_a_recipe_without_a_shelf_life_still_gets_a_traceable_lot(): void
    {
        $recipe = $this->createRecipe(shelfLifeDays: null);
        $this->produce($recipe, 10);

        $batch = $this->finishedLot();

        $this->assertNotNull($batch->batch_number, 'Shelf-stable output is still worth tracing.');
        $this->assertNull($batch->expiry_date);
    }

    public function test_the_cooks_own_reference_is_used_as_the_batch_number_when_given(): void
    {
        $recipe = $this->createRecipe(shelfLifeDays: 2);
        $this->produce($recipe, 10, reference: 'TRAY-A');

        $this->assertSame('TRAY-A', $this->finishedLot()->batch_number);
    }

    public function test_the_oldest_cooked_batch_is_sold_first(): void
    {
        $recipe = $this->createRecipe(shelfLifeDays: 2);

        $this->produce($recipe, 10);
        $this->travel(1)->days();
        $this->produce($recipe, 10);

        $lots = InventoryBatch::query()
            ->where('product_variant_id', $this->jollof->id)
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $lots, 'Each cook is its own lot.');

        // Sell 12 trays: the first batch empties before the fresher one is touched.
        app(PostInventoryMovementAction::class)->execute([
            'tenant_id' => $this->tenant->id, 'inventory_location_id' => $this->location->id,
            'product_variant_id' => $this->jollof->id, 'movement_type' => InventoryMovementType::StockOut->value,
            'quantity' => 12,
        ]);

        $this->assertSame(0.0, (float) $lots[0]->refresh()->quantity_remaining);
        $this->assertSame(8.0, (float) $lots[1]->refresh()->quantity_remaining);
    }

    public function test_the_produce_dialog_shows_expected_yield_and_stock_availability(): void
    {
        $this->createRecipe(shelfLifeDays: 2);

        $response = $this->actingAs($this->user)
            ->get(route('admin.inventory.production.show', ['product' => $this->jollof->product_id, 'tenant' => $this->tenant->id]))
            ->assertOk();

        // Expected yield sits beside actual so the variance is visible while typing,
        // and is disabled because it is the recipe's number, not the cook's.
        $response->assertSee('Expected yield')
            ->assertSee('Actual yield')
            ->assertSee('disabled', false)
            // Availability column, filled from the chosen source store.
            ->assertSee('Available')
            ->assertSee('Recipe qty')
            ->assertSee('data-produce-available', false);
    }

    public function test_the_source_picker_groups_prep_stations_first(): void
    {
        $this->createRecipe(shelfLifeDays: null);

        // Ingredients usually come from the station that cooks, but a central store is a
        // legitimate source — so stations are grouped first, not made the only option.
        $this->actingAs($this->user)
            ->get(route('admin.inventory.production.show', ['product' => $this->jollof->product_id, 'tenant' => $this->tenant->id]))
            ->assertOk()
            ->assertSee('Prep stations', false)
            ->assertSee('Other stores', false);
    }

    public function test_a_finished_product_starts_as_cooked_in_batches(): void
    {
        $this->createRecipe(shelfLifeDays: null);

        $this->actingAs($this->user)
            ->get(route('admin.inventory.production.show', ['product' => $this->jollof->product_id, 'tenant' => $this->tenant->id]))
            ->assertOk()
            ->assertSee('How is this made?')
            ->assertSee('Cooked in batches ahead of time')
            ->assertSee('Made to order');

        $this->assertFalse($this->jollof->product->refresh()->usesRecipeDepletion());
    }

    public function test_switching_to_made_to_order_makes_the_recipe_deplete_at_sale(): void
    {
        $this->createRecipe(shelfLifeDays: null);
        $product = $this->jollof->product;

        // Before: the engine that consumes ingredients at sale cannot see this product.
        $this->assertFalse($product->usesRecipeDepletion());

        $this->actingAs($this->user)
            ->patch(route('admin.inventory.production.stock-policy', $product), [
                'tenant' => $this->tenant->id,
                'stock_policy' => 'recipe',
            ])
            ->assertRedirect();

        $this->assertTrue($product->refresh()->usesRecipeDepletion());
    }

    public function test_switching_back_to_batches_restores_stocked_behaviour(): void
    {
        $this->createRecipe(shelfLifeDays: null);
        $product = $this->jollof->product;
        $product->update(['stock_policy' => 'recipe']);

        $this->actingAs($this->user)
            ->patch(route('admin.inventory.production.stock-policy', $product), [
                'tenant' => $this->tenant->id,
                'stock_policy' => 'tracked',
            ])
            ->assertRedirect();

        $this->assertFalse($product->refresh()->usesRecipeDepletion());
    }

    public function test_switching_to_made_to_order_warns_about_finished_stock_left_behind(): void
    {
        $recipe = $this->createRecipe(shelfLifeDays: null);
        $this->produce($recipe, 10);
        $product = $this->jollof->product;

        // 10 trays exist. Nothing will draw them down once it is made to order.
        $this->actingAs($this->user)
            ->patch(route('admin.inventory.production.stock-policy', $product), [
                'tenant' => $this->tenant->id,
                'stock_policy' => 'recipe',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', fn (string $m): bool => str_contains($m, 'still on hand'));
    }

    public function test_an_unknown_policy_is_refused(): void
    {
        $this->createRecipe(shelfLifeDays: null);

        $this->actingAs($this->user)
            ->patch(route('admin.inventory.production.stock-policy', $this->jollof->product), [
                'tenant' => $this->tenant->id,
                'stock_policy' => 'sometimes',
            ])
            ->assertSessionHasErrors('stock_policy');

        $this->assertFalse($this->jollof->product->refresh()->usesRecipeDepletion());
    }

    private function createRecipe(?int $shelfLifeDays): Recipe
    {
        $payload = [
            'tenant' => $this->tenant->id,
            'name' => 'Jollof Rice (tray)',
            'output_product_variant_id' => $this->jollof->id,
            'yield_quantity' => 10,
            'items' => [['component_product_variant_id' => $this->rice->id, 'quantity' => 20]],
        ];

        if ($shelfLifeDays !== null) {
            $payload['shelf_life_days'] = $shelfLifeDays;
        }

        $this->actingAs($this->user)
            ->post(route('admin.inventory.production.recipes.store'), $payload)
            ->assertRedirect();

        return Recipe::query()->where('tenant_id', $this->tenant->id)->latest('id')->firstOrFail();
    }

    private function produce(Recipe $recipe, float $yield, ?string $reference = null): void
    {
        $this->actingAs($this->user)
            ->post(route('admin.inventory.production.record'), array_filter([
                'tenant' => $this->tenant->id,
                'recipe_id' => $recipe->id,
                'source_location_id' => $this->location->id,
                'actual_yield_quantity' => $yield,
                'reference_number' => $reference,
                'items' => [['component_product_variant_id' => $this->rice->id, 'actual_quantity' => 20, 'planned_quantity' => 20]],
            ]))
            ->assertRedirect();
    }

    private function finishedLot(): InventoryBatch
    {
        return InventoryBatch::query()
            ->where('product_variant_id', $this->jollof->id)
            ->latest('id')
            ->firstOrFail();
    }

    private function makeVariant(string $name, string $sku, ProductType $type): ProductVariant
    {
        $product = Product::query()->create([
            'tenant_id' => $this->tenant->id, 'name' => $name, 'slug' => str($name)->slug()->value(),
            'product_type' => $type->value, 'status' => ProductStatus::Active->value,
        ]);

        return ProductVariant::query()->create([
            'tenant_id' => $this->tenant->id, 'product_id' => $product->id, 'variant_name' => 'Default',
            'sku' => $sku, 'status' => ProductStatus::Active->value,
        ]);
    }
}
