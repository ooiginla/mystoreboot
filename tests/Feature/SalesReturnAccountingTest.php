<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Business\Models\Branch;
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
use Modules\Sales\Actions\CreateSalesOrderAction;
use Modules\Sales\Actions\RefundCancelledOrderAction;
use Modules\Sales\Enums\SalesOrderStatus;
use Modules\Sales\Enums\SalesPaymentStatus;
use Modules\Sales\Models\SalesOrder;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

class SalesReturnAccountingTest extends TestCase
{
    use RefreshDatabase;

    public function test_returning_a_paid_completed_order_books_customer_credit_not_negative_receivable(): void
    {
        [$user, $order, $stock] = $this->completedPaidSale();
        $orderItemId = $order->items()->value('id');

        // Return both units.
        $this->actingAs($user)
            ->post(route('admin.sales.orders.returns.store', $order), [
                'return_date' => now()->toDateString(),
                'items' => [['sales_order_item_id' => $orderItemId, 'quantity' => 2]],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(SalesOrderStatus::Returned, $order->order_status);
        $this->assertSame(SalesPaymentStatus::CustomerCredit, $order->payment_status);
        $this->assertSame(200000, (int) $order->customer_credit_minor);
        // Goods went back into stock (3 left after the sale, back to 5).
        $this->assertSame(5, (int) $stock->refresh()->quantity_on_hand);

        $journal = FinanceJournalEntry::query()->with('lines.account')
            ->where('source_type', 'sales_return')->where('source_event', 'approved')->firstOrFail();

        // Revenue reversed, paid money parked as refundable Customer Credit — NOT Accounts Receivable.
        $this->assertTrue($journal->lines->contains(fn ($l): bool => $l->account->code === '4030' && $l->debit_minor === 200000));
        $this->assertTrue($journal->lines->contains(fn ($l): bool => $l->account->code === '2300' && $l->credit_minor === 200000));
        $this->assertFalse($journal->lines->contains(fn ($l): bool => $l->account->code === '1100'));
        // COGS reversed, stock value restored.
        $this->assertTrue($journal->lines->contains(fn ($l): bool => $l->account->code === '1200' && $l->debit_minor === 120000));
        $this->assertTrue($journal->lines->contains(fn ($l): bool => $l->account->code === 'EXP-5000' && $l->credit_minor === 120000));
        // Entry balances.
        $this->assertSame((int) $journal->lines->sum('debit_minor'), (int) $journal->lines->sum('credit_minor'));
    }

    public function test_returned_order_customer_credit_can_be_refunded_out(): void
    {
        [$user, $order] = $this->completedPaidSale();
        $orderItemId = $order->items()->value('id');

        $this->actingAs($user)
            ->post(route('admin.sales.orders.returns.store', $order), [
                'return_date' => now()->toDateString(),
                'items' => [['sales_order_item_id' => $orderItemId, 'quantity' => 2]],
            ])
            ->assertRedirect();

        $order->refresh();
        $refundAction = app(RefundCancelledOrderAction::class);
        $this->assertSame(200000, $refundAction->refundableMinor($order));

        // Pay the credit back out (cash refund).
        $refunded = $refundAction->execute($order, ['refund_date' => now()->toDateString(), 'payment_method' => 'Cash'], $user->id);

        $this->assertSame(200000, $refunded);
        $order->refresh();
        $this->assertSame(0, (int) $order->customer_credit_minor);
        $this->assertSame(200000, (int) $order->refunded_minor);
        $this->assertSame(SalesPaymentStatus::Refunded, $order->payment_status);

        $refundJournal = FinanceJournalEntry::query()->with('lines.account')
            ->where('source_type', 'sales_order')->where('source_id', $order->id)->where('source_event', 'refunded_cancelled_order')->firstOrFail();
        $this->assertTrue($refundJournal->lines->contains(fn ($l): bool => $l->account->code === '2300' && $l->debit_minor === 200000));
        $this->assertTrue($refundJournal->lines->contains(fn ($l): bool => $l->account->code === '1000' && $l->credit_minor === 200000));
    }

    /**
     * A completed, fully-paid cash sale of 2 units (price 1000, cost 600) with inventory.
     *
     * @return array{User, SalesOrder, InventoryStockLevel}
     */
    private function completedPaidSale(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Return Shop',
            'slug' => 'return-shop',
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
        $customer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Ada',
            'last_name' => 'Buyer',
            'phone' => '08000000000',
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Return Product',
            'slug' => 'return-product',
            'product_type' => ProductType::Product->value,
            'base_price_minor' => 100000,
            'base_cost_price_minor' => 60000,
            'tax_behavior' => TaxBehavior::Exempt->value,
            'status' => ProductStatus::Active->value,
        ]);
        $variant = ProductVariant::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'variant_name' => 'Default',
            'sku' => 'RETURN-SKU',
            'selling_price_minor' => 100000,
            'cost_price_minor' => 60000,
            'tax_behavior' => TaxBehavior::Exempt->value,
            'status' => ProductStatus::Active->value,
        ]);
        $location = InventoryLocation::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'Return Stock',
            'code' => 'RETURN-STOCK',
            'location_type' => InventoryLocationType::Branch->value,
            'status' => 'active',
        ]);
        $stock = InventoryStockLevel::query()->create([
            'tenant_id' => $tenant->id,
            'inventory_location_id' => $location->id,
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
            'average_cost_minor' => 60000,
        ]);
        $user = User::factory()->create(['is_platform_admin' => true]);

        $order = app(CreateSalesOrderAction::class)->execute([
            'tenant_id' => $tenant->id,
            'source' => 'offline',
            'record_as' => 'completed_sale',
            'branch_id' => $branch->id,
            'inventory_location_id' => $location->id,
            'customer_id' => $customer->id,
            'order_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'amount_paid' => '2000',
            'shipping' => 0,
            'admin_discount_type' => 'amount',
            'admin_discount_value' => 0,
            'delivery_status' => 'delivered',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 2, 'unit_price' => '1000']],
        ], $user->id);

        return [$user, $order, $stock];
    }
}
