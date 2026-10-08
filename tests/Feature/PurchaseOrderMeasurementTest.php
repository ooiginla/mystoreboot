<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Inventory\Enums\InventoryLocationType;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Inventory\Models\UnitCategory;
use Modules\Inventory\Models\UnitOfMeasure;
use Modules\Procurement\Actions\SavePurchaseOrderAction;
use Modules\Procurement\Enums\PurchaseOrderStatus;
use Modules\Procurement\Models\Vendor;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class PurchaseOrderMeasurementTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_order_accepts_a_measured_quantity_and_total_line_cost(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Measured Purchasing', 'slug' => 'measured-purchasing', 'status' => TenantStatus::Active,
            'business_type' => 'retail', 'country_code' => 'NG', 'timezone' => 'Africa/Lagos', 'currency_code' => 'NGN',
        ]);
        $location = InventoryLocation::query()->create([
            'tenant_id' => $tenant->id, 'name' => 'Main Store', 'code' => 'MAIN',
            'location_type' => InventoryLocationType::Branch->value, 'status' => 'active',
        ]);
        $vendor = Vendor::query()->create(['tenant_id' => $tenant->id, 'name' => 'Bulk Supplier']);
        $category = UnitCategory::query()->create([
            'tenant_id' => $tenant->id, 'name' => 'Biscuit packs', 'is_default' => false,
        ]);
        $piece = UnitOfMeasure::query()->create([
            'tenant_id' => $tenant->id, 'unit_category_id' => $category->id, 'code' => 'pc', 'name' => 'Piece',
            'dimension' => 'count', 'to_base_factor' => 1, 'is_base_for_dimension' => true, 'status' => 'active',
        ]);
        $carton = UnitOfMeasure::query()->create([
            'tenant_id' => $tenant->id, 'unit_category_id' => $category->id, 'code' => 'carton', 'name' => 'Carton',
            'dimension' => 'count', 'to_base_factor' => 24, 'is_base_for_dimension' => false, 'status' => 'active',
        ]);
        $product = Product::query()->create([
            'tenant_id' => $tenant->id, 'name' => 'Biscuit', 'slug' => 'biscuit-po',
            'product_type' => ProductType::RawMaterial->value, 'status' => ProductStatus::Active->value,
            'unit_category_id' => $category->id,
        ]);
        $variant = ProductVariant::query()->create([
            'tenant_id' => $tenant->id, 'product_id' => $product->id, 'variant_name' => 'Default',
            'sku' => 'BISCUIT-PO', 'base_unit_id' => $piece->id, 'status' => ProductStatus::Active->value,
        ]);

        $purchaseOrder = app(SavePurchaseOrderAction::class)->execute([
            'tenant_id' => $tenant->id,
            'vendor_id' => $vendor->id,
            'po_number' => 'PO-MEASURED-1',
            'order_date' => '2026-10-08',
            'items' => [[
                'product_variant_id' => $variant->id,
                'inventory_location_id' => $location->id,
                'quantity_ordered' => 2,
                'unit_id' => $carton->id,
                'line_total' => '1,200.00',
            ]],
        ]);

        $item = $purchaseOrder->items->firstOrFail();

        $this->assertSame(2.0, (float) $item->entered_quantity);
        $this->assertSame($carton->id, $item->entered_unit_id);
        $this->assertSame('carton', $item->entered_unit_code);
        $this->assertSame(48.0, (float) $item->quantity_ordered);
        $this->assertSame(2500, (int) $item->unit_cost_minor);
        $this->assertSame(120000, (int) $item->line_total_minor);
        $this->assertSame(120000, (int) $purchaseOrder->subtotal_minor);

        $purchaseOrder->update(['status' => PurchaseOrderStatus::Approved->value]);
        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($user)
            ->get(route('admin.procurement.index', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSeeText('Total line cost')
            ->assertSeeText('Enter the total cost for this entire line.')
            ->assertSee('name="items[0][quantity_received]" type="number" min="0.0001"', false)
            ->assertSee('value="0" required aria-label="Quantity received in pc"', false);

        $this->actingAs($user)
            ->post(route('admin.procurement.purchase-orders.receive', $purchaseOrder), [
                'received_at' => '2026-10-08',
                'items' => [[
                    'purchase_order_item_id' => $item->id,
                    'quantity_received' => 0,
                ]],
            ])
            ->assertSessionHasErrors([
                'items.0.quantity_received' => 'Receive quantity cannot be zero.',
            ]);
    }
}
