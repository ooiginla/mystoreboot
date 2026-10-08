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
use Modules\Inventory\Enums\InventoryLocationType;
use Modules\Inventory\Enums\InventoryMovementType;
use Modules\Inventory\Enums\StockCondition;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Inventory\Models\InventoryMovement;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Inventory\Models\UnitCategory;
use Modules\Inventory\Models\UnitOfMeasure;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class MovementUnitConversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_movement_quantity_in_a_chosen_unit_converts_to_base(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Conv Co', 'slug' => 'conv-co', 'status' => TenantStatus::Active,
            'business_type' => 'restaurant', 'country_code' => 'NG', 'timezone' => 'Africa/Lagos', 'currency_code' => 'NGN',
        ]);
        $branch = Branch::query()->create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'MAIN', 'status' => 'active', 'is_primary' => true]);
        $location = InventoryLocation::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'name' => 'Main', 'code' => 'MAIN',
            'location_type' => InventoryLocationType::Branch->value, 'status' => 'active',
        ]);

        $category = UnitCategory::query()->create(['tenant_id' => $tenant->id, 'name' => 'Biscuit', 'is_default' => false]);
        $base = UnitOfMeasure::query()->create([
            'tenant_id' => $tenant->id, 'unit_category_id' => $category->id, 'code' => 'pc', 'name' => 'Piece',
            'dimension' => 'count', 'to_base_factor' => 1, 'is_base_for_dimension' => true, 'status' => 'active',
        ]);
        $carton = UnitOfMeasure::query()->create([
            'tenant_id' => $tenant->id, 'unit_category_id' => $category->id, 'code' => 'carton', 'name' => 'Carton',
            'dimension' => 'count', 'to_base_factor' => 24, 'is_base_for_dimension' => false, 'status' => 'active',
        ]);

        $product = Product::query()->create([
            'tenant_id' => $tenant->id, 'name' => 'Biscuit', 'slug' => 'biscuit',
            'product_type' => ProductType::RawMaterial->value, 'status' => ProductStatus::Active->value,
            'unit_category_id' => $category->id,
        ]);
        $variant = ProductVariant::query()->create([
            'tenant_id' => $tenant->id, 'product_id' => $product->id, 'variant_name' => 'Default',
            'sku' => 'BISC-1', 'base_unit_id' => $base->id, 'status' => ProductStatus::Active->value,
        ]);

        $user = User::factory()->create(['is_platform_admin' => true]);

        // Receive two differently measured lines in one post: 2 cartons + 10 pieces.
        $this->actingAs($user)->post(route('admin.inventory.movements.store'), [
            'tenant_id' => $tenant->id,
            'inventory_location_id' => $location->id,
            'stock_condition' => StockCondition::Sellable->value,
            'items' => [
                [
                    'product_variant_id' => $variant->id,
                    'movement_type' => InventoryMovementType::StockIn->value,
                    'quantity' => 2,
                    'unit_id' => $carton->id,
                    'total_cost' => '240',
                ],
                [
                    'product_variant_id' => $variant->id,
                    'movement_type' => InventoryMovementType::StockIn->value,
                    'quantity' => 10,
                    'unit_id' => $base->id,
                    'total_cost' => '50',
                ],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(58.0, (float) InventoryStockLevel::query()
            ->where('inventory_location_id', $location->id)
            ->where('product_variant_id', $variant->id)
            ->value('quantity_on_hand'));

        $cartonMovement = InventoryMovement::query()->oldest('id')->firstOrFail();
        $this->assertSame(2.0, (float) $cartonMovement->entered_quantity);
        $this->assertSame($carton->id, $cartonMovement->entered_unit_id);
        $this->assertSame('carton', $cartonMovement->entered_unit_code);
        $this->assertSame(500, (int) $cartonMovement->unit_cost_minor);
        $this->assertSame(24000, (int) $cartonMovement->movement_value_minor);

        $this->assertCount(2, InventoryMovement::query()->get());

        $this->actingAs($user)
            ->get(route('admin.inventory.index', ['tenant' => $tenant->id]).'#movements')
            ->assertOk()
            ->assertSee('<th>Quantity</th>', false)
            ->assertSee('<th>Cost</th>', false)
            ->assertDontSee('<th>Reference</th>', false)
            ->assertSee('2 carton')
            ->assertSee('48 pc in base unit')
            ->assertSee('NGN 5.00/pc')
            ->assertSee('NGN 240.00');
    }

    public function test_a_transfer_entered_in_kg_moves_the_right_number_of_grams_and_the_dialog_shows_the_unit(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Bakery Co', 'slug' => 'bakery-co', 'status' => TenantStatus::Active,
            'business_type' => 'restaurant', 'country_code' => 'NG', 'timezone' => 'Africa/Lagos', 'currency_code' => 'NGN',
        ]);
        $branch = Branch::query()->create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'MAIN', 'status' => 'active', 'is_primary' => true]);
        $store = InventoryLocation::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'name' => 'Central Store', 'code' => 'CENTRAL',
            'location_type' => InventoryLocationType::Branch->value, 'status' => 'active',
        ]);
        $kitchen = InventoryLocation::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'name' => 'Kitchen', 'code' => 'KITCHEN',
            'location_type' => InventoryLocationType::Branch->value, 'status' => 'active',
        ]);

        $weight = UnitCategory::query()->create(['tenant_id' => $tenant->id, 'name' => 'Weight', 'is_default' => false]);
        $gram = UnitOfMeasure::query()->create([
            'tenant_id' => $tenant->id, 'unit_category_id' => $weight->id, 'code' => 'g', 'name' => 'Gram',
            'dimension' => 'weight', 'to_base_factor' => 1, 'is_base_for_dimension' => true, 'status' => 'active',
        ]);
        $kg = UnitOfMeasure::query()->create([
            'tenant_id' => $tenant->id, 'unit_category_id' => $weight->id, 'code' => 'kg', 'name' => 'Kilogram',
            'dimension' => 'weight', 'to_base_factor' => 1000, 'is_base_for_dimension' => false, 'status' => 'active',
        ]);
        $product = Product::query()->create([
            'tenant_id' => $tenant->id, 'name' => 'Flour', 'slug' => 'flour',
            'product_type' => ProductType::RawMaterial->value, 'status' => ProductStatus::Active->value,
            'unit_category_id' => $weight->id,
        ]);
        $flour = ProductVariant::query()->create([
            'tenant_id' => $tenant->id, 'product_id' => $product->id, 'variant_name' => 'Default',
            'sku' => 'FLOUR-1', 'base_unit_id' => $gram->id, 'status' => ProductStatus::Active->value,
        ]);
        $user = User::factory()->create(['is_platform_admin' => true]);

        // 10 kg received into the store.
        $this->actingAs($user)->post(route('admin.inventory.movements.store'), [
            'tenant_id' => $tenant->id, 'inventory_location_id' => $store->id, 'product_variant_id' => $flour->id,
            'movement_type' => InventoryMovementType::StockIn->value, 'stock_condition' => StockCondition::Sellable->value,
            'quantity' => 10, 'unit_id' => $kg->id, 'total_cost' => '20000',
        ])->assertSessionHasNoErrors();

        // Two lines are transferred together (2.5 kg + 1.5 kg).
        $this->actingAs($user)->post(route('admin.inventory.movements.store'), [
            'tenant_id' => $tenant->id, 'inventory_location_id' => $store->id, 'destination_inventory_location_id' => $kitchen->id,
            'product_variant_id' => $flour->id, 'movement_type' => InventoryMovementType::TransferOut->value,
            'stock_condition' => StockCondition::Sellable->value,
            'items' => [
                ['product_variant_id' => $flour->id, 'quantity' => 2.5, 'unit_id' => $kg->id],
                ['product_variant_id' => $flour->id, 'quantity' => 1.5, 'unit_id' => $kg->id],
            ],
        ])->assertSessionHasNoErrors();

        $onHand = fn (InventoryLocation $location): float => (float) InventoryStockLevel::query()
            ->where('inventory_location_id', $location->id)
            ->where('product_variant_id', $flour->id)
            ->value('quantity_on_hand');

        $this->assertSame(6000.0, $onHand($store));
        $this->assertSame(4000.0, $onHand($kitchen));
        $this->assertSame(2, InventoryMovement::query()
            ->where('movement_type', InventoryMovementType::TransferOut->value)
            ->count());

        // If a later row fails, the earlier row is rolled back as part of the same transfer.
        $this->actingAs($user)->post(route('admin.inventory.movements.store'), [
            'tenant_id' => $tenant->id, 'inventory_location_id' => $store->id, 'destination_inventory_location_id' => $kitchen->id,
            'movement_type' => InventoryMovementType::TransferOut->value, 'stock_condition' => StockCondition::Sellable->value,
            'items' => [
                ['product_variant_id' => $flour->id, 'quantity' => 1, 'unit_id' => $kg->id],
                ['product_variant_id' => $flour->id, 'quantity' => 99, 'unit_id' => $kg->id],
            ],
        ])->assertSessionHasErrors('quantity');

        $this->assertSame(6000.0, $onHand($store));
        $this->assertSame(4000.0, $onHand($kitchen));

        // Both dialogs carry the unit beside the quantity, and the page knows flour's units.
        $response = $this->actingAs($user)->get(route('admin.inventory.index', ['tenant' => $tenant->id]))->assertOk();
        $this->assertSame(2, substr_count($response->getContent(), 'required data-qty-input>'));
        $response->assertSee('"code":"kg"', false);
    }
}
