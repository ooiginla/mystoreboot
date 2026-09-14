<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Business\Models\Branch;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Customers\Models\Customer;
use Modules\Inventory\Enums\InventoryLocationType;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class RawMaterialTest extends TestCase
{
    use RefreshDatabase;

    public function test_enum_separates_sellable_from_stockable(): void
    {
        $this->assertNotContains(ProductType::RawMaterial, ProductType::sellable());
        $this->assertContains(ProductType::RawMaterial, ProductType::stockable());
        $this->assertArrayNotHasKey('raw_material', ProductType::options());
    }

    public function test_raw_material_can_be_created_and_never_appears_as_sellable(): void
    {
        [$tenant] = $this->context();
        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($user)->post(route('admin.catalog.raw-materials.store'), [
            'tenant_id' => $tenant->id,
            'tenant' => $tenant->id,
            'name' => 'Rice (raw)',
            'sku' => 'RAW-RICE',
        ])->assertRedirect();

        $material = Product::query()->where('tenant_id', $tenant->id)->where('name', 'Rice (raw)')->firstOrFail();
        $this->assertSame(ProductType::RawMaterial, $material->product_type);
        $this->assertSame(1, $material->variants()->count());

        // It is not part of the sellable catalog listing (which queries Product/Service).
        $this->assertFalse(
            Product::query()->where('tenant_id', $tenant->id)
                ->whereIn('product_type', array_map(fn (ProductType $t) => $t->value, ProductType::sellable()))
                ->whereKey($material->id)->exists()
        );
    }

    public function test_raw_material_cannot_be_added_to_a_sale(): void
    {
        [$tenant, $branch, $location] = $this->context();
        $user = User::factory()->create(['is_platform_admin' => true]);

        $product = Product::query()->create([
            'tenant_id' => $tenant->id, 'name' => 'Oil (raw)', 'slug' => 'oil-raw',
            'product_type' => ProductType::RawMaterial->value, 'status' => 'active',
        ]);
        $variant = ProductVariant::query()->create([
            'tenant_id' => $tenant->id, 'product_id' => $product->id, 'variant_name' => 'Default',
            'sku' => 'RAW-OIL', 'status' => 'active',
        ]);
        $customer = Customer::query()->create([
            'tenant_id' => $tenant->id, 'first_name' => 'Walk', 'last_name' => 'In', 'phone' => '08000000000', 'status' => 'active',
        ]);

        $this->actingAs($user)->post(route('admin.sales.orders.store'), [
            'tenant_id' => $tenant->id,
            'source' => 'offline',
            'record_as' => 'completed_sale',
            'branch_id' => $branch->id,
            'inventory_location_id' => $location->id,
            'customer_id' => $customer->id,
            'order_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'amount_paid' => '0',
            'shipping' => '0',
            'admin_discount_type' => 'amount',
            'admin_discount_value' => '0',
            'delivery_status' => 'delivered',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => '100']],
        ])->assertSessionHasErrors('items');
    }

    /**
     * @return array{0: Tenant, 1: Branch, 2: InventoryLocation}
     */
    private function context(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'RM Co', 'slug' => 'rm-co', 'status' => TenantStatus::Active,
            'business_type' => 'restaurant', 'country_code' => 'NG', 'timezone' => 'Africa/Lagos', 'currency_code' => 'NGN',
        ]);
        $branch = Branch::query()->create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'MAIN', 'status' => 'active', 'is_primary' => true]);
        $location = InventoryLocation::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'name' => 'Main', 'code' => 'MAIN',
            'location_type' => InventoryLocationType::Branch->value, 'is_sellable_point' => true, 'status' => 'active',
        ]);

        return [$tenant, $branch, $location];
    }
}
