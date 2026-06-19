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
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceJournalEntry;
use Modules\Inventory\Actions\PostInventoryMovementAction;
use Modules\Inventory\Enums\InventoryLocationType;
use Modules\Inventory\Enums\InventoryMovementType;
use Modules\Inventory\Enums\StockCondition;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Sales\Actions\CreateSalesOrderAction;
use Modules\Sales\Models\SalesTillSession;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

class SalesCostingTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_cogs_uses_inventory_average_cost_not_variant_default_cost(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Cost Shop',
            'slug' => 'cost-shop',
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
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'location_type' => InventoryLocationType::Branch->value,
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Rice',
            'slug' => 'rice',
            'product_type' => ProductType::Product->value,
            'base_price_minor' => 120000,
            'base_cost_price_minor' => 50000,
            'tax_behavior' => TaxBehavior::Exempt->value,
            'status' => ProductStatus::Active->value,
        ]);
        $variant = ProductVariant::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'variant_name' => '50kg',
            'sku' => 'RICE-50',
            'selling_price_minor' => 120000,
            'cost_price_minor' => 50000,
            'tax_behavior' => TaxBehavior::Exempt->value,
            'status' => ProductStatus::Active->value,
        ]);
        $customer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Walk-In',
            'last_name' => 'Customer',
            'phone' => 'WALK-IN',
            'status' => 'active',
        ]);
        $user = User::factory()->create(['is_platform_admin' => true]);
        $tillSession = SalesTillSession::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'session_number' => 'TILL-TEST-1',
            'status' => 'open',
            'opening_float_minor' => 0,
            'opened_at' => now(),
        ]);

        app(PostInventoryMovementAction::class)->execute([
            'tenant_id' => $tenant->id,
            'inventory_location_id' => $location->id,
            'product_variant_id' => $variant->id,
            'movement_type' => InventoryMovementType::StockIn->value,
            'stock_condition' => StockCondition::Sellable->value,
            'quantity' => 10,
            'unit_cost' => 700,
            'reference_type' => 'purchase_order',
            'reference_number' => 'PO-TEST',
            'occurred_at' => '2026-06-08',
        ]);

        $this->assertSame(70000, InventoryStockLevel::query()->where('product_variant_id', $variant->id)->firstOrFail()->average_cost_minor);

        $order = app(CreateSalesOrderAction::class)->execute([
            'tenant_id' => $tenant->id,
            'sales_till_session_id' => $tillSession->id,
            'branch_id' => $branch->id,
            'inventory_location_id' => $location->id,
            'customer_id' => $customer->id,
            'order_date' => '2026-06-08',
            'is_credit_sale' => false,
            'payment_method' => 'Cash',
            'amount_paid' => '2400',
            'shipping' => 0,
            'admin_discount_type' => 'amount',
            'admin_discount_value' => 0,
            'delivery_status' => 'delivered',
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 2, 'unit_price' => '1200'],
            ],
        ], $user->id);

        $item = $order->items()->firstOrFail();
        $this->assertSame(70000, $item->unit_cost_minor);

        $journal = FinanceJournalEntry::query()
            ->with('lines.account')
            ->where('tenant_id', $tenant->id)
            ->where('source_type', 'sales_order')
            ->where('source_id', $order->id)
            ->firstOrFail();

        $this->assertTrue($journal->lines->contains(fn ($line): bool => $line->account->code === '5000' && $line->debit_minor === 140000));
        $this->assertTrue($journal->lines->contains(fn ($line): bool => $line->account->code === '1200' && $line->credit_minor === 140000));
    }

    public function test_till_must_balance_before_it_can_close(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Till Shop',
            'slug' => 'till-shop',
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
        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($user)
            ->post(route('admin.sales.tills.open'), [
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'opening_float' => '100.00',
            ])
            ->assertRedirect();

        $tillSession = SalesTillSession::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->actingAs($user)
            ->post(route('admin.sales.tills.movements.store', $tillSession), [
                'movement_type' => 'cash_in',
                'amount' => '50.00',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('admin.sales.tills.close', $tillSession), [
                'actuals' => ['Cash' => '140.00'],
            ])
            ->assertSessionHasErrors('actuals');

        $this->actingAs($user)
            ->post(route('admin.sales.tills.close', $tillSession), [
                'actuals' => ['Cash' => '150.00'],
            ])
            ->assertRedirect();

        $this->assertSame('closed', $tillSession->refresh()->status);
        $this->assertSame(15000, $tillSession->actual_total_minor);
        $this->assertSame(0, $tillSession->variance_total_minor);

        $vaultAccount = FinanceAccount::query()->where('tenant_id', $tenant->id)->where('code', 'BV-'.$branch->id)->firstOrFail();
        $tillAccount = FinanceAccount::query()->where('tenant_id', $tenant->id)->where('code', 'CT-'.$tillSession->id)->firstOrFail();
        $handoverJournal = FinanceJournalEntry::query()
            ->with('lines.account')
            ->where('tenant_id', $tenant->id)
            ->where('source_type', 'sales_till_session')
            ->where('source_id', $tillSession->id)
            ->where('source_event', 'closed_remitted')
            ->firstOrFail();

        $this->assertTrue($handoverJournal->lines->contains(fn ($line): bool => $line->finance_account_id === $vaultAccount->id && $line->debit_minor === 15000));
        $this->assertTrue($handoverJournal->lines->contains(fn ($line): bool => $line->finance_account_id === $tillAccount->id && $line->credit_minor === 15000));
    }
}
