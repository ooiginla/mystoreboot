<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Business\Models\Branch;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Finance\Models\FinanceJournalEntry;
use Modules\Inventory\Enums\InventoryLocationType;
use Modules\Inventory\Enums\InventoryMovementType;
use Modules\Inventory\Enums\StockCondition;
use Modules\Inventory\Models\InventoryBatch;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Inventory\Models\InventoryMovement;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class InventoryMovementReversalTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_manual_movement_can_be_reversed_once_with_stock_cost_and_accounting_restored(): void
    {
        [$tenant, $location, $variant] = $this->context();
        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->postMovement($user, $tenant, $location, $variant, InventoryMovementType::OpeningStock, 10, '500');
        $this->postMovement($user, $tenant, $location, $variant, InventoryMovementType::StockIn, 10, '1000');

        $original = InventoryMovement::query()
            ->where('movement_type', InventoryMovementType::StockIn->value)
            ->sole();
        $stock = InventoryStockLevel::query()->sole();
        $this->assertSame(20.0, (float) $stock->quantity_on_hand);
        $this->assertSame(7500, (int) $stock->average_cost_minor);

        $this->actingAs($user)
            ->post(route('admin.inventory.movements.reverse', $original), ['tenant' => $tenant->id])
            ->assertRedirect(route('admin.inventory.index', ['tenant' => $tenant->id]).'#movements')
            ->assertSessionHasNoErrors();

        $reversal = InventoryMovement::query()->where('reversal_of_movement_id', $original->id)->sole();
        $this->assertSame(InventoryMovementType::AdjustmentOut, $reversal->movement_type);
        $this->assertSame(-10.0, (float) $reversal->quantity);
        $this->assertSame($user->id, $reversal->reversed_by_user_id);
        $this->assertSame('inventory_reversal', $reversal->reference_type);

        $stock->refresh();
        $this->assertSame(10.0, (float) $stock->quantity_on_hand);
        $this->assertSame(5000, (int) $stock->average_cost_minor);

        $originalJournal = FinanceJournalEntry::query()
            ->with('lines.account')
            ->where('source_type', 'inventory_movement')
            ->where('source_id', $original->id)
            ->sole();
        $reversalJournal = FinanceJournalEntry::query()
            ->with('lines.account')
            ->where('source_type', 'inventory_movement')
            ->where('source_id', $reversal->id)
            ->sole();
        $originalLines = $originalJournal->lines->mapWithKeys(fn ($line): array => [
            $line->account->code => [(int) $line->debit_minor, (int) $line->credit_minor],
        ])->all();
        $reversalLines = $reversalJournal->lines->mapWithKeys(fn ($line): array => [
            $line->account->code => [(int) $line->debit_minor, (int) $line->credit_minor],
        ])->all();
        foreach ($originalLines as $account => [$debit, $credit]) {
            $this->assertSame([$credit, $debit], $reversalLines[$account]);
        }

        $movementCount = InventoryMovement::query()->count();
        $this->actingAs($user)
            ->from(route('admin.inventory.index', ['tenant' => $tenant->id]).'#movements')
            ->post(route('admin.inventory.movements.reverse', $original), ['tenant' => $tenant->id])
            ->assertSessionHasErrors('movement');
        $this->assertSame($movementCount, InventoryMovement::query()->count());

        $this->actingAs($user)
            ->get(route('admin.inventory.index', ['tenant' => $tenant->id]).'#movements')
            ->assertOk()
            ->assertSee('Reversed')
            ->assertSee('Reversal');
    }

    public function test_reversing_a_transfer_moves_stock_back_without_duplicate_accounting(): void
    {
        [$tenant, $source, $variant] = $this->context();
        $destination = InventoryLocation::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $source->branch_id,
            'name' => 'Back Store',
            'code' => 'BACK',
            'location_type' => InventoryLocationType::StoreRoom->value,
            'status' => 'active',
        ]);
        $user = User::factory()->create(['is_platform_admin' => true]);
        $this->postMovement($user, $tenant, $source, $variant, InventoryMovementType::OpeningStock, 10, '500');

        $this->actingAs($user)->post(route('admin.inventory.movements.store'), [
            'tenant_id' => $tenant->id,
            'inventory_location_id' => $source->id,
            'destination_inventory_location_id' => $destination->id,
            'movement_type' => InventoryMovementType::TransferOut->value,
            'stock_condition' => StockCondition::Sellable->value,
            'items' => [[
                'product_variant_id' => $variant->id,
                'quantity' => 4,
            ]],
        ])->assertSessionHasNoErrors();

        $original = InventoryMovement::query()
            ->where('movement_type', InventoryMovementType::TransferOut->value)
            ->sole();
        $this->actingAs($user)
            ->post(route('admin.inventory.movements.reverse', $original), ['tenant' => $tenant->id])
            ->assertSessionHasNoErrors();

        $quantityAt = fn (InventoryLocation $location): float => (float) InventoryStockLevel::query()
            ->where('inventory_location_id', $location->id)
            ->where('product_variant_id', $variant->id)
            ->value('quantity_on_hand');
        $this->assertSame(10.0, $quantityAt($source));
        $this->assertSame(0.0, $quantityAt($destination));
        $this->assertDatabaseHas('inventory_movements', [
            'movement_type' => InventoryMovementType::TransferOut->value,
            'inventory_location_id' => $destination->id,
            'destination_inventory_location_id' => $source->id,
            'reversal_of_movement_id' => $original->id,
        ]);

        $this->assertSame(2, FinanceJournalEntry::query()
            ->where('source_type', 'inventory_movement')
            ->whereIn('source_id', [$original->id, $original->reversal()->value('id')])
            ->count());
    }

    public function test_reversing_a_batch_receipt_removes_the_same_batch(): void
    {
        [$tenant, $location, $variant] = $this->context();
        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($user)->post(route('admin.inventory.movements.store'), [
            'tenant_id' => $tenant->id,
            'inventory_location_id' => $location->id,
            'product_variant_id' => $variant->id,
            'movement_type' => InventoryMovementType::OpeningStock->value,
            'stock_condition' => StockCondition::Sellable->value,
            'quantity' => 10,
            'total_cost' => '500',
            'batch_number' => 'LOT-REV-1',
            'expiry_date' => '2027-10-12',
        ])->assertSessionHasNoErrors();

        $original = InventoryMovement::query()->sole();
        $batch = InventoryBatch::query()->sole();
        $this->assertSame($original->id, $batch->source_inventory_movement_id);
        $this->assertSame(10.0, (float) $batch->quantity_remaining);

        $this->actingAs($user)
            ->post(route('admin.inventory.movements.reverse', $original), ['tenant' => $tenant->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(0.0, (float) $batch->refresh()->quantity_remaining);
        $this->assertDatabaseHas('inventory_movement_batches', [
            'inventory_movement_id' => $original->reversal()->value('id'),
            'inventory_batch_id' => $batch->id,
            'quantity' => 10,
        ]);
    }

    private function postMovement(
        User $user,
        Tenant $tenant,
        InventoryLocation $location,
        ProductVariant $variant,
        InventoryMovementType $type,
        float $quantity,
        string $totalCost,
    ): void {
        $this->actingAs($user)->post(route('admin.inventory.movements.store'), [
            'tenant_id' => $tenant->id,
            'inventory_location_id' => $location->id,
            'product_variant_id' => $variant->id,
            'movement_type' => $type->value,
            'stock_condition' => StockCondition::Sellable->value,
            'quantity' => $quantity,
            'total_cost' => $totalCost,
        ])->assertSessionHasNoErrors();
    }

    /** @return array{Tenant, InventoryLocation, ProductVariant} */
    private function context(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Reversal Co', 'slug' => 'reversal-co', 'status' => TenantStatus::Active,
            'business_type' => 'retail', 'country_code' => 'NG', 'timezone' => 'Africa/Lagos', 'currency_code' => 'NGN',
        ]);
        $branch = Branch::query()->create([
            'tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'MAIN', 'status' => 'active', 'is_primary' => true,
        ]);
        $location = InventoryLocation::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'name' => 'Main', 'code' => 'MAIN',
            'location_type' => InventoryLocationType::Branch->value, 'status' => 'active',
        ]);
        $product = Product::query()->create([
            'tenant_id' => $tenant->id, 'name' => 'Rice', 'slug' => 'rice',
            'product_type' => ProductType::RawMaterial->value, 'status' => ProductStatus::Active->value,
        ]);
        $variant = ProductVariant::query()->create([
            'tenant_id' => $tenant->id, 'product_id' => $product->id, 'variant_name' => 'Default',
            'sku' => 'RICE-1', 'status' => ProductStatus::Active->value,
        ]);

        return [$tenant, $location, $variant];
    }
}
