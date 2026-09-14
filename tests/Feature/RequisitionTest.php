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
use Modules\Inventory\Enums\RequisitionStatus;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Inventory\Models\StockRequisition;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class RequisitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_requisition_can_be_submitted_approved_and_fulfilled_moving_stock_between_stores(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Multi Store', 'slug' => 'multi-store', 'status' => TenantStatus::Active,
            'business_type' => 'retail', 'country_code' => 'NG', 'timezone' => 'Africa/Lagos', 'currency_code' => 'NGN',
        ]);
        $branch = Branch::query()->create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'MAIN', 'status' => 'active', 'is_primary' => true]);
        $source = InventoryLocation::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'name' => 'Central Store', 'code' => 'CENTRAL',
            'location_type' => InventoryLocationType::Warehouse->value, 'status' => 'active',
        ]);
        $destination = InventoryLocation::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'name' => 'Grill Store', 'code' => 'GRILL',
            'location_type' => InventoryLocationType::StoreRoom->value, 'status' => 'active',
        ]);
        $product = Product::query()->create([
            'tenant_id' => $tenant->id, 'name' => 'Rice', 'slug' => 'rice',
            'product_type' => ProductType::Product->value, 'status' => ProductStatus::Active->value,
        ]);
        $variant = ProductVariant::query()->create([
            'tenant_id' => $tenant->id, 'product_id' => $product->id, 'variant_name' => 'Default',
            'sku' => 'RICE-R', 'status' => ProductStatus::Active->value,
        ]);

        app(PostInventoryMovementAction::class)->execute([
            'tenant_id' => $tenant->id, 'inventory_location_id' => $source->id,
            'product_variant_id' => $variant->id, 'movement_type' => InventoryMovementType::OpeningStock->value,
            'quantity' => 100, 'unit_cost_minor' => 500,
        ]);

        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($user)->post(route('admin.inventory.requisitions.store'), [
            'tenant' => $tenant->id,
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'items' => [['product_variant_id' => $variant->id, 'requested_quantity' => 30]],
        ])->assertRedirect();

        $requisition = StockRequisition::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame(RequisitionStatus::Submitted, $requisition->status);

        $this->actingAs($user)
            ->get(route('admin.inventory.requisitions.index', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('data-dialog-open="requisition-detail-'.$requisition->id.'"', false)
            ->assertSee('data-requisition-detail="'.$requisition->id.'"', false)
            ->assertSeeInOrder(['Item breakdown', '<th>Item</th>', '<th class="req-num">Quantity</th>', '<th>Unit</th>'], false);

        $this->actingAs($user)->post(route('admin.inventory.requisitions.approve', $requisition))->assertRedirect();
        $this->assertSame(RequisitionStatus::Approved, $requisition->refresh()->status);

        $this->actingAs($user)->post(route('admin.inventory.requisitions.fulfil', $requisition))->assertRedirect();
        $this->assertSame(RequisitionStatus::Fulfilled, $requisition->refresh()->status);

        $onHand = fn (int $locationId): float => (float) InventoryStockLevel::query()
            ->where('inventory_location_id', $locationId)
            ->where('product_variant_id', $variant->id)
            ->value('quantity_on_hand');

        $this->assertSame(70.0, $onHand($source->id));      // 100 - 30 shipped
        $this->assertSame(30.0, $onHand($destination->id)); // received at grill store
    }

    public function test_the_list_shows_each_as_the_unit_when_none_was_chosen(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Unit Co', 'slug' => 'unit-co', 'status' => TenantStatus::Active,
            'business_type' => 'retail', 'country_code' => 'NG', 'timezone' => 'Africa/Lagos', 'currency_code' => 'NGN',
        ]);
        $branch = Branch::query()->create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'M1', 'status' => 'active', 'is_primary' => true]);
        $mk = fn (string $n, string $c) => InventoryLocation::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'name' => $n, 'code' => $c,
            'location_type' => InventoryLocationType::Warehouse->value, 'status' => 'active',
        ]);
        $source = $mk('Central Store', 'CEN-U');
        $destination = $mk('Poolside Bar', 'POOL-U');

        $product = Product::query()->create([
            'tenant_id' => $tenant->id, 'name' => 'Asconti Agnol Wine', 'slug' => 'wine-u',
            'product_type' => ProductType::Product->value, 'status' => ProductStatus::Active->value,
        ]);
        $variant = ProductVariant::query()->create([
            'tenant_id' => $tenant->id, 'product_id' => $product->id,
            // Named after its own product, as real catalogues often are.
            'variant_name' => 'Asconti Agnol Wine', 'sku' => 'WINE-U', 'status' => ProductStatus::Active->value,
        ]);

        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($user)->post(route('admin.inventory.requisitions.store'), [
            'tenant' => $tenant->id,
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            // No unit picked — the dialog's blank option is "each".
            'items' => [['product_variant_id' => $variant->id, 'requested_quantity' => 20]],
        ])->assertRedirect();

        $this->actingAs($user)
            ->get(route('admin.inventory.requisitions.index', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('<td>pc</td>', false)
            ->assertSee('Central Store')
            ->assertSee('Poolside Bar')
            // The variant repeats the product name, so it must not be printed twice.
            ->assertDontSee('Asconti Agnol Wine — Asconti Agnol Wine');
    }

    public function test_a_requisition_cannot_name_another_tenants_location(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Mine', 'slug' => 'mine-req', 'status' => TenantStatus::Active,
            'business_type' => 'retail', 'country_code' => 'NG', 'timezone' => 'Africa/Lagos', 'currency_code' => 'NGN',
        ]);
        $branch = Branch::query()->create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'MAIN', 'status' => 'active', 'is_primary' => true]);
        $mine = InventoryLocation::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'name' => 'My Store', 'code' => 'MYSTORE',
            'location_type' => InventoryLocationType::Warehouse->value, 'status' => 'active',
        ]);

        $other = Tenant::query()->create([
            'name' => 'Theirs', 'slug' => 'theirs-req', 'status' => TenantStatus::Active,
            'business_type' => 'retail', 'country_code' => 'NG', 'timezone' => 'Africa/Lagos', 'currency_code' => 'NGN',
        ]);
        $otherBranch = Branch::query()->create(['tenant_id' => $other->id, 'name' => 'Main', 'code' => 'MAIN2', 'status' => 'active', 'is_primary' => true]);
        $foreign = InventoryLocation::query()->create([
            'tenant_id' => $other->id, 'branch_id' => $otherBranch->id, 'name' => 'Their Store', 'code' => 'THEIRS',
            'location_type' => InventoryLocationType::Warehouse->value, 'status' => 'active',
        ]);

        $product = Product::query()->create([
            'tenant_id' => $tenant->id, 'name' => 'Beans', 'slug' => 'beans',
            'product_type' => ProductType::Product->value, 'status' => ProductStatus::Active->value,
        ]);
        $variant = ProductVariant::query()->create([
            'tenant_id' => $tenant->id, 'product_id' => $product->id, 'variant_name' => 'Default',
            'sku' => 'BEANS-X', 'status' => ProductStatus::Active->value,
        ]);

        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($user)->post(route('admin.inventory.requisitions.store'), [
            'tenant' => $tenant->id,
            'source_location_id' => $foreign->id,
            'destination_location_id' => $mine->id,
            'items' => [['product_variant_id' => $variant->id, 'requested_quantity' => 5]],
        ])->assertNotFound();

        $this->assertSame(0, StockRequisition::query()->where('tenant_id', $tenant->id)->count());
    }
}
