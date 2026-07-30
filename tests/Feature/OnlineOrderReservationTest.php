<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\Business\Models\Branch;
use Modules\Business\Models\OnlineStore;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Enums\TaxBehavior;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Customers\Models\Customer;
use Modules\Finance\Models\FinanceJournalEntry;
use Modules\Inventory\Enums\InventoryLocationType;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Sales\Enums\SalesOrderStatus;
use Modules\Sales\Enums\SalesPaymentStatus;
use Modules\Sales\Models\SalesOrder;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

class OnlineOrderReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_completing_paid_online_order_with_gateway_charge_posts_a_balanced_journal(): void
    {
        [$admin, $store, $variant, $stock] = $this->fixture(onHand: 5, averageCostMinor: 100000);

        config([
            'services.paystack.base_url' => 'https://api.paystack.co',
            'services.paystack.public_key' => 'pk_test',
            'services.paystack.secret_key' => 'sk_test',
        ]);
        DB::table('global_configs')->insert([
            'tenant_id' => $store->tenant_id,
            'key' => 'PAYMENT_GATEWAY_CHARGE',
            'value' => json_encode(['percentage_rate' => 0, 'fixed_amount_minor' => 9850], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 1. Shopper checks out two units — stock is reserved, not yet deducted.
        $this->postJson(route('storefront.storefront.store.checkout', $store), [
            'customer' => ['name' => 'Ada Buyer', 'phone' => '08030000000', 'email' => 'ada@example.com', 'address' => '12 Marina Road'],
            'shipping_option' => 'Lagos',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 2]],
        ])->assertOk();

        $order = SalesOrder::query()->where('source', 'online')->firstOrFail();
        $this->assertTrue($order->stock_reserved);
        $this->assertSame(2, $stock->refresh()->quantity_reserved);
        $this->assertSame(5, $stock->quantity_on_hand);
        $this->assertSame(3, $stock->quantity_available);

        // 2. Gateway surcharge is added and the shopper pays via Paystack.
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://pay.test/x', 'access_code' => 'ac', 'reference' => 'PSK-'.$order->id.'-AAA']]),
            'api.paystack.co/transaction/verify/*' => Http::response(['status' => true, 'data' => ['status' => 'success', 'amount' => 659850, 'currency' => 'NGN']]),
        ]);

        $this->postJson(route('storefront.storefront.store.checkout.paystack.initialize', [$store, $order]), [
            'payment_method' => 'storeboot_paystack',
        ])->assertOk();

        $this->get(route('storefront.storefront.store.checkout.paystack.callback', [$store, $order, 'reference' => 'PSK-'.$order->id.'-AAA']))
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(SalesPaymentStatus::Paid, $order->payment_status);
        $this->assertSame(659850, $order->total_minor);
        $this->assertSame(9850, (int) $order->gateway_charge_minor);
        $this->assertNull($order->reserved_until);

        // The gateway receipt is on the books as a deposit liability before completion.
        $deposit = FinanceJournalEntry::query()->with('lines.account')
            ->where('source_type', 'sales_order_payment')->where('source_event', 'deposit_received')->firstOrFail();
        $this->assertTrue($deposit->lines->contains(fn ($l): bool => $l->account->code === '1060' && $l->debit_minor === 659850));
        $this->assertTrue($deposit->lines->contains(fn ($l): bool => $l->account->code === '2310' && $l->credit_minor === 659850));

        // 3. Staff complete the order — previously this threw "Journal entry is not balanced".
        $this->actingAs($admin)
            ->post(route('admin.sales.orders.status.update', $order), ['order_status' => SalesOrderStatus::Completed->value])
            ->assertRedirect(route('admin.sales.orders.index', ['tenant' => $order->tenant_id]).'#orders')
            ->assertSessionHasNoErrors();

        $completion = FinanceJournalEntry::query()->with('lines.account')
            ->where('source_type', 'sales_order')->where('source_id', $order->id)->where('source_event', 'completed')->firstOrFail();

        // The entry balances and books the surcharge to gateway-charge recovery income.
        $this->assertSame((int) $completion->lines->sum('debit_minor'), (int) $completion->lines->sum('credit_minor'));
        $this->assertTrue($completion->lines->contains(fn ($l): bool => $l->account->code === '4130' && $l->credit_minor === 9850));
        $this->assertTrue($completion->lines->contains(fn ($l): bool => $l->account->code === '2310' && $l->debit_minor === 659850));
        $this->assertTrue($completion->lines->contains(fn ($l): bool => $l->account->code === '4000' && $l->credit_minor === 500000));
        $this->assertTrue($completion->lines->contains(fn ($l): bool => $l->account->code === '4010' && $l->credit_minor === 150000));
        $this->assertTrue($completion->lines->contains(fn ($l): bool => $l->account->code === 'EXP-5000' && $l->debit_minor === 200000));

        // Deposit credited at payment (659850) and debited at completion (659850)
        // nets the liability to zero; stock is now deducted and unreserved.
        $this->assertSame(SalesOrderStatus::Completed, $order->refresh()->order_status);
        $this->assertSame(3, $stock->refresh()->quantity_on_hand);
        $this->assertSame(0, $stock->quantity_reserved);
    }

    public function test_online_checkout_reserves_stock_and_prevents_overselling_the_last_unit(): void
    {
        [, $store, $variant, $stock] = $this->fixture(onHand: 1, averageCostMinor: 100000);

        $payload = fn (): array => [
            'customer' => ['name' => 'Buyer One', 'phone' => '08031111111', 'email' => 'one@example.com', 'address' => '1 Road'],
            'shipping_option' => 'Lagos',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
        ];

        $this->postJson(route('storefront.storefront.store.checkout', $store), $payload())->assertOk();
        $this->assertSame(1, $stock->refresh()->quantity_reserved);
        $this->assertSame(0, $stock->quantity_available);

        // The last unit is already reserved — a second shopper cannot buy it.
        $this->postJson(route('storefront.storefront.store.checkout', $store), $payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('items');

        $this->assertSame(1, SalesOrder::query()->where('source', 'online')->count());
        $this->assertSame(1, $stock->refresh()->quantity_reserved);
        $this->assertSame(1, $stock->quantity_on_hand);
    }

    public function test_expired_unpaid_online_order_is_auto_cancelled_and_releases_its_reservation(): void
    {
        [, $store, $variant, $stock] = $this->fixture(onHand: 4, averageCostMinor: 100000);
        $stock->update(['quantity_reserved' => 1]);

        $expired = $this->reservedOnlineOrder($store, $variant, reservedUntil: now()->subMinutes(10), paidMinor: 0);
        $paid = $this->reservedOnlineOrder($store, $variant, reservedUntil: null, paidMinor: 250000);
        $stock->update(['quantity_reserved' => 2]);

        $this->artisan('sales:expire-reservations')->assertExitCode(0);

        $this->assertSame(SalesOrderStatus::Cancelled, $expired->refresh()->order_status);
        $this->assertSame(SalesPaymentStatus::Unpaid, $expired->payment_status);
        $this->assertFalse($expired->stock_reserved);

        // The paid order (its hold already cleared) is untouched.
        $this->assertSame(SalesOrderStatus::Pending, $paid->refresh()->order_status);

        // Only the expired order's single reserved unit was released.
        $this->assertSame(1, $stock->refresh()->quantity_reserved);
    }

    public function test_admin_cancelling_an_unpaid_reserved_online_order_returns_the_stock(): void
    {
        [$admin, $store, $variant, $stock] = $this->fixture(onHand: 3, averageCostMinor: 100000);
        $order = $this->reservedOnlineOrder($store, $variant, reservedUntil: now()->addMinutes(20), paidMinor: 0);
        $stock->update(['quantity_reserved' => 1]);

        $this->actingAs($admin)
            ->post(route('admin.sales.orders.cancel', $order))
            ->assertRedirect(route('admin.sales.orders.index', ['tenant' => $order->tenant_id]).'#orders');

        $this->assertSame(SalesOrderStatus::Cancelled, $order->refresh()->order_status);
        $this->assertFalse($order->stock_reserved);
        // Stock is returned to availability even though the scheduler never ran.
        $this->assertSame(0, $stock->refresh()->quantity_reserved);
        $this->assertSame(3, $stock->quantity_on_hand);
    }

    public function test_checkout_lazily_releases_an_expired_hold_so_the_last_unit_can_be_bought(): void
    {
        [, $store, $variant, $stock] = $this->fixture(onHand: 1, averageCostMinor: 100000);

        // An abandoned unpaid order is holding the only unit, and its window has passed.
        $expired = $this->reservedOnlineOrder($store, $variant, reservedUntil: now()->subMinutes(45), paidMinor: 0);
        $stock->update(['quantity_reserved' => 1]);
        $this->assertSame(0, $stock->refresh()->quantity_available);

        // A new shopper checks out the same unit — the stale hold is swept first (no scheduler needed).
        $this->postJson(route('storefront.storefront.store.checkout', $store), [
            'customer' => ['name' => 'Fresh Buyer', 'phone' => '08039999999', 'email' => 'fresh@example.com', 'address' => '9 Road'],
            'shipping_option' => 'Lagos',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
        ])->assertOk();

        $this->assertSame(SalesOrderStatus::Cancelled, $expired->refresh()->order_status);
        // The expired hold was released and re-held by the new order (still 1 reserved, not 2).
        $this->assertSame(1, $stock->refresh()->quantity_reserved);
        $this->assertSame(2, SalesOrder::query()->where('source', 'online')->count());
    }

    /**
     * @return array{User, OnlineStore, ProductVariant, InventoryStockLevel}
     */
    private function fixture(int $onHand, int $averageCostMinor): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Reserve Shop',
            'slug' => 'reserve-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
        $branch = Branch::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Head Office',
            'code' => 'HO',
            'status' => 'active',
            'is_primary' => true,
        ]);
        $store = OnlineStore::query()->create([
            'tenant_id' => $tenant->id,
            'username' => 'reserve-store',
            'store_name' => 'Reserve Store',
            'fulfilment_branch_id' => $branch->id,
            'payment_methods' => ['storeboot_paystack', 'pay_on_delivery'],
            'shipping_options' => [['location' => 'Lagos', 'description' => '3-5 days', 'price' => 1500]],
            'pages' => [],
            'faqs' => [],
            'is_active' => true,
            'maintenance_mode' => false,
            'reservation_hold_minutes' => 30,
        ]);
        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Reserve Product',
            'slug' => 'reserve-product',
            'product_type' => ProductType::Product->value,
            'base_price_minor' => 250000,
            'base_cost_price_minor' => 120000,
            'tax_behavior' => TaxBehavior::Exempt->value,
            'status' => ProductStatus::Active->value,
        ]);
        $variant = ProductVariant::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'variant_name' => 'Default',
            'sku' => 'RESERVE-SKU',
            'selling_price_minor' => 250000,
            'cost_price_minor' => 120000,
            'tax_behavior' => TaxBehavior::Exempt->value,
            'status' => ProductStatus::Active->value,
        ]);
        $location = InventoryLocation::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'Reserve Stock',
            'code' => 'RESERVE-STOCK',
            'location_type' => InventoryLocationType::Branch->value,
            'status' => 'active',
        ]);
        $stock = InventoryStockLevel::query()->create([
            'tenant_id' => $tenant->id,
            'inventory_location_id' => $location->id,
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => $onHand,
            'quantity_reserved' => 0,
            'average_cost_minor' => $averageCostMinor,
        ]);
        $admin = User::factory()->create(['is_platform_admin' => true]);

        return [$admin, $store, $variant, $stock];
    }

    private function reservedOnlineOrder(OnlineStore $store, ProductVariant $variant, ?Carbon $reservedUntil, int $paidMinor): SalesOrder
    {
        $customer = Customer::query()->create([
            'tenant_id' => $store->tenant_id,
            'first_name' => 'Web',
            'last_name' => 'Buyer',
            'phone' => '0803'.random_int(1000000, 9999999),
            'status' => 'active',
        ]);
        $location = InventoryLocation::query()->where('tenant_id', $store->tenant_id)->firstOrFail();
        $order = SalesOrder::query()->create([
            'tenant_id' => $store->tenant_id,
            'branch_id' => $store->fulfilment_branch_id,
            'inventory_location_id' => $location->id,
            'stock_reserved' => true,
            'reserved_until' => $reservedUntil,
            'customer_id' => $customer->id,
            'source' => 'online',
            'order_number' => 'SO-'.random_int(10000, 99999),
            'invoice_number' => 'INV-'.random_int(10000, 99999),
            'receipt_number' => 'RCT-'.random_int(10000, 99999),
            'order_status' => SalesOrderStatus::Pending->value,
            'payment_status' => $paidMinor > 0 ? SalesPaymentStatus::Paid->value : SalesPaymentStatus::Pending->value,
            'order_date' => now()->toDateString(),
            'subtotal_minor' => 250000,
            'total_minor' => 250000,
            'paid_minor' => $paidMinor,
        ]);
        $order->items()->create([
            'tenant_id' => $store->tenant_id,
            'product_variant_id' => $variant->id,
            'item_name' => 'Reserve Product / Default',
            'sku' => $variant->sku,
            'quantity' => 1,
            'unit_price_minor' => 250000,
            'unit_cost_minor' => 100000,
            'tax_minor' => 0,
            'line_total_minor' => 250000,
        ]);

        return $order;
    }
}
