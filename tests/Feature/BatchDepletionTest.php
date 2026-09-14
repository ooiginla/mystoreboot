<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Business\Models\Branch;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Inventory\Actions\PostInventoryMovementAction;
use Modules\Inventory\Enums\InventoryLocationType;
use Modules\Inventory\Enums\InventoryMovementType;
use Modules\Inventory\Enums\StockCondition;
use Modules\Inventory\Models\InventoryBatch;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Inventory\Models\InventoryMovement;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class BatchDepletionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private InventoryLocation $store;

    private InventoryLocation $kitchen;

    private ProductVariant $milk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::query()->create([
            'name' => 'Fresh Foods', 'slug' => 'fresh-foods', 'status' => TenantStatus::Active,
            'business_type' => 'restaurant', 'country_code' => 'NG', 'timezone' => 'Africa/Lagos', 'currency_code' => 'NGN',
        ]);
        $branch = Branch::query()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Main', 'code' => 'MAIN', 'status' => 'active', 'is_primary' => true,
        ]);
        $this->store = InventoryLocation::query()->create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $branch->id, 'name' => 'Cold Store', 'code' => 'COLD',
            'location_type' => InventoryLocationType::Warehouse->value, 'status' => 'active',
        ]);
        $this->kitchen = InventoryLocation::query()->create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $branch->id, 'name' => 'Kitchen', 'code' => 'KITCHEN',
            'location_type' => InventoryLocationType::StoreRoom->value, 'status' => 'active',
        ]);

        $product = Product::query()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Milk', 'slug' => 'milk',
            'product_type' => ProductType::RawMaterial->value, 'status' => ProductStatus::Active->value,
        ]);
        $this->milk = ProductVariant::query()->create([
            'tenant_id' => $this->tenant->id, 'product_id' => $product->id, 'variant_name' => 'Default',
            'sku' => 'MILK-1', 'status' => ProductStatus::Active->value,
        ]);
    }

    public function test_outbound_stock_takes_the_soonest_expiring_lot_first(): void
    {
        // Received out of expiry order on purpose: the later-received crate expires first.
        $this->receive(10, '2026-12-31', 'LOT-DEC');
        $this->receive(10, '2026-10-01', 'LOT-OCT');

        $this->issue(12);

        $this->assertSame(0.0, $this->batchRemaining('LOT-OCT'), 'The October lot must go first.');
        $this->assertSame(8.0, $this->batchRemaining('LOT-DEC'), 'Only the overflow comes from December.');
    }

    public function test_lots_without_an_expiry_date_are_taken_last_and_then_oldest_first(): void
    {
        $this->receive(5, null, 'LOT-UNDATED');
        $this->receive(5, '2027-01-15', 'LOT-DATED');

        $this->issue(6);

        $this->assertSame(0.0, $this->batchRemaining('LOT-DATED'), 'A dated lot outranks an undated one.');
        $this->assertSame(4.0, $this->batchRemaining('LOT-UNDATED'));
    }

    public function test_the_movement_records_which_lots_it_drew_from(): void
    {
        $this->receive(10, '2026-10-01', 'LOT-OCT');
        $this->receive(10, '2026-12-31', 'LOT-DEC');

        $this->issue(14);

        $movement = InventoryMovement::query()
            ->where('movement_type', InventoryMovementType::StockOut->value)
            ->latest('id')
            ->firstOrFail();

        $allocations = $movement->batchAllocations()->with('batch')->get();

        $this->assertCount(2, $allocations, 'A movement spanning two lots records two allocations.');
        $this->assertSame(10.0, (float) $allocations->firstWhere('batch.batch_number', 'LOT-OCT')->quantity);
        $this->assertSame(4.0, (float) $allocations->firstWhere('batch.batch_number', 'LOT-DEC')->quantity);
    }

    public function test_quarantined_lots_are_never_drawn_from(): void
    {
        $this->receive(10, '2026-10-01', 'LOT-GOOD');

        InventoryBatch::query()->create([
            'tenant_id' => $this->tenant->id,
            'inventory_location_id' => $this->store->id,
            'product_variant_id' => $this->milk->id,
            'batch_number' => 'LOT-SPOILED',
            'expiry_date' => '2026-01-01', // Would sort first under FEFO if it were eligible.
            'stock_condition' => StockCondition::Damaged->value,
            'quantity_remaining' => 10,
            'unit_cost_minor' => 500,
        ]);

        $this->issue(4);

        $this->assertSame(10.0, $this->batchRemaining('LOT-SPOILED'), 'Damaged stock is not on the shelf.');
        $this->assertSame(6.0, $this->batchRemaining('LOT-GOOD'));
    }

    public function test_a_transfer_carries_lot_identity_to_the_destination(): void
    {
        $this->receive(10, '2026-10-01', 'LOT-OCT');
        $this->receive(10, '2026-12-31', 'LOT-DEC');

        app(PostInventoryMovementAction::class)->execute([
            'tenant_id' => $this->tenant->id,
            'inventory_location_id' => $this->store->id,
            'destination_inventory_location_id' => $this->kitchen->id,
            'product_variant_id' => $this->milk->id,
            'movement_type' => InventoryMovementType::TransferOut->value,
            'quantity' => 12,
        ]);

        $this->assertSame(0.0, $this->batchRemaining('LOT-OCT', $this->store->id));
        $this->assertSame(8.0, $this->batchRemaining('LOT-DEC', $this->store->id));

        // The kitchen now holds the same two lots, with their expiry dates intact.
        $this->assertSame(10.0, $this->batchRemaining('LOT-OCT', $this->kitchen->id));
        $this->assertSame(2.0, $this->batchRemaining('LOT-DEC', $this->kitchen->id));

        $moved = InventoryBatch::query()
            ->where('inventory_location_id', $this->kitchen->id)
            ->where('batch_number', 'LOT-OCT')
            ->firstOrFail();
        $this->assertSame('2026-10-01', $moved->expiry_date->toDateString());
    }

    public function test_untracked_stock_still_moves_when_no_lots_were_recorded(): void
    {
        // Opening stock with no batch number or expiry creates no batch record at all.
        app(PostInventoryMovementAction::class)->execute([
            'tenant_id' => $this->tenant->id,
            'inventory_location_id' => $this->store->id,
            'product_variant_id' => $this->milk->id,
            'movement_type' => InventoryMovementType::OpeningStock->value,
            'quantity' => 20,
            'unit_cost_minor' => 500,
        ]);

        $this->assertSame(0, InventoryBatch::query()->where('product_variant_id', $this->milk->id)->count());

        $this->issue(5);

        $movement = InventoryMovement::query()
            ->where('movement_type', InventoryMovementType::StockOut->value)
            ->latest('id')
            ->firstOrFail();

        // Nothing to trace, but the movement is not blocked — stock levels stay authoritative.
        $this->assertSame(0, $movement->batchAllocations()->count());
        $this->assertSame(15.0, (float) $movement->stock_after);
    }

    private function receive(float $quantity, ?string $expiry, string $batchNumber): void
    {
        app(PostInventoryMovementAction::class)->execute([
            'tenant_id' => $this->tenant->id,
            'inventory_location_id' => $this->store->id,
            'product_variant_id' => $this->milk->id,
            'movement_type' => InventoryMovementType::StockIn->value,
            'quantity' => $quantity,
            'unit_cost_minor' => 500,
            'batch_number' => $batchNumber,
            'expiry_date' => $expiry,
        ]);
    }

    private function issue(float $quantity): void
    {
        app(PostInventoryMovementAction::class)->execute([
            'tenant_id' => $this->tenant->id,
            'inventory_location_id' => $this->store->id,
            'product_variant_id' => $this->milk->id,
            'movement_type' => InventoryMovementType::StockOut->value,
            'quantity' => $quantity,
        ]);
    }

    private function batchRemaining(string $batchNumber, ?int $locationId = null): float
    {
        return (float) InventoryBatch::query()
            ->where('inventory_location_id', $locationId ?? $this->store->id)
            ->where('product_variant_id', $this->milk->id)
            ->where('batch_number', $batchNumber)
            ->value('quantity_remaining');
    }
}
