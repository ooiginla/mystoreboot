<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Enums\CategoryType;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductCategory;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class CatalogCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_can_be_edited_and_soft_deleted_without_deleting_its_products(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Category Management Shop',
            'slug' => 'category-management-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
        $user = User::factory()->create(['is_platform_admin' => true]);
        $category = ProductCategory::query()->create([
            'tenant_id' => $tenant->id,
            'category_type' => CategoryType::Product,
            'name' => 'Seasonal',
            'slug' => 'seasonal',
            'status' => 'active',
        ]);
        $child = ProductCategory::query()->create([
            'tenant_id' => $tenant->id,
            'parent_id' => $category->id,
            'category_type' => CategoryType::Product,
            'name' => 'Holiday',
            'slug' => 'holiday',
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'product_type' => ProductType::Product,
            'name' => 'Seasonal Hamper',
            'slug' => 'seasonal-hamper',
            'status' => ProductStatus::Active,
            'base_price_minor' => 250000,
        ]);

        $this->actingAs($user)
            ->get(route('admin.catalog.index', ['tenant' => $tenant->id]).'#categories')
            ->assertOk()
            ->assertSee('category-edit-'.$category->id, false)
            ->assertSee(route('admin.catalog.categories.destroy', $category), false);

        $this->actingAs($user)
            ->put(route('admin.catalog.categories.update', $category), [
                'tenant_id' => $tenant->id,
                'category_type' => CategoryType::Product->value,
                'name' => 'Seasonal Offers',
                'slug' => 'seasonal-offers',
                'description' => 'Limited-time products.',
            ])
            ->assertRedirect(route('admin.catalog.index', ['tenant' => $tenant->id]).'#categories');

        $this->assertDatabaseHas('product_categories', [
            'id' => $category->id,
            'name' => 'Seasonal Offers',
            'slug' => 'seasonal-offers',
            'deleted_at' => null,
        ]);

        $this->actingAs($user)
            ->delete(route('admin.catalog.categories.destroy', $category))
            ->assertRedirect(route('admin.catalog.index', ['tenant' => $tenant->id]).'#categories');

        $fallback = ProductCategory::query()
            ->where('tenant_id', $tenant->id)
            ->where('category_type', CategoryType::Product)
            ->where('name', 'Uncategorized')
            ->firstOrFail();

        $this->assertSoftDeleted('product_categories', ['id' => $category->id]);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'category_id' => $fallback->id,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('product_categories', [
            'id' => $child->id,
            'parent_id' => null,
            'deleted_at' => null,
        ]);
    }
}
