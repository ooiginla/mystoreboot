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
use Modules\Catalog\Models\ProductTax;
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
        ProductCategory::query()->create([
            'tenant_id' => $tenant->id,
            'parent_id' => $category->id,
            'category_type' => CategoryType::Product->value,
            'name' => 'Shirts',
            'slug' => 'shirts',
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

        $this->actingAs($user)
            ->post(route('admin.catalog.taxes.store'), [
                'tenant_id' => $tenant->id,
                'name' => 'VAT',
                'rate' => '7.50',
                'description' => 'Value added tax',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.catalog.index', ['tenant' => $tenant->id]).'#taxes');

        $tag = ProductTag::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $tax = ProductTax::query()->where('tenant_id', $tenant->id)->firstOrFail();
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
                'images' => [
                    UploadedFile::fake()->image('summer-shirt-side.jpg', 900, 900),
                    UploadedFile::fake()->image('summer-shirt-back.jpg', 900, 900),
                ],
                'status' => ProductStatus::Active->value,
                'tax_behavior' => TaxBehavior::Taxable->value,
                'tax_ids' => [$tax->id],
                'tag_ids' => [$tag->id],
                'attribute_value_ids' => [$red->id, $blue->id],
            ])
            ->assertRedirect(route('admin.catalog.index', ['tenant' => $tenant->id]));

        $product = Product::query()->with(['images', 'tags', 'taxes', 'attributeValues.definition'])->where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertTrue($product->tags->contains($tag));
        $this->assertTrue($product->taxes->contains($tax));
        $this->assertSame('7.50', $product->tax_rate);
        $this->assertEqualsCanonicalizing(['Blue', 'Red'], $product->attributeValues->pluck('value')->all());
        $this->assertStringStartsWith("tenants/{$tenant->id}/catalog/products/", $product->image_path);
        Storage::disk('public')->assertExists($product->image_path);
        $this->assertCount(2, $product->images);
        $product->images->each(function ($image) use ($tenant): void {
            $this->assertStringStartsWith("tenants/{$tenant->id}/catalog/products/", $image->image_path);
            Storage::disk('public')->assertExists($image->image_path);
        });

        $this->actingAs($user)
            ->get(route('admin.catalog.index', ['tenant' => $tenant->id]).'#products')
            ->assertOk()
            ->assertSee('/storage/'.$product->image_path, false)
            ->assertSee('Additional Product Images')
            ->assertSee('Drop files here or click to upload.')
            ->assertSee('Tags')
            ->assertSee('Attributes')
            ->assertSee('Taxes')
            ->assertSee('VAT')
            ->assertSee('Add tag')
            ->assertSee('Save attribute')
            ->assertSee('50% Off')
            ->assertSee('Color')
            ->assertSee('— Shirts');
    }

    public function test_tags_and_attributes_can_be_created_inline_while_saving_product(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Inline Catalog Shop',
            'slug' => 'inline-catalog-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
        $category = ProductCategory::query()->create([
            'tenant_id' => $tenant->id,
            'category_type' => CategoryType::Product->value,
            'name' => 'Shoes',
            'slug' => 'shoes',
            'status' => 'active',
        ]);
        $color = ProductAttributeDefinition::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Color',
            'slug' => 'color',
        ]);
        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($user)
            ->post(route('admin.catalog.products.store'), [
                'tenant_id' => $tenant->id,
                'product_type' => ProductType::Product->value,
                'category_id' => $category->id,
                'name' => 'Inline Sneaker',
                'base_price' => '25000',
                'base_cost_price' => '11000',
                'status' => ProductStatus::Active->value,
                'tax_behavior' => TaxBehavior::Taxable->value,
                'new_tags' => 'Featured, New Arrival',
                'new_attribute_values' => [
                    $color->id => 'Black, White',
                ],
                'new_attributes' => [
                    ['name' => 'Material', 'values' => 'Leather, Canvas'],
                ],
            ])
            ->assertRedirect(route('admin.catalog.index', ['tenant' => $tenant->id]));

        $product = Product::query()
            ->with(['tags', 'attributeValues.definition'])
            ->where('tenant_id', $tenant->id)
            ->where('name', 'Inline Sneaker')
            ->firstOrFail();

        $this->assertEqualsCanonicalizing(['Featured', 'New Arrival'], $product->tags->pluck('name')->all());
        $this->assertEqualsCanonicalizing(['Black', 'Canvas', 'Leather', 'White'], $product->attributeValues->pluck('value')->all());
        $this->assertTrue(
            $product->attributeValues->contains(fn ($value): bool => $value->value === 'Leather' && $value->definition?->name === 'Material')
        );

        $this->actingAs($user)
            ->get(route('admin.catalog.index', ['tenant' => $tenant->id]).'#products')
            ->assertOk()
            ->assertSee('Add new tag')
            ->assertSee('Create new attribute')
            ->assertSee('Add value under Color');
    }

    public function test_admin_product_and_service_lists_are_paginated(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Paged Catalog Shop',
            'slug' => 'paged-catalog-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
        $productCategory = ProductCategory::query()->create([
            'tenant_id' => $tenant->id,
            'category_type' => CategoryType::Product->value,
            'name' => 'Products',
            'slug' => 'products',
            'status' => 'active',
        ]);
        $serviceCategory = ProductCategory::query()->create([
            'tenant_id' => $tenant->id,
            'category_type' => CategoryType::Service->value,
            'name' => 'Services',
            'slug' => 'services',
            'status' => 'active',
        ]);
        $user = User::factory()->create(['is_platform_admin' => true]);

        foreach (range(1, 21) as $index) {
            Product::query()->create([
                'tenant_id' => $tenant->id,
                'category_id' => $productCategory->id,
                'product_type' => ProductType::Product->value,
                'name' => 'Paged Product '.$index,
                'slug' => 'paged-product-'.$index,
                'status' => ProductStatus::Active->value,
            ]);
            Product::query()->create([
                'tenant_id' => $tenant->id,
                'category_id' => $serviceCategory->id,
                'product_type' => ProductType::Service->value,
                'name' => 'Paged Service '.$index,
                'slug' => 'paged-service-'.$index,
                'status' => ProductStatus::Active->value,
            ]);
        }

        $this->actingAs($user)
            ->get(route('admin.catalog.index', ['tenant' => $tenant->id]).'#products')
            ->assertOk()
            ->assertSee('Paged Product 21')
            ->assertSee('products_page=2', false)
            ->assertSee('services_page=2', false);

        $this->actingAs($user)
            ->get(route('admin.catalog.index', ['tenant' => $tenant->id, 'products_page' => 2]).'#products')
            ->assertOk()
            ->assertSee('Paged Product 1')
            ->assertSee('products_page=1', false);

        $this->actingAs($user)
            ->get(route('admin.catalog.index', ['tenant' => $tenant->id, 'services_page' => 2]).'#services')
            ->assertOk()
            ->assertSee('Paged Service 1')
            ->assertSee('services_page=1', false);
    }
}
