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
use Modules\Inventory\Actions\PostInventoryMovementAction;
use Modules\Inventory\Enums\InventoryLocationType;
use Modules\Inventory\Enums\InventoryMovementType;
use Modules\Inventory\Models\InventoryBatch;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class BatchTraceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private InventoryLocation $store;

    private InventoryLocation $kitchen;

    private InventoryLocation $bar;

    private ProductVariant $milk;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::query()->create([
            'name' => 'Trace Foods', 'slug' => 'trace-foods', 'status' => TenantStatus::Active,
            'business_type' => 'restaurant', 'country_code' => 'NG', 'timezone' => 'Africa/Lagos', 'currency_code' => 'NGN',
        ]);
        $branch = Branch::query()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Main', 'code' => 'MAIN', 'status' => 'active', 'is_primary' => true,
        ]);

        $make = fn (string $name, string $code, string $type): InventoryLocation => InventoryLocation::query()->create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $branch->id, 'name' => $name, 'code' => $code,
            'location_type' => $type, 'status' => 'active',
        ]);

        $this->store = $make('Cold Store', 'COLD', InventoryLocationType::Warehouse->value);
        $this->kitchen = $make('Kitchen', 'KITCHEN', InventoryLocationType::StoreRoom->value);
        $this->bar = $make('Bar', 'BAR', InventoryLocationType::StoreRoom->value);

        $product = Product::query()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Milk', 'slug' => 'milk',
            'product_type' => ProductType::RawMaterial->value, 'status' => ProductStatus::Active->value,
        ]);
        $this->milk = ProductVariant::query()->create([
            'tenant_id' => $this->tenant->id, 'product_id' => $product->id, 'variant_name' => 'Default',
            'sku' => 'MILK-T', 'status' => ProductStatus::Active->value,
        ]);

        $this->user = User::factory()->create(['is_platform_admin' => true]);
    }

    public function test_the_lot_list_finds_a_batch_by_its_number(): void
    {
        $this->receive(20, '2026-11-30', 'LOT-RECALL');
        $this->receive(20, '2026-12-31', 'LOT-OTHER');

        $this->actingAs($this->user)
            ->get(route('admin.inventory.batches.index', ['tenant' => $this->tenant->id, 'q' => 'LOT-RECALL']))
            ->assertOk()
            ->assertSee('LOT-RECALL')
            ->assertDontSee('LOT-OTHER');
    }

    public function test_the_lot_list_can_show_batches_that_are_already_used_up(): void
    {
        $this->receive(10, '2026-11-30', 'LOT-GONE');
        $this->issue(10);

        $this->assertSame(0.0, (float) $this->batch('LOT-GONE')->quantity_remaining);

        // A recall has to reach consumed stock, so exhausted lots must stay findable.
        $this->actingAs($this->user)
            ->get(route('admin.inventory.batches.index', ['tenant' => $this->tenant->id, 'show' => 'exhausted']))
            ->assertOk()
            ->assertSee('LOT-GONE');

        $this->actingAs($this->user)
            ->get(route('admin.inventory.batches.index', ['tenant' => $this->tenant->id, 'show' => 'in-stock']))
            ->assertOk()
            ->assertDontSee('LOT-GONE');
    }

    public function test_the_trace_view_shows_the_movements_that_drew_from_the_lot(): void
    {
        $this->receive(20, '2026-11-30', 'LOT-RECALL');
        $this->issue(5);

        $batch = $this->batch('LOT-RECALL');

        $this->actingAs($this->user)
            ->get(route('admin.inventory.batches.show', ['batch' => $batch->id, 'tenant' => $this->tenant->id]))
            ->assertOk()
            ->assertSee('Where this lot went')
            ->assertSee(InventoryMovementType::StockOut->label());

        $this->assertSame(5.0, $batch->load('allocations.movement')->consumedQuantity());
        $this->assertSame(20.0, $batch->receivedQuantity());
    }

    public function test_a_recall_follows_the_lot_through_transfers_into_other_stores(): void
    {
        $this->receive(30, '2026-11-30', 'LOT-RECALL');

        $this->transfer($this->store, $this->kitchen, 12);
        // Second hop: some of the kitchen's stock moves on to the bar.
        $this->transfer($this->kitchen, $this->bar, 4);

        $origin = $this->batch('LOT-RECALL', $this->store->id);

        $response = $this->actingAs($this->user)
            ->get(route('admin.inventory.batches.show', ['batch' => $origin->id, 'tenant' => $this->tenant->id]))
            ->assertOk()
            ->assertSee('Downstream lots');

        // Both hops appear, so the recall reaches every store the lot touched.
        $response->assertSee('Kitchen')->assertSee('Bar');

        $kitchenLot = $this->batch('LOT-RECALL', $this->kitchen->id);
        $barLot = $this->batch('LOT-RECALL', $this->bar->id);

        $this->assertSame($origin->id, $kitchenLot->source_inventory_batch_id);
        $this->assertSame($kitchenLot->id, $barLot->source_inventory_batch_id, 'The chain must link hop to hop, not all back to the origin.');
        $this->assertSame('2026-11-30', $barLot->expiry_date->toDateString());
    }

    public function test_a_downstream_lot_links_back_to_where_it_came_from(): void
    {
        $this->receive(30, '2026-11-30', 'LOT-RECALL');
        $this->transfer($this->store, $this->kitchen, 10);

        $kitchenLot = $this->batch('LOT-RECALL', $this->kitchen->id);

        $this->actingAs($this->user)
            ->get(route('admin.inventory.batches.show', ['batch' => $kitchenLot->id, 'tenant' => $this->tenant->id]))
            ->assertOk()
            ->assertSee('Transferred in from')
            ->assertSee('Cold Store');
    }

    public function test_an_expired_lot_still_holding_stock_is_called_out(): void
    {
        $this->receive(10, now()->subDays(3)->toDateString(), 'LOT-STALE');

        $batch = $this->batch('LOT-STALE');

        $this->actingAs($this->user)
            ->get(route('admin.inventory.batches.show', ['batch' => $batch->id, 'tenant' => $this->tenant->id]))
            ->assertOk()
            ->assertSee('expired on');
    }

    public function test_a_lot_from_another_tenant_is_not_reachable(): void
    {
        $this->receive(10, '2026-11-30', 'LOT-PRIVATE');
        $batch = $this->batch('LOT-PRIVATE');

        $other = Tenant::query()->create([
            'name' => 'Other Co', 'slug' => 'other-co', 'status' => TenantStatus::Active,
            'business_type' => 'retail', 'country_code' => 'NG', 'timezone' => 'Africa/Lagos', 'currency_code' => 'NGN',
        ]);

        $this->actingAs($this->user)
            ->get(route('admin.inventory.batches.show', ['batch' => $batch->id, 'tenant' => $other->id]))
            ->assertForbidden();
    }

    private function receive(float $quantity, string $expiry, string $batchNumber): void
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

    private function transfer(InventoryLocation $from, InventoryLocation $to, float $quantity): void
    {
        app(PostInventoryMovementAction::class)->execute([
            'tenant_id' => $this->tenant->id,
            'inventory_location_id' => $from->id,
            'destination_inventory_location_id' => $to->id,
            'product_variant_id' => $this->milk->id,
            'movement_type' => InventoryMovementType::TransferOut->value,
            'quantity' => $quantity,
        ]);
    }

    private function batch(string $batchNumber, ?int $locationId = null): InventoryBatch
    {
        return InventoryBatch::query()
            ->where('inventory_location_id', $locationId ?? $this->store->id)
            ->where('product_variant_id', $this->milk->id)
            ->where('batch_number', $batchNumber)
            ->firstOrFail();
    }
}
