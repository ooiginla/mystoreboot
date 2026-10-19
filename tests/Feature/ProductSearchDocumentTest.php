<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Enums\CategoryType;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductCategory;
use Modules\Catalog\Models\ProductSearchDocument;
use Modules\Catalog\Models\ProductTag;
use Modules\Catalog\Models\ProductVariant;
use Modules\Catalog\Support\ProductSearchDocumentBuilder;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class ProductSearchDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_document_combines_product_and_related_searchable_fields(): void
    {
        [$tenant, $product] = $this->productContext();
        $tag = ProductTag::query()->create(['tenant_id' => $tenant->id, 'name' => 'Premium', 'slug' => 'premium']);
        $product->tags()->attach($tag);

        app(ProductSearchDocumentBuilder::class)->rebuild($product);

        $document = ProductSearchDocument::query()->where('product_id', $product->id)->firstOrFail();

        $this->assertSame($tenant->id, $document->tenant_id);
        $this->assertStringContainsString('Dark Chocolate', $document->search_text);
        $this->assertStringContainsString('CHO-900', $document->search_text);
        $this->assertStringContainsString('Sweet House', $document->search_text);
        $this->assertStringContainsString('Confectionery', $document->search_text);
        $this->assertStringContainsString('Premium', $document->search_text);
    }

    public function test_rebuild_command_is_idempotent_and_does_not_change_product_data(): void
    {
        [$tenant, $product] = $this->productContext();
        $before = $product->fresh()->getAttributes();

        $this->artisan('catalog:rebuild-search', ['--tenant' => $tenant->id, '--chunk' => 25])
            ->assertSuccessful();
        $this->artisan('catalog:rebuild-search', ['--tenant' => $tenant->id, '--chunk' => 25])
            ->assertSuccessful();

        $this->assertTrue(Schema::hasTable('product_search_documents'));
        $this->assertSame(1, ProductSearchDocument::query()->where('product_id', $product->id)->count());
        $this->assertSame($before, $product->fresh()->getAttributes());
    }

    public function test_catalog_search_uses_documents_and_ranks_an_exact_sku_first(): void
    {
        [$tenant, $exactProduct] = $this->productContext();
        $tag = ProductTag::query()->create(['tenant_id' => $tenant->id, 'name' => 'Premium', 'slug' => 'premium']);
        $exactProduct->tags()->attach($tag);
        app(ProductSearchDocumentBuilder::class)->rebuild($exactProduct);

        $nameMatch = Product::query()->create([
            'tenant_id' => $tenant->id, 'category_id' => $exactProduct->category_id,
            'name' => 'CHO-900 Display Stand', 'slug' => 'cho-900-display-stand',
            'product_type' => ProductType::Product->value, 'status' => ProductStatus::Active->value,
        ]);
        ProductVariant::query()->create([
            'tenant_id' => $tenant->id, 'product_id' => $nameMatch->id,
            'variant_name' => 'Default', 'sku' => 'DISPLAY-1', 'status' => ProductStatus::Active->value,
        ]);
        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($user)
            ->get(route('admin.catalog.index', ['tenant' => $tenant->id, 'products_q' => 'CHO-900']).'#products')
            ->assertOk()
            ->assertViewHas('productItems', fn ($items): bool => $items->total() === 2
                && $items->first()?->id === $exactProduct->id);

        $this->actingAs($user)
            ->get(route('admin.catalog.index', ['tenant' => $tenant->id, 'products_q' => 'Premium']).'#products')
            ->assertOk()
            ->assertViewHas('productItems', fn ($items): bool => $items->total() === 1
                && $items->first()?->id === $exactProduct->id);
    }

    public function test_search_falls_back_safely_while_documents_are_not_fully_backfilled(): void
    {
        [$tenant, $product] = $this->productContext();
        ProductSearchDocument::query()->where('product_id', $product->id)->delete();
        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($user)
            ->get(route('admin.catalog.index', ['tenant' => $tenant->id, 'products_q' => 'Confectionery']).'#products')
            ->assertOk()
            ->assertViewHas('productItems', fn ($items): bool => $items->total() === 1
                && $items->first()?->id === $product->id);
    }

    public function test_search_document_refreshes_when_related_catalogue_data_changes(): void
    {
        [$tenant, $product] = $this->productContext();
        $variant = $product->variants()->firstOrFail();
        $category = $product->category()->firstOrFail();
        $tag = ProductTag::query()->create(['tenant_id' => $tenant->id, 'name' => 'Original Tag', 'slug' => 'original-tag']);
        $product->tags()->attach($tag);
        app(ProductSearchDocumentBuilder::class)->rebuild($product);

        $variant->update(['sku' => 'CHO-UPDATED']);
        $category->update(['name' => 'Updated Confectionery']);
        $tag->update(['name' => 'Updated Premium']);

        $text = ProductSearchDocument::query()->where('product_id', $product->id)->value('search_text');

        $this->assertStringContainsString('CHO-UPDATED', $text);
        $this->assertStringContainsString('Updated Confectionery', $text);
        $this->assertStringContainsString('Updated Premium', $text);
        $this->assertStringNotContainsString('CHO-900', $text);
    }

    /**
     * @return array{Tenant, Product}
     */
    private function productContext(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Search Shop', 'slug' => 'search-shop', 'status' => TenantStatus::Active,
            'business_type' => 'retail', 'country_code' => 'NG', 'timezone' => 'Africa/Lagos', 'currency_code' => 'NGN',
        ]);
        $category = ProductCategory::query()->create([
            'tenant_id' => $tenant->id, 'category_type' => CategoryType::Product->value,
            'name' => 'Confectionery', 'slug' => 'confectionery', 'status' => 'active',
        ]);
        $product = Product::query()->create([
            'tenant_id' => $tenant->id, 'category_id' => $category->id,
            'name' => 'Dark Chocolate', 'slug' => 'dark-chocolate', 'brand' => 'Sweet House',
            'product_type' => ProductType::Product->value, 'status' => ProductStatus::Active->value,
        ]);
        ProductVariant::query()->create([
            'tenant_id' => $tenant->id, 'product_id' => $product->id,
            'variant_name' => 'Large Bar', 'sku' => 'CHO-900', 'barcode' => '1234567890',
            'status' => ProductStatus::Active->value,
        ]);

        return [$tenant, $product];
    }
}
