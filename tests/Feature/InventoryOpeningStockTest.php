<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Branch;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Finance\Models\FinanceJournalEntry;
use Modules\Inventory\Actions\PostInventoryMovementAction;
use Modules\Inventory\Enums\InventoryLocationType;
use Modules\Inventory\Enums\InventoryMovementType;
use Modules\Inventory\Enums\StockCondition;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Inventory\Models\InventoryMovement;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class InventoryOpeningStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_stock_is_available_and_requires_a_positive_unit_cost(): void
    {
        [$tenant, $location, $variant] = $this->inventoryContext();
        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($user)
            ->get(route('admin.inventory.index', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('value="opening_stock"', false)
            ->assertSee('Opening stock')
            ->assertSee('data-movement-unit-cost', false)
            ->assertSee("['opening_stock', 'stock_in']", false)
            ->assertSee('Uses current average cost');

        $payload = [
            'tenant_id' => $tenant->id,
            'inventory_location_id' => $location->id,
            'product_variant_id' => $variant->id,
            'movement_type' => InventoryMovementType::OpeningStock->value,
            'stock_condition' => StockCondition::Sellable->value,
            'quantity' => 12,
        ];

        $this->actingAs($user)
            ->post(route('admin.inventory.movements.store'), $payload)
            ->assertSessionHasErrors('unit_cost');

        $this->actingAs($user)
            ->post(route('admin.inventory.movements.store'), [...$payload, 'unit_cost' => 0])
            ->assertSessionHasErrors('unit_cost');

        $stockInPayload = [
            ...$payload,
            'movement_type' => InventoryMovementType::StockIn->value,
        ];

        $this->actingAs($user)
            ->post(route('admin.inventory.movements.store'), $stockInPayload)
            ->assertSessionHasErrors('unit_cost');

        $this->actingAs($user)
            ->post(route('admin.inventory.movements.store'), [...$stockInPayload, 'unit_cost' => 0])
            ->assertSessionHasErrors('unit_cost');

        $this->assertArrayNotHasKey(
            InventoryMovementType::Returned->value,
            InventoryMovementType::options(),
        );

        $this->actingAs($user)
            ->post(route('admin.inventory.movements.store'), [
                ...$payload,
                'movement_type' => InventoryMovementType::Returned->value,
            ])
            ->assertSessionHasErrors('movement_type');

        $this->actingAs($user)
            ->post(route('admin.inventory.movements.store'), [
                ...$payload,
                'movement_type' => InventoryMovementType::AdjustmentIn->value,
                'reference_type' => 'sales_order',
            ])
            ->assertSessionHasErrors('reference_type');
    }

    public function test_opening_stock_debits_inventory_and_credits_opening_balance_equity(): void
    {
        [$tenant, $location, $variant, $branch] = $this->inventoryContext();

        app(PostInventoryMovementAction::class)->execute([
            'tenant_id' => $tenant->id,
            'inventory_location_id' => $location->id,
            'product_variant_id' => $variant->id,
            'movement_type' => InventoryMovementType::OpeningStock->value,
            'stock_condition' => StockCondition::Sellable->value,
            'quantity' => 12,
            'unit_cost' => 250,
            'occurred_at' => '2026-07-25',
            'notes' => 'Imported when Storeboot went live.',
        ]);

        $stockLevel = InventoryStockLevel::query()
            ->where('inventory_location_id', $location->id)
            ->where('product_variant_id', $variant->id)
            ->firstOrFail();

        $this->assertSame(12, $stockLevel->quantity_on_hand);
        $this->assertSame(25000, $stockLevel->average_cost_minor);

        $journal = FinanceJournalEntry::query()
            ->with('lines.account')
            ->where('tenant_id', $tenant->id)
            ->where('source_type', 'inventory_movement')
            ->sole();

        $this->assertSame('Inventory opening stock', $journal->memo);
        $this->assertTrue($journal->lines->contains(
            fn ($line): bool => $line->account->code === '1200'
                && $line->branch_id === $branch->id
                && $line->debit_minor === 300000,
        ));
        $this->assertTrue($journal->lines->contains(
            fn ($line): bool => $line->account->code === '3400'
                && $line->account->name === 'Opening Balance Equity'
                && $line->account->type === 'equity'
                && $line->branch_id === $branch->id
                && $line->credit_minor === 300000,
        ));

        $user = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($user)
            ->get(route('admin.finance.journals', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('Prepare clearing journal')
            ->assertSee('data-opening-equity-balance="300000"', false);
    }

    public function test_opening_stock_action_cannot_bypass_the_unit_cost_requirement(): void
    {
        [$tenant, $location, $variant] = $this->inventoryContext();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Enter a unit cost greater than zero for opening stock.');

        app(PostInventoryMovementAction::class)->execute([
            'tenant_id' => $tenant->id,
            'inventory_location_id' => $location->id,
            'product_variant_id' => $variant->id,
            'movement_type' => InventoryMovementType::OpeningStock->value,
            'stock_condition' => StockCondition::Sellable->value,
            'quantity' => 12,
            'unit_cost' => 0,
        ]);
    }

    public function test_other_manual_movements_ignore_submitted_unit_cost_and_use_current_average(): void
    {
        [$tenant, $location, $variant] = $this->inventoryContext();
        $user = User::factory()->create(['is_platform_admin' => true]);

        app(PostInventoryMovementAction::class)->execute([
            'tenant_id' => $tenant->id,
            'inventory_location_id' => $location->id,
            'product_variant_id' => $variant->id,
            'movement_type' => InventoryMovementType::OpeningStock->value,
            'stock_condition' => StockCondition::Sellable->value,
            'quantity' => 10,
            'unit_cost' => 100,
        ]);

        $this->actingAs($user)
            ->post(route('admin.inventory.movements.store'), [
                'tenant_id' => $tenant->id,
                'inventory_location_id' => $location->id,
                'product_variant_id' => $variant->id,
                'movement_type' => InventoryMovementType::AdjustmentIn->value,
                'stock_condition' => StockCondition::Sellable->value,
                'quantity' => 2,
                'unit_cost' => 999,
            ])
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect(route('admin.inventory.index', ['tenant' => $tenant->id]).'#movements');

        $stockLevel = InventoryStockLevel::query()
            ->where('inventory_location_id', $location->id)
            ->where('product_variant_id', $variant->id)
            ->firstOrFail();
        $movement = InventoryMovement::query()
            ->where('movement_type', InventoryMovementType::AdjustmentIn->value)
            ->sole();

        $this->assertSame(12, $stockLevel->quantity_on_hand);
        $this->assertSame(10000, $stockLevel->average_cost_minor);
        $this->assertSame(10000, $movement->unit_cost_minor);
    }

    public function test_transfer_posts_inventory_between_branch_ledgers_without_changing_total_inventory(): void
    {
        [$tenant, $sourceLocation, $variant, $sourceBranch] = $this->inventoryContext();
        $destinationBranch = Branch::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Second Branch',
            'code' => 'SECOND',
            'status' => 'active',
            'is_primary' => false,
        ]);
        $destinationLocation = InventoryLocation::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $destinationBranch->id,
            'name' => 'Second Branch',
            'code' => 'SECOND',
            'location_type' => InventoryLocationType::Branch->value,
            'status' => 'active',
        ]);

        app(PostInventoryMovementAction::class)->execute([
            'tenant_id' => $tenant->id,
            'inventory_location_id' => $sourceLocation->id,
            'product_variant_id' => $variant->id,
            'movement_type' => InventoryMovementType::OpeningStock->value,
            'stock_condition' => StockCondition::Sellable->value,
            'quantity' => 10,
            'unit_cost' => 100,
        ]);

        app(PostInventoryMovementAction::class)->execute([
            'tenant_id' => $tenant->id,
            'inventory_location_id' => $sourceLocation->id,
            'destination_inventory_location_id' => $destinationLocation->id,
            'product_variant_id' => $variant->id,
            'movement_type' => InventoryMovementType::TransferOut->value,
            'stock_condition' => StockCondition::Sellable->value,
            'quantity' => 4,
        ]);

        $journal = FinanceJournalEntry::query()
            ->with('lines.account')
            ->where('source_type', 'inventory_movement')
            ->where('source_event', 'transferred')
            ->sole();

        $this->assertTrue($journal->lines->contains(
            fn ($line): bool => $line->account->code === '1200'
                && $line->branch_id === $destinationBranch->id
                && $line->debit_minor === 40000,
        ));
        $this->assertTrue($journal->lines->contains(
            fn ($line): bool => $line->account->code === '1200'
                && $line->branch_id === $sourceBranch->id
                && $line->credit_minor === 40000,
        ));
        $this->assertSame(6, InventoryStockLevel::query()->where('inventory_location_id', $sourceLocation->id)->sole()->quantity_on_hand);
        $this->assertSame(4, InventoryStockLevel::query()->where('inventory_location_id', $destinationLocation->id)->sole()->quantity_on_hand);
        $this->assertSame(
            100000,
            InventoryStockLevel::query()->get()->sum(
                fn (InventoryStockLevel $level): int => $level->quantity_on_hand * $level->average_cost_minor,
            ),
        );
    }

    /**
     * @return array{Tenant, InventoryLocation, ProductVariant, Branch}
     */
    private function inventoryContext(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Existing Store',
            'slug' => 'existing-store',
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
            'name' => 'Imported Product',
            'slug' => 'imported-product',
            'product_type' => ProductType::Product->value,
            'status' => ProductStatus::Active->value,
        ]);
        $variant = ProductVariant::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'variant_name' => 'Default',
            'sku' => 'IMPORTED-001',
            'status' => ProductStatus::Active->value,
        ]);

        return [$tenant, $location, $variant, $branch];
    }
}
