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
use Modules\Inventory\Enums\InventoryLocationType;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Inventory\Models\UnitCategory;
use Modules\Inventory\Models\UnitOfMeasure;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class ReorderLevelTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private InventoryLocation $store;

    private InventoryLocation $bar;

    private ProductVariant $flour;

    private UnitOfMeasure $kg;

    private UnitOfMeasure $litre;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::query()->create([
            'name' => 'Reorder Co', 'slug' => 'reorder-co', 'status' => TenantStatus::Active,
            'business_type' => 'restaurant', 'country_code' => 'NG', 'timezone' => 'Africa/Lagos', 'currency_code' => 'NGN',
        ]);
        $branch = Branch::query()->create(['tenant_id' => $this->tenant->id, 'name' => 'Main', 'code' => 'MAIN', 'status' => 'active', 'is_primary' => true]);
        $this->store = $this->location($branch, 'Central Store', 'CENTRAL');
        $this->bar = $this->location($branch, 'Pool Bar', 'POOL');

        $weight = UnitCategory::query()->create(['tenant_id' => $this->tenant->id, 'name' => 'Weight', 'is_default' => false]);
        $gram = UnitOfMeasure::query()->create([
            'tenant_id' => $this->tenant->id, 'unit_category_id' => $weight->id, 'code' => 'g', 'name' => 'Gram',
            'dimension' => 'weight', 'to_base_factor' => 1, 'is_base_for_dimension' => true, 'status' => 'active',
        ]);
        $this->kg = UnitOfMeasure::query()->create([
            'tenant_id' => $this->tenant->id, 'unit_category_id' => $weight->id, 'code' => 'kg', 'name' => 'Kilogram',
            'dimension' => 'weight', 'to_base_factor' => 1000, 'is_base_for_dimension' => false, 'status' => 'active',
        ]);
        $volume = UnitCategory::query()->create(['tenant_id' => $this->tenant->id, 'name' => 'Volume', 'is_default' => false]);
        $this->litre = UnitOfMeasure::query()->create([
            'tenant_id' => $this->tenant->id, 'unit_category_id' => $volume->id, 'code' => 'l', 'name' => 'Litre',
            'dimension' => 'volume', 'to_base_factor' => 1000, 'is_base_for_dimension' => false, 'status' => 'active',
        ]);

        $product = Product::query()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Flour', 'slug' => 'flour',
            'product_type' => ProductType::RawMaterial->value, 'status' => ProductStatus::Active->value,
            'unit_category_id' => $weight->id,
        ]);
        $this->flour = ProductVariant::query()->create([
            'tenant_id' => $this->tenant->id, 'product_id' => $product->id, 'variant_name' => 'Default',
            'sku' => 'FLOUR-1', 'base_unit_id' => $gram->id, 'status' => ProductStatus::Active->value,
        ]);

        $this->user = User::factory()->create(['is_platform_admin' => true]);
    }

    public function test_a_decimal_level_in_kg_is_stored_in_grams_and_flags_low_stock(): void
    {
        $this->stock($this->store, 2000);
        $back = route('admin.inventory.reorder-levels.index', ['tenant' => $this->tenant->id]);

        $this->actingAs($this->user)->from($back)->post(route('admin.inventory.reorder.save'), [
            'tenant_id' => $this->tenant->id,
            'rows' => [
                $this->row($this->store, 2.5, 10, $this->kg->id),
                $this->row($this->bar, null, null, $this->kg->id),
            ],
        ])->assertSessionHasNoErrors()->assertRedirect($back)->assertSessionHas('status', 'Reorder level saved.');

        $level = $this->levelAt($this->store);
        $this->assertSame(2500.0, (float) $level->reorder_level);
        $this->assertSame(10000.0, (float) $level->reorder_quantity);
        $this->assertTrue($level->is_low_stock, '2000 g available is at or below 2.5 kg');

        // A blank row for a location the item was never kept at creates nothing.
        $this->assertNull($this->levelAt($this->bar));
    }

    public function test_clearing_a_level_stops_monitoring(): void
    {
        $this->stock($this->store, 100, level: 500);

        $this->actingAs($this->user)->post(route('admin.inventory.reorder.save'), [
            'tenant_id' => $this->tenant->id,
            'rows' => [$this->row($this->store, null, null)],
        ])->assertSessionHasNoErrors();

        $level = $this->levelAt($this->store);
        $this->assertSame(0.0, (float) $level->reorder_level);
        $this->assertFalse($level->is_low_stock);
    }

    public function test_a_unit_the_item_is_not_measured_in_is_rejected(): void
    {
        $this->actingAs($this->user)->post(route('admin.inventory.reorder.save'), [
            'tenant_id' => $this->tenant->id,
            'rows' => [$this->row($this->store, 2, 0, $this->litre->id)],
        ])->assertSessionHasErrors('rows.0.unit_id');

        $this->assertNull($this->levelAt($this->store));
    }

    public function test_a_location_from_another_business_is_rejected(): void
    {
        $other = Tenant::query()->create([
            'name' => 'Other Co', 'slug' => 'other-co', 'status' => TenantStatus::Active,
            'business_type' => 'restaurant', 'country_code' => 'NG', 'timezone' => 'Africa/Lagos', 'currency_code' => 'NGN',
        ]);
        $otherBranch = Branch::query()->create(['tenant_id' => $other->id, 'name' => 'Main', 'code' => 'MAIN', 'status' => 'active', 'is_primary' => true]);
        $foreign = InventoryLocation::query()->create([
            'tenant_id' => $other->id, 'branch_id' => $otherBranch->id, 'name' => 'Theirs', 'code' => 'THEIRS',
            'location_type' => InventoryLocationType::Branch->value, 'status' => 'active',
        ]);

        $this->actingAs($this->user)->post(route('admin.inventory.reorder.save'), [
            'tenant_id' => $this->tenant->id,
            'rows' => [$this->row($foreign, 5, 0)],
        ])->assertSessionHasErrors('rows.0.product_variant_id');
    }

    public function test_bulk_page_shows_each_item_with_its_status_at_the_location(): void
    {
        $this->stock($this->store, 2000, level: 2500);

        $this->actingAs($this->user)
            ->get(route('admin.inventory.reorder-levels.index', ['tenant' => $this->tenant->id, 'location' => $this->store->id]))
            ->assertOk()
            ->assertSee('Flour')
            ->assertSee('1 low at Central Store')
            ->assertSee('name="rows[0][unit_id]"', false);

        $this->actingAs($this->user)
            ->get(route('admin.inventory.reorder-levels.index', ['tenant' => $this->tenant->id, 'location' => $this->bar->id, 'status' => 'low']))
            ->assertOk()
            ->assertSee('No items match these filters.');
    }

    public function test_inventory_and_raw_material_screens_offer_the_reorder_dialog(): void
    {
        $this->stock($this->store, 2000, level: 2500);

        $this->actingAs($this->user)
            ->get(route('admin.inventory.index', ['tenant' => $this->tenant->id]))
            ->assertOk()
            ->assertSee('id="reorder-dialog"', false)
            ->assertSee('Set levels by location');

        $this->actingAs($this->user)
            ->get(route('admin.catalog.raw-materials.index', ['tenant' => $this->tenant->id]))
            ->assertOk()
            ->assertSee('id="reorder-dialog"', false)
            ->assertSee('Low at 1 of 1');
    }

    private function location(Branch $branch, string $name, string $code): InventoryLocation
    {
        return InventoryLocation::query()->create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $branch->id, 'name' => $name, 'code' => $code,
            'location_type' => InventoryLocationType::Branch->value, 'status' => 'active',
        ]);
    }

    private function stock(InventoryLocation $location, float $onHand, float $level = 0): void
    {
        InventoryStockLevel::query()->create([
            'tenant_id' => $this->tenant->id,
            'inventory_location_id' => $location->id,
            'product_variant_id' => $this->flour->id,
            'quantity_on_hand' => $onHand,
            'reorder_level' => $level,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(InventoryLocation $location, ?float $level, ?float $quantity, ?int $unitId = null): array
    {
        return [
            'inventory_location_id' => $location->id,
            'product_variant_id' => $this->flour->id,
            'unit_id' => $unitId,
            'reorder_level' => $level,
            'reorder_quantity' => $quantity,
        ];
    }

    private function levelAt(InventoryLocation $location): ?InventoryStockLevel
    {
        return InventoryStockLevel::query()
            ->where('inventory_location_id', $location->id)
            ->where('product_variant_id', $this->flour->id)
            ->first();
    }
}
