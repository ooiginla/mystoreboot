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
use Modules\Inventory\Enums\StockCountStatus;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Inventory\Models\InventoryMovement;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Inventory\Models\StockCount;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class StockCountTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private InventoryLocation $location;

    private ProductVariant $rice;

    private ProductVariant $oil;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::query()->create([
            'name' => 'Count House', 'slug' => 'count-house', 'status' => TenantStatus::Active,
            'business_type' => 'restaurant', 'country_code' => 'NG', 'timezone' => 'Africa/Lagos', 'currency_code' => 'NGN',
        ]);
        $branch = Branch::query()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Main', 'code' => 'MAIN', 'status' => 'active', 'is_primary' => true,
        ]);
        $this->location = InventoryLocation::query()->create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $branch->id, 'name' => 'Central Store', 'code' => 'CENTRAL',
            'location_type' => InventoryLocationType::Warehouse->value, 'status' => 'active',
        ]);

        $this->rice = $this->makeVariant('Rice', 'RICE-C', ProductType::RawMaterial);
        $this->oil = $this->makeVariant('Oil', 'OIL-C', ProductType::RawMaterial);

        $this->stockIn($this->rice, 100, 500);   // 100 units at 5.00
        $this->stockIn($this->oil, 20, 1_000);   // 20 units at 10.00

        $this->user = User::factory()->create(['is_platform_admin' => true]);
    }

    public function test_opening_a_count_snapshots_current_on_hand_for_the_location(): void
    {
        $this->actingAs($this->user)
            ->post(route('admin.inventory.stock-counts.store'), [
                'tenant' => $this->tenant->id,
                'inventory_location_id' => $this->location->id,
                'is_blind' => '1',
            ])
            ->assertRedirect();

        $count = StockCount::query()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->assertSame(StockCountStatus::Counting, $count->status);
        $this->assertTrue($count->is_blind);
        $this->assertSame(2, $count->items()->count());

        $riceLine = $count->items()->where('product_variant_id', $this->rice->id)->firstOrFail();
        $this->assertSame(100.0, (float) $riceLine->system_quantity);
        $this->assertSame(500, (int) $riceLine->unit_cost_minor);
        $this->assertNull($riceLine->counted_quantity, 'A fresh line must be uncounted, not zero.');
    }

    public function test_posting_a_count_adjusts_stock_to_what_was_counted(): void
    {
        $count = $this->openCount();
        $riceLine = $count->items()->where('product_variant_id', $this->rice->id)->firstOrFail();
        $oilLine = $count->items()->where('product_variant_id', $this->oil->id)->firstOrFail();

        // 6 units of rice are missing; the oil shelf holds 2 more than the system thought.
        $this->actingAs($this->user)
            ->put(route('admin.inventory.stock-counts.items.update', $count), [
                'tenant' => $this->tenant->id,
                'counted' => [$riceLine->id => '94', $oilLine->id => '22'],
            ])
            ->assertRedirect();

        $this->assertSame(StockCountStatus::Review, $count->refresh()->status);
        $this->assertSame(-6.0, $count->items()->find($riceLine->id)->variance());

        $this->actingAs($this->user)
            ->post(route('admin.inventory.stock-counts.post', $count), ['tenant' => $this->tenant->id])
            ->assertRedirect();

        $count->refresh();
        $this->assertSame(StockCountStatus::Posted, $count->status);
        $this->assertNotNull($count->posted_at);

        $this->assertSame(94.0, $this->onHand($this->rice));
        $this->assertSame(22.0, $this->onHand($this->oil));

        // Shrinkage of 6 @ 5.00 against a gain of 2 @ 10.00 nets to -10.00.
        $this->assertSame(-1_000, $count->load('items')->varianceValueMinor());

        $adjustments = InventoryMovement::query()
            ->where('reference_type', 'stock_count')
            ->where('reference_id', $count->id)
            ->get();

        $this->assertCount(2, $adjustments);
        $this->assertSame(
            InventoryMovementType::AdjustmentOut->value,
            $adjustments->firstWhere('product_variant_id', $this->rice->id)->movement_type->value,
        );
        $this->assertSame(
            InventoryMovementType::AdjustmentIn->value,
            $adjustments->firstWhere('product_variant_id', $this->oil->id)->movement_type->value,
        );
    }

    public function test_uncounted_lines_are_left_alone_and_a_counted_zero_writes_the_stock_off(): void
    {
        $count = $this->openCount();
        $oilLine = $count->items()->where('product_variant_id', $this->oil->id)->firstOrFail();

        // Rice is left blank (nobody counted it); the oil shelf was checked and was empty.
        $this->actingAs($this->user)
            ->put(route('admin.inventory.stock-counts.items.update', $count), [
                'tenant' => $this->tenant->id,
                'counted' => [$oilLine->id => '0'],
            ])
            ->assertRedirect();

        $this->actingAs($this->user)
            ->post(route('admin.inventory.stock-counts.post', $count), ['tenant' => $this->tenant->id])
            ->assertRedirect();

        $this->assertSame(100.0, $this->onHand($this->rice), 'An uncounted line must not be adjusted.');
        $this->assertSame(0.0, $this->onHand($this->oil), 'A counted zero is a real write-off.');
    }

    public function test_posting_applies_the_variance_on_top_of_movements_made_while_counting(): void
    {
        $count = $this->openCount();
        $riceLine = $count->items()->where('product_variant_id', $this->rice->id)->firstOrFail();

        // Counter finds 94 of the 100 the system had — a shortfall of 6.
        $this->actingAs($this->user)
            ->put(route('admin.inventory.stock-counts.items.update', $count), [
                'tenant' => $this->tenant->id,
                'counted' => [$riceLine->id => '94'],
            ])
            ->assertRedirect();

        // Meanwhile the kitchen draws 10 more before anyone posts the sheet.
        app(PostInventoryMovementAction::class)->execute([
            'tenant_id' => $this->tenant->id, 'inventory_location_id' => $this->location->id,
            'product_variant_id' => $this->rice->id, 'movement_type' => InventoryMovementType::StockOut->value,
            'quantity' => 10,
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.inventory.stock-counts.post', $count), ['tenant' => $this->tenant->id])
            ->assertRedirect();

        // 100 - 10 drawn - 6 found missing = 84. The interim draw survives the post.
        $this->assertSame(84.0, $this->onHand($this->rice));
    }

    public function test_a_second_count_cannot_be_opened_while_one_is_still_running(): void
    {
        $this->openCount();

        $this->actingAs($this->user)
            ->post(route('admin.inventory.stock-counts.store'), [
                'tenant' => $this->tenant->id,
                'inventory_location_id' => $this->location->id,
            ])
            ->assertSessionHasErrors('inventory_location_id');

        $this->assertSame(1, StockCount::query()->where('tenant_id', $this->tenant->id)->count());
    }

    public function test_blind_count_hides_the_system_quantity_until_review(): void
    {
        $count = $this->openCount();

        $url = route('admin.inventory.stock-counts.show', ['stockCount' => $count->id, 'tenant' => $this->tenant->id]);

        $this->actingAs($this->user)->get($url)
            ->assertOk()
            ->assertDontSee('>System<', false);

        $count->update(['status' => StockCountStatus::Review->value]);

        $this->actingAs($this->user)->get($url)
            ->assertOk()
            ->assertSee('>System<', false);
    }

    private function openCount(): StockCount
    {
        $this->actingAs($this->user)->post(route('admin.inventory.stock-counts.store'), [
            'tenant' => $this->tenant->id,
            'inventory_location_id' => $this->location->id,
            'is_blind' => '1',
        ])->assertRedirect();

        return StockCount::query()->where('tenant_id', $this->tenant->id)->latest('id')->firstOrFail();
    }

    private function makeVariant(string $name, string $sku, ProductType $type): ProductVariant
    {
        $product = Product::query()->create([
            'tenant_id' => $this->tenant->id, 'name' => $name, 'slug' => str($name)->slug()->value(),
            'product_type' => $type->value, 'status' => ProductStatus::Active->value,
        ]);

        return ProductVariant::query()->create([
            'tenant_id' => $this->tenant->id, 'product_id' => $product->id, 'variant_name' => 'Default',
            'sku' => $sku, 'status' => ProductStatus::Active->value,
        ]);
    }

    private function stockIn(ProductVariant $variant, float $quantity, int $unitCostMinor): void
    {
        app(PostInventoryMovementAction::class)->execute([
            'tenant_id' => $this->tenant->id,
            'inventory_location_id' => $this->location->id,
            'product_variant_id' => $variant->id,
            'movement_type' => InventoryMovementType::OpeningStock->value,
            'quantity' => $quantity,
            'unit_cost_minor' => $unitCostMinor,
        ]);
    }

    private function onHand(ProductVariant $variant): float
    {
        return (float) InventoryStockLevel::query()
            ->where('inventory_location_id', $this->location->id)
            ->where('product_variant_id', $variant->id)
            ->value('quantity_on_hand');
    }
}
