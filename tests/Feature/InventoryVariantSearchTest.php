<?php

declare(strict_types=1);

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

final class InventoryVariantSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_movement_dialogs_use_the_searchable_variant_picker(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Inventory Shop',
            'slug' => 'inventory-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Canvas Sneaker',
            'slug' => 'canvas-sneaker',
            'product_type' => ProductType::Product,
            'status' => ProductStatus::Active,
        ]);
        ProductVariant::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'variant_name' => 'Black / 42',
            'sku' => 'CANVAS-BLK-42',
            'status' => ProductStatus::Active,
        ]);
        $user = User::factory()->create(['is_platform_admin' => true]);

        $response = $this->actingAs($user)
            ->get(route('admin.inventory.index', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('Canvas Sneaker / Black / 42 (CANVAS-BLK-42)')
            ->assertSee('data-sku="CANVAS-BLK-42"', false)
            ->assertSee('data-variant-search-picker', false)
            ->assertSee('data-variant-search-options', false)
            ->assertSee('No matching product variants');

        $this->assertSame(2, substr_count($response->getContent(), '<div class="variant-search-picker" data-variant-search-picker>'));
        $this->assertSame(2, substr_count($response->getContent(), 'aria-label="Search product variants"'));
    }
}
