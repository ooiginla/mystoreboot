<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Branch;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Enums\TaxBehavior;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Customers\Models\Customer;
use Modules\Customers\Models\CustomerGroup;
use Modules\Customers\Models\CustomerPriceList;
use Modules\Customers\Models\CustomerPriceListItem;
use Modules\Customers\Models\CustomerSpecificPrice;
use Modules\Customers\Support\CustomerCreditService;
use Modules\Customers\Support\CustomerPricingService;
use Modules\Inventory\Enums\InventoryLocationType;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Sales\Models\SalesOrder;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

class CustomerPricingAndCreditTest extends TestCase
{
    use RefreshDatabase;

    public function test_pricing_precedence_and_quantity_breaks_are_resolved_authoritatively(): void
    {
        [$tenant, , $variant] = $this->catalog('Pricing Company', 'pricing-company');
        $wholesale = CustomerPriceList::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Wholesale',
            'code' => 'WHOLESALE',
            'is_active' => true,
        ]);
        $reseller = CustomerPriceList::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Reseller',
            'code' => 'RESELLER',
            'is_active' => true,
        ]);
        foreach ([[1, 80000], [10, 70000]] as [$minimum, $price]) {
            CustomerPriceListItem::query()->create([
                'tenant_id' => $tenant->id,
                'customer_price_list_id' => $wholesale->id,
                'product_variant_id' => $variant->id,
                'minimum_quantity' => $minimum,
                'unit_price_minor' => $price,
            ]);
        }
        CustomerPriceListItem::query()->create([
            'tenant_id' => $tenant->id,
            'customer_price_list_id' => $reseller->id,
            'product_variant_id' => $variant->id,
            'minimum_quantity' => 1,
            'unit_price_minor' => 75000,
        ]);
        $group = CustomerGroup::query()->create([
            'tenant_id' => $tenant->id,
            'customer_price_list_id' => $wholesale->id,
            'name' => 'Wholesalers',
            'credit_enabled' => true,
        ]);
        $customer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'customer_group_id' => $group->id,
            'customer_price_list_id' => $reseller->id,
            'first_name' => 'Direct',
            'phone' => '08010000001',
            'status' => 'active',
        ]);

        $quote = app(CustomerPricingService::class)->quotes($tenant, $customer, [$variant->id => 12])->get($variant->id);
        $this->assertSame(75000, $quote['unit_price_minor']);
        $this->assertSame('Reseller', $quote['label']);
        $this->assertSame('customer_price_list', $quote['source']);

        CustomerSpecificPrice::query()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'product_variant_id' => $variant->id,
            'minimum_quantity' => 5,
            'unit_price_minor' => 60000,
            'is_active' => true,
        ]);
        $specific = app(CustomerPricingService::class)->quotes($tenant, $customer->fresh(), [$variant->id => 5])->get($variant->id);
        $this->assertSame(60000, $specific['unit_price_minor']);
        $this->assertSame('Customer price', $specific['label']);

        $customer->update(['customer_price_list_id' => null]);
        CustomerSpecificPrice::query()->delete();
        $groupTier = app(CustomerPricingService::class)->quotes($tenant, $customer->fresh(), [$variant->id => 12])->get($variant->id);
        $this->assertSame(70000, $groupTier['unit_price_minor']);
        $this->assertSame(10.0, $groupTier['minimum_quantity']);
        $this->assertSame('Wholesale', $groupTier['label']);
    }

    public function test_quote_endpoint_returns_credit_visibility_and_rejects_cross_tenant_products(): void
    {
        [$tenant, , $variant] = $this->catalog('Quote Company', 'quote-company');
        [$otherTenant, , $otherVariant] = $this->catalog('Other Company', 'other-company');
        $customer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Credit',
            'phone' => '08010000002',
            'status' => 'active',
            'credit_policy' => 'enabled',
            'credit_limit_minor' => 200000,
            'account_balance_minor' => 50000,
        ]);
        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($user)->postJson(route('admin.sales.pricing.quote'), [
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 2]],
        ])->assertOk()
            ->assertJsonPath('credit.outstanding_minor', 50000)
            ->assertJsonPath('credit.available_minor', 150000)
            ->assertJsonPath('prices.0.label', 'Retail')
            ->assertJsonPath('prices.0.unit_price_minor', 100000)
            ->assertJsonPath('can_override_credit', true);

        $this->actingAs($user)->postJson(route('admin.sales.pricing.quote'), [
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'items' => [['product_variant_id' => $otherVariant->id, 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('items.0.product_variant_id');

        $this->assertNotSame($tenant->id, $otherTenant->id);
    }

    public function test_group_pricelist_can_discount_the_current_retail_price_by_percentage(): void
    {
        $this->travelTo('2026-10-26 12:00:00');

        [$tenant, , $variant] = $this->catalog('Percentage Pricing Company', 'percentage-pricing-company');
        $priceList = CustomerPriceList::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Wholesaler Pricelist',
            'code' => 'WHOLESALE-PERCENT',
            'is_active' => true,
            'starts_at' => '2026-10-26',
            'ends_at' => '2026-10-31',
        ]);
        CustomerPriceListItem::query()->create([
            'tenant_id' => $tenant->id,
            'customer_price_list_id' => $priceList->id,
            'product_variant_id' => $variant->id,
            'minimum_quantity' => 5,
            'price_type' => 'percentage',
            'unit_price_minor' => 0,
            'discount_percent' => 15,
        ]);
        $group = CustomerGroup::query()->create([
            'tenant_id' => $tenant->id,
            'customer_price_list_id' => $priceList->id,
            'name' => 'Wholesalers',
        ]);
        $customer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'customer_group_id' => $group->id,
            'first_name' => 'Percentage',
            'phone' => '08010000011',
            'status' => 'active',
        ]);

        $quote = app(CustomerPricingService::class)->quotes($tenant, $customer, [$variant->id => 5])->get($variant->id);
        $this->assertSame(85000, $quote['unit_price_minor']);
        $this->assertSame('group_price_list', $quote['source']);
        $this->assertSame('Wholesaler Pricelist', $quote['label']);

        $variant->update(['selling_price_minor' => 120000]);
        $updatedQuote = app(CustomerPricingService::class)->quotes($tenant, $customer, [$variant->id => 5])->get($variant->id);
        $this->assertSame(102000, $updatedQuote['unit_price_minor']);
    }

    public function test_recorded_sale_uses_customer_price_snapshot_and_credit_terms(): void
    {
        [$tenant, $branch, $variant, $location] = $this->catalog('Sale Company', 'sale-company');
        $customer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Wholesale',
            'phone' => '08010000003',
            'status' => 'active',
            'credit_policy' => 'enabled',
            'credit_limit_minor' => 100000,
            'credit_terms_days' => 30,
            'account_balance_minor' => 40000,
        ]);
        CustomerSpecificPrice::query()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'product_variant_id' => $variant->id,
            'minimum_quantity' => 1,
            'unit_price_minor' => 60000,
            'is_active' => true,
        ]);
        $user = User::factory()->create(['is_platform_admin' => true]);
        $payload = [
            'tenant_id' => $tenant->id,
            'source' => 'offline',
            'record_as' => 'completed_sale',
            'branch_id' => $branch->id,
            'inventory_location_id' => $location->id,
            'customer_id' => $customer->id,
            'order_date' => '2026-10-26',
            'is_credit_sale' => '1',
            'payment_method' => 'Cash',
            'amount_paid' => '0',
            'shipping' => '0',
            'admin_discount_type' => 'amount',
            'admin_discount_value' => '0',
            'delivery_status' => 'delivered',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => '600']],
        ];

        $this->actingAs($user)->post(route('admin.sales.orders.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $order = SalesOrder::query()->with('items')->firstOrFail();
        $line = $order->items->firstOrFail();
        $this->assertSame(60000, $line->unit_price_minor);
        $this->assertSame(100000, $line->base_unit_price_minor);
        $this->assertSame('customer_price', $line->pricing_source);
        $this->assertSame('Customer price', $line->pricing_label);
        $this->assertSame('2026-11-25', $order->credit_due_date->toDateString());
        $this->assertSame(100000, $customer->fresh()->account_balance_minor);

        $this->actingAs($user)->post(route('admin.sales.orders.store'), array_replace_recursive($payload, [
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => '1000']],
        ]))->assertSessionHasErrors('items');
    }

    public function test_credit_limit_requires_permission_and_a_reason_to_override(): void
    {
        [$tenant] = $this->catalog('Limit Company', 'limit-company');
        $customer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Limited',
            'phone' => '08010000004',
            'status' => 'active',
            'credit_policy' => 'enabled',
            'credit_limit_minor' => 100000,
            'account_balance_minor' => 90000,
        ]);
        $cashier = User::factory()->create();

        try {
            app(CustomerCreditService::class)->authorizeNewCredit($tenant, $customer, 20000, $cashier);
            $this->fail('The credit limit should have blocked the cashier.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount_paid', $exception->errors());
        }

        $manager = User::factory()->create(['is_platform_admin' => true]);
        try {
            app(CustomerCreditService::class)->authorizeNewCredit($tenant, $customer, 20000, $manager);
            $this->fail('An override reason should be required.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('credit_override_reason', $exception->errors());
        }

        $decision = app(CustomerCreditService::class)->authorizeNewCredit($tenant, $customer, 20000, $manager, 'Approved temporary increase');
        $this->assertTrue($decision['overridden']);
        $this->assertSame(10000, $decision['profile']['available_minor']);
    }

    public function test_sales_screens_expose_price_labels_and_credit_before_checkout(): void
    {
        [$tenant] = $this->catalog('UI Company', 'ui-company');
        $user = User::factory()->create(['is_platform_admin' => true]);
        Customer::query()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Visible Credit',
            'phone' => '08010000005',
            'status' => 'active',
            'credit_policy' => 'enabled',
            'credit_limit_minor' => 100000,
        ]);

        $this->actingAs($user)->get(route('admin.sales.index', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('data-pricing-quote-url', false)
            ->assertSee('Available credit')
            ->assertSee('data-customer-credit-available', false)
            ->assertSee('pricing-line-label', false)
            ->assertSee('name="credit_override_reason"', false);

        $this->actingAs($user)->get(route('admin.customers.index', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('Pricelist')
            ->assertSee('Customer pricing')
            ->assertSee('Available credit')
            ->assertSee('Create pricelist');
    }

    public function test_pricelist_index_opens_a_dedicated_product_management_page(): void
    {
        [$tenant, , $variant] = $this->catalog('Pricelist UI Company', 'pricelist-ui-company');
        $priceList = CustomerPriceList::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Wholesaler Pricelist',
            'code' => 'WHOLESALER',
            'is_active' => true,
        ]);
        $item = CustomerPriceListItem::query()->create([
            'tenant_id' => $tenant->id,
            'customer_price_list_id' => $priceList->id,
            'product_variant_id' => $variant->id,
            'minimum_quantity' => 5,
            'unit_price_minor' => 75000,
        ]);
        $user = User::factory()->create(['is_platform_admin' => true]);
        $showUrl = route('admin.customers.price-lists.show', ['priceList' => $priceList, 'tenant' => $tenant->id]);

        $this->actingAs($user)
            ->get(route('admin.customers.index', ['tenant' => $tenant->id]).'#price-lists')
            ->assertOk()
            ->assertSee('Wholesaler Pricelist')
            ->assertSee($showUrl, false)
            ->assertDontSee('price-list-item-edit-'.$item->id);

        $this->actingAs($user)
            ->get($showUrl)
            ->assertOk()
            ->assertSee('Products and prices')
            ->assertSee('Bread')
            ->assertSee('NGN 750.00')
            ->assertSee('id="price-list-item-dialog"', false)
            ->assertSee('id="price-list-item-edit-'.$item->id.'"', false)
            ->assertSee(route('admin.customers.price-list-items.update', $item), false)
            ->assertSee(route('admin.customers.price-list-items.destroy', $item), false);
    }

    public function test_pricelist_products_can_be_added_edited_and_deleted(): void
    {
        [$tenant, , $variant] = $this->catalog('Pricelist CRUD Company', 'pricelist-crud-company');
        $priceList = CustomerPriceList::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Reseller Pricelist',
            'code' => 'RESELLER',
            'is_active' => true,
        ]);
        $user = User::factory()->create(['is_platform_admin' => true]);
        $payload = [
            'tenant_id' => $tenant->id,
            'customer_price_list_id' => $priceList->id,
            'product_variant_id' => $variant->id,
            'minimum_quantity' => 10,
            'unit_price' => '700.00',
        ];

        $this->actingAs($user)
            ->post(route('admin.customers.price-list-items.store'), $payload)
            ->assertRedirect(route('admin.customers.price-lists.show', ['priceList' => $priceList, 'tenant' => $tenant->id]));

        $item = CustomerPriceListItem::query()->where('customer_price_list_id', $priceList->id)->firstOrFail();
        $this->assertSame(70000, $item->unit_price_minor);

        $this->actingAs($user)
            ->put(route('admin.customers.price-list-items.update', $item), array_replace($payload, [
                'minimum_quantity' => 20,
                'price_type' => 'percentage',
                'discount_percent' => '12.5',
            ]))
            ->assertRedirect(route('admin.customers.price-lists.show', ['priceList' => $priceList, 'tenant' => $tenant->id]));

        $this->assertDatabaseHas('customer_price_list_items', [
            'id' => $item->id,
            'minimum_quantity' => 20,
            'price_type' => 'percentage',
            'unit_price_minor' => 0,
            'discount_percent' => 12.5,
        ]);

        $this->actingAs($user)
            ->delete(route('admin.customers.price-list-items.destroy', $item))
            ->assertRedirect(route('admin.customers.price-lists.show', ['priceList' => $priceList, 'tenant' => $tenant->id]));

        $this->assertDatabaseMissing('customer_price_list_items', ['id' => $item->id]);
    }

    public function test_customer_pricing_tab_filters_by_customer_or_product_and_uses_dialog_forms(): void
    {
        [$tenant, , $breadVariant] = $this->catalog('Specific Pricing UI', 'specific-pricing-ui');
        $cake = Product::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Cake',
            'slug' => 'cake-specific-pricing-ui',
            'product_type' => ProductType::Product->value,
            'base_price_minor' => 200000,
            'track_inventory' => false,
            'tax_behavior' => TaxBehavior::Exempt->value,
            'status' => ProductStatus::Active->value,
        ]);
        $cakeVariant = ProductVariant::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $cake->id,
            'variant_name' => 'Default',
            'sku' => 'CAKE-SPECIFIC',
            'selling_price_minor' => 200000,
            'tax_behavior' => TaxBehavior::Exempt->value,
            'status' => ProductStatus::Active->value,
        ]);
        $breadCustomer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Bread',
            'last_name' => 'Buyer',
            'phone' => '08010009001',
            'status' => 'active',
        ]);
        $cakeCustomer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Cake',
            'last_name' => 'Buyer',
            'phone' => '08010009002',
            'status' => 'active',
        ]);
        $breadPrice = CustomerSpecificPrice::query()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $breadCustomer->id,
            'product_variant_id' => $breadVariant->id,
            'minimum_quantity' => 1,
            'unit_price_minor' => 80000,
            'is_active' => true,
        ]);
        $cakePrice = CustomerSpecificPrice::query()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $cakeCustomer->id,
            'product_variant_id' => $cakeVariant->id,
            'minimum_quantity' => 1,
            'unit_price_minor' => 150000,
            'is_active' => true,
        ]);
        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($user)
            ->get(route('admin.customers.index', [
                'tenant' => $tenant->id,
                'pricing_customer_id' => $breadCustomer->id,
            ]).'#customer-pricing')
            ->assertOk()
            ->assertSee('data-specific-price-row="'.$breadPrice->id.'"', false)
            ->assertDontSee('data-specific-price-row="'.$cakePrice->id.'"', false)
            ->assertSee('id="specific-price-dialog"', false)
            ->assertSee('id="specific-price-edit-'.$breadPrice->id.'"', false)
            ->assertSee(route('admin.customers.specific-prices.update', $breadPrice), false);

        $this->actingAs($user)
            ->get(route('admin.customers.index', [
                'tenant' => $tenant->id,
                'pricing_variant_id' => $cakeVariant->id,
            ]).'#customer-pricing')
            ->assertOk()
            ->assertSee('data-specific-price-row="'.$cakePrice->id.'"', false)
            ->assertDontSee('data-specific-price-row="'.$breadPrice->id.'"', false);

        $this->actingAs($user)
            ->put(route('admin.customers.specific-prices.update', $breadPrice), [
                'tenant_id' => $tenant->id,
                'customer_id' => $breadCustomer->id,
                'product_variant_id' => $breadVariant->id,
                'minimum_quantity' => 2,
                'unit_price' => '750.00',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.customers.index', ['tenant' => $tenant->id]).'#customer-pricing');

        $this->assertDatabaseHas('customer_specific_prices', [
            'id' => $breadPrice->id,
            'minimum_quantity' => 2,
            'unit_price_minor' => 75000,
        ]);
    }

    /** @return array{Tenant, Branch, ProductVariant, InventoryLocation} */
    private function catalog(string $name, string $slug): array
    {
        $tenant = Tenant::query()->create([
            'name' => $name,
            'slug' => $slug,
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
        $branch = Branch::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'status' => 'active',
            'is_primary' => true,
        ]);
        $location = InventoryLocation::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'Main Stock',
            'code' => 'MAIN-STOCK',
            'location_type' => InventoryLocationType::Branch->value,
            'is_sellable_point' => true,
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Bread',
            'slug' => 'bread-'.$slug,
            'product_type' => ProductType::Product->value,
            'base_price_minor' => 100000,
            'track_inventory' => false,
            'tax_behavior' => TaxBehavior::Exempt->value,
            'status' => ProductStatus::Active->value,
        ]);
        $variant = ProductVariant::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'variant_name' => 'Default',
            'sku' => 'BREAD-'.strtoupper(substr($slug, 0, 8)),
            'selling_price_minor' => 100000,
            'tax_behavior' => TaxBehavior::Exempt->value,
            'status' => ProductStatus::Active->value,
        ]);

        return [$tenant, $branch, $variant, $location];
    }
}
