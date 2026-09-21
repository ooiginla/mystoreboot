<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Business\Models\OnlineStore;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Enums\TaxBehavior;
use Modules\Catalog\Models\Product;
use Modules\Procurement\Models\Vendor;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class CatalogProductSourcingTest extends TestCase
{
    use RefreshDatabase;

    public function test_suppliers_references_and_external_images_can_be_saved_on_a_product(): void
    {
        $tenant = $this->tenant('Sourcing Shop', 'sourcing-shop');
        $supplier = Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Existing Supplier',
            'status' => 'active',
        ]);
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)
            ->post(route('admin.catalog.products.store'), $this->payload($tenant, [
                'supplier_ids' => [$supplier->id],
                'new_supplier' => [
                    'name' => 'New Wholesale Source',
                    'email' => 'orders@wholesale.example',
                    'phone' => '+2348000000000',
                ],
                'supplier_references' => [
                    ['url' => 'https://supplier.example/products/blue-shirt'],
                ],
                'external_images' => [
                    ['url' => 'https://cdn.supplier.example/images/blue-shirt-front.jpg', 'alt_text' => 'Blue shirt, front view'],
                ],
            ]))
            ->assertRedirect(route('admin.catalog.index', ['tenant' => $tenant->id]));

        $product = Product::query()->where('tenant_id', $tenant->id)->where('slug', 'sourced-product')->firstOrFail();
        $newSupplier = Vendor::query()->where('tenant_id', $tenant->id)->where('name', 'New Wholesale Source')->firstOrFail();

        $this->assertEqualsCanonicalizing(
            [$supplier->id, $newSupplier->id],
            $product->suppliers()->pluck('vendors.id')->all(),
        );
        $this->assertDatabaseHas('product_supplier', [
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'vendor_id' => $supplier->id,
        ]);
        $this->assertDatabaseHas('product_supplier_references', [
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'url' => 'https://supplier.example/products/blue-shirt',
        ]);
        $this->assertDatabaseHas('product_external_images', [
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'url' => 'https://cdn.supplier.example/images/blue-shirt-front.jpg',
            'alt_text' => 'Blue shirt, front view',
            'sort_order' => 0,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.catalog.index', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('Product suppliers')
            ->assertSee('Supplier Product Reference link')
            ->assertSee('Product image links')
            ->assertSee('Existing Supplier')
            ->assertSee('https://supplier.example/products/blue-shirt', false)
            ->assertSee('https://cdn.supplier.example/images/blue-shirt-front.jpg', false);
    }

    public function test_supplier_and_reference_links_are_replaced_when_product_is_updated(): void
    {
        $tenant = $this->tenant('Update Sources', 'update-sources');
        $firstSupplier = Vendor::query()->create(['tenant_id' => $tenant->id, 'name' => 'First Supplier', 'status' => 'active']);
        $secondSupplier = Vendor::query()->create(['tenant_id' => $tenant->id, 'name' => 'Second Supplier', 'status' => 'active']);
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)->post(route('admin.catalog.products.store'), $this->payload($tenant, [
            'supplier_ids' => [$firstSupplier->id],
            'supplier_references' => [['url' => 'https://supplier.example/old']],
            'external_images' => [['url' => 'https://cdn.supplier.example/old.jpg']],
        ]))->assertRedirect();

        $product = Product::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->actingAs($admin)->put(route('admin.catalog.products.update', $product), $this->payload($tenant, [
            'supplier_ids' => [$secondSupplier->id],
            'supplier_references' => [['url' => 'https://supplier.example/new']],
            'external_images' => [['url' => 'https://cdn.supplier.example/new.jpg']],
        ]))->assertRedirect();

        $this->assertSame([$secondSupplier->id], $product->suppliers()->pluck('vendors.id')->all());
        $this->assertDatabaseMissing('product_supplier_references', ['product_id' => $product->id, 'url' => 'https://supplier.example/old']);
        $this->assertDatabaseHas('product_supplier_references', ['product_id' => $product->id, 'url' => 'https://supplier.example/new']);
        $this->assertDatabaseMissing('product_external_images', ['product_id' => $product->id, 'url' => 'https://cdn.supplier.example/old.jpg']);
        $this->assertDatabaseHas('product_external_images', ['product_id' => $product->id, 'url' => 'https://cdn.supplier.example/new.jpg']);
    }

    public function test_a_supplier_from_another_tenant_cannot_be_assigned(): void
    {
        $tenant = $this->tenant('Protected Shop', 'protected-shop');
        $otherTenant = $this->tenant('Other Shop', 'other-shop');
        $foreignSupplier = Vendor::query()->create(['tenant_id' => $otherTenant->id, 'name' => 'Foreign Supplier', 'status' => 'active']);
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)
            ->post(route('admin.catalog.products.store'), $this->payload($tenant, [
                'supplier_ids' => [$foreignSupplier->id],
            ]))
            ->assertSessionHasErrors('supplier_ids.0');

        $this->assertDatabaseMissing('products', ['tenant_id' => $tenant->id, 'slug' => 'sourced-product']);
    }

    public function test_sourcing_metadata_is_not_rendered_on_the_storefront(): void
    {
        $tenant = $this->tenant('Private Sources', 'private-sources');
        $store = OnlineStore::query()->create([
            'tenant_id' => $tenant->id,
            'username' => 'private-sources-store',
            'store_name' => 'Private Sources Store',
            'is_active' => true,
        ]);
        $supplier = Vendor::query()->create(['tenant_id' => $tenant->id, 'name' => 'Secret Supplier Name', 'status' => 'active']);
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)->post(route('admin.catalog.products.store'), $this->payload($tenant, [
            'supplier_ids' => [$supplier->id],
            'supplier_references' => [[
                'url' => 'https://secret-supplier.example/private-product-page',
            ]],
            'external_images' => [[
                'url' => 'https://images.example/storefront-fallback.jpg',
                'alt_text' => 'Sourced product front view',
            ]],
        ]))->assertRedirect();

        $this->get(route('storefront.storefront.store.products.show', [$store, 'sourced-product']))
            ->assertOk()
            ->assertDontSee('Secret Supplier Name')
            ->assertDontSee('secret-supplier.example', false)
            ->assertSee('https://images.example/storefront-fallback.jpg', false);

        $this->get(route('storefront.storefront.store.home', $store))
            ->assertOk()
            ->assertDontSee('Secret Supplier Name')
            ->assertDontSee('secret-supplier.example', false)
            ->assertSee('https://images.example/storefront-fallback.jpg', false);
    }

    public function test_uploaded_product_images_take_priority_over_external_image_links(): void
    {
        $tenant = $this->tenant('Image Priority', 'image-priority');
        $store = OnlineStore::query()->create([
            'tenant_id' => $tenant->id,
            'username' => 'image-priority-store',
            'store_name' => 'Image Priority Store',
            'is_active' => true,
        ]);
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)->post(route('admin.catalog.products.store'), $this->payload($tenant, [
            'external_images' => [['url' => 'https://images.example/should-not-display.jpg']],
        ]))->assertRedirect();

        $product = Product::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $product->update(['image_path' => 'tenants/example/catalog/products/uploaded.jpg']);

        $this->get(route('storefront.storefront.store.products.show', [$store, 'sourced-product']))
            ->assertOk()
            ->assertSee('/storage/tenants/example/catalog/products/uploaded.jpg', false)
            ->assertDontSee('https://images.example/should-not-display.jpg', false);

        $this->get(route('storefront.storefront.store.home', $store))
            ->assertOk()
            ->assertSee('/storage/tenants/example/catalog/products/uploaded.jpg', false)
            ->assertDontSee('https://images.example/should-not-display.jpg', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Tenant $tenant, array $overrides = []): array
    {
        return array_replace_recursive([
            'tenant_id' => $tenant->id,
            'product_type' => ProductType::Product->value,
            'name' => 'Sourced Product',
            'base_price' => '2500',
            'base_cost_price' => '1500',
            'tax_behavior' => TaxBehavior::Exempt->value,
            'status' => ProductStatus::Active->value,
            'has_variants' => '0',
        ], $overrides);
    }

    private function tenant(string $name, string $slug): Tenant
    {
        return Tenant::query()->create([
            'name' => $name,
            'slug' => $slug,
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
    }
}
