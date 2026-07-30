<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Business\Models\OnlineStore;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Enums\TaxBehavior;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductBadge;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

class CatalogProductBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_multiple_badges_can_be_assigned_while_only_two_visible_badges_render_on_storefront_cards(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Badge Shop',
            'slug' => 'badge-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
        $store = OnlineStore::query()->create([
            'tenant_id' => $tenant->id,
            'username' => 'badge-store',
            'store_name' => 'Badge Store',
            'is_active' => true,
        ]);
        $user = User::factory()->create(['is_platform_admin' => true]);

        foreach ([
            ['name' => 'Featured', 'background_color' => '#1d4ed8', 'text_color' => '#ffffff', 'is_visible' => '1'],
            ['name' => 'Limited', 'background_color' => '#7c3aed', 'text_color' => '#ffffff', 'is_visible' => '1'],
            ['name' => 'Seasonal', 'background_color' => '#047857', 'text_color' => '#ffffff', 'is_visible' => '1'],
            ['name' => 'Internal', 'background_color' => '#111827', 'text_color' => '#ffffff', 'is_visible' => '0'],
        ] as $badge) {
            $this->actingAs($user)
                ->post(route('admin.catalog.badges.store'), [
                    'tenant_id' => $tenant->id,
                    ...$badge,
                ])
                ->assertRedirect();
        }

        $badges = ProductBadge::query()->where('tenant_id', $tenant->id)->orderBy('name')->get();

        $this->actingAs($user)
            ->post(route('admin.catalog.products.store'), [
                'tenant_id' => $tenant->id,
                'product_type' => ProductType::Product->value,
                'name' => 'Badged Shirt',
                'base_price' => '15000',
                'base_cost_price' => '8000',
                'tax_behavior' => TaxBehavior::Exempt->value,
                'status' => ProductStatus::Active->value,
                'badge_ids' => $badges->pluck('id')->all(),
            ])
            ->assertRedirect(route('admin.catalog.index', ['tenant' => $tenant->id]));

        $product = Product::query()->with('badges')->where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertCount(4, $product->badges);

        $this->actingAs($user)
            ->get(route('admin.catalog.index', ['tenant' => $tenant->id]).'#badges')
            ->assertOk()
            ->assertSee('Product Badges')
            ->assertSee('Storefront badges')
            ->assertSee('name="badge_ids[]"', false)
            ->assertSee('data-checkbox-accordion', false)
            ->assertSee('Create a new badge')
            ->assertSee('data-badge-preview', false)
            ->assertSee('Storefront preview')
            ->assertSee('Sample product')
            ->assertSee('You can assign several; the store displays the first two.');

        $this->get(route('storefront.storefront.store.home', $store))
            ->assertOk()
            ->assertSee('Featured')
            ->assertSee('Limited')
            ->assertDontSee('Seasonal')
            ->assertDontSee('Internal')
            ->assertSee('background: #1d4ed8; color: #ffffff;', false);

        $this->actingAs($user)
            ->put(route('admin.catalog.products.update', $product), [
                'tenant_id' => $tenant->id,
                'product_type' => ProductType::Product->value,
                'name' => $product->name,
                'base_price' => '15000',
                'base_cost_price' => '8000',
                'tax_behavior' => TaxBehavior::Exempt->value,
                'status' => ProductStatus::Active->value,
                'badge_ids' => $badges->pluck('id')->all(),
                'new_badge' => [
                    'name' => 'Spotlight',
                    'background_color' => '#be123c',
                    'text_color' => '#ffffff',
                ],
            ])
            ->assertRedirect(route('admin.catalog.index', ['tenant' => $tenant->id]));

        $inlineBadge = ProductBadge::query()
            ->where('tenant_id', $tenant->id)
            ->where('name', 'Spotlight')
            ->firstOrFail();

        $this->assertTrue($product->badges()->whereKey($inlineBadge->id)->exists());
    }
}
