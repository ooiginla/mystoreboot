<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Business\Models\Branch;
use Modules\Business\Models\OnlineStore;
use Modules\Catalog\Actions\GenerateProductSeoAction;
use Modules\Catalog\Enums\CategoryType;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Enums\TaxBehavior;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductCategory;
use Modules\Catalog\Models\ProductVariant;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

class ProductSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_page_renders_seo_meta_and_structured_data(): void
    {
        [$store, $product] = $this->fixture('seo-shop');

        $response = $this->get(route('storefront.storefront.store.products.show', [$store, $product->slug]))
            ->assertOk();

        // Meta + canonical + Open Graph derived live from the product.
        $response->assertSee('<meta name="description"', false);
        $response->assertSee('Soft cotton crew-neck tee', false); // description text
        $response->assertSee('<meta name="keywords"', false);
        $response->assertSee('<link rel="canonical"', false);
        $response->assertSee('property="og:type" content="product"', false);
        // JSON-LD Product structured data (the Google rich-result signal).
        $response->assertSee('application/ld+json', false);
        $response->assertSee('"@type":"Product"', false);
        $response->assertSee('"@type":"Organization"', false);
        $response->assertSee('"@type":"BreadcrumbList"', false);
        $response->assertSee('"seller":{"@id":', false);
        $response->assertSee('"availability":"https://schema.org/InStock"', false);
        // Descriptive image alt text.
        $response->assertSee('alt="Cotton Tee', false);
    }

    public function test_stored_seo_is_preferred_over_derived(): void
    {
        [$store, $product] = $this->fixture('pref-shop');
        $product->forceFill(['seo' => [
            'meta_title' => 'Hand-picked Cotton Tee — Best price',
            'meta_description' => 'A curated, human-written meta description for search engines.',
            'keywords' => ['cotton tee', 'unisex shirt'],
            'tags' => ['tees'],
            'image_alt' => 'Curated cotton tee alt text',
            'generated_at' => now()->toIso8601String(),
        ]])->save();

        $this->get(route('storefront.storefront.store.products.show', [$store, $product->slug]))
            ->assertOk()
            ->assertSee('A curated, human-written meta description for search engines.', false)
            ->assertSee('alt="Curated cotton tee alt text"', false);
    }

    public function test_generate_seo_action_stores_ai_result(): void
    {
        config(['services.ai.provider' => 'openai', 'services.openai.api_key' => 'sk-test', 'services.openai.base_url' => 'https://api.openai.com']);
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'meta_title' => 'Cotton Tee | Seo Shop',
                    'meta_description' => 'Breathable cotton crew-neck tee for everyday wear.',
                    'keywords' => ['cotton tee', 't-shirt', 'unisex'],
                    'tags' => ['tees', 'casual'],
                    'image_alt' => 'White cotton crew-neck t-shirt',
                ])]]],
            ]),
        ]);

        [, $product] = $this->fixture('ai-seo-shop');

        $seo = app(GenerateProductSeoAction::class)->execute($product->fresh(['category', 'tags']));

        $this->assertSame('ai', $seo['source']);
        $this->assertSame('Cotton Tee | Seo Shop', $seo['meta_title']);
        $this->assertNotEmpty($product->fresh()->seo['generated_at']);
    }

    public function test_generate_seo_action_falls_back_to_direct_without_ai(): void
    {
        config(['services.ai.provider' => 'anthropic', 'services.anthropic.api_key' => null, 'services.openai.api_key' => null]);
        Http::fake();

        [, $product] = $this->fixture('no-ai-seo-shop');

        $seo = app(GenerateProductSeoAction::class)->execute($product->fresh(['category', 'tags']));

        Http::assertNothingSent();
        $this->assertSame('direct', $seo['source']);
        $this->assertNotEmpty($seo['meta_title']);
        $this->assertNotEmpty($seo['generated_at']);
    }

    public function test_refresh_command_generates_seo_for_products_missing_it(): void
    {
        config(['services.anthropic.api_key' => null, 'services.openai.api_key' => null]);
        Http::fake();

        [, $product] = $this->fixture('cmd-shop');
        $this->assertNull($product->seo);

        $this->artisan('catalog:refresh-product-seo', ['--tenant' => $product->tenant_id])->assertExitCode(0);

        $product->refresh();
        $this->assertNotNull($product->seo);
        $this->assertNotEmpty($product->seo['meta_title']);
    }

    /**
     * @return array{OnlineStore, Product}
     */
    private function fixture(string $username): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Seo Tenant '.$username,
            'slug' => 'seo-'.$username,
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
        $branch = Branch::query()->create([
            'tenant_id' => $tenant->id, 'name' => 'HQ', 'code' => 'HQ', 'status' => 'active', 'is_primary' => true,
        ]);
        $store = OnlineStore::query()->create([
            'tenant_id' => $tenant->id,
            'username' => $username,
            'store_name' => 'Seo Shop',
            'fulfilment_branch_id' => $branch->id,
            'payment_methods' => ['pay_on_delivery'],
            'shipping_options' => [],
            'pages' => [],
            'faqs' => [],
            'is_active' => true,
            'maintenance_mode' => false,
        ]);
        $category = ProductCategory::query()->create([
            'tenant_id' => $tenant->id,
            'category_type' => CategoryType::Product->value,
            'name' => 'Apparel',
            'slug' => 'apparel-'.$username,
            'status' => 'active',
        ]);
        // Attach to the store so the product is visible on the storefront.
        $store->categories()->syncWithoutDetaching([$category->id]);
        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'name' => 'Cotton Tee',
            'slug' => 'cotton-tee-'.$username,
            'brand' => 'RenoWear',
            'product_type' => ProductType::Product->value,
            'description' => 'Soft cotton crew-neck tee for everyday comfort.',
            'base_price_minor' => 500000,
            'tax_behavior' => TaxBehavior::Exempt->value,
            'status' => ProductStatus::Active->value,
            'image_path' => 'tenants/'.$tenant->id.'/catalog/products/tee-'.$username.'.png',
        ]);
        ProductVariant::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'variant_name' => 'Default',
            'sku' => 'TEE-'.strtoupper($username),
            'selling_price_minor' => 500000,
            'tax_behavior' => TaxBehavior::Exempt->value,
            'status' => ProductStatus::Active->value,
        ]);

        return [$store, $product->load(['category', 'tags', 'variants'])];
    }
}
