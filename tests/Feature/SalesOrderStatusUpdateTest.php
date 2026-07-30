<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Access\Enums\MembershipStatus;
use Modules\Access\Models\Permission;
use Modules\Access\Models\Role;
use Modules\Access\Models\TenantMembership;
use Modules\Business\Models\Branch;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Enums\TaxBehavior;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Customers\Models\Customer;
use Modules\Finance\Models\FinanceJournalEntry;
use Modules\Inventory\Enums\InventoryLocationType;
use Modules\Inventory\Enums\InventoryMovementType;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Inventory\Models\InventoryMovement;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Sales\Enums\SalesOrderStatus;
use Modules\Sales\Enums\SalesPaymentStatus;
use Modules\Sales\Models\SalesOrder;
use Modules\Subscriptions\Enums\SubscriptionStatus;
use Modules\Subscriptions\Models\Module;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\TenantSubscription;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

class SalesOrderStatusUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_is_not_an_available_sales_order_status(): void
    {
        $this->assertNotContains('draft', SalesOrderStatus::values());
    }

    public function test_order_and_delivery_status_can_be_updated_from_the_order_dialog(): void
    {
        [$user, $order] = $this->fixture();

        $this->actingAs($user)
            ->get(route('admin.sales.orders.index', ['tenant' => $order->tenant_id]).'#orders')
            ->assertOk()
            ->assertSee('Update order status')
            ->assertSee('Update delivery status')
            ->assertSee('data-order-list-actions', false)
            ->assertSee('data-order-dialog-actions', false)
            ->assertSee('Cancel Order')
            // A pending order cannot be returned — Return is completed-only now.
            ->assertDontSee('Return Order')
            ->assertSee('Generate Receipt')
            ->assertSee('Generate Invoice')
            ->assertSee('data-order-status-form', false)
            ->assertSee('Are you sure you want to complete Order', false)
            ->assertSee('Source')
            ->assertSee('Offline')
            ->assertSee('data-dialog-open="sales-receipt-'.$order->id.'"', false)
            // Return is completed-only; a pending order shows no return trigger.
            ->assertDontSee('data-dialog-open="order-return-'.$order->id.'"', false)
            ->assertSee('3:34 PM · '.now()->format('M j, Y'));

        $this->actingAs($user)
            ->post(route('admin.sales.orders.status.update', $order), [
                'order_status' => SalesOrderStatus::Processing->value,
            ])
            ->assertRedirect(route('admin.sales.orders.index', ['tenant' => $order->tenant_id]).'#orders');

        $this->actingAs($user)
            ->post(route('admin.sales.orders.delivery-status.update', $order), [
                'delivery_status' => 'delivered',
            ])
            ->assertRedirect(route('admin.sales.orders.index', ['tenant' => $order->tenant_id]).'#orders');

        $order->refresh();
        $this->assertSame(SalesOrderStatus::Processing, $order->order_status);
        $this->assertSame('delivered', $order->delivery_status);
    }

    public function test_order_status_button_cannot_bypass_the_cancellation_workflow(): void
    {
        [$user, $order] = $this->fixture();

        $this->actingAs($user)
            ->from(route('admin.sales.orders.index', ['tenant' => $order->tenant_id]).'#orders')
            ->post(route('admin.sales.orders.status.update', $order), [
                'order_status' => SalesOrderStatus::Cancelled->value,
            ])
            ->assertSessionHasErrors('order_status');

        $this->assertSame(SalesOrderStatus::Pending, $order->refresh()->order_status);
    }

    public function test_order_listing_can_be_filtered_by_branch_source_order_status_and_payment_status(): void
    {
        [$user, $order] = $this->fixture();

        $this->actingAs($user)
            ->get(route('admin.sales.orders.index', [
                'tenant' => $order->tenant_id,
                'order_branch' => $order->branch_id,
                'order_source' => 'offline',
                'order_status' => SalesOrderStatus::Pending->value,
                'order_payment_status' => SalesPaymentStatus::Paid->value,
            ]).'#orders')
            ->assertOk()
            ->assertSee('form-grid order-filter-grid', false)
            ->assertSee('name="order_branch"', false)
            ->assertSee('name="order_source"', false)
            ->assertSee('name="order_status"', false)
            ->assertSee('name="order_payment_status"', false)
            ->assertViewHas('orders', fn ($orders): bool => $orders->count() === 1 && $orders->first()->is($order));

        $this->actingAs($user)
            ->get(route('admin.sales.orders.index', [
                'tenant' => $order->tenant_id,
                'order_source' => 'online',
            ]).'#orders')
            ->assertOk()
            ->assertViewHas('orders', fn ($orders): bool => $orders->isEmpty());
    }

    public function test_tenant_user_with_sales_update_permission_can_update_order_status(): void
    {
        [$user, $order] = $this->fixture();
        $user->update(['is_platform_admin' => false]);
        $tenant = Tenant::query()->findOrFail($order->tenant_id);
        $tenant->update(['settings' => ['rbac_enforced' => true]]);
        $permission = Permission::query()->firstOrCreate(['slug' => 'sales.update'], [
            'module' => 'sales',
            'name' => 'Update sales',
        ]);
        $viewPermission = Permission::query()->firstOrCreate(['slug' => 'sales.view'], [
            'module' => 'sales',
            'name' => 'View sales',
        ]);
        $role = Role::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Sales Manager',
            'slug' => 'sales-manager',
        ]);
        $role->permissions()->attach([$permission->id, $viewPermission->id]);
        TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => MembershipStatus::Active,
        ]);

        $this->actingAs($user)
            ->get(route('admin.sales.orders.index', ['tenant' => $tenant->id]).'#orders')
            ->assertOk();

        $this->actingAs($user)
            ->post(route('admin.sales.orders.status.update', $order), [
                'order_status' => SalesOrderStatus::Completed->value,
            ])
            ->assertRedirect(route('admin.sales.orders.index', ['tenant' => $tenant->id]).'#orders');

        $this->assertSame(SalesOrderStatus::Completed, $order->refresh()->order_status);
    }

    public function test_completing_pending_order_posts_inventory_accounting_and_receivable_once(): void
    {
        [$user, $order] = $this->fixture(inventoryEnabled: true, paidMinor: 40000);
        $stock = InventoryStockLevel::query()
            ->where('product_variant_id', $order->items()->value('product_variant_id'))
            ->firstOrFail();

        $this->actingAs($user)
            ->post(route('admin.sales.orders.status.update', $order), [
                'order_status' => SalesOrderStatus::Completed->value,
            ])
            ->assertRedirect(route('admin.sales.orders.index', ['tenant' => $order->tenant_id]).'#orders');

        $order->refresh();
        $journal = FinanceJournalEntry::query()
            ->with('lines.account')
            ->where('source_type', 'sales_order')
            ->where('source_id', $order->id)
            ->where('source_event', 'completed')
            ->firstOrFail();

        $this->assertSame(SalesOrderStatus::Completed, $order->order_status);
        $this->assertTrue($order->is_credit_sale);
        $this->assertSame(110000, $order->customer->refresh()->account_balance_minor);
        $this->assertSame(1, $stock->refresh()->quantity_on_hand);
        $this->assertDatabaseHas('inventory_movements', [
            'reference_type' => 'sales_order',
            'reference_id' => $order->id,
            'movement_type' => InventoryMovementType::StockOut->value,
            'quantity' => -1,
        ]);
        $this->assertTrue($journal->lines->contains(fn ($line): bool => $line->account->code === '2310' && $line->debit_minor === 40000));
        $this->assertTrue($journal->lines->contains(fn ($line): bool => $line->account->code === '1100' && $line->debit_minor === 110000));
        $this->assertTrue($journal->lines->contains(fn ($line): bool => $line->account->code === '4000' && $line->credit_minor === 150000));
        $this->assertTrue($journal->lines->contains(fn ($line): bool => $line->account->code === 'EXP-5000' && $line->debit_minor === 50000));
        $this->assertTrue($journal->lines->contains(fn ($line): bool => $line->account->code === '1200' && $line->credit_minor === 50000));

        $this->actingAs($user)
            ->post(route('admin.sales.orders.status.update', $order), [
                'order_status' => SalesOrderStatus::Completed->value,
            ])
            ->assertRedirect(route('admin.sales.orders.index', ['tenant' => $order->tenant_id]).'#orders');

        $this->assertSame(1, FinanceJournalEntry::query()
            ->where('source_type', 'sales_order')
            ->where('source_id', $order->id)
            ->where('source_event', 'completed')
            ->count());
        $this->assertSame(1, InventoryMovement::query()
            ->where('reference_type', 'sales_order')
            ->where('reference_id', $order->id)
            ->count());
        $this->assertSame(110000, $order->customer->refresh()->account_balance_minor);
    }

    public function test_completing_order_without_inventory_subscription_skips_stock_and_uses_optional_estimated_cogs(): void
    {
        [$user, $order] = $this->fixture(inventoryEnabled: false, estimatedCogs: true);

        $this->actingAs($user)
            ->post(route('admin.sales.orders.status.update', $order), [
                'order_status' => SalesOrderStatus::Completed->value,
            ])
            ->assertRedirect(route('admin.sales.orders.index', ['tenant' => $order->tenant_id]).'#orders');

        $journal = FinanceJournalEntry::query()
            ->with('lines.account')
            ->where('source_type', 'sales_order')
            ->where('source_id', $order->id)
            ->where('source_event', 'completed')
            ->firstOrFail();

        $this->assertSame(SalesOrderStatus::Completed, $order->refresh()->order_status);
        $this->assertNull($order->inventory_location_id);
        $this->assertFalse(InventoryMovement::query()
            ->where('reference_type', 'sales_order')
            ->where('reference_id', $order->id)
            ->exists());
        $this->assertTrue($journal->lines->contains(fn ($line): bool => $line->account->code === 'EXP-5000' && $line->debit_minor === 50000));
        $this->assertTrue($journal->lines->contains(fn ($line): bool => $line->account->code === '1200' && $line->credit_minor === 50000));
    }

    public function test_completed_sale_does_not_require_inventory_location_when_inventory_is_not_subscribed(): void
    {
        [$user, $existingOrder] = $this->fixture(inventoryEnabled: false, estimatedCogs: true);
        $variantId = $existingOrder->items()->value('product_variant_id');

        $this->actingAs($user)
            ->post(route('admin.sales.orders.store'), [
                'tenant_id' => $existingOrder->tenant_id,
                'source' => 'offline',
                'record_as' => 'completed_sale',
                'branch_id' => $existingOrder->branch_id,
                'customer_id' => $existingOrder->customer_id,
                'order_date' => now()->toDateString(),
                'payment_method' => 'Cash',
                'amount_paid' => '1500',
                'shipping' => '0',
                'admin_discount_type' => 'amount',
                'admin_discount_value' => '0',
                'delivery_status' => 'delivered',
                'items' => [
                    ['product_variant_id' => $variantId, 'quantity' => 1, 'unit_price' => '1500'],
                ],
            ])
            ->assertRedirect(route('admin.sales.orders.index', ['tenant' => $existingOrder->tenant_id]).'#orders')
            ->assertSessionHasNoErrors();

        $sale = SalesOrder::query()->whereKeyNot($existingOrder->id)->firstOrFail();
        $this->assertSame(SalesOrderStatus::Completed, $sale->order_status);
        $this->assertNull($sale->inventory_location_id);
        $this->assertFalse(InventoryMovement::query()
            ->where('reference_type', 'sales_order')
            ->where('reference_id', $sale->id)
            ->exists());
        $this->assertDatabaseHas('finance_journal_entries', [
            'source_type' => 'sales_order',
            'source_id' => $sale->id,
            'source_event' => 'created',
        ]);
    }

    public function test_completed_order_cannot_be_moved_back_to_processing(): void
    {
        [$user, $order] = $this->fixture();
        $order->update(['order_status' => SalesOrderStatus::Completed->value]);

        $this->actingAs($user)
            ->post(route('admin.sales.orders.status.update', $order), [
                'order_status' => SalesOrderStatus::Processing->value,
            ])
            ->assertSessionHasErrors('order_status');

        $this->assertSame(SalesOrderStatus::Completed, $order->refresh()->order_status);
    }

    /**
     * @return array{User, SalesOrder}
     */
    private function fixture(
        bool $inventoryEnabled = false,
        bool $estimatedCogs = false,
        int $paidMinor = 150000,
    ): array {
        $tenant = Tenant::query()->create([
            'name' => 'Status Shop',
            'slug' => 'status-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
            'settings' => ['use_estimated_cost_for_cogs' => $estimatedCogs],
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
            'name' => 'Status Product',
            'slug' => 'status-product',
            'product_type' => ProductType::Product->value,
            'base_price_minor' => 150000,
            'base_cost_price_minor' => 50000,
            'tax_behavior' => TaxBehavior::Exempt->value,
            'status' => ProductStatus::Active->value,
        ]);
        $variant = ProductVariant::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'variant_name' => 'Default',
            'sku' => 'STATUS-SKU',
            'selling_price_minor' => 150000,
            'cost_price_minor' => 50000,
            'tax_behavior' => TaxBehavior::Exempt->value,
            'status' => ProductStatus::Active->value,
        ]);
        $location = null;
        if ($inventoryEnabled) {
            $location = InventoryLocation::query()->create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'name' => 'Status Stock',
                'code' => 'STATUS-STOCK',
                'location_type' => InventoryLocationType::Branch->value,
                'status' => 'active',
            ]);
            InventoryStockLevel::query()->create([
                'tenant_id' => $tenant->id,
                'inventory_location_id' => $location->id,
                'product_variant_id' => $variant->id,
                'quantity_on_hand' => 2,
                'average_cost_minor' => 50000,
            ]);
        } else {
            Module::query()->create([
                'name' => 'Inventory Management',
                'slug' => 'inventory',
                'is_core' => false,
                'is_active' => true,
            ]);
            $plan = Plan::query()->create([
                'name' => 'Sales without Inventory',
                'slug' => 'sales-without-inventory',
                'currency_code' => 'NGN',
                'is_active' => true,
            ]);
            TenantSubscription::query()->create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active->value,
                'billing_interval' => 'monthly',
            ]);
        }
        $user = User::factory()->create(['is_platform_admin' => true]);
        $order = SalesOrder::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'inventory_location_id' => $location?->id,
            'customer_id' => $customer->id,
            'source' => 'offline',
            'order_number' => 'SO-STATUS-001',
            'invoice_number' => 'INV-STATUS-001',
            'receipt_number' => 'RCT-STATUS-001',
            'order_status' => SalesOrderStatus::Pending->value,
            'payment_status' => $paidMinor >= 150000
                ? SalesPaymentStatus::Paid->value
                : ($paidMinor > 0 ? SalesPaymentStatus::PartiallyPaid->value : SalesPaymentStatus::Unpaid->value),
            'delivery_status' => 'pending',
            'order_date' => now()->toDateString(),
            'subtotal_minor' => 150000,
            'total_minor' => 150000,
            'paid_minor' => $paidMinor,
            'payment_method' => $paidMinor > 0 ? 'Cash' : null,
            'created_at' => '2026-07-28 15:34:00',
        ]);
        $order->items()->create([
            'tenant_id' => $tenant->id,
            'product_variant_id' => $variant->id,
            'item_name' => 'Status Product / Default',
            'sku' => $variant->sku,
            'quantity' => 1,
            'unit_price_minor' => 150000,
            'unit_cost_minor' => $estimatedCogs ? 50000 : 0,
            'tax_minor' => 0,
            'line_total_minor' => 150000,
        ]);
        if ($paidMinor > 0) {
            $order->payments()->create([
                'tenant_id' => $tenant->id,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'Cash',
                'amount_minor' => $paidMinor,
            ]);
        }

        return [$user, $order];
    }
}
