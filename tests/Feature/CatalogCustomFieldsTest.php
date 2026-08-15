<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Enums\CategoryType;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Enums\TaxBehavior;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductCategory;
use Modules\Catalog\Models\ProductCustomDefinition;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class CatalogCustomFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_fields_can_be_created_and_edited_from_product_tabs(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Custom Catalog',
            'slug' => 'custom-catalog',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
        $category = ProductCategory::query()->create([
            'tenant_id' => $tenant->id,
            'category_type' => CategoryType::Product->value,
            'name' => 'Groceries',
            'slug' => 'groceries',
            'status' => 'active',
        ]);
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.catalog.index', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertDontSee('data-tab-target="custom"', false)
            ->assertSee('data-tab-target="tags-attributes"', false)
            ->assertSee('data-catalog-management-accordion="custom"', false)
            ->assertSee('data-accordion-icon="custom"', false)
            ->assertDontSee('href="#product-dialog-custom"', false)
            ->assertSee('Custom product choices')
            ->assertSee('data-dialog-open="custom-definition-dialog"', false)
            ->assertSee('data-custom-definition-tag-input', false)
            ->assertSee('data-custom-definition-value-input', false)
            ->assertSee('data-custom-definition-values', false);
        $this->actingAs($admin)
            ->get(route('admin.catalog.index', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('Personalization')
            ->assertSee('Enable personalization for this product')
            ->assertSee('name="personalization[fields][customized_text]"', false)
            ->assertSee('name="personalization[fields][additional_info]"', false)
            ->assertSee('name="personalization[fields][photograph]"', false);

        $this->post(route('admin.catalog.custom-definitions.store'), [
            'tenant_id' => $tenant->id,
            'name' => 'Unit',
            'values' => '1, 2, 3, 4, 5',
            'is_customer_selectable' => '1',
        ])->assertRedirect(route('admin.catalog.index', ['tenant' => $tenant->id]).'#tags-attributes')
            ->assertSessionHas('catalog_accordion', 'custom');

        $this->assertTrue(ProductCustomDefinition::query()->where('tenant_id', $tenant->id)->where('name', 'Unit')->firstOrFail()->is_customer_selectable);

        $this->post(route('admin.catalog.custom-definitions.store'), [
            'tenant_id' => $tenant->id,
            'name' => 'Source location',
            'values' => 'Ikeja, Ogba, Lagos Island',
        ])->assertRedirect(route('admin.catalog.index', ['tenant' => $tenant->id]).'#tags-attributes')
            ->assertSessionHas('catalog_accordion', 'custom');

        $this->get(route('admin.catalog.index', ['tenant' => $tenant->id]).'#tags-attributes')
            ->assertOk()
            ->assertSee('Unit')
            ->assertSee('Lagos Island')
            ->assertSee('5 values');

        $payload = [
            'tenant_id' => $tenant->id,
            'product_type' => ProductType::Product->value,
            'category_id' => $category->id,
            'name' => 'Market Bundle',
            'base_price' => '2500',
            'base_cost_price' => '1500',
            'tax_behavior' => TaxBehavior::Exempt->value,
            'status' => ProductStatus::Active->value,
            'has_variants' => '0',
            'custom_fields' => [
                ['key' => 'Unit', 'values' => '1, 2, 3, 4, 5', 'is_assigned' => '1', 'is_customer_selectable' => '1'],
                ['key' => 'Source location', 'values' => 'Ikeja, Ogba, Lagos Island', 'is_assigned' => '1', 'is_customer_selectable' => '0'],
            ],
            'personalization' => [
                'enabled' => '1',
                'fields' => [
                    'customized_text' => '1',
                    'additional_info' => '1',
                    'photograph' => '0',
                ],
            ],
        ];

        $this->post(route('admin.catalog.products.store'), $payload)
            ->assertRedirect(route('admin.catalog.index', ['tenant' => $tenant->id]));

        $product = Product::query()->where('tenant_id', $tenant->id)->where('slug', 'market-bundle')->firstOrFail();
        $this->assertSame(['1', '2', '3', '4', '5'], $product->custom_fields[0]['values']);
        $this->assertTrue($product->custom_fields[0]['is_customer_selectable']);
        $this->assertFalse($product->custom_fields[1]['is_customer_selectable']);
        $this->assertSame([
            'enabled' => true,
            'fields' => ['customized_text' => true, 'additional_info' => true, 'photograph' => false],
        ], $product->personalization_settings);

        $this->get(route('admin.catalog.index', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertDontSee('href="#edit-product-'.$product->id.'-custom"', false)
            ->assertSee('href="#edit-product-'.$product->id.'-tags-attributes"', false)
            ->assertSee('href="#edit-product-'.$product->id.'-personalization"', false)
            ->assertSee('Assign to product')
            ->assertSee('Lagos Island');

        $this->put(route('admin.catalog.products.update', $product), array_replace($payload, [
            'custom_fields' => [
                ['key' => 'Unit', 'values' => '1, 2, 3, 4, 5', 'is_assigned' => '0', 'is_customer_selectable' => '0'],
                ['key' => 'Source location', 'values' => 'Ikeja, Ogba, Lagos Island', 'is_assigned' => '1', 'is_customer_selectable' => '1'],
            ],
        ]))->assertRedirect(route('admin.catalog.index', ['tenant' => $tenant->id]));

        $this->assertSame([
            ['key' => 'Source location', 'values' => ['Ikeja', 'Ogba', 'Lagos Island'], 'is_customer_selectable' => true],
        ], $product->refresh()->custom_fields);
    }
}
