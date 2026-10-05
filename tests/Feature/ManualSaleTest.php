<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Business\Models\Branch;
use Modules\Catalog\Enums\CategoryType;
use Modules\Catalog\Models\ProductCategory;
use Modules\Customers\Models\Customer;
use Modules\Finance\Models\FinanceJournalEntry;
use Modules\Inventory\Models\InventoryMovement;
use Modules\Sales\Models\SalesOrder;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

class ManualSaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_sale_captures_estimated_cost_without_posting_inventory_or_accounting_cogs(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Simple Jewelry Shop',
            'slug' => 'simple-jewelry-shop',
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
        $category = ProductCategory::query()->create([
            'tenant_id' => $tenant->id,
            'category_type' => CategoryType::Product->value,
            'name' => 'Earrings',
            'slug' => 'earrings',
            'status' => 'active',
        ]);
        $customer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Walk-In',
            'phone' => 'WALK-IN',
            'status' => 'active',
        ]);
        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($user)
            ->get(route('admin.sales.index', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('Add Item to Cart')
            ->assertSee('Existing product')
            ->assertSee('Manual item')
            ->assertSee('What did you sell?')
            ->assertSee('Cost price')
            ->assertSee('Earrings');

        $this->actingAs($user)
            ->post(route('admin.sales.orders.store'), [
                'tenant_id' => $tenant->id,
                'source' => 'offline',
                'record_as' => 'completed_sale',
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'order_date' => '2026-10-05',
                'payment_method' => 'Cash',
                'amount_paid' => '10000',
                'shipping' => '0',
                'admin_discount_type' => 'amount',
                'admin_discount_value' => '0',
                'delivery_status' => 'delivered',
                'items' => [[
                    'line_type' => 'manual',
                    'item_name' => 'Gold earring',
                    'product_category_id' => $category->id,
                    'quantity' => 2,
                    'unit_price' => '5000',
                    'unit_cost' => '2000',
                ]],
            ])
            ->assertRedirect(route('admin.sales.orders.index', ['tenant' => $tenant->id]).'#orders');

        $order = SalesOrder::query()->with('items')->firstOrFail();
        $item = $order->items->firstOrFail();

        $this->assertNull($order->inventory_location_id);
        $this->assertNull($item->product_variant_id);
        $this->assertSame('manual', $item->line_type);
        $this->assertSame('estimated', $item->cost_basis);
        $this->assertSame('Gold earring', $item->item_name);
        $this->assertSame('Earrings', $item->category_name);
        $this->assertSame(200000, $item->unit_cost_minor);
        $this->assertFalse((bool) $item->inventory_tracked);
        $this->assertDatabaseCount((new InventoryMovement)->getTable(), 0);

        $journal = FinanceJournalEntry::query()
            ->with('lines.account')
            ->where('source_type', 'sales_order')
            ->where('source_id', $order->id)
            ->firstOrFail();

        $this->assertTrue($journal->lines->contains(fn ($line): bool => $line->account->code === '1000' && $line->debit_minor === 1000000));
        $this->assertTrue($journal->lines->contains(fn ($line): bool => $line->account->code === '4000' && $line->credit_minor === 1000000));
        $this->assertFalse($journal->lines->contains(fn ($line): bool => in_array($line->account->code, ['1200', 'EXP-5000'], true)));

        $this->actingAs($user)
            ->get(route('admin.finance.reports.show', [
                'report' => 'product-profitability',
                'tenant' => $tenant->id,
                'date_from' => '2026-10-01',
                'date_to' => '2026-10-31',
            ]))
            ->assertOk()
            ->assertSee('Gold earring')
            ->assertSee('Earrings')
            ->assertSee('₦4,000.00 est.')
            ->assertSee('₦6,000.00');
    }

    public function test_manual_sale_without_cost_is_reported_as_unknown(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Unknown Cost Shop',
            'slug' => 'unknown-cost-shop',
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
        $customer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Walk-In',
            'phone' => 'WALK-IN',
            'status' => 'active',
        ]);
        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($user)->post(route('admin.sales.orders.store'), [
            'tenant_id' => $tenant->id,
            'source' => 'offline',
            'record_as' => 'completed_sale',
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'order_date' => '2026-10-05',
            'payment_method' => 'Cash',
            'amount_paid' => '15000',
            'delivery_status' => 'delivered',
            'items' => [[
                'line_type' => 'manual',
                'item_name' => 'Beaded jewelry',
                'quantity' => 1,
                'unit_price' => '15000',
            ]],
        ])->assertRedirect();

        $item = SalesOrder::query()->firstOrFail()->items()->firstOrFail();
        $this->assertSame('unknown', $item->cost_basis);
        $this->assertSame('Uncategorized', $item->category_name);

        $this->actingAs($user)
            ->get(route('admin.finance.reports.show', [
                'report' => 'product-profitability',
                'tenant' => $tenant->id,
                'date_from' => '2026-10-01',
                'date_to' => '2026-10-31',
            ]))
            ->assertOk()
            ->assertSee('Unknown')
            ->assertSee('Not available');
    }
}
