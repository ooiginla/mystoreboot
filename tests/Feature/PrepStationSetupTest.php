<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Business\Models\Branch;
use Modules\Inventory\Enums\InventoryLocationType;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

/**
 * Prep stations are inventory locations flagged `is_prep_station` — a bar or a grill is
 * one thing in the real world, so it is one row here. "Where it was made" and "whose
 * stock it came from" are the same record and cannot disagree.
 */
final class PrepStationSetupTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Branch $branch;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::query()->create([
            'name' => 'Station Co', 'slug' => 'station-co', 'status' => TenantStatus::Active,
            'business_type' => 'restaurant', 'country_code' => 'NG', 'timezone' => 'Africa/Lagos', 'currency_code' => 'NGN',
        ]);
        $this->branch = Branch::query()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Main', 'code' => 'MAIN', 'status' => 'active', 'is_primary' => true,
        ]);

        // Location types are seeded lazily on the inventory page; these tests post
        // straight to the endpoints, which validate the type against that table.
        app(\Modules\Inventory\Actions\EnsureLocationTypesAction::class)->forTenant($this->tenant->id);

        $this->user = User::factory()->create(['is_platform_admin' => true]);
    }

    public function test_a_location_can_be_created_as_a_prep_station(): void
    {
        $this->actingAs($this->user)
            ->post(route('admin.inventory.locations.store'), [
                'tenant_id' => $this->tenant->id,
                'branch_id' => $this->branch->id,
                'name' => 'Grill',
                'code' => 'GRILL',
                'location_type' => InventoryLocationType::StoreRoom->value,
                'status' => 'active',
                'is_prep_station' => '1',
            ])
            ->assertRedirect();

        $grill = InventoryLocation::query()->where('tenant_id', $this->tenant->id)->where('name', 'Grill')->firstOrFail();

        $this->assertTrue($grill->is_prep_station);
        // One row: it is both the kitchen section and the store its ingredients come from.
        $this->assertFalse($grill->is_sellable_point);
    }

    public function test_an_existing_store_can_be_turned_into_a_prep_station(): void
    {
        $store = $this->makeLocation('Cold Store', 'COLD', prepStation: false);

        $this->actingAs($this->user)
            ->put(route('admin.inventory.locations.update', $store), [
                'tenant' => $this->tenant->id,
                'name' => 'Cold Store',
                'code' => 'COLD',
                'location_type' => InventoryLocationType::StoreRoom->value,
                'is_prep_station' => '1',
            ])
            ->assertRedirect();

        $this->assertTrue($store->refresh()->is_prep_station);
    }

    public function test_a_flagged_location_becomes_a_kitchen_screen(): void
    {
        $grill = $this->makeLocation('Grill', 'GRILL', prepStation: true);
        $this->makeLocation('Central Store', 'CENTRAL', prepStation: false);

        $this->actingAs($this->user)
            ->get(route('admin.sales.kds.index', ['tenant' => $this->tenant->id]))
            ->assertOk()
            ->assertSee('Grill')
            ->assertDontSee('Central Store');

        $this->actingAs($this->user)
            ->get(route('admin.sales.kds.station', ['station' => $grill->id, 'tenant' => $this->tenant->id]))
            ->assertOk();
    }

    public function test_a_plain_store_has_no_kitchen_screen(): void
    {
        $store = $this->makeLocation('Central Store', 'CENTRAL', prepStation: false);

        // Opening a screen for somewhere food is not made is meaningless, not empty.
        $this->actingAs($this->user)
            ->get(route('admin.sales.kds.station', ['station' => $store->id, 'tenant' => $this->tenant->id]))
            ->assertNotFound();
    }

    public function test_the_locations_list_marks_which_locations_are_prep_stations(): void
    {
        $this->makeLocation('Grill', 'GRILL', prepStation: true);

        $this->actingAs($this->user)
            ->get(route('admin.inventory.index', ['tenant' => $this->tenant->id]))
            ->assertOk()
            ->assertSee('Prep station');
    }

    public function test_another_tenants_station_screen_is_not_reachable(): void
    {
        $grill = $this->makeLocation('Grill', 'GRILL', prepStation: true);

        $other = Tenant::query()->create([
            'name' => 'Other', 'slug' => 'other-ps', 'status' => TenantStatus::Active,
            'business_type' => 'retail', 'country_code' => 'NG', 'timezone' => 'Africa/Lagos', 'currency_code' => 'NGN',
        ]);

        $this->actingAs($this->user)
            ->get(route('admin.sales.kds.station', ['station' => $grill->id, 'tenant' => $other->id]))
            ->assertForbidden();
    }

    private function makeLocation(string $name, string $code, bool $prepStation): InventoryLocation
    {
        return InventoryLocation::query()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => $name,
            'code' => $code,
            'location_type' => InventoryLocationType::StoreRoom->value,
            'status' => 'active',
            'is_prep_station' => $prepStation,
        ]);
    }
}
