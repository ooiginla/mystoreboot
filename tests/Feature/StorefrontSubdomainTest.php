<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Business\Models\OnlineStore;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Models\Product;
use Modules\Storefront\Support\StorefrontUrl;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class StorefrontSubdomainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('storefront.base_domain', 'storeboot.test');
        config()->set('storefront.scheme', 'https');
    }

    public function test_storefront_is_available_on_its_tenant_subdomain_and_generates_subdomain_links(): void
    {
        [, $store] = $this->storeFixture('alpha-store', 'Alpha Store');

        $url = StorefrontUrl::route($store);

        $this->assertSame('https://alpha-store.storeboot.test', rtrim($url, '/'));

        $this->get($url)
            ->assertOk()
            ->assertSee('Alpha Store')
            ->assertSee('href="'.$url.'"', false);
    }

    public function test_www_host_is_served_by_the_main_marketing_site_not_the_storefront_router(): void
    {
        $this->get('https://www.storeboot.test/')
            ->assertOk()
            ->assertSee('Storeboot');
    }

    public function test_a_subdomain_cannot_access_another_tenants_product(): void
    {
        [, $alphaStore] = $this->storeFixture('alpha-store', 'Alpha Store');
        [$betaTenant] = $this->storeFixture('beta-store', 'Beta Store');
        $product = Product::query()->create([
            'tenant_id' => $betaTenant->id,
            'name' => 'Beta-only product',
            'slug' => 'beta-only-product',
            'status' => ProductStatus::Active->value,
            'base_price_minor' => 10000,
        ]);

        $this->get(StorefrontUrl::route($alphaStore, 'products.show', ['productSlug' => $product->slug]))
            ->assertNotFound();
    }

    public function test_legacy_storefront_url_remains_available(): void
    {
        [, $store] = $this->storeFixture('legacy-store', 'Legacy Store');

        $this->get(route('storefront.storefront.store.home', $store))
            ->assertOk()
            ->assertSee('Legacy Store');
    }

    public function test_reserved_subdomain_is_rejected_when_saving_a_store(): void
    {
        [$tenant] = $this->storeFixture('existing-store', 'Existing Store');
        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($user)
            ->post(route('admin.business.online-store.save'), [
                'tenant_id' => $tenant->id,
                'username' => 'www',
                'store_name' => 'Existing Store',
                'theme_primary_color' => '#005f73',
                'theme_secondary_color' => '#ee9b00',
                'paystack_method' => 'none',
            ])
            ->assertSessionHasErrors('username');
    }

    public function test_store_address_availability_is_checked_across_stores_while_ignoring_the_current_store(): void
    {
        [$currentTenant] = $this->storeFixture('current-store', 'Current Store');
        $this->storeFixture('already-taken', 'Taken Store');
        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($user)
            ->getJson(route('admin.business.online-store.address-availability', [
                'tenant_id' => $currentTenant->id,
                'username' => 'already-taken',
            ]))
            ->assertOk()
            ->assertJsonPath('available', false)
            ->assertJsonPath('message', 'Sorry, already-taken.storeboot.com has already been taken. Please pick a new store address.');

        $this->actingAs($user)
            ->getJson(route('admin.business.online-store.address-availability', [
                'tenant_id' => $currentTenant->id,
                'username' => 'current-store',
            ]))
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('message', 'current-store.storeboot.com is available.');

        $this->actingAs($user)
            ->getJson(route('admin.business.online-store.address-availability', [
                'tenant_id' => $currentTenant->id,
                'username' => 'www',
            ]))
            ->assertOk()
            ->assertJsonPath('available', false)
            ->assertJsonPath('message', 'Sorry, www.storeboot.com is reserved by Storeboot. Please pick a new store address.');

        $this->actingAs($user)
            ->post(route('admin.business.online-store.save'), [
                'tenant_id' => $currentTenant->id,
                'username' => 'already-taken',
                'store_name' => 'Current Store',
                'theme_primary_color' => '#005f73',
                'theme_secondary_color' => '#ee9b00',
                'paystack_method' => 'none',
            ])
            ->assertSessionHasErrors([
                'username' => 'Sorry, that store address has already been taken. Please pick a new one.',
            ]);
    }

    /**
     * @return array{Tenant, OnlineStore}
     */
    private function storeFixture(string $subdomain, string $storeName): array
    {
        $tenant = Tenant::query()->create([
            'name' => $storeName,
            'slug' => $subdomain,
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);

        $store = OnlineStore::query()->create([
            'tenant_id' => $tenant->id,
            'username' => $subdomain,
            'store_name' => $storeName,
            'theme_primary_color' => '#005f73',
            'theme_secondary_color' => '#ee9b00',
            'payment_methods' => [],
            'pages' => [],
            'faqs' => [],
            'is_active' => true,
            'maintenance_mode' => false,
        ]);

        return [$tenant, $store];
    }
}
