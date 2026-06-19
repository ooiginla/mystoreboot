<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Catalog\Enums\CategoryType;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Enums\TaxBehavior;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductAttributeDefinition;
use Modules\Catalog\Models\ProductCategory;
use Modules\Catalog\Models\ProductTag;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

class CatalogTagsAttributesTest extends TestCase
{
    use RefreshDatabase;

    public function test_tags_and_attributes_can_be_created_and_assigned_to_a_product(): void
    {
        Storage::fake('public');

        $tenant = Tenant::query()->create([
            'name' => 'Catalog Shop',
            'slug' => 'catalog-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
        $category = ProductCategory::query()->create([
            'tenant_id' => $tenant->id,
            'category_type' => CategoryType::Product->value,
            'name' => 'Clothing',
            'slug' => 'clothing',
            'status' => 'active',
        ]);
        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($user)
            ->post(route('admin.catalog.tags.store'), [
                'tenant_id' => $tenant->id,
                'name' => '50% Off',
            ])
            ->assertRedirect(route('admin.catalog.index', ['tenant' => $tenant->id]).'#tags');

        $this->actingAs($user)
            ->post(route('admin.catalog.attributes.store'), [
                'tenant_id' => $tenant->id,
                'name' => 'Color',
                'values' => 'Red, Blue, Green, Black',
            ])
            ->assertRedirect(route('admin.catalog.index', ['tenant' => $tenant->id]).'#attributes');

        $tag = ProductTag::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $attribute = ProductAttributeDefinition::query()->with('values')->where('tenant_id', $tenant->id)->firstOrFail();
        $red = $attribute->values->firstWhere('value', 'Red');
        $blue = $attribute->values->firstWhere('value', 'Blue');

        $this->actingAs($user)
            ->post(route('admin.catalog.products.store'), [
                'tenant_id' => $tenant->id,
                'product_type' => ProductType::Product->value,
                'category_id' => $category->id,
                'name' => 'Summer Shirt',
                'base_price' => '12000',
                'base_cost_price' => '7000',
                'image' => UploadedFile::fake()->image('summer-shirt.jpg', 900, 900),
                'status' => ProductStatus::Active->value,
                'tax_behavior' => TaxBehavior::Taxable->value,
                'tag_ids' => [$tag->id],
                'attribute_value_ids' => [$red->id, $blue->id],
            ])
            ->assertRedirect(route('admin.catalog.index', ['tenant' => $tenant->id]));

        $product = Product::query()->with(['tags', 'attributeValues.definition'])->where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertTrue($product->tags->contains($tag));
        $this->assertEqualsCanonicalizing(['Blue', 'Red'], $product->attributeValues->pluck('value')->all());
        $this->assertStringStartsWith("tenants/{$tenant->id}/catalog/products/", $product->image_path);
        Storage::disk('public')->assertExists($product->image_path);

        $this->actingAs($user)
            ->get(route('admin.catalog.index', ['tenant' => $tenant->id]).'#products')
            ->assertOk()
            ->assertSee('/storage/'.$product->image_path, false)
            ->assertSee('Tags')
            ->assertSee('Attributes')
            ->assertSee('Add tag')
            ->assertSee('Save attribute')
            ->assertSee('50% Off')
            ->assertSee('Color');
    }
}
