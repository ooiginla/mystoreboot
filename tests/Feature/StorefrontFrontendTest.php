<?php

namespace Tests\Feature;

use App\Mail\OnlineOrderConfirmationMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Modules\Business\Models\OnlineStore;
use Modules\Catalog\Enums\CategoryType;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductCategory;
use Modules\Catalog\Models\ProductOption;
use Modules\Catalog\Models\ProductOptionValue;
use Modules\Catalog\Models\ProductVariant;
use Modules\Customers\Models\Customer;
use Modules\Customers\Models\CustomerAddress;
use Modules\Customers\Models\SupportTicket;
use Modules\Sales\Models\SalesOrder;
use Modules\Storefront\Http\Controllers\StorefrontController;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

class StorefrontFrontendTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_storefront_uses_online_store_configuration(): void
    {
        [$tenant, $store] = $this->storeFixture([
            'slides' => [
                [
                    'image_path' => 'tenants/demo/online-store/heroes/slide-one.jpg',
                    'hero_image_tag' => 'Fresh drop',
                    'hero_image_text' => 'Launch collection',
                    'hero_image_description' => 'Shop the newest arrivals.',
                ],
                [
                    'image_path' => 'tenants/demo/online-store/heroes/slide-two.jpg',
                    'hero_image_tag' => 'Wholesale',
                    'hero_image_text' => 'Bulk delivery made easy',
                    'hero_image_description' => 'Restock fast with reliable fulfilment.',
                ],
            ],
        ]);
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

        $response = $this->get(route('storefront.storefront.store.home', $store))
            ->assertOk()
            ->assertSee('Demo Store')
            ->assertSee('Free delivery today')
            ->assertSee('All Categories')
            ->assertSee('Footwear')
            ->assertSee('data-mobile-nav-toggle', false)
            ->assertSee('data-mobile-menu-label>Menu</span>', false)
            ->assertSee('id="store-mobile-nav"', false)
            ->assertSee('data-continue-shopping', false)
            ->assertSee('href="'.route('storefront.storefront.store.home', $store).'"', false)
            ->assertSee('Your order has been placed successfully and your cart has been cleared.')
            ->assertSee('Launch collection')
            ->assertSee('Bulk delivery made easy')
            ->assertSee('data-store-hero-slider', false)
            ->assertSee('data-store-hero-next', false)
            ->assertSee('Our Products')
            ->assertSee('City Runner')
            ->assertSee('Lagos (3-5 days)')
            ->assertSee('Save this address for future use')
            ->assertSee("if (saveAddressCheckbox?.checked) requiredFields.push('checkout_address_label');", false)
            ->assertSee("name.startsWith('customer.') || name === 'shipping_option'", false)
            ->assertSee('placeholder="Recipient Full name"', false)
            ->assertSee('placeholder="Recipient Phone Number(s)"', false)
            ->assertSee('placeholder="Recipient Delivery Address"', false)
            ->assertSee('placeholder="Recipient City"', false)
            ->assertSee('Additional Info')
            ->assertSee('name="checkout_notes"', false)
            ->assertSee('data-progress-step="payment"', false)
            ->assertDontSee('data-progress-step="confirm"', false)
            ->assertSee('Use a new address')
            ->assertSee('WhatsApp', false);

        $this->assertLessThan(
            strpos($response->getContent(), 'name="checkout_name"'),
            strpos($response->getContent(), 'name="checkout_email"'),
        );
    }

    public function test_checkout_customer_lookup_is_scoped_to_the_store_tenant(): void
    {
        [$tenant, $store] = $this->storeFixture();
        $customer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Ada',
            'last_name' => 'Buyer',
            'email' => 'ADA@example.com',
            'phone' => '08030000000',
            'address' => '12 Marina Road, Lagos',
            'city' => 'Lagos',
        ]);
        CustomerAddress::query()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'label' => 'Home',
            'address' => '12 Marina Road, Lagos',
            'city' => 'Lagos',
            'is_default' => true,
            'last_used_at' => now(),
        ]);
        $otherTenant = Tenant::query()->create([
            'name' => 'Another Tenant',
            'slug' => 'another-tenant',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
        Customer::query()->create([
            'tenant_id' => $otherTenant->id,
            'first_name' => 'Wrong',
            'email' => 'ada@example.com',
            'phone' => '00000000000',
            'address' => 'Wrong address',
        ]);

        $this->postJson(route('storefront.storefront.store.checkout.customer-lookup', $store), [
            'email' => 'ada@example.com',
        ])
            ->assertOk()
            ->assertJson([
                'found' => true,
                'customer' => [
                    'name' => 'Ada Buyer',
                    'phone' => '08030000000',
                    'address' => '12 Marina Road, Lagos',
                    'city' => 'Lagos',
                    'addresses' => [
                        [
                            'label' => 'Home',
                            'address' => '12 Marina Road, Lagos',
                            'city' => 'Lagos',
                            'is_default' => true,
                        ],
                    ],
                ],
            ]);
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
                'specifications' => $index === 1 ? "Material: Cotton\nFit - Regular\nCare | Machine wash\nWi-Fi compatible" : null,
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
            ->assertSee('Specifications')
            ->assertSee('Cotton')
            ->assertSee('Regular')
            ->assertSee('Machine wash')
            ->assertSee('Wi-Fi compatible')
            ->assertSee('<strong class="text-[var(--store-ink)]">Material:</strong>', false)
            ->assertSee('<strong class="text-[var(--store-ink)]">Fit -</strong>', false)
            ->assertSee('<strong class="text-[var(--store-ink)]">Care |</strong>', false)
            ->assertSee('divide-y divide-[var(--store-line)]', false)
            ->assertSee('Reviews')
            ->assertSee('Share this product')
            ->assertSee('YOU MIGHT ALSO LIKE');

        Product::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Uncategorised Product',
            'slug' => 'uncategorised-product',
            'status' => ProductStatus::Active->value,
            'base_price_minor' => 50000,
        ]);

        $this->get(route('storefront.storefront.store.categories.show', [$store, $category->slug]))
            ->assertOk()
            ->assertSee('Accessories')
            ->assertSee('Product 1')
            ->assertDontSee('Uncategorised Product')
            ->assertDontSee('<section class="store-hero', false)
            ->assertDontSee('<div class="relative mt-8" data-collection-carousel>', false);
    }

    public function test_storefront_search_filters_visible_products_and_preserves_the_query(): void
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
            'description' => 'Lightweight everyday trainers',
            'status' => ProductStatus::Active->value,
            'base_price_minor' => 250000,
        ]);
        Product::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'name' => 'Leather Brogue',
            'slug' => 'leather-brogue',
            'status' => ProductStatus::Active->value,
            'base_price_minor' => 320000,
        ]);

        $this->get(route('storefront.storefront.store.home', [$store, 'search' => 'runner']))
            ->assertOk()
            ->assertSee('data-store-search', false)
            ->assertSee('placeholder="Find Products"', false)
            ->assertSee('value="runner"', false)
            ->assertSee('Search results for “runner”')
            ->assertSee('1 product found.')
            ->assertSee('City Runner')
            ->assertDontSee('Leather Brogue')
            ->assertDontSee('<section class="store-hero', false)
            ->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    public function test_variant_products_show_from_price_and_expose_live_detail_pricing(): void
    {
        [$tenant, $store] = $this->storeFixture();
        $category = ProductCategory::query()->create([
            'tenant_id' => $tenant->id,
            'category_type' => CategoryType::Product->value,
            'name' => 'Clothing',
            'slug' => 'clothing',
            'status' => ProductStatus::Active->value,
        ]);
        $store->categories()->attach($category->id);
        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'name' => 'Everyday Shirt',
            'slug' => 'everyday-shirt',
            'product_type' => ProductType::Product->value,
            'has_variants' => true,
            'status' => ProductStatus::Active->value,
            'base_price_minor' => 999999,
        ]);
        $option = ProductOption::query()->create([
            'product_id' => $product->id,
            'name' => 'Size',
            'sort_order' => 0,
        ]);
        $small = ProductOptionValue::query()->create([
            'product_option_id' => $option->id,
            'value' => 'Small',
            'sort_order' => 0,
        ]);
        $medium = ProductOptionValue::query()->create([
            'product_option_id' => $option->id,
            'value' => 'Medium',
            'sort_order' => 1,
        ]);
        $large = ProductOptionValue::query()->create([
            'product_option_id' => $option->id,
            'value' => 'Large',
            'sort_order' => 2,
        ]);

        $variantRows = [
            ['name' => 'Small', 'sku' => 'SHIRT-S', 'price' => 120000, 'compare' => null, 'status' => ProductStatus::Active, 'value' => $small],
            ['name' => 'Medium', 'sku' => 'SHIRT-M', 'price' => 180000, 'compare' => 200000, 'status' => ProductStatus::Active, 'value' => $medium],
            ['name' => 'Large', 'sku' => 'SHIRT-L', 'price' => 240000, 'compare' => null, 'status' => ProductStatus::Active, 'value' => $large],
            ['name' => 'Discontinued', 'sku' => 'SHIRT-X', 'price' => 50000, 'compare' => null, 'status' => ProductStatus::Discontinued, 'value' => $large],
        ];

        foreach ($variantRows as $row) {
            $variant = ProductVariant::query()->create([
                'tenant_id' => $tenant->id,
                'product_id' => $product->id,
                'variant_name' => $row['name'],
                'sku' => $row['sku'],
                'selling_price_minor' => $row['price'],
                'compare_at_price_minor' => $row['compare'],
                'cost_price_minor' => 50000,
                'status' => $row['status']->value,
            ]);
            $variant->optionValues()->attach($row['value']->id);
        }

        $detailsUrl = route('storefront.storefront.store.products.show', [$store, $product->slug]);

        $this->get(route('storefront.storefront.store.home', $store))
            ->assertOk()
            ->assertSee('From ₦1,200.00')
            ->assertSee('data-variant-price-mode="from"', false)
            ->assertSee('store-product-card', false)
            ->assertSee('store-product-card-price', false)
            ->assertSee('store-product-card-action', false)
            ->assertSee('Choose options')
            ->assertSee($detailsUrl, false)
            ->assertDontSee('₦9,999.99')
            ->assertDontSee('₦500.00');

        $this->get($detailsUrl)
            ->assertOk()
            ->assertSee('data-variant-option', false)
            ->assertSee('Small · SKU SHIRT-S')
            ->assertSee('"priceMinor":120000', false)
            ->assertSee('"priceMinor":180000', false)
            ->assertSee('"priceMinor":240000', false)
            ->assertSee('"compareMinor":200000', false)
            ->assertSee('data-variant-cart-button', false)
            ->assertDontSee('SHIRT-X')
            ->assertDontSee('"priceMinor":50000', false)
            ->assertDontSee('₦9,999.99');
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

        foreach (['home', 'about', 'faq', 'contact', 'track'] as $routeName) {
            $this->get(route('storefront.storefront.store.'.$routeName, $store))
                ->assertOk()
                ->assertSee('We will be back soon')
                ->assertSee($store->store_name)
                ->assertDontSee('Our Products');
        }

        $this->get(route('storefront.storefront.store.products.show', [$store, 'direct-product-link']))
            ->assertOk()
            ->assertSee('We will be back soon');
    }

    public function test_order_tracking_accepts_sales_order_reference_and_remains_tenant_scoped(): void
    {
        [$firstTenant, $firstStore] = $this->storeFixture([
            'username' => 'first-tracking-store',
            'subdomain' => 'first-tracking-store',
            'store_name' => 'First Tracking Store',
        ]);
        $secondTenant = Tenant::query()->create([
            'name' => 'Second Tracking Tenant',
            'slug' => 'second-tracking-tenant',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
        $secondStore = OnlineStore::query()->create([
            'tenant_id' => $secondTenant->id,
            'username' => 'second-tracking-store',
            'subdomain' => 'second-tracking-store',
            'store_name' => 'Second Tracking Store',
            'theme_primary_color' => '#005f73',
            'theme_secondary_color' => '#ee9b00',
            'payment_methods' => [],
            'shipping_options' => [],
            'social_accounts' => [],
            'pages' => [],
            'faqs' => [],
            'is_active' => true,
            'maintenance_mode' => false,
        ]);
        $sharedOrderNumber = 'SO-20260813-00002';

        $firstOrder = SalesOrder::query()->create([
            'tenant_id' => $firstTenant->id,
            'source' => 'online',
            'order_number' => $sharedOrderNumber,
            'invoice_number' => 'INV-FIRST-00002',
            'receipt_number' => 'RCT-FIRST-00002',
            'order_status' => 'pending',
            'payment_status' => 'unpaid',
            'order_date' => now()->toDateString(),
            'subtotal_minor' => 125000,
            'total_minor' => 125000,
        ]);
        $secondOrder = SalesOrder::query()->create([
            'tenant_id' => $secondTenant->id,
            'source' => 'online',
            'order_number' => $sharedOrderNumber,
            'invoice_number' => 'INV-SECOND-00002',
            'receipt_number' => 'RCT-SECOND-00002',
            'order_status' => 'processing',
            'payment_status' => 'paid',
            'order_date' => now()->toDateString(),
            'subtotal_minor' => 975000,
            'total_minor' => 975000,
        ]);

        $this->get(route('storefront.storefront.store.track', [
            $firstStore,
            'reference' => strtolower($sharedOrderNumber),
        ]))
            ->assertOk()
            ->assertSee('Order '.$sharedOrderNumber)
            ->assertSee('₦ 1,250.00')
            ->assertDontSee('₦ 9,750.00');

        $this->get(route('storefront.storefront.store.track', [
            $firstStore,
            'reference' => $secondOrder->tracking_reference,
        ]))
            ->assertOk()
            ->assertSee('No order found')
            ->assertDontSee('Order '.$sharedOrderNumber);

        $this->get(route('storefront.storefront.store.track', [
            $secondStore,
            'reference' => $firstOrder->tracking_reference,
        ]))
            ->assertOk()
            ->assertSee('No order found');
    }

    public function test_storefront_content_pages_use_configured_copy(): void
    {
        [, $store] = $this->storeFixture([
            'pages' => [
                'about_us' => '<h2 onclick="alert(1)">Our <strong>story</strong></h2><script>alert("unsafe")</script><p>Built for careful shoppers. <a href="javascript:alert(1)">Bad link</a> <a href="https://example.com">Good link</a></p>',
                'terms_of_use' => 'Use the store fairly.',
                'return_policy' => 'Returns within seven days.',
                'privacy_policy' => 'We protect your data.',
                'shipping_information' => 'Ships within Lagos.',
            ],
            'faqs' => [
                ['question' => 'Do you deliver?', 'answer' => 'Yes, we do.'],
            ],
        ]);

        $this->get(route('storefront.storefront.store.about', $store))
            ->assertOk()
            ->assertSee('<h2>Our <strong>story</strong></h2>', false)
            ->assertSee('Built for careful shoppers.')
            ->assertSee('<a>Bad link</a>', false)
            ->assertSee('<a href="https://example.com">Good link</a>', false)
            ->assertDontSee('alert("unsafe")', false)
            ->assertDontSee('onclick=', false)
            ->assertDontSee('javascript:', false);
        $this->get(route('storefront.storefront.store.faq', $store))->assertOk()->assertSee('Do you deliver?')->assertSee('Yes, we do.');
        $this->get(route('storefront.storefront.store.refunds', $store))->assertOk()->assertSee('Returns within seven days.');
        $this->get(route('storefront.storefront.store.privacy', $store))->assertOk()->assertSee('We protect your data.');
        $this->get(route('storefront.storefront.store.shipping', $store))->assertOk()->assertSee('Ships within Lagos.');
    }

    public function test_bank_transfer_details_are_labeled_and_hidden_until_selected(): void
    {
        [, $store] = $this->storeFixture([
            'payment_methods' => ['pay_on_delivery', 'bank_account', 'storeboot_paystack'],
            'bank_accounts' => [
                ['bank_name' => 'GTB', 'account_name' => 'Reno Supermart', 'account_number' => '0009987892'],
            ],
        ]);

        $response = $this->get(route('storefront.storefront.store.home', $store))
            ->assertOk()
            ->assertSee('data-bank-transfer-details hidden', false)
            ->assertSee('Bank Name:', false)
            ->assertSee('GTB')
            ->assertSee('Account Name:', false)
            ->assertSee('Reno Supermart')
            ->assertSee('Account Number:', false)
            ->assertSee('0009987892')
            ->assertDontSee('GTB | Reno Supermart | 0009987892');

        $content = $response->getContent();
        $this->assertLessThan(strpos($content, 'Bank transfer details'), strpos($content, 'value="bank_account"'));
        $this->assertLessThan(strpos($content, 'value="storeboot_paystack"'), strpos($content, 'Bank transfer details'));
    }

    public function test_contact_page_creates_customer_support_ticket(): void
    {
        [, $store] = $this->storeFixture([
            'hero_image_path' => 'tenants/demo/online-store/heroes/contact-banner.jpg',
            'site_email' => 'hello@demo-store.test',
            'address' => '10 Market Road',
            'city' => 'Lagos',
            'country' => 'Nigeria',
        ]);

        $this->get(route('storefront.storefront.store.contact', $store))
            ->assertOk()
            ->assertSee('Home')
            ->assertSee('Contact Us')
            ->assertSee('Get in touch with us')
            ->assertSee('contact-layout', false)
            ->assertSee('/storage/tenants/demo/online-store/heroes/contact-banner.jpg', false)
            ->assertSee('08010000000')
            ->assertSee('hello@demo-store.test')
            ->assertSee('10 Market Road, Lagos, Nigeria')
            ->assertSee('Send us a message')
            ->assertSee('Your message here...');

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

    public function test_checkout_creates_pending_online_sales_order_and_returns_reference(): void
    {
        Mail::fake();
        Storage::fake('public');
        [$tenant, $store] = $this->storeFixture();
        $category = ProductCategory::query()->create([
            'tenant_id' => $tenant->id,
            'category_type' => CategoryType::Product->value,
            'name' => 'Footwear',
            'slug' => 'footwear',
            'status' => 'active',
        ]);
        $store->categories()->attach($category->id);
        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'name' => 'City Runner',
            'slug' => 'city-runner',
            'status' => ProductStatus::Active->value,
            'base_price_minor' => 250000,
            'custom_fields' => [
                ['key' => 'Unit', 'values' => ['1', '2', '3'], 'is_customer_selectable' => true],
                ['key' => 'Internal source', 'values' => ['Warehouse A'], 'is_customer_selectable' => false],
            ],
            'personalization_settings' => [
                'enabled' => true,
                'fields' => ['customized_text' => true, 'additional_info' => true, 'photograph' => true],
            ],
        ]);
        Product::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'name' => 'Related Plain Product',
            'slug' => 'related-plain-product',
            'product_type' => ProductType::Product->value,
            'status' => ProductStatus::Active->value,
            'base_price_minor' => 100000,
        ]);
        $variant = ProductVariant::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'variant_name' => 'Default',
            'sku' => 'CITY-RUNNER',
            'selling_price_minor' => 250000,
            'cost_price_minor' => 120000,
            'status' => ProductStatus::Active->value,
        ]);

        $this->get(route('storefront.storefront.store.products.show', [$store, $product->slug]))
            ->assertOk()
            ->assertSee('data-custom-key="Unit"', false)
            ->assertSee('<option value="2">2</option>', false)
            ->assertSee('Would you like to personalise this item?')
            ->assertSee('No, keep it plain')
            ->assertSee('Yes, personalise it')
            ->assertSee('Customized Text')
            ->assertSee('Additional Info/Note')
            ->assertSee('Upload photograph')
            ->assertSee("body.append('product_id', {$product->id})", false)
            ->assertDontSee('Internal source')
            ->assertDontSee('Warehouse A');

        $photoUpload = $this->post(route('storefront.storefront.store.personalization.photo', $store), [
            'product_id' => $product->id,
            'photograph' => UploadedFile::fake()->image('family-photo.jpg', 800, 600),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonStructure(['token', 'name']);
        $photoToken = $photoUpload->json('token');

        $this->post(route('storefront.storefront.store.personalization.photo', $store), [
            'product_id' => $product->id,
            'photograph' => UploadedFile::fake()->create('instructions.txt', 10, 'text/plain'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonValidationErrors('photograph');

        $response = $this->postJson(route('storefront.storefront.store.checkout', $store), [
            'customer' => [
                'name' => 'Ada Lovelace',
                'phone' => '08030000000',
                'email' => 'ada@example.com',
                'address' => '12 Marina Road, Lagos',
                'city' => 'Lagos',
                'save_address' => true,
                'address_label' => 'Home',
            ],
            'shipping_option' => 'Lagos',
            'payment_method' => 'bank_account',
            'notes' => 'Please call when you arrive.',
            'items' => [
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 2,
                    'custom_selections' => ['Unit' => '2'],
                    'personalization' => [
                        'requested' => true,
                        'customized_text' => 'Happy Birthday Ada',
                        'additional_info' => 'Gold text, centred engraving.',
                        'photograph_token' => $photoToken,
                        'photograph_name' => 'family-photo.jpg',
                    ],
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure(['order_id', 'order_reference']);

        $order = SalesOrder::query()->with('customer', 'items')->firstOrFail();

        $this->assertSame($order->id, $response->json('order_id'));
        $this->assertSame($order->order_number, $response->json('order_reference'));
        $this->assertSame('online', $order->source);
        $this->assertSame('pending', $order->order_status->value);
        $this->assertSame('pending', $order->payment_status->value);
        $this->assertSame(650000, $order->total_minor);
        $this->assertSame('ada@example.com', $order->customer->email);
        $this->assertSame(1, Customer::query()->count());
        $this->assertSame(1, $order->items->count());
        $this->assertSame('Lagos', $order->delivery_city);
        $this->assertSame('Please call when you arrive.', $order->notes);
        $this->assertSame(['Unit' => '2'], $order->items->first()->custom_selections);
        $this->assertTrue($order->items->first()->personalization['requested']);
        $this->assertSame('Happy Birthday Ada', $order->items->first()->personalization['customized_text']);
        $this->assertSame('Gold text, centred engraving.', $order->items->first()->personalization['additional_info']);
        $this->assertSame('family-photo.jpg', $order->items->first()->personalization['photograph_name']);
        Storage::disk('public')->assertExists($order->items->first()->personalization['photograph_path']);
        $this->assertDatabaseHas('customer_addresses', [
            'tenant_id' => $tenant->id,
            'customer_id' => $order->customer_id,
            'label' => 'Home',
            'address' => '12 Marina Road, Lagos',
            'city' => 'Lagos',
            'is_default' => true,
        ]);
        Mail::assertSent(OnlineOrderConfirmationMail::class, fn (OnlineOrderConfirmationMail $mail): bool => $mail->hasTo('ada@example.com'));

        (new OnlineOrderConfirmationMail($store->load('tenant'), $order))
            ->assertSeeInHtml($order->order_number)
            ->assertSeeInHtml('City Runner')
            ->assertSeeInHtml('12 Marina Road, Lagos');
    }

    public function test_online_order_confirmation_is_sent_when_environment_is_production(): void
    {
        Mail::fake();
        [$tenant, $store] = $this->storeFixture();
        $customer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Ada',
            'email' => 'ada@example.com',
            'phone' => '08030000000',
            'address' => '12 Marina Road, Lagos',
        ]);
        $order = SalesOrder::query()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'source' => 'online',
            'order_number' => 'SO-MAIL-001',
            'invoice_number' => 'INV-MAIL-001',
            'receipt_number' => 'RCT-MAIL-001',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'order_date' => now()->toDateString(),
            'total_minor' => 200000,
            'delivery_address' => $customer->address,
        ]);
        $originalEnvironment = app()->environment();
        $method = new \ReflectionMethod(StorefrontController::class, 'sendOrderConfirmation');
        $method->setAccessible(true);

        try {
            app()->detectEnvironment(fn (): string => 'production');
            $method->invoke(app(StorefrontController::class), $store->load('tenant'), $order);
        } finally {
            app()->detectEnvironment(fn (): string => $originalEnvironment);
        }

        Mail::assertSent(OnlineOrderConfirmationMail::class, fn (OnlineOrderConfirmationMail $mail): bool => $mail->hasTo('ada@example.com'));
    }

    public function test_storeboot_paystack_initializes_payment_for_pending_online_order(): void
    {
        config([
            'services.paystack.public_key' => 'pk_test_storeboot',
            'services.paystack.secret_key' => 'sk_test_storeboot',
            'services.paystack.base_url' => 'https://api.paystack.co',
        ]);
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'message' => 'Authorization URL created',
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.test/pay/demo',
                    'access_code' => 'access-demo',
                    'reference' => 'PSK-demo',
                ],
            ]),
        ]);

        [$tenant, $store] = $this->storeFixture([
            'payment_methods' => ['storeboot_paystack'],
        ]);
        $customer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Ada',
            'phone' => '08030000000',
            'email' => 'ada@example.com',
        ]);
        $order = SalesOrder::query()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'source' => 'online',
            'order_number' => 'SO-TEST',
            'invoice_number' => 'INV-TEST',
            'receipt_number' => 'RCT-TEST',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'order_date' => now()->toDateString(),
            'total_minor' => 650000,
        ]);

        $this->postJson(route('storefront.storefront.store.checkout.paystack.initialize', [$store, $order]), [
            'payment_method' => 'storeboot_paystack',
        ])
            ->assertOk()
            ->assertJsonPath('authorization_url', 'https://checkout.paystack.test/pay/demo')
            ->assertJsonPath('public_key', 'pk_test_storeboot')
            ->assertJsonPath('gateway_charge_minor', 19750)
            ->assertJsonPath('amount', 669750);

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer sk_test_storeboot')
            && $request['email'] === 'ada@example.com'
            && $request['amount'] === 669750
            && $request['metadata']['sales_order_id'] === $order->id);
    }

    public function test_paystack_initialization_uses_tenant_payment_gateway_charge_config(): void
    {
        config([
            'services.paystack.public_key' => 'pk_test_storeboot',
            'services.paystack.secret_key' => 'sk_test_storeboot',
            'services.paystack.base_url' => 'https://api.paystack.co',
        ]);
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'message' => 'Authorization URL created',
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.test/pay/demo',
                    'access_code' => 'access-demo',
                    'reference' => 'PSK-demo',
                ],
            ]),
        ]);

        [$tenant, $store] = $this->storeFixture([
            'payment_methods' => ['storeboot_paystack'],
        ]);
        DB::table('global_configs')->insert([
            'tenant_id' => $tenant->id,
            'key' => 'PAYMENT_GATEWAY_CHARGE',
            'value' => json_encode([
                'percentage_rate' => 2,
                'fixed_amount_minor' => 5000,
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $customer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Ada',
            'phone' => '08030000000',
            'email' => 'ada@example.com',
        ]);
        $order = SalesOrder::query()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'source' => 'online',
            'order_number' => 'SO-TEST',
            'invoice_number' => 'INV-TEST',
            'receipt_number' => 'RCT-TEST',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'order_date' => now()->toDateString(),
            'total_minor' => 650000,
        ]);

        $this->postJson(route('storefront.storefront.store.checkout.paystack.initialize', [$store, $order]), [
            'payment_method' => 'storeboot_paystack',
        ])
            ->assertOk()
            ->assertJsonPath('gateway_charge_minor', 18000)
            ->assertJsonPath('amount', 668000);

        $this->assertDatabaseHas('sales_orders', [
            'id' => $order->id,
            'gateway_charge_minor' => 18000,
            'total_minor' => 668000,
        ]);
    }

    public function test_self_hosted_paystack_verification_uses_tenant_keys_and_marks_order_paid(): void
    {
        config(['services.paystack.base_url' => 'https://api.paystack.co']);
        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'message' => 'Verification successful',
                'data' => [
                    'status' => 'success',
                    'amount' => 659850,
                    'currency' => 'NGN',
                ],
            ]),
        ]);

        [$tenant, $store] = $this->storeFixture([
            'payment_methods' => ['self_hosted_paystack'],
            'payment_settings' => [
                'paystack' => [
                    'public_key' => 'pk_test_tenant',
                    'private_key' => 'sk_test_tenant',
                ],
            ],
        ]);
        $customer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Ada',
            'phone' => '08030000000',
            'email' => 'ada@example.com',
        ]);
        $order = SalesOrder::query()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'source' => 'online',
            'order_number' => 'SO-TEST',
            'invoice_number' => 'INV-TEST',
            'receipt_number' => 'RCT-TEST',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'order_date' => now()->toDateString(),
            'payment_method' => 'self_hosted_paystack',
            'subtotal_minor' => 650000,
            'gateway_charge_minor' => 9850,
            'total_minor' => 659850,
        ]);

        $this->get(route('storefront.storefront.store.checkout.paystack.callback', [$store, $order, 'reference' => 'PSK-test']))
            ->assertRedirect(route('storefront.storefront.store.home', $store))
            ->assertSessionHas('status');

        $order->refresh();

        $this->assertSame('paid', $order->payment_status->value);
        $this->assertSame('pending', $order->order_status->value);
        $this->assertSame(659850, $order->paid_minor);
        $this->assertDatabaseHas('sales_order_payments', [
            'sales_order_id' => $order->id,
            'reference_number' => 'PSK-test',
            'amount_minor' => 659850,
        ]);
        $this->assertDatabaseHas('online_collected_payments', [
            'tenant_id' => $tenant->id,
            'sales_order_id' => $order->id,
            'provider' => 'paystack',
            'provider_reference' => 'PSK-test',
            'product_amount_minor' => 650000,
            'shipping_amount_minor' => 0,
            'gateway_charge_minor' => 9850,
            'customer_total_minor' => 650000,
            'amount_minor' => 659850,
            'storeboot_profit_minor' => 9850,
            'is_settled' => false,
        ]);
        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer sk_test_tenant'));
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
            'shipping_options' => [['location' => 'Lagos', 'description' => '3-5 days', 'price' => 1500]],
            'social_accounts' => ['whatsapp' => '08020000000'],
            'pages' => [],
            'faqs' => [],
            'is_active' => true,
            'maintenance_mode' => false,
        ], $overrides));

        return [$tenant, $store];
    }
}
