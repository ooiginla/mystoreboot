<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Business\Models\OnlineStore;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Enums\TaxBehavior;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductCollection;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

class CatalogProductCollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_collection_can_be_created_and_assigned_to_a_product(): void
    {
        $tenant = $this->tenant();
        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($user)
            ->post(route('admin.catalog.product-collections.store'), [
                'tenant_id' => $tenant->id,
                'name' => 'Trending Now',
                'is_visible' => '1',
            ])
            ->assertRedirect(route('admin.catalog.index', ['tenant' => $tenant->id]).'#collections')
            ->assertSessionHas('status', 'Collection Trending Now created.');

        $collection = ProductCollection::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertSame('manual', $collection->collection_type);
        $this->assertTrue($collection->is_visible);
        $this->assertNull($collection->description);
        $this->assertNull($collection->rules);
        $this->assertNull($collection->badge_text);
        $this->assertNull($collection->badge_color);
        $this->assertNull($collection->sort_order);
        $this->assertNull($collection->starts_at);
        $this->assertNull($collection->ends_at);

        $this->actingAs($user)
            ->post(route('admin.catalog.products.store'), [
                'tenant_id' => $tenant->id,
                'product_type' => ProductType::Product->value,
                'name' => 'Collection Product',
                'base_price' => '2500',
                'base_cost_price' => '1200',
                'tax_behavior' => TaxBehavior::Exempt->value,
                'status' => ProductStatus::Active->value,
                'collection_ids' => [$collection->id],
            ])
            ->assertRedirect(route('admin.catalog.index', ['tenant' => $tenant->id]));

        $product = Product::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertTrue($product->collections()->whereKey($collection->id)->exists());

        $this->actingAs($user)
            ->get(route('admin.catalog.index', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('Product Collections')
            ->assertSee('Trending Now')
            ->assertSee('Visible on store')
            ->assertSee('Create a new collection')
            ->assertSee('name="collection_ids[]"', false);

        $this->actingAs($user)
            ->put(route('admin.catalog.products.update', $product), [
                'tenant_id' => $tenant->id,
                'product_type' => ProductType::Product->value,
                'name' => $product->name,
                'base_price' => '2500',
                'base_cost_price' => '1200',
                'tax_behavior' => TaxBehavior::Exempt->value,
                'status' => ProductStatus::Active->value,
                'collection_ids' => [$collection->id],
                'new_collection' => [
                    'name' => 'Summer Picks',
                    'is_visible' => '1',
                ],
            ])
            ->assertRedirect(route('admin.catalog.index', ['tenant' => $tenant->id]));

        $inlineCollection = ProductCollection::query()
            ->where('tenant_id', $tenant->id)
            ->where('name', 'Summer Picks')
            ->firstOrFail();

        $this->assertTrue($product->collections()->whereKey($inlineCollection->id)->exists());
    }

    public function test_only_visible_non_empty_collections_render_on_the_storefront(): void
    {
        $tenant = $this->tenant();
        $store = OnlineStore::query()->create([
            'tenant_id' => $tenant->id,
            'username' => 'collection-store',
            'store_name' => 'Collection Store',
            'is_active' => true,
        ]);
        $visibleProduct = Product::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Featured Shirt',
            'slug' => 'featured-shirt',
            'product_type' => ProductType::Product,
            'status' => ProductStatus::Active,
            'base_price_minor' => 250000,
        ]);
        $hiddenProduct = Product::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Hidden Shirt',
            'slug' => 'hidden-shirt',
            'product_type' => ProductType::Product,
            'status' => ProductStatus::Active,
            'base_price_minor' => 200000,
        ]);
        $visibleCollection = ProductCollection::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Trending Now',
            'slug' => 'trending-now',
            'collection_type' => 'manual',
            'is_visible' => true,
        ]);
        $hiddenCollection = ProductCollection::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Private Picks',
            'slug' => 'private-picks',
            'collection_type' => 'manual',
            'is_visible' => false,
        ]);
        $visibleCollection->products()->attach($visibleProduct);
        $hiddenCollection->products()->attach($hiddenProduct);

        $this->get(route('storefront.storefront.store.home', $store))
            ->assertOk()
            ->assertSee('Collections')
            ->assertSee(route('storefront.storefront.store.collections.show', [$store, 'trending-now']), false)
            ->assertSee('Trending Now')
            ->assertSee('collection-trending-now', false)
            ->assertSee('data-collection-carousel', false)
            ->assertSee('data-collection-prev', false)
            ->assertSee('data-collection-next', false)
            ->assertDontSee('Private Picks');

        $this->get(route('storefront.storefront.store.products.show', [$store, $visibleProduct->slug]))
            ->assertOk()
            ->assertSee('Collections')
            ->assertSee(route('storefront.storefront.store.collections.show', [$store, 'trending-now']), false)
            ->assertDontSee('Private Picks');

        $this->get(route('storefront.storefront.store.collections.show', [$store, 'trending-now']))
            ->assertOk()
            ->assertSee('Trending Now')
            ->assertSee('Featured Shirt')
            ->assertDontSee('Hidden Shirt')
            ->assertDontSee('<section class="store-hero', false)
            ->assertDontSee('<div class="relative mt-8" data-collection-carousel>', false);
    }

    private function tenant(): Tenant
    {
        return Tenant::query()->create([
            'name' => 'Collection Shop',
            'slug' => 'collection-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
    }
}
