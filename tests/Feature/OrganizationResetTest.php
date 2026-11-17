<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Business\Models\Branch;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class OrganizationResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_clears_transactions_resets_inventory_and_keeps_config(): void
    {
        $tenant = $this->tenant('Reset Me', 'reset-me');
        $survivor = $this->tenant('Keep Me', 'keep-me');
        $admin = User::factory()->create(['is_platform_admin' => true]);

        // ----- Config / catalogue that MUST survive -----
        $branch = Branch::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Main',
            'code' => 'MAIN',
            'status' => 'active',
            'is_primary' => true,
        ]);
        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Tenant Product',
            'slug' => 'tenant-product',
            'product_type' => ProductType::Product->value,
            'status' => ProductStatus::Active->value,
        ]);
        $variant = ProductVariant::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'variant_name' => 'Default',
            'sku' => 'RESET-001',
            'status' => ProductStatus::Active->value,
        ]);
        $financeAccount = DB::table('finance_accounts')->insertGetId([
            'tenant_id' => $tenant->id,
            'code' => 'CASH-1001',
            'name' => 'Cash',
            'type' => 'asset',
            'normal_balance' => 'debit',
        ]);
        $expenseCategory = DB::table('finance_expense_categories')->insertGetId([
            'tenant_id' => $tenant->id,
            'finance_account_id' => $financeAccount,
            'name' => 'General',
            'code' => 'GEN',
        ]);
        $location = DB::table('inventory_locations')->insertGetId([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'Main Store',
        ]);
        DB::table('online_stores')->insert([
            'tenant_id' => $tenant->id,
            'store_name' => 'Reset Me Online',
            'username' => 'reset-me',
        ]);

        // ----- Transactional data that MUST be wiped -----
        DB::table('sales_orders')->insert([
            'tenant_id' => $tenant->id,
            'order_number' => 'SO-1',
            'invoice_number' => 'INV-1',
            'receipt_number' => 'RC-1',
            'order_date' => '2026-01-01',
        ]);
        $journalId = DB::table('finance_journal_entries')->insertGetId([
            'tenant_id' => $tenant->id,
            'entry_number' => 'JE-1',
            'entry_date' => '2026-01-01',
        ]);
        DB::table('finance_journal_lines')->insert([
            'tenant_id' => $tenant->id,
            'finance_journal_entry_id' => $journalId,
            'finance_account_id' => $financeAccount,
            'debit_minor' => 5000,
            'credit_minor' => 0,
        ]);
        DB::table('finance_expenses')->insert([
            'tenant_id' => $tenant->id,
            'finance_expense_category_id' => $expenseCategory,
            'expense_number' => 'EXP-1',
            'expense_date' => '2026-01-01',
            'amount_minor' => 2500,
        ]);
        $walletId = DB::table('wallets')->insertGetId([
            'tenant_id' => $tenant->id,
            'currency_code' => 'NGN',
            'available_balance_minor' => 90000,
            'pending_balance_minor' => 10000,
        ]);
        DB::table('wallet_transactions')->insert([
            'tenant_id' => $tenant->id,
            'wallet_id' => $walletId,
            'direction' => 'credit',
            'state' => 'available',
            'category' => 'adjustment',
            'amount_minor' => 1000,
            'currency_code' => 'NGN',
        ]);
        $customerId = DB::table('customers')->insertGetId([
            'tenant_id' => $tenant->id,
            'first_name' => 'Ada',
            'phone' => '08000000000',
            'loyalty_points' => 50,
            'account_balance_minor' => 7500,
        ]);
        DB::table('inventory_stock_levels')->insert([
            'tenant_id' => $tenant->id,
            'inventory_location_id' => $location,
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => 40,
            'quantity_reserved' => 5,
            'average_cost_minor' => 1500,
        ]);

        // Survivor tenant keeps its own transaction untouched.
        DB::table('sales_orders')->insert([
            'tenant_id' => $survivor->id,
            'order_number' => 'SO-S',
            'invoice_number' => 'INV-S',
            'receipt_number' => 'RC-S',
            'order_date' => '2026-01-01',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.business.organizations.reset', $tenant), [
                'confirmation' => $tenant->name,
            ])
            ->assertRedirect(route('admin.business.organizations.index'))
            ->assertSessionHas('status');

        // Transactions wiped for this tenant.
        foreach (['sales_orders', 'finance_journal_entries', 'finance_journal_lines', 'finance_expenses', 'wallet_transactions'] as $table) {
            $this->assertSame(0, DB::table($table)->where('tenant_id', $tenant->id)->count(), "{$table} should be emptied.");
        }

        // Inventory reset to zero (row kept).
        $stock = DB::table('inventory_stock_levels')->where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($stock, 'Stock-level row must be kept.');
        $this->assertSame(0, (int) $stock->quantity_on_hand);
        $this->assertSame(0, (int) $stock->quantity_reserved);
        $this->assertSame(0, (int) $stock->average_cost_minor);

        // Wallet balances zeroed (row kept).
        $wallet = DB::table('wallets')->where('tenant_id', $tenant->id)->first();
        $this->assertSame(0, (int) $wallet->available_balance_minor);
        $this->assertSame(0, (int) $wallet->pending_balance_minor);

        // Customer kept but balances zeroed.
        $customer = DB::table('customers')->where('id', $customerId)->first();
        $this->assertNotNull($customer, 'Customer record must be kept.');
        $this->assertSame(0, (int) $customer->loyalty_points);
        $this->assertSame(0, (int) $customer->account_balance_minor);

        // Config / catalogue preserved.
        $this->assertNotNull(Product::query()->find($product->id));
        $this->assertNotNull(ProductVariant::query()->find($variant->id));
        $this->assertNotNull(Branch::query()->find($branch->id));
        $this->assertSame(1, DB::table('finance_accounts')->where('tenant_id', $tenant->id)->count());
        $this->assertSame(1, DB::table('finance_expense_categories')->where('tenant_id', $tenant->id)->count());
        $this->assertSame(1, DB::table('inventory_locations')->where('tenant_id', $tenant->id)->count());
        $this->assertSame(1, DB::table('online_stores')->where('tenant_id', $tenant->id)->count());

        // Survivor untouched.
        $this->assertSame(1, DB::table('sales_orders')->where('tenant_id', $survivor->id)->count());
    }

    public function test_reset_requires_platform_admin_and_exact_name(): void
    {
        $tenant = $this->tenant('Guarded Org', 'guarded-org');
        $regularUser = User::factory()->create();
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($regularUser)
            ->post(route('admin.business.organizations.reset', $tenant), ['confirmation' => $tenant->name])
            ->assertForbidden();

        $this->actingAs($admin)
            ->from(route('admin.business.organizations.index'))
            ->post(route('admin.business.organizations.reset', $tenant), ['confirmation' => 'wrong name'])
            ->assertRedirect(route('admin.business.organizations.index'))
            ->assertSessionHasErrors('confirmation');
    }

    public function test_index_lists_recent_first_with_created_column_and_reset_dialog(): void
    {
        $older = $this->tenant('Older Org', 'older-org');
        $older->forceFill(['created_at' => Carbon::parse('2026-01-01 09:00:00')])->save();
        $newer = $this->tenant('Newer Org', 'newer-org');
        $newer->forceFill(['created_at' => Carbon::parse('2026-06-01 09:00:00')])->save();
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $response = $this->actingAs($admin)
            ->get(route('admin.business.organizations.index'))
            ->assertOk()
            ->assertSee('<th>Created</th>', false)
            ->assertSee('data-dialog-open="reset-organization-'.$newer->id.'"', false)
            ->assertSee(route('admin.business.organizations.reset', $newer), false);

        $content = $response->getContent();
        $this->assertLessThan(
            strpos($content, 'data-organization-id="'.$older->id.'"'),
            strpos($content, 'data-organization-id="'.$newer->id.'"'),
            'Most recently created organization should be listed first.',
        );
    }

    private function tenant(string $name, string $slug): Tenant
    {
        return Tenant::query()->create([
            'name' => $name,
            'slug' => $slug,
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
    }
}
