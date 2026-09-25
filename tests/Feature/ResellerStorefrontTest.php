<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Business\Models\OnlineStore;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Models\Product;
use Modules\Reseller\Enums\PricingMode;
use Modules\Reseller\Enums\ProductAvailability;
use Modules\Reseller\Models\ResellerOrder;
use Modules\Reseller\Models\ResellerProduct;
use Modules\Reseller\Models\ResellerSetting;
use Modules\Reseller\Models\ResellerSupplier;
use Modules\Subscriptions\Models\Module;
use Modules\Subscriptions\Models\TenantModuleEntitlement;
use Modules\Tenancy\Enums\CommerceMode;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class ResellerStorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_reseller_storefront_uses_only_recovered_products(): void
    {
        [$tenant, $store, $product] = $this->fixture();
        Product::query()->create([
            'tenant_id' => $tenant->id,
            'product_type' => ProductType::Product,
            'name' => 'Native Hidden Product',
            'slug' => 'native-hidden-product',
            'status' => ProductStatus::Active,
            'is_finished_product' => true,
            'base_price_minor' => 50000,
        ]);

        $this->get(route('storefront.storefront.store.home', $store))
            ->assertOk()
            ->assertSee('Recovered Shirt')
            ->assertSee('Supplier Website')
            ->assertDontSee('Native Hidden Product');

        $this->get(route('storefront.storefront.store.products.show', [$store, $product->id]))
            ->assertOk()
            ->assertSee('Recovered Shirt')
            ->assertSee('Add to Cart')
            ->assertSee('Buy It Now');

        $this->get(route('storefront.storefront.store.products.show', [$store, 'native-hidden-product']))
            ->assertNotFound();
    }

    public function test_reseller_checkout_snapshots_prices_and_creates_pending_payment(): void
    {
        [, $store, $product] = $this->fixture();

        $response = $this->postJson(route('storefront.storefront.store.checkout', $store), [
            'customer' => [
                'name' => 'Ada Buyer',
                'email' => 'ada@example.com',
                'phone' => '+2348000000000',
                'address' => '1 Market Road',
                'city' => 'Lagos',
            ],
            'shipping_option' => 'Lagos',
            'payment_method' => 'place_order',
            'items' => [[
                'product_variant_id' => $product->id,
                'quantity' => 2,
            ]],
        ])->assertOk()->assertJsonStructure(['order_id', 'order_reference']);

        $order = ResellerOrder::query()->with(['items', 'payments'])->findOrFail($response->json('order_id'));
        $this->assertSame(260000, $order->subtotal_minor);
        $this->assertSame(100000, $order->delivery_minor);
        $this->assertSame(360000, $order->total_minor);
        $this->assertSame('Recovered Shirt', $order->items->first()->product_name);
        $this->assertSame(100000, $order->items->first()->source_price_minor);
        $this->assertSame(130000, $order->items->first()->unit_selling_price_minor);
        $this->assertSame(1000, $order->items->first()->percentage_markup_basis_points);
        $this->assertSame(20000, $order->items->first()->fixed_markup_minor);
        $this->assertSame('pending', $order->payments->first()->status);

        $this->get(route('storefront.storefront.store.track', ['store' => $store, 'reference' => $order->order_reference]))
            ->assertOk()
            ->assertSee($order->order_reference)
            ->assertSee('Recovered Shirt');

        $user = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($user)
            ->get(route('admin.reseller.orders.index', ['tenant' => $store->tenant_id]))
            ->assertOk()
            ->assertSee('Reseller')
            ->assertSee($store->tenant->name)
            ->assertSee($order->order_reference)
            ->assertSee('Pending')
            ->assertDontSee('Save payment status');

        $this->actingAs($user)
            ->patch(route('admin.reseller.orders.payment-status.update', ['order' => $order, 'tenant' => $store->tenant_id]), [
                'payment_status' => 'paid',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', "Payment status for {$order->order_reference} updated to Paid.");

        $this->assertSame('paid', $order->refresh()->payment_status);
        $payment = $order->payments()->latest('id')->firstOrFail();
        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($payment->processed_at);
        $this->assertSame('pending', data_get($payment->metadata, 'status_history.0.from'));
        $this->assertSame('paid', data_get($payment->metadata, 'status_history.0.to'));

        $item = $order->items->first();
        $this->actingAs($user)
            ->get(route('admin.reseller.orders.show', ['order' => $order, 'tenant' => $store->tenant_id]))
            ->assertOk()
            ->assertSee('Supplier Website')
            ->assertSee('Update fulfilment')
            ->assertSee('Save payment status');

        $this->actingAs($user)
            ->patch(route('admin.reseller.orders.items.update', ['order' => $order, 'item' => $item, 'tenant' => $store->tenant_id]), [
                'fulfilment_status' => 'delivered',
                'tracking_reference' => 'TRACK-100',
                'tracking_url' => 'https://carrier.example/track/TRACK-100',
            ])
            ->assertRedirect();

        $this->assertSame('delivered', $item->refresh()->fulfilment_status);
        $this->assertSame('fulfilled', $order->refresh()->fulfilment_status);
        $this->assertSame('completed', $order->order_status);

        $this->actingAs($user)
            ->patch(route('admin.reseller.products.update', ['product' => $product, 'tenant' => $store->tenant_id]), [
                'is_visible' => '0',
                'is_excluded' => '1',
            ])
            ->assertRedirect();

        $this->assertTrue($product->refresh()->is_excluded);
        $this->assertFalse($product->is_visible);
    }

    /** @return array{Tenant, OnlineStore, ResellerProduct} */
    private function fixture(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Reseller Front',
            'slug' => 'reseller-front-'.str()->random(6),
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'commerce_mode' => CommerceMode::Reseller,
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
        $module = Module::query()->where('slug', 'reseller')->firstOrFail();
        TenantModuleEntitlement::query()->create([
            'tenant_id' => $tenant->id,
            'module_id' => $module->id,
            'is_enabled' => true,
        ]);
        $store = OnlineStore::query()->create([
            'tenant_id' => $tenant->id,
            'username' => 'reseller-front-'.str()->random(6),
            'store_name' => 'Reseller Front',
            'payment_methods' => ['place_order'],
            'shipping_options' => [['location' => 'Lagos', 'price' => '1000']],
            'is_active' => true,
        ]);
        ResellerSetting::query()->create([
            'tenant_id' => $tenant->id,
            'pricing_mode' => PricingMode::Combined,
            'percentage_markup_basis_points' => 1000,
            'fixed_markup_minor' => 20000,
            'show_source_store' => true,
        ]);
        $supplier = ResellerSupplier::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Supplier Website',
            'website_url' => 'https://supplier.example',
        ]);
        $product = ResellerProduct::query()->create([
            'tenant_id' => $tenant->id,
            'supplier_id' => $supplier->id,
            'product_url' => 'https://supplier.example/products/shirt',
            'name' => 'Recovered Shirt',
            'main_image_url' => 'https://supplier.example/images/shirt.jpg',
            'source_price_minor' => 100000,
            'selling_price_minor' => 130000,
            'currency_code' => 'NGN',
            'availability' => ProductAvailability::InStock,
            'category' => 'Clothing',
            'sku' => 'RS-1',
            'source_store_name' => 'Supplier Website',
            'is_visible' => true,
            'last_checked_at' => now(),
        ]);

        return [$tenant, $store, $product];
    }
}
