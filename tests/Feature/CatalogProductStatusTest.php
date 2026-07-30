<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

class CatalogProductStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_status_can_be_changed_from_the_catalog_listing(): void
    {
        $tenant = $this->tenant();
        $product = $this->product($tenant, ProductStatus::Draft);
        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($user)
            ->patch(route('admin.catalog.products.status.update', $product), [
                'tenant_id' => $tenant->id,
                'status' => ProductStatus::Active->value,
            ])
            ->assertRedirect(route('admin.catalog.index', ['tenant' => $tenant->id]).'#products')
            ->assertSessionHas('status', "{$product->name} is now Live.");

        $this->assertSame(ProductStatus::Active, $product->refresh()->status);
        $this->assertSame(ProductStatus::Active, $product->variants()->firstOrFail()->status);
    }

    public function test_product_with_variants_needs_a_live_variant_before_it_can_be_published(): void
    {
        $tenant = $this->tenant();
        $product = $this->product($tenant, ProductStatus::Draft, hasVariants: true);
        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($user)
            ->from(route('admin.catalog.index', ['tenant' => $tenant->id]).'#products')
            ->patch(route('admin.catalog.products.status.update', $product), [
                'tenant_id' => $tenant->id,
                'status' => ProductStatus::Active->value,
            ])
            ->assertRedirect(route('admin.catalog.index', ['tenant' => $tenant->id]).'#products')
            ->assertSessionHasErrors([
                'status' => 'Make at least one variant Live before publishing this product.',
            ]);

        $this->assertSame(ProductStatus::Draft, $product->refresh()->status);
    }

    public function test_product_with_variants_can_be_published_when_a_variant_is_live(): void
    {
        $tenant = $this->tenant();
        $product = $this->product($tenant, ProductStatus::Draft, hasVariants: true);
        $product->variants()->firstOrFail()->update(['status' => ProductStatus::Active]);
        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($user)
            ->patch(route('admin.catalog.products.status.update', $product), [
                'tenant_id' => $tenant->id,
                'status' => ProductStatus::Active->value,
            ])
            ->assertRedirect(route('admin.catalog.index', ['tenant' => $tenant->id]).'#products')
            ->assertSessionHasNoErrors();

        $this->assertSame(ProductStatus::Active, $product->refresh()->status);
        $this->assertSame(ProductStatus::Active, $product->variants()->firstOrFail()->status);
    }

    public function test_catalog_listing_displays_live_status_and_status_menu(): void
    {
        $tenant = $this->tenant();
        $product = $this->product($tenant, ProductStatus::Active);
        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($user)
            ->get(route('admin.catalog.index', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('Live')
            ->assertSee('Change product status')
            ->assertSee(route('admin.catalog.products.status.update', $product), false)
            ->assertSee('Edit Status Product')
            ->assertSee('Delete Status Product')
            ->assertSee(route('admin.catalog.products.destroy', $product), false);
    }

    public function test_product_can_be_soft_deleted_from_the_catalog_listing(): void
    {
        $tenant = $this->tenant();
        $product = $this->product($tenant, ProductStatus::Active);
        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($user)
            ->delete(route('admin.catalog.products.destroy', $product), [
                'tenant_id' => $tenant->id,
            ])
            ->assertRedirect(route('admin.catalog.index', ['tenant' => $tenant->id]).'#products')
            ->assertSessionHas('status', "{$product->name} deleted.");

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    private function tenant(): Tenant
    {
        return Tenant::query()->create([
            'name' => 'Status Shop',
            'slug' => 'status-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
    }

    private function product(Tenant $tenant, ProductStatus $status, bool $hasVariants = false): Product
    {
        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Status Product',
            'slug' => 'status-product',
            'product_type' => ProductType::Product,
            'has_variants' => $hasVariants,
            'status' => $status,
        ]);

        ProductVariant::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'variant_name' => $hasVariants ? 'Status Product - Small' : $product->name,
            'sku' => 'STATUS-'.$product->id,
            'selling_price_minor' => 1000,
            'cost_price_minor' => 500,
            'status' => $status,
        ]);

        return $product;
    }
}
