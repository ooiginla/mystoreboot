<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Business\Models\OnlineStore;
use Modules\Catalog\Enums\CategoryType;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductCategory;
use Modules\Customers\Models\SupportTicket;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

class StorefrontFrontendTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_storefront_uses_online_store_configuration(): void
    {
        [$tenant, $store] = $this->storeFixture();
        $category = ProductCategory::query()->create([
            'tenant_id' => $tenant->id,
            'category_type' => CategoryType::Product->value,
            'name' => 'Footwear',
            'slug' => 'footwear',
            'status' => 'active',
        ]);
        $store->categories()->attach($category->id);
        Product::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'name' => 'City Runner',
            'slug' => 'city-runner',
            'status' => ProductStatus::Active->value,
            'base_price_minor' => 250000,
        ]);

        $this->get(route('storefront.storefront.store.home', $store))
            ->assertOk()
            ->assertSee('Demo Store')
            ->assertSee('Free delivery today')
            ->assertSee('All Categories')
            ->assertSee('Footwear')
            ->assertSee('Launch collection')
            ->assertSee('Our Products')
            ->assertSee('City Runner')
            ->assertSee('WhatsApp', false);
    }

    public function test_storefront_products_are_paginated_and_link_to_product_details(): void
    {
        [$tenant, $store] = $this->storeFixture();
        $category = ProductCategory::query()->create([
            'tenant_id' => $tenant->id,
            'category_type' => CategoryType::Product->value,
            'name' => 'Accessories',
            'slug' => 'accessories',
            'status' => 'active',
        ]);
        $store->categories()->attach($category->id);

        foreach (range(1, 17) as $index) {
            Product::query()->create([
                'tenant_id' => $tenant->id,
                'category_id' => $category->id,
                'name' => 'Product '.$index,
                'slug' => 'product-'.$index,
                'description' => 'Helpful product details '.$index,
                'status' => ProductStatus::Active->value,
                'base_price_minor' => 100000 + $index,
                'created_at' => now()->subMinutes($index),
                'updated_at' => now()->subMinutes($index),
            ]);
        }

        $this->get(route('storefront.storefront.store.home', $store))
            ->assertOk()
            ->assertSee('Product 1')
            ->assertSee('Product 16')
            ->assertDontSee('Product 17')
            ->assertSee(route('storefront.storefront.store.products.show', [$store, 'product-1']), false)
            ->assertSee('Next', false);

        $this->get(route('storefront.storefront.store.products.show', [$store, 'product-1']))
            ->assertOk()
            ->assertSee('Product Description')
            ->assertSee('Reviews')
            ->assertSee('Share this product')
            ->assertSee('YOU MIGHT ALSO LIKE');
    }

    public function test_services_are_moved_to_services_menu_and_excluded_from_product_listing(): void
    {
        [$tenant, $store] = $this->storeFixture();
        $category = ProductCategory::query()->create([
            'tenant_id' => $tenant->id,
            'category_type' => CategoryType::Product->value,
            'name' => 'Catalog',
            'slug' => 'catalog',
            'status' => 'active',
        ]);
        $store->categories()->attach($category->id);

        Product::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'name' => 'Physical Product',
            'slug' => 'physical-product',
            'product_type' => ProductType::Product->value,
            'status' => ProductStatus::Active->value,
            'base_price_minor' => 150000,
        ]);
        Product::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'name' => 'Installation Service',
            'slug' => 'installation-service',
            'product_type' => ProductType::Service->value,
            'status' => ProductStatus::Active->value,
            'base_price_minor' => 250000,
        ]);

        $this->get(route('storefront.storefront.store.home', $store))
            ->assertOk()
            ->assertSee('Services')
            ->assertSee('Physical Product')
            ->assertDontSee('Installation Service');

        $this->get(route('storefront.storefront.store.services', $store))
            ->assertOk()
            ->assertSee('Our Services')
            ->assertSee('Installation Service')
            ->assertDontSee('Physical Product');

        $this->get(route('storefront.storefront.store.services.show', [$store, 'installation-service']))
            ->assertOk()
            ->assertSee('Installation Service')
            ->assertSee('Product Description');

        $this->get(route('storefront.storefront.store.products.show', [$store, 'installation-service']))
            ->assertNotFound();
    }

    public function test_storefront_maintenance_mode_shows_be_back_message(): void
    {
        [, $store] = $this->storeFixture(['maintenance_mode' => true]);

        $this->get(route('storefront.storefront.store.home', $store))
            ->assertOk()
            ->assertSee('We will be back soon')
            ->assertDontSee('Our Products');
    }

    public function test_storefront_content_pages_use_configured_copy(): void
    {
        [, $store] = $this->storeFixture([
            'pages' => [
                'about_us' => 'Built for careful shoppers.',
                'terms_of_use' => 'Use the store fairly.',
                'return_policy' => 'Returns within seven days.',
                'privacy_policy' => 'We protect your data.',
                'shipping_information' => 'Ships within Lagos.',
            ],
            'faqs' => [
                ['question' => 'Do you deliver?', 'answer' => 'Yes, we do.'],
            ],
        ]);

        $this->get(route('storefront.storefront.store.about', $store))->assertOk()->assertSee('Built for careful shoppers.');
        $this->get(route('storefront.storefront.store.faq', $store))->assertOk()->assertSee('Do you deliver?')->assertSee('Yes, we do.');
        $this->get(route('storefront.storefront.store.refunds', $store))->assertOk()->assertSee('Returns within seven days.');
        $this->get(route('storefront.storefront.store.privacy', $store))->assertOk()->assertSee('We protect your data.');
        $this->get(route('storefront.storefront.store.shipping', $store))->assertOk()->assertSee('Ships within Lagos.');
    }

    public function test_contact_page_creates_customer_support_ticket(): void
    {
        [, $store] = $this->storeFixture();

        $this->post(route('storefront.storefront.store.contact.submit', $store), [
            'name' => 'Ada Lovelace',
            'phone' => '08030000000',
            'email' => 'ada@example.com',
            'subject' => 'Delivery question',
            'message' => 'Can you deliver tomorrow?',
        ])
            ->assertRedirect();

        $this->assertDatabaseHas('customers', [
            'tenant_id' => $store->tenant_id,
            'phone' => '08030000000',
            'first_name' => 'Ada',
        ]);
        $this->assertDatabaseHas('support_tickets', [
            'tenant_id' => $store->tenant_id,
            'subject' => 'Delivery question',
            'category' => 'Online store contact',
        ]);
        $this->assertSame(1, SupportTicket::query()->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{Tenant, OnlineStore}
     */
    private function storeFixture(array $overrides = []): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Demo Tenant',
            'slug' => 'demo-tenant',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);

        $store = OnlineStore::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'username' => 'demo-store',
            'store_name' => 'Demo Store',
            'announcement' => 'Free delivery today',
            'store_phone' => '08010000000',
            'store_whatsapp' => '08020000000',
            'hero_image_text' => 'Launch collection',
            'hero_image_description' => 'Shop the newest arrivals.',
            'theme_primary_color' => '#005f73',
            'theme_secondary_color' => '#ee9b00',
            'payment_methods' => ['pay_on_delivery', 'bank_account'],
            'shipping_options' => [['location' => 'Lagos', 'price' => 1500]],
            'social_accounts' => ['whatsapp' => '08020000000'],
            'pages' => [],
            'faqs' => [],
            'is_active' => true,
            'maintenance_mode' => false,
        ], $overrides));

        return [$tenant, $store];
    }
}
