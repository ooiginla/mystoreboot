<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Business\Models\Branch;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Inventory\Models\LocationType;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class LocationTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_location_type_can_be_created_and_used_on_a_sellable_location(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Hotel Co', 'slug' => 'hotel-co', 'status' => TenantStatus::Active,
            'business_type' => 'restaurant', 'country_code' => 'NG', 'timezone' => 'Africa/Lagos', 'currency_code' => 'NGN',
        ]);
        Branch::query()->create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'MAIN', 'status' => 'active', 'is_primary' => true]);
        app(\Modules\Inventory\Actions\EnsureLocationTypesAction::class)->forTenant($tenant->id);
        $user = User::factory()->create(['is_platform_admin' => true]);

        // Add a custom location type.
        $this->actingAs($user)->post(route('admin.inventory.location-types.store'), [
            'tenant' => $tenant->id,
            'label' => 'Kitchen store',
        ])->assertRedirect();

        $this->assertDatabaseHas('location_types', [
            'tenant_id' => $tenant->id, 'key' => 'kitchen_store', 'label' => 'Kitchen store', 'is_system' => false,
        ]);

        // Create a location using the custom type, flagged as a point of sale.
        $this->actingAs($user)->post(route('admin.inventory.locations.store'), [
            'tenant_id' => $tenant->id,
            'name' => 'Grill Kitchen Store',
            'code' => 'GRILL-KS',
            'location_type' => 'kitchen_store',
            'is_sellable_point' => '1',
        ])->assertRedirect();

        $location = InventoryLocation::query()->where('tenant_id', $tenant->id)->where('code', 'GRILL-KS')->firstOrFail();
        $this->assertSame('kitchen_store', $location->location_type);
        $this->assertTrue($location->is_sellable_point);

        // The edit dialog updates name, code, type and the sells-here flag together.
        $this->actingAs($user)->put(route('admin.inventory.locations.update', $location->id), [
            'tenant' => $tenant->id,
            'name' => 'Grill Store (renamed)',
            'code' => 'GRILL-KS2',
            'location_type' => 'kitchen_store',
            'is_sellable_point' => '0',
        ])->assertRedirect();
        $location->refresh();
        $this->assertSame('Grill Store (renamed)', $location->name);
        $this->assertSame('GRILL-KS2', $location->code);
        $this->assertFalse($location->is_sellable_point);

        // Turning selling back on via the same endpoint.
        $this->actingAs($user)->put(route('admin.inventory.locations.update', $location->id), [
            'tenant' => $tenant->id,
            'name' => $location->name, 'code' => $location->code,
            'location_type' => 'kitchen_store', 'is_sellable_point' => '1',
        ])->assertRedirect();
        $this->assertTrue($location->refresh()->is_sellable_point);

        // An unknown location type is rejected.
        $this->actingAs($user)->post(route('admin.inventory.locations.store'), [
            'tenant_id' => $tenant->id,
            'name' => 'Bad', 'code' => 'BAD-1', 'location_type' => 'does_not_exist',
        ])->assertSessionHasErrors('location_type');

        // Any type (built-in included) can be renamed.
        $branchType = LocationType::query()->where('tenant_id', $tenant->id)->where('key', 'branch')->firstOrFail();
        $this->actingAs($user)->put(route('admin.inventory.location-types.update', $branchType->id), [
            'tenant' => $tenant->id, 'label' => 'Outlet',
        ])->assertRedirect();
        $this->assertSame('Outlet', $branchType->refresh()->label);

        // A type with no stock in its locations can be removed (built-in or custom).
        $this->actingAs($user)->delete(route('admin.inventory.location-types.destroy', $branchType->id), ['tenant' => $tenant->id])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('location_types', ['id' => $branchType->id]);

        // But a type whose locations hold stock cannot be removed.
        $kitchenType = LocationType::query()->where('tenant_id', $tenant->id)->where('key', 'kitchen_store')->firstOrFail();
        app(\Modules\Inventory\Actions\PostInventoryMovementAction::class)->execute([
            'tenant_id' => $tenant->id, 'inventory_location_id' => $location->id,
            'product_variant_id' => $this->stockedVariant($tenant->id, $location->id),
            'movement_type' => \Modules\Inventory\Enums\InventoryMovementType::OpeningStock->value,
            'quantity' => 5, 'unit_cost_minor' => 100,
        ]);
        $this->actingAs($user)->delete(route('admin.inventory.location-types.destroy', $kitchenType->id), ['tenant' => $tenant->id])
            ->assertSessionHasErrors('location_type');
        $this->assertDatabaseHas('location_types', ['id' => $kitchenType->id]);

        // The location's type can be changed via the edit dialog.
        $this->actingAs($user)->put(route('admin.inventory.locations.update', $location->id), [
            'tenant' => $tenant->id,
            'name' => $location->name, 'code' => $location->code,
            'location_type' => 'warehouse', 'is_sellable_point' => '1',
        ])->assertRedirect();
        $this->assertSame('warehouse', $location->refresh()->location_type);
    }

    public function test_disabling_sells_here_on_a_branch_location_is_not_reset_on_reload(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Reset Co', 'slug' => 'reset-co', 'status' => TenantStatus::Active,
            'business_type' => 'retail', 'country_code' => 'NG', 'timezone' => 'Africa/Lagos', 'currency_code' => 'NGN',
        ]);
        $branch = Branch::query()->create(['tenant_id' => $tenant->id, 'name' => 'HQ', 'code' => 'HQ', 'status' => 'active', 'is_primary' => true]);
        $ensure = app(\Modules\Inventory\Actions\EnsureInventoryLocationsAction::class);
        $location = $ensure->forBranch($branch);
        app(\Modules\Inventory\Actions\EnsureLocationTypesAction::class)->forTenant($tenant->id);
        $this->assertTrue($location->is_sellable_point); // sellable by default on creation

        $user = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($user)->put(route('admin.inventory.locations.update', $location->id), [
            'tenant' => $tenant->id,
            'name' => $location->name, 'code' => $location->code,
            'location_type' => $location->location_type, 'is_sellable_point' => '0',
        ])->assertRedirect();
        $this->assertFalse($location->refresh()->is_sellable_point);

        // Re-provisioning (which runs on every inventory page load) must not turn it back on.
        $ensure->forTenant($tenant->id);
        $this->assertFalse($location->refresh()->is_sellable_point);
    }

    private function stockedVariant(string $tenantId, int $locationId): int
    {
        $product = \Modules\Catalog\Models\Product::query()->create([
            'tenant_id' => $tenantId, 'name' => 'Flour', 'slug' => 'flour-'.uniqid(),
            'product_type' => \Modules\Catalog\Enums\ProductType::Product->value,
            'status' => \Modules\Catalog\Enums\ProductStatus::Active->value,
        ]);

        return \Modules\Catalog\Models\ProductVariant::query()->create([
            'tenant_id' => $tenantId, 'product_id' => $product->id, 'variant_name' => 'Default',
            'sku' => 'FLOUR-'.uniqid(), 'status' => \Modules\Catalog\Enums\ProductStatus::Active->value,
        ])->id;
    }
}
