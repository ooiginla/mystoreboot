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
use Modules\Inventory\Enums\RequisitionStatus;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Inventory\Models\StockRequisition;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

/**
 * Approving a requisition must actually promise the stock. Before reservations, two
 * requisitions could both be approved against the same 12kg and the second would fail
 * only at fulfilment.
 */
final class RequisitionReservationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private InventoryLocation $source;

    private InventoryLocation $destination;

    private ProductVariant $rice;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::query()->create([
            'name' => 'Reserve Co', 'slug' => 'reserve-co', 'status' => TenantStatus::Active,
            'business_type' => 'restaurant', 'country_code' => 'NG', 'timezone' => 'Africa/Lagos', 'currency_code' => 'NGN',
        ]);
        $branch = Branch::query()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Main', 'code' => 'MAIN', 'status' => 'active', 'is_primary' => true,
        ]);
        $this->source = InventoryLocation::query()->create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $branch->id, 'name' => 'Central Store', 'code' => 'CENTRAL',
            'location_type' => InventoryLocationType::Warehouse->value, 'status' => 'active',
        ]);
        $this->destination = InventoryLocation::query()->create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $branch->id, 'name' => 'Grill Store', 'code' => 'GRILL',
            'location_type' => InventoryLocationType::StoreRoom->value, 'status' => 'active',
        ]);

        $product = Product::query()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Rice', 'slug' => 'rice-res',
            'product_type' => ProductType::RawMaterial->value, 'status' => ProductStatus::Active->value,
        ]);
        $this->rice = ProductVariant::query()->create([
            'tenant_id' => $this->tenant->id, 'product_id' => $product->id, 'variant_name' => 'Default',
            'sku' => 'RICE-RES', 'status' => ProductStatus::Active->value,
        ]);

        app(PostInventoryMovementAction::class)->execute([
            'tenant_id' => $this->tenant->id, 'inventory_location_id' => $this->source->id,
            'product_variant_id' => $this->rice->id, 'movement_type' => InventoryMovementType::OpeningStock->value,
            'quantity' => 12, 'unit_cost_minor' => 500,
        ]);

        $this->user = User::factory()->create(['is_platform_admin' => true]);
    }

    public function test_approving_holds_the_stock_at_the_source(): void
    {
        $requisition = $this->submit(10);

        $this->assertSame(0.0, $this->reserved(), 'Submitting alone must not hold stock.');

        $this->approve($requisition)->assertRedirect();

        $this->assertSame(RequisitionStatus::Approved, $requisition->refresh()->status);
        $this->assertSame(10.0, $this->reserved());
        $this->assertSame(2.0, $this->available(), 'Only the unreserved balance stays available.');
        $this->assertSame(12.0, $this->onHand(), 'Reserving moves nothing physically.');
    }

    public function test_a_second_requisition_cannot_be_approved_against_reserved_stock(): void
    {
        $first = $this->submit(10);
        $second = $this->submit(5);

        $this->approve($first)->assertRedirect();

        $this->approve($second)->assertSessionHasErrors('items');

        $this->assertSame(RequisitionStatus::Submitted, $second->refresh()->status, 'A failed approval must not change status.');
        $this->assertSame(10.0, $this->reserved(), 'The failed approval must not add a partial hold.');
    }

    public function test_fulfilling_releases_the_hold_and_ships_the_stock(): void
    {
        $requisition = $this->submit(10);
        $this->approve($requisition)->assertRedirect();

        $this->actingAs($this->user)
            ->post(route('admin.inventory.requisitions.fulfil', $requisition), ['tenant' => $this->tenant->id])
            ->assertRedirect();

        $this->assertSame(RequisitionStatus::Fulfilled, $requisition->refresh()->status);
        $this->assertSame(0.0, $this->reserved(), 'The hold must not outlive the shipment.');
        $this->assertSame(2.0, $this->onHand());
        $this->assertSame(2.0, $this->available());
        $this->assertSame(10.0, $this->onHand($this->destination));
    }

    public function test_cancelling_an_approved_requisition_gives_the_stock_back(): void
    {
        $requisition = $this->submit(10);
        $this->approve($requisition)->assertRedirect();
        $this->assertSame(10.0, $this->reserved());

        $this->actingAs($this->user)
            ->post(route('admin.inventory.requisitions.cancel', $requisition), ['tenant' => $this->tenant->id])
            ->assertRedirect();

        $this->assertSame(RequisitionStatus::Cancelled, $requisition->refresh()->status);
        $this->assertSame(0.0, $this->reserved());
        $this->assertSame(12.0, $this->available(), 'Cancelling frees the stock for everyone else.');
    }

    public function test_cancelling_a_submitted_requisition_does_not_touch_reservations(): void
    {
        $requisition = $this->submit(10);

        $this->actingAs($this->user)
            ->post(route('admin.inventory.requisitions.cancel', $requisition), ['tenant' => $this->tenant->id])
            ->assertRedirect();

        $this->assertSame(0.0, $this->reserved());
        $this->assertSame(12.0, $this->available());
    }

    public function test_a_short_fulfilment_still_releases_the_whole_hold(): void
    {
        $requisition = $this->submit(10);
        $this->approve($requisition)->assertRedirect();

        $item = $requisition->refresh()->items->first();

        // Only 4 are actually shipped against a hold of 10.
        $this->actingAs($this->user)
            ->post(route('admin.inventory.requisitions.fulfil', $requisition), [
                'tenant' => $this->tenant->id,
                'fulfilled' => [$item->id => 4],
            ])
            ->assertRedirect();

        $this->assertSame(0.0, $this->reserved(), 'The unshipped balance must not stay locked away.');
        $this->assertSame(8.0, $this->onHand());
        $this->assertSame(8.0, $this->available());
        $this->assertSame(4.0, $this->onHand($this->destination));
    }

    private function submit(float $quantity): StockRequisition
    {
        $this->actingAs($this->user)->post(route('admin.inventory.requisitions.store'), [
            'tenant' => $this->tenant->id,
            'source_location_id' => $this->source->id,
            'destination_location_id' => $this->destination->id,
            'items' => [['product_variant_id' => $this->rice->id, 'requested_quantity' => $quantity]],
        ])->assertRedirect();

        return StockRequisition::query()->where('tenant_id', $this->tenant->id)->latest('id')->firstOrFail();
    }

    private function approve(StockRequisition $requisition): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user)
            ->post(route('admin.inventory.requisitions.approve', $requisition), ['tenant' => $this->tenant->id]);
    }

    private function level(?InventoryLocation $location = null): ?InventoryStockLevel
    {
        return InventoryStockLevel::query()
            ->where('inventory_location_id', ($location ?? $this->source)->id)
            ->where('product_variant_id', $this->rice->id)
            ->first();
    }

    private function reserved(): float
    {
        return (float) ($this->level()?->quantity_reserved ?? 0);
    }

    private function available(): float
    {
        return (float) ($this->level()?->quantity_available ?? 0);
    }

    private function onHand(?InventoryLocation $location = null): float
    {
        return (float) ($this->level($location)?->quantity_on_hand ?? 0);
    }
}
