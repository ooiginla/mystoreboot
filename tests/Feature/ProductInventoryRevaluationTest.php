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
use Modules\Finance\Models\FinanceJournalEntry;
use Modules\Inventory\Actions\EnsureInventoryLocationsAction;
use Modules\Inventory\Actions\PostInventoryMovementAction;
use Modules\Inventory\Enums\InventoryMovementType;
use Modules\Inventory\Enums\StockCondition;
use Modules\Inventory\Models\InventoryMovement;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

class ProductInventoryRevaluationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_cost_change_revalues_existing_stock_without_changing_quantity(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Revaluation Shop',
            'slug' => 'revaluation-shop',
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
        $location = app(EnsureInventoryLocationsAction::class)->forBranch($branch);
        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Costed Product',
            'slug' => 'costed-product',
            'product_type' => ProductType::Product,
            'status' => ProductStatus::Draft,
            'track_inventory' => true,
            'base_price_minor' => 10000,
            'base_cost_price_minor' => 5000,
            'tax_behavior' => TaxBehavior::Exempt,
        ]);
        $variant = ProductVariant::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'variant_name' => $product->name,
            'sku' => 'COST-1',
            'selling_price_minor' => 10000,
            'cost_price_minor' => 5000,
            'tax_behavior' => TaxBehavior::Exempt,
            'status' => ProductStatus::Draft,
        ]);

        app(PostInventoryMovementAction::class)->execute([
            'tenant_id' => $tenant->id,
            'inventory_location_id' => $location->id,
            'product_variant_id' => $variant->id,
            'movement_type' => InventoryMovementType::OpeningStock->value,
            'stock_condition' => StockCondition::Sellable->value,
            'quantity' => 2,
            'unit_cost' => 50,
        ]);

        $user = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($user)
            ->get(route('admin.catalog.index', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertDontSee('Revalue current inventory using the saved cost')
            ->assertSee('Revalue existing inventory?')
            ->assertSee('class="btn danger" type="button" data-revalue-confirm-yes', false)
            ->assertSee('class="btn primary" type="button" data-revalue-confirm-no', false)
            ->assertSee('data-revalue-inventory-input', false);

        $this->actingAs($user)
            ->put(route('admin.catalog.products.update', $product), [
                'tenant_id' => $tenant->id,
                'product_type' => ProductType::Product->value,
                'name' => $product->name,
                'base_price' => '100',
                'base_cost_price' => '60',
                'tax_behavior' => TaxBehavior::Exempt->value,
                'status' => ProductStatus::Draft->value,
                'revalue_inventory' => '1',
            ])
            ->assertRedirect(route('admin.catalog.index', ['tenant' => $tenant->id]));

        $stock = InventoryStockLevel::query()->where('product_variant_id', $variant->id)->sole();
        $this->assertSame(2, (int) $stock->quantity_on_hand);
        $this->assertSame(6000, $stock->average_cost_minor);

        $movement = InventoryMovement::query()
            ->where('movement_type', InventoryMovementType::Revaluation->value)
            ->sole();
        $this->assertSame(0, (int) $movement->quantity);
        $this->assertSame(2000, $movement->movement_value_minor);
        $this->assertStringContainsString('by user '.$user->id, (string) $movement->notes);

        $journal = FinanceJournalEntry::query()
            ->with('lines.account')
            ->where('source_event', 'revalued')
            ->sole();
        $this->assertTrue($journal->lines->contains(fn ($line): bool => $line->account->code === '1200' && $line->debit_minor === 2000));
        $this->assertTrue($journal->lines->contains(fn ($line): bool => $line->account->code === '4120' && $line->credit_minor === 2000));

        // Choosing No still saves the new product cost for future stock without
        // rewriting the value of the two units already on hand.
        $this->actingAs($user)
            ->put(route('admin.catalog.products.update', $product), [
                'tenant_id' => $tenant->id,
                'product_type' => ProductType::Product->value,
                'name' => $product->name,
                'base_price' => '100',
                'base_cost_price' => '70',
                'tax_behavior' => TaxBehavior::Exempt->value,
                'status' => ProductStatus::Draft->value,
                'revalue_inventory' => '0',
            ])
            ->assertRedirect(route('admin.catalog.index', ['tenant' => $tenant->id]));

        $this->assertSame(7000, (int) $variant->refresh()->cost_price_minor);
        $this->assertSame(6000, InventoryStockLevel::query()->where('product_variant_id', $variant->id)->sole()->average_cost_minor);
        $this->assertSame(1, InventoryMovement::query()->where('movement_type', InventoryMovementType::Revaluation->value)->count());
    }
}
