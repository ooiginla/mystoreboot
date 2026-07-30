<?php

namespace Tests\Feature;

use App\Mail\OnlineOrderConfirmationMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Business\Models\Branch;
use Modules\Business\Models\OnlineStore;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Enums\TaxBehavior;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Customers\Models\Customer;
use Modules\Sales\Enums\SalesOrderStatus;
use Modules\Sales\Enums\SalesPaymentStatus;
use Modules\Sales\Models\SalesOrder;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

class OrderTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_order_gets_a_unique_global_tracking_reference(): void
    {
        [, $storeA] = $this->storeFixture('shop-a');
        [, $storeB] = $this->storeFixture('shop-b');

        $orderA = $this->onlineOrder($storeA);
        $orderB = $this->onlineOrder($storeB);

        $this->assertStringStartsWith('TRK-', (string) $orderA->tracking_reference);
        $this->assertStringStartsWith('TRK-', (string) $orderB->tracking_reference);
        // Globally unique even across different tenants.
        $this->assertNotSame($orderA->tracking_reference, $orderB->tracking_reference);
    }

    public function test_storefront_track_page_shows_the_order_with_timeline(): void
    {
        [, $store] = $this->storeFixture('reno-mart');
        $order = $this->onlineOrder($store, paymentStatus: SalesPaymentStatus::Paid, orderStatus: SalesOrderStatus::Processing, deliveryStatus: 'processing');

        $this->get(route('storefront.storefront.store.track', [$store, 'reference' => $order->tracking_reference]))
            ->assertOk()
            ->assertSee($order->tracking_reference)
            ->assertSee('Order '.$order->order_number)
            ->assertSee('Peak Milk 20g')
            ->assertSee('Order: Processing')
            ->assertSee('Payment: Paid')
            // Timeline milestones.
            ->assertSee('Order placed')
            ->assertSee('Payment received')
            ->assertSee('Processing')
            ->assertSee('Out for delivery')
            ->assertSee('Delivered');
    }

    public function test_lowercase_reference_still_matches(): void
    {
        [, $store] = $this->storeFixture('case-shop');
        $order = $this->onlineOrder($store);

        $this->get(route('storefront.storefront.store.track', [$store, 'reference' => strtolower((string) $order->tracking_reference)]))
            ->assertOk()
            ->assertSee($order->tracking_reference);
    }

    public function test_tracking_is_scoped_to_the_store_tenant(): void
    {
        [, $storeA] = $this->storeFixture('tenant-a');
        [, $storeB] = $this->storeFixture('tenant-b');
        $orderA = $this->onlineOrder($storeA);

        // Store B must not reveal store A's order, even with the exact reference.
        $this->get(route('storefront.storefront.store.track', [$storeB, 'reference' => $orderA->tracking_reference]))
            ->assertOk()
            ->assertSee('No order found')
            ->assertDontSee('Order '.$orderA->order_number);
    }

    public function test_unknown_reference_shows_not_found(): void
    {
        [, $store] = $this->storeFixture('empty-shop');

        $this->get(route('storefront.storefront.store.track', [$store, 'reference' => 'TRK-NOPE1234']))
            ->assertOk()
            ->assertSee('No order found');
    }

    public function test_confirmation_email_includes_tracking_reference_and_track_link(): void
    {
        [$tenant, $store] = $this->storeFixture('mail-shop');
        $order = $this->onlineOrder($store);
        $order->setRelation('store', $store);

        $rendered = (new OnlineOrderConfirmationMail($store->loadMissing('tenant'), $order))->render();

        $this->assertStringContainsString((string) $order->tracking_reference, $rendered);
        $this->assertStringContainsString(route('storefront.storefront.store.track', [$store, 'reference' => $order->tracking_reference]), $rendered);
    }

    /**
     * @return array{Tenant, OnlineStore}
     */
    private function storeFixture(string $username): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Track Tenant '.$username,
            'slug' => 'track-'.$username,
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
        $branch = Branch::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'HQ',
            'code' => 'HQ',
            'status' => 'active',
            'is_primary' => true,
        ]);
        $store = OnlineStore::query()->create([
            'tenant_id' => $tenant->id,
            'username' => $username,
            'store_name' => ucwords(str_replace('-', ' ', $username)),
            'fulfilment_branch_id' => $branch->id,
            'payment_methods' => ['pay_on_delivery'],
            'shipping_options' => [['location' => 'Lagos', 'description' => '3-5 days', 'price' => 1500]],
            'pages' => [],
            'faqs' => [],
            'is_active' => true,
            'maintenance_mode' => false,
        ]);

        return [$tenant, $store];
    }

    private function onlineOrder(
        OnlineStore $store,
        SalesPaymentStatus $paymentStatus = SalesPaymentStatus::Pending,
        SalesOrderStatus $orderStatus = SalesOrderStatus::Pending,
        string $deliveryStatus = 'pending',
    ): SalesOrder {
        $customer = Customer::query()->create([
            'tenant_id' => $store->tenant_id,
            'first_name' => 'Ada',
            'last_name' => 'Buyer',
            'phone' => '0803'.random_int(1000000, 9999999),
            'email' => 'ada'.random_int(1000, 9999).'@example.com',
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'tenant_id' => $store->tenant_id,
            'name' => 'Peak Milk 20g',
            'slug' => 'peak-milk-'.random_int(1000, 9999),
            'product_type' => ProductType::Product->value,
            'base_price_minor' => 250000,
            'tax_behavior' => TaxBehavior::Exempt->value,
            'status' => ProductStatus::Active->value,
        ]);
        $variant = ProductVariant::query()->create([
            'tenant_id' => $store->tenant_id,
            'product_id' => $product->id,
            'variant_name' => 'Default',
            'sku' => 'PEAK-'.random_int(1000, 9999),
            'selling_price_minor' => 250000,
            'tax_behavior' => TaxBehavior::Exempt->value,
            'status' => ProductStatus::Active->value,
        ]);
        $order = SalesOrder::query()->create([
            'tenant_id' => $store->tenant_id,
            'branch_id' => $store->fulfilment_branch_id,
            'customer_id' => $customer->id,
            'source' => 'online',
            'order_number' => 'SO-'.random_int(100000, 999999),
            'invoice_number' => 'INV-'.random_int(100000, 999999),
            'receipt_number' => 'RCT-'.random_int(100000, 999999),
            'order_status' => $orderStatus->value,
            'payment_status' => $paymentStatus->value,
            'delivery_status' => $deliveryStatus,
            'order_date' => now()->toDateString(),
            'subtotal_minor' => 500000,
            'total_minor' => 650000,
            'shipping_minor' => 150000,
            'paid_minor' => $paymentStatus === SalesPaymentStatus::Paid ? 650000 : 0,
            'delivery_address' => '12 Marina Road, Lagos',
        ]);
        $order->items()->create([
            'tenant_id' => $store->tenant_id,
            'product_variant_id' => $variant->id,
            'item_name' => 'Peak Milk 20g',
            'sku' => 'PEAK-20',
            'quantity' => 2,
            'unit_price_minor' => 250000,
            'unit_cost_minor' => 0,
            'tax_minor' => 0,
            'line_total_minor' => 500000,
        ]);

        return $order->refresh();
    }
}
