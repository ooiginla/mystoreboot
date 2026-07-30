<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Business\Models\OnlineStore;
use Modules\Catalog\Actions\EnsureDefaultProductCategoryAction;
use Modules\Catalog\Enums\CategoryType;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductCategory;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

class DefaultProductCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_uncategorized_is_created_and_used_for_products_and_online_stores(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Default Category Shop',
            'slug' => 'default-category-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);

        $category = ProductCategory::query()
            ->where('tenant_id', $tenant->id)
            ->where('category_type', CategoryType::Product->value)
            ->where('name', 'Uncategorized')
            ->firstOrFail();

        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Unsorted Product',
            'slug' => 'unsorted-product',
            'product_type' => ProductType::Product,
            'status' => ProductStatus::Active,
            'base_price_minor' => 100000,
        ]);

        $this->assertSame($category->id, $product->category_id);

        $store = OnlineStore::query()->create([
            'tenant_id' => $tenant->id,
            'username' => 'default-category-store',
            'store_name' => 'Default Category Store',
            'is_active' => true,
        ]);

        $this->assertTrue($store->categories()->whereKey($category->id)->exists());

        DB::table('products')->where('id', $product->id)->update(['category_id' => null]);

        app(EnsureDefaultProductCategoryAction::class)->execute($tenant->id);

        $this->assertSame($category->id, $product->fresh()->category_id);
        $this->assertSame(
            1,
            ProductCategory::query()
                ->where('tenant_id', $tenant->id)
                ->where('name', 'Uncategorized')
                ->count()
        );
    }
}
