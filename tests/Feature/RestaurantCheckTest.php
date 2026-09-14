<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Business\Models\Branch;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Enums\StockPolicy;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Inventory\Actions\PostInventoryMovementAction;
use Modules\Inventory\Enums\InventoryLocationType;
use Modules\Inventory\Enums\InventoryMovementType;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Inventory\Models\Recipe;
use Modules\Sales\Enums\CheckStatus;
use Modules\Sales\Enums\TableStatus;
use Modules\Sales\Enums\TicketStatus;
use Modules\Sales\Models\KitchenOrderTicket;
use Modules\Sales\Models\RestaurantTable;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\ServiceArea;
use Modules\Sales\Support\DineInSettings;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class RestaurantCheckTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private InventoryLocation $store;

    private ServiceArea $area;

    private RestaurantTable $table;

    private InventoryLocation $grill;

    private InventoryLocation $bar;

    private ProductVariant $suya;   // grill, recipe-depleted

    private ProductVariant $beer;   // bar, no recipe

    private ProductVariant $chicken; // raw material behind the suya recipe

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::query()->create([
            'name' => 'Lagos Grill', 'slug' => 'lagos-grill', 'status' => TenantStatus::Active,
            'business_type' => 'restaurant', 'country_code' => 'NG', 'timezone' => 'Africa/Lagos', 'currency_code' => 'NGN',
        ]);
        $branch = Branch::query()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Main', 'code' => 'MAIN', 'status' => 'active', 'is_primary' => true,
        ]);
        $this->store = InventoryLocation::query()->create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $branch->id, 'name' => 'Kitchen Store', 'code' => 'KSTORE',
            'location_type' => InventoryLocationType::StoreRoom->value, 'status' => 'active', 'is_sellable_point' => true,
        ]);
        $this->area = ServiceArea::query()->create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $branch->id,
            'sellable_location_id' => $this->store->id, 'name' => 'Main Restaurant', 'status' => 'active',
        ]);
        $this->table = RestaurantTable::query()->create([
            'tenant_id' => $this->tenant->id, 'service_area_id' => $this->area->id,
            'name' => 'T1', 'seats' => 4, 'status' => TableStatus::Available->value,
        ]);

        $this->grill = InventoryLocation::query()->create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $branch->id,
            'name' => 'Grill', 'code' => 'GRILL',
            'location_type' => InventoryLocationType::StoreRoom->value, 'status' => 'active',
            'is_prep_station' => true,
        ]);
        $this->bar = InventoryLocation::query()->create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $branch->id,
            'name' => 'Bar', 'code' => 'BAR',
            'location_type' => InventoryLocationType::StoreRoom->value, 'status' => 'active',
            'is_prep_station' => true,
        ]);

        $this->chicken = $this->makeVariant('Chicken', 'CHK-R', ProductType::RawMaterial, 0);
        $this->suya = $this->makeVariant('Suya', 'SUYA-1', ProductType::Product, 350000, $this->grill, StockPolicy::Recipe);
        $this->beer = $this->makeVariant('Beer', 'BEER-1', ProductType::Product, 120000, $this->bar);

        // One portion of suya eats 0.5 of a chicken.
        $recipe = Recipe::query()->create([
            'tenant_id' => $this->tenant->id,
            'output_product_variant_id' => $this->suya->id,
            'name' => 'Suya portion', 'yield_quantity' => 1, 'version' => 1,
            'is_active' => true, 'status' => 'active',
        ]);
        $recipe->items()->create([
            'tenant_id' => $this->tenant->id,
            'component_product_variant_id' => $this->chicken->id,
            'quantity' => 0.5, 'sort_order' => 1,
        ]);

        // The grill makes the suya, so the grill is where its chicken sits. Under the
        // collapsed model the station and the store are the same row.
        app(PostInventoryMovementAction::class)->execute([
            'tenant_id' => $this->tenant->id, 'inventory_location_id' => $this->grill->id,
            'product_variant_id' => $this->chicken->id, 'movement_type' => InventoryMovementType::OpeningStock->value,
            'quantity' => 40, 'unit_cost_minor' => 50000,
        ]);

        $this->user = User::factory()->create(['is_platform_admin' => true]);
    }

    public function test_seating_guests_opens_a_check_and_occupies_the_table(): void
    {
        $this->actingAs($this->user)
            ->post(route('admin.sales.restaurant.checks.open', $this->table), [
                'tenant' => $this->tenant->id, 'cover_count' => 3,
            ])
            ->assertRedirect();

        $check = SalesOrder::query()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->assertSame(CheckStatus::Open, $check->check_status);
        $this->assertSame(3, $check->cover_count);
        $this->assertSame($this->table->id, $check->restaurant_table_id);
        $this->assertTrue($check->isCheck());
        // The area's own store decides where the food comes out of.
        $this->assertSame($this->store->id, $check->inventory_location_id);
        $this->assertSame(TableStatus::Occupied, $this->table->refresh()->status);
    }

    public function test_a_table_cannot_hold_two_open_checks(): void
    {
        $this->openCheck();

        $this->actingAs($this->user)
            ->post(route('admin.sales.restaurant.checks.open', $this->table), [
                'tenant' => $this->tenant->id, 'cover_count' => 2,
            ])
            ->assertSessionHasErrors('restaurant_table_id');

        $this->assertSame(1, SalesOrder::query()->where('tenant_id', $this->tenant->id)->count());
    }

    public function test_the_check_stays_open_and_keeps_growing_across_rounds(): void
    {
        $check = $this->openCheck();

        $this->addItems($check, [['product_variant_id' => $this->suya->id, 'quantity' => 2]]);
        $this->assertSame(700000, (int) $check->refresh()->subtotal_minor);

        // A second round, later in the meal, lands on the same check.
        $this->addItems($check, [['product_variant_id' => $this->beer->id, 'quantity' => 3]]);
        $check->refresh();

        $this->assertSame(CheckStatus::Open, $check->check_status, 'The bill must stay open between rounds.');
        $this->assertSame(2, $check->items()->count());
        $this->assertSame(700000 + 360000, (int) $check->subtotal_minor);
    }

    public function test_a_variant_that_repeats_the_product_name_is_not_printed_twice(): void
    {
        // Real catalogues often name the only variant after the product itself; a kitchen
        // ticket reading "Beer — Beer" is noise a cook has to look past.
        $echo = $this->makeVariant('Palm Wine', 'PALM-1', ProductType::Product, 90000);
        $echo->update(['variant_name' => 'Palm Wine']);

        $check = $this->openCheck();
        $this->addItems($check, [['product_variant_id' => $echo->id, 'quantity' => 1]]);

        $this->assertSame('Palm Wine', $check->refresh()->items->first()->item_name);
    }

    public function test_prices_come_from_the_catalogue_not_the_request(): void
    {
        $check = $this->openCheck();

        // A server must never be able to type their own price into a bill.
        $this->addItems($check, [[
            'product_variant_id' => $this->suya->id, 'quantity' => 1, 'unit_price' => 1,
        ]]);

        $this->assertSame(350000, (int) $check->refresh()->items->first()->unit_price_minor);
    }

    public function test_firing_routes_items_to_the_station_that_makes_them(): void
    {
        $check = $this->openCheck();
        $this->addItems($check, [
            ['product_variant_id' => $this->suya->id, 'quantity' => 2],
            ['product_variant_id' => $this->beer->id, 'quantity' => 2],
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.sales.restaurant.checks.fire', $check), ['tenant' => $this->tenant->id])
            ->assertRedirect();

        $tickets = KitchenOrderTicket::query()->where('sales_order_id', $check->id)->with('items.orderItem')->get();

        $this->assertCount(2, $tickets, 'Grill and bar each get their own ticket.');

        $grillTicket = $tickets->firstWhere('prep_location_id', $this->grill->id);
        $barTicket = $tickets->firstWhere('prep_location_id', $this->bar->id);

        $this->assertSame('Suya', $grillTicket->items->first()->orderItem->item_name);
        $this->assertSame('Beer', $barTicket->items->first()->orderItem->item_name);
        $this->assertSame(TicketStatus::Queued, $grillTicket->status);
    }

    public function test_firing_stamps_items_as_sent_and_leaves_nothing_pending(): void
    {
        $check = $this->openCheck();
        $this->addItems($check, [['product_variant_id' => $this->suya->id, 'quantity' => 1]]);

        $this->assertCount(1, $check->refresh()->load('items')->unfiredItems());

        $this->fire($check);
        $check->refresh()->load('items');

        $this->assertCount(0, $check->unfiredItems());
        $this->assertCount(1, $check->firedItems());
        $this->assertNotNull($check->items->first()->fired_at);
    }

    public function test_firing_twice_with_nothing_new_is_refused(): void
    {
        $check = $this->openCheck();
        $this->addItems($check, [['product_variant_id' => $this->suya->id, 'quantity' => 1]]);
        $this->fire($check);

        $this->actingAs($this->user)
            ->post(route('admin.sales.restaurant.checks.fire', $check), ['tenant' => $this->tenant->id])
            ->assertSessionHasErrors('check');
    }

    public function test_a_later_round_fires_only_the_new_items(): void
    {
        $check = $this->openCheck();
        $this->addItems($check, [['product_variant_id' => $this->suya->id, 'quantity' => 1]]);
        $this->fire($check);

        $this->addItems($check, [['product_variant_id' => $this->beer->id, 'quantity' => 1]]);
        $this->fire($check);

        $tickets = KitchenOrderTicket::query()->where('sales_order_id', $check->id)->get();

        $this->assertCount(2, $tickets, 'The second fire must not re-send the first round.');
        $this->assertSame(1, $tickets->last()->items()->count());
    }

    public function test_ingredients_leave_the_store_when_the_kitchen_is_sent_the_order(): void
    {
        $check = $this->openCheck();
        $this->addItems($check, [['product_variant_id' => $this->suya->id, 'quantity' => 4]]);

        $this->assertSame(40.0, $this->chickenOnHand(), 'Nothing is consumed while items sit on the pad.');

        $this->fire($check);

        // 4 portions × 0.5 chicken = 2 consumed, during service — not at payment.
        $this->assertSame(38.0, $this->chickenOnHand());
    }

    public function test_a_tenant_set_to_deplete_at_settle_consumes_nothing_at_fire(): void
    {
        $this->tenant->update([
            'settings' => DineInSettings::merge($this->tenant, ['depletion' => DineInSettings::DEPLETE_AT_SETTLE]),
        ]);

        $check = $this->openCheck();
        $this->addItems($check, [['product_variant_id' => $this->suya->id, 'quantity' => 4]]);
        $this->fire($check);

        $this->assertSame(40.0, $this->chickenOnHand(), 'Settle-timing tenants keep the counter-sale behaviour.');
        $this->assertSame(
            1,
            KitchenOrderTicket::query()->where('sales_order_id', $check->id)->count(),
            'The kitchen is still sent the order either way.',
        );
    }

    public function test_a_kitchen_hand_moves_a_ticket_through_cooking_to_ready(): void
    {
        $check = $this->openCheck();
        $this->addItems($check, [['product_variant_id' => $this->suya->id, 'quantity' => 1]]);
        $this->fire($check);

        $ticket = KitchenOrderTicket::query()->where('sales_order_id', $check->id)->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('admin.sales.kds.tickets.advance', $ticket), ['tenant' => $this->tenant->id]);
        $this->assertSame(TicketStatus::Preparing, $ticket->refresh()->status);
        $this->assertNotNull($ticket->started_at);

        $this->actingAs($this->user)
            ->post(route('admin.sales.kds.tickets.advance', $ticket), ['tenant' => $this->tenant->id]);
        $this->assertSame(TicketStatus::Ready, $ticket->refresh()->status);
        $this->assertNotNull($ticket->ready_at);
    }

    public function test_the_kitchen_screen_shows_only_its_own_stations_work(): void
    {
        $check = $this->openCheck();
        $this->addItems($check, [
            ['product_variant_id' => $this->suya->id, 'quantity' => 1],
            ['product_variant_id' => $this->beer->id, 'quantity' => 1],
        ]);
        $this->fire($check);

        $this->actingAs($this->user)
            ->get(route('admin.sales.kds.station', ['station' => $this->grill->id, 'tenant' => $this->tenant->id]))
            ->assertOk()
            ->assertSee('Grill')
            ->assertSee('Suya')
            ->assertDontSee('Beer');
    }

    public function test_the_floor_map_shows_a_seated_table_with_its_running_total(): void
    {
        $check = $this->openCheck();
        $this->addItems($check, [['product_variant_id' => $this->suya->id, 'quantity' => 2]]);

        $this->actingAs($this->user)
            ->get(route('admin.sales.restaurant.floor', ['tenant' => $this->tenant->id]))
            ->assertOk()
            ->assertSee('T1')
            ->assertSee('Occupied')
            ->assertSee('7,000.00');
    }

    public function test_a_retail_order_is_untouched_by_the_dine_in_columns(): void
    {
        $retail = SalesOrder::query()->create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'SO-RETAIL-1',
            'invoice_number' => 'INV-RETAIL-1',
            'receipt_number' => 'RCT-RETAIL-1',
            'order_status' => 'pending',
            'payment_status' => 'unpaid',
            'order_date' => now()->toDateString(),
            'subtotal_minor' => 1000, 'total_minor' => 1000,
        ]);

        $this->assertFalse($retail->isCheck(), 'A null service area means an ordinary retail sale.');
        $this->assertNull($retail->check_status);
    }

    public function test_a_check_from_another_tenant_is_not_reachable(): void
    {
        $check = $this->openCheck();

        $other = Tenant::query()->create([
            'name' => 'Other', 'slug' => 'other-rest', 'status' => TenantStatus::Active,
            'business_type' => 'retail', 'country_code' => 'NG', 'timezone' => 'Africa/Lagos', 'currency_code' => 'NGN',
        ]);

        $this->actingAs($this->user)
            ->get(route('admin.sales.restaurant.check', ['order' => $check->id, 'tenant' => $other->id]))
            ->assertForbidden();
    }

    public function test_the_floor_plan_lists_the_selected_areas_tables(): void
    {
        $other = ServiceArea::query()->create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $this->area->branch_id,
            'name' => 'Pool Bar', 'status' => 'active',
        ]);
        RestaurantTable::query()->create([
            'tenant_id' => $this->tenant->id, 'service_area_id' => $other->id,
            'name' => 'P1', 'seats' => 2, 'status' => TableStatus::Available->value,
        ]);

        // One area at a time: picking Pool Bar must not also list the restaurant's tables.
        $this->actingAs($this->user)
            ->get(route('admin.sales.restaurant.areas.index', ['tenant' => $this->tenant->id, 'area' => $other->id]))
            ->assertOk()
            ->assertSee('P1')
            ->assertDontSee('>T1<', false);

        $this->actingAs($this->user)
            ->get(route('admin.sales.restaurant.areas.index', ['tenant' => $this->tenant->id, 'area' => $this->area->id]))
            ->assertOk()
            ->assertSee('T1')
            ->assertDontSee('>P1<', false);
    }

    public function test_a_table_status_changes_in_one_click_with_no_save_step(): void
    {
        $this->actingAs($this->user)
            ->patch(route('admin.sales.restaurant.tables.status', $this->table), [
                'tenant' => $this->tenant->id, 'status' => TableStatus::Dirty->value,
            ])
            ->assertRedirect();

        $this->assertSame(TableStatus::Dirty, $this->table->refresh()->status);
    }

    public function test_changing_status_returns_to_the_area_that_was_being_edited(): void
    {
        $this->actingAs($this->user)
            ->patch(route('admin.sales.restaurant.tables.status', $this->table), [
                'tenant' => $this->tenant->id, 'area' => $this->area->id, 'status' => TableStatus::Reserved->value,
            ])
            ->assertRedirect(route('admin.sales.restaurant.areas.index', [
                'tenant' => $this->tenant->id, 'area' => $this->area->id,
            ]));
    }

    public function test_a_table_holding_guests_cannot_be_marked_free(): void
    {
        $this->openCheck();

        $this->actingAs($this->user)
            ->patch(route('admin.sales.restaurant.tables.status', $this->table), [
                'tenant' => $this->tenant->id, 'status' => TableStatus::Available->value,
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(TableStatus::Occupied, $this->table->refresh()->status);
    }

    public function test_an_unknown_status_is_refused(): void
    {
        $this->actingAs($this->user)
            ->patch(route('admin.sales.restaurant.tables.status', $this->table), [
                'tenant' => $this->tenant->id, 'status' => 'on-fire',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(TableStatus::Available, $this->table->refresh()->status);
    }

    public function test_an_area_can_be_given_the_store_it_was_missing(): void
    {
        $orphan = ServiceArea::query()->create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $this->area->branch_id,
            'name' => 'Terrace', 'status' => 'active',
        ]);
        $this->assertNull($orphan->sellable_location_id);

        $this->actingAs($this->user)
            ->put(route('admin.sales.restaurant.areas.update', $orphan), [
                'tenant' => $this->tenant->id,
                'name' => 'Terrace',
                'sellable_location_id' => $this->store->id,
            ])
            ->assertRedirect();

        $this->assertSame($this->store->id, $orphan->refresh()->sellable_location_id);
    }

    public function test_a_blank_check_can_be_cancelled_to_free_the_table(): void
    {
        $check = $this->openCheck();
        $this->assertSame(TableStatus::Occupied, $this->table->refresh()->status);

        $this->actingAs($this->user)
            ->post(route('admin.sales.restaurant.checks.cancel', $check), ['tenant' => $this->tenant->id])
            ->assertRedirect();

        $this->assertSame(CheckStatus::Voided, $check->refresh()->check_status);
        // Nothing was ever served, so the table goes straight back to available.
        $this->assertSame(TableStatus::Available, $this->table->refresh()->status);
    }

    public function test_a_check_with_unfired_items_can_still_be_cancelled(): void
    {
        $check = $this->openCheck();
        $this->addItems($check, [['product_variant_id' => $this->suya->id, 'quantity' => 2]]);

        // Ordered but never sent — no food was cooked, so this costs nothing to undo.
        $this->actingAs($this->user)
            ->post(route('admin.sales.restaurant.checks.cancel', $check), ['tenant' => $this->tenant->id])
            ->assertRedirect();

        $this->assertSame(CheckStatus::Voided, $check->refresh()->check_status);
        $this->assertSame(TableStatus::Available, $this->table->refresh()->status);
        $this->assertSame(40.0, $this->chickenOnHand(), 'Nothing was cooked, so no stock moved.');
    }

    public function test_a_check_with_food_already_cooked_cannot_be_cancelled_outright(): void
    {
        $check = $this->openCheck();
        $this->addItems($check, [['product_variant_id' => $this->suya->id, 'quantity' => 2]]);
        $this->fire($check);

        $this->actingAs($this->user)
            ->post(route('admin.sales.restaurant.checks.cancel', $check), ['tenant' => $this->tenant->id])
            ->assertSessionHasErrors('check');

        $this->assertSame(CheckStatus::Open, $check->refresh()->check_status);
        $this->assertSame(TableStatus::Occupied, $this->table->refresh()->status);
    }

    public function test_a_cancelled_table_can_be_seated_again(): void
    {
        $first = $this->openCheck();
        $this->actingAs($this->user)
            ->post(route('admin.sales.restaurant.checks.cancel', $first), ['tenant' => $this->tenant->id])
            ->assertRedirect();

        // The whole point: a table freed by mistake must be reusable immediately.
        $second = $this->openCheck(4);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(CheckStatus::Open, $second->check_status);
        $this->assertSame(TableStatus::Occupied, $this->table->refresh()->status);
    }

    public function test_a_cancelled_check_cannot_be_cancelled_twice(): void
    {
        $check = $this->openCheck();
        $this->actingAs($this->user)
            ->post(route('admin.sales.restaurant.checks.cancel', $check), ['tenant' => $this->tenant->id])
            ->assertRedirect();

        $this->actingAs($this->user)
            ->post(route('admin.sales.restaurant.checks.cancel', $check), ['tenant' => $this->tenant->id])
            ->assertSessionHasErrors('check');
    }

    public function test_the_floor_offers_to_free_a_table_seated_by_mistake(): void
    {
        $this->openCheck();

        $this->actingAs($this->user)
            ->get(route('admin.sales.restaurant.floor', ['tenant' => $this->tenant->id]))
            ->assertOk()
            ->assertSee('Free table');
    }

    public function test_an_unsent_item_can_be_removed_without_touching_the_rest(): void
    {
        $check = $this->openCheck();
        $this->addItems($check, [
            ['product_variant_id' => $this->suya->id, 'quantity' => 1],
            ['product_variant_id' => $this->beer->id, 'quantity' => 1],
        ]);

        $suyaLine = $check->refresh()->items->firstWhere('product_variant_id', $this->suya->id);

        $this->actingAs($this->user)
            ->delete(route('admin.sales.restaurant.checks.items.destroy', ['order' => $check->id, 'item' => $suyaLine->id]), [
                'tenant' => $this->tenant->id,
            ])
            ->assertRedirect();

        $check->refresh()->load('items');
        $this->assertSame(1, $check->items->count());
        $this->assertSame('Beer', $check->items->first()->item_name);
        // The bill follows the pad down.
        $this->assertSame(120000, (int) $check->subtotal_minor);
    }

    public function test_an_item_already_sent_to_the_kitchen_cannot_be_removed(): void
    {
        $check = $this->openCheck();
        $this->addItems($check, [['product_variant_id' => $this->suya->id, 'quantity' => 1]]);
        $this->fire($check);

        $line = $check->refresh()->items->first();

        // The food is cooked; deleting the line would hide real waste.
        $this->actingAs($this->user)
            ->delete(route('admin.sales.restaurant.checks.items.destroy', ['order' => $check->id, 'item' => $line->id]), [
                'tenant' => $this->tenant->id,
            ])
            ->assertSessionHasErrors('items');

        $this->assertSame(1, $check->refresh()->items()->count());
    }

    public function test_removing_every_unsent_item_leaves_a_check_that_can_be_cancelled(): void
    {
        $check = $this->openCheck();
        $this->addItems($check, [['product_variant_id' => $this->beer->id, 'quantity' => 1]]);

        $line = $check->refresh()->items->first();
        $this->actingAs($this->user)
            ->delete(route('admin.sales.restaurant.checks.items.destroy', ['order' => $check->id, 'item' => $line->id]), [
                'tenant' => $this->tenant->id,
            ])
            ->assertRedirect();

        $this->assertSame(0, (int) $check->refresh()->subtotal_minor);
        $this->assertSame(0, $check->items()->count());
    }

    public function test_a_line_from_another_check_cannot_be_removed_through_this_one(): void
    {
        $check = $this->openCheck();
        $this->addItems($check, [['product_variant_id' => $this->beer->id, 'quantity' => 1]]);
        $line = $check->refresh()->items->first();

        $otherTable = RestaurantTable::query()->create([
            'tenant_id' => $this->tenant->id, 'service_area_id' => $this->area->id,
            'name' => 'T2', 'seats' => 2, 'status' => TableStatus::Available->value,
        ]);
        $this->actingAs($this->user)->post(route('admin.sales.restaurant.checks.open', $otherTable), [
            'tenant' => $this->tenant->id, 'cover_count' => 2,
        ])->assertRedirect();
        $other = SalesOrder::query()->where('tenant_id', $this->tenant->id)->latest('id')->firstOrFail();

        $this->actingAs($this->user)
            ->delete(route('admin.sales.restaurant.checks.items.destroy', ['order' => $other->id, 'item' => $line->id]), [
                'tenant' => $this->tenant->id,
            ])
            ->assertNotFound();

        $this->assertSame(1, $check->refresh()->items()->count());
    }

    public function test_tapping_the_same_item_twice_raises_the_quantity_instead_of_stacking_rows(): void
    {
        $check = $this->openCheck();

        $this->addItems($check, [['product_variant_id' => $this->beer->id, 'quantity' => 1]]);
        $this->addItems($check, [['product_variant_id' => $this->beer->id, 'quantity' => 1]]);
        $this->addItems($check, [['product_variant_id' => $this->beer->id, 'quantity' => 1]]);

        $check->refresh()->load('items');

        $this->assertSame(1, $check->items->count(), 'Three taps of one item is one line.');
        $this->assertSame(3.0, (float) $check->items->first()->quantity);
        $this->assertSame(360000, (int) $check->subtotal_minor);
    }

    public function test_a_sent_line_is_not_merged_into_by_a_later_round(): void
    {
        $check = $this->openCheck();
        $this->addItems($check, [['product_variant_id' => $this->beer->id, 'quantity' => 1]]);
        $this->fire($check);

        // The sent line has its own kitchen ticket; a second round must be its own line.
        $this->addItems($check, [['product_variant_id' => $this->beer->id, 'quantity' => 1]]);

        $check->refresh()->load('items');
        $this->assertSame(2, $check->items->count());
        $this->assertSame(1, $check->unfiredItems()->count());
    }

    public function test_a_line_can_be_poured_from_another_bar_when_the_guests_own_is_dry(): void
    {
        // Poolside has no wine; the lounge bar has 12.
        $wine = $this->makeVariant('Wine', 'WINE-1', ProductType::Product, 500000);
        $lounge = InventoryLocation::query()->create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $this->area->branch_id,
            'name' => 'Lounge Bar', 'code' => 'LOUNGE',
            'location_type' => InventoryLocationType::StoreRoom->value, 'status' => 'active',
            'is_sellable_point' => true,
        ]);
        app(PostInventoryMovementAction::class)->execute([
            'tenant_id' => $this->tenant->id, 'inventory_location_id' => $lounge->id,
            'product_variant_id' => $wine->id, 'movement_type' => InventoryMovementType::OpeningStock->value,
            'quantity' => 12, 'unit_cost_minor' => 200000,
        ]);

        $check = $this->openCheck();
        $this->addItems($check, [['product_variant_id' => $wine->id, 'quantity' => 1]]);
        $line = $check->refresh()->items->first();

        // Falls back to the area's own store, which has none.
        $this->assertSame($this->store->id, $line->inventory_location_id);

        $this->actingAs($this->user)
            ->patch(route('admin.sales.restaurant.checks.items.source', ['order' => $check->id, 'item' => $line->id]), [
                'tenant' => $this->tenant->id,
                'inventory_location_id' => $lounge->id,
            ])
            ->assertRedirect();

        // The bill is untouched; only the shelf it comes off changed.
        $this->assertSame($lounge->id, $line->refresh()->inventory_location_id);
        $this->assertSame(500000, (int) $check->refresh()->subtotal_minor);
        $this->assertSame(1, $check->items()->count());
    }

    public function test_the_pad_shows_where_each_tracked_line_comes_from_and_what_is_there(): void
    {
        $wine = $this->makeVariant('Wine', 'WINE-2', ProductType::Product, 500000);
        app(PostInventoryMovementAction::class)->execute([
            'tenant_id' => $this->tenant->id, 'inventory_location_id' => $this->store->id,
            'product_variant_id' => $wine->id, 'movement_type' => InventoryMovementType::OpeningStock->value,
            'quantity' => 6, 'unit_cost_minor' => 200000,
        ]);

        $check = $this->openCheck();
        $this->addItems($check, [['product_variant_id' => $wine->id, 'quantity' => 1]]);

        $this->actingAs($this->user)
            ->get(route('admin.sales.restaurant.check', ['order' => $check->id, 'tenant' => $this->tenant->id]))
            ->assertOk()
            ->assertSee('Kitchen Store')
            ->assertSee('available');
    }

    public function test_a_short_line_is_called_out_on_the_pad(): void
    {
        // Nothing anywhere: the line must say so rather than look fine.
        $wine = $this->makeVariant('Wine', 'WINE-3', ProductType::Product, 500000);

        $check = $this->openCheck();
        $this->addItems($check, [['product_variant_id' => $wine->id, 'quantity' => 2]]);

        $this->actingAs($this->user)
            ->get(route('admin.sales.restaurant.check', ['order' => $check->id, 'tenant' => $this->tenant->id]))
            ->assertOk()
            ->assertSee('not enough');
    }

    public function test_the_source_of_a_line_already_in_the_kitchen_cannot_be_changed(): void
    {
        $check = $this->openCheck();
        $this->addItems($check, [['product_variant_id' => $this->suya->id, 'quantity' => 1]]);
        $this->fire($check);

        $line = $check->refresh()->items->first();
        $original = $line->inventory_location_id;

        $this->actingAs($this->user)
            ->patch(route('admin.sales.restaurant.checks.items.source', ['order' => $check->id, 'item' => $line->id]), [
                'tenant' => $this->tenant->id,
                'inventory_location_id' => $this->bar->id,
            ])
            ->assertSessionHasErrors('items');

        $this->assertSame($original, $line->refresh()->inventory_location_id);
    }

    public function test_a_made_to_order_line_shows_the_kitchen_it_comes_out_of(): void
    {
        // Suya is recipe-depleted and made at the Grill.
        $check = $this->openCheck();
        $this->addItems($check, [['product_variant_id' => $this->suya->id, 'quantity' => 1]]);

        $this->actingAs($this->user)
            ->get(route('admin.sales.restaurant.check', ['order' => $check->id, 'tenant' => $this->tenant->id]))
            ->assertOk()
            ->assertSee('Grill')
            ->assertSee('made to order');
    }

    public function test_a_tracked_line_before_a_recipe_line_renders_without_overwriting_shortfalls(): void
    {
        $check = $this->openCheck();
        $this->addItems($check, [
            ['product_variant_id' => $this->beer->id, 'quantity' => 1],
            ['product_variant_id' => $this->suya->id, 'quantity' => 1],
        ]);

        $this->actingAs($this->user)
            ->get(route('admin.sales.restaurant.check', ['order' => $check->id, 'tenant' => $this->tenant->id]))
            ->assertOk()
            ->assertSee('Beer')
            ->assertSee('Suya')
            ->assertSee('made to order');
    }

    public function test_a_made_to_order_line_with_no_kitchen_says_so(): void
    {
        // A recipe product with no prep station falls back to the check's own store,
        // which is not a kitchen — the pad must not pretend that is fine.
        $orphan = $this->makeVariant('Pepper Soup', 'PSOUP-1', ProductType::Product, 200000, null, StockPolicy::Recipe);

        $check = $this->openCheck();
        $this->addItems($check, [['product_variant_id' => $orphan->id, 'quantity' => 1]]);

        $this->actingAs($this->user)
            ->get(route('admin.sales.restaurant.check', ['order' => $check->id, 'tenant' => $this->tenant->id]))
            ->assertOk()
            ->assertSee('not a prep station');
    }

    public function test_the_pad_names_the_ingredient_the_kitchen_is_short_of(): void
    {
        // Drain the grill's chicken so the suya cannot be made.
        app(PostInventoryMovementAction::class)->execute([
            'tenant_id' => $this->tenant->id, 'inventory_location_id' => $this->grill->id,
            'product_variant_id' => $this->chicken->id, 'movement_type' => InventoryMovementType::StockOut->value,
            'quantity' => 40,
        ]);

        $check = $this->openCheck();
        $this->addItems($check, [['product_variant_id' => $this->suya->id, 'quantity' => 1]]);

        $this->actingAs($this->user)
            ->get(route('admin.sales.restaurant.check', ['order' => $check->id, 'tenant' => $this->tenant->id]))
            ->assertOk()
            ->assertSee('Short on')
            ->assertSee('Chicken');
    }

    public function test_a_short_line_stays_on_the_pad_while_the_rest_of_the_round_fires(): void
    {
        app(PostInventoryMovementAction::class)->execute([
            'tenant_id' => $this->tenant->id, 'inventory_location_id' => $this->grill->id,
            'product_variant_id' => $this->chicken->id, 'movement_type' => InventoryMovementType::StockOut->value,
            'quantity' => 40,
        ]);

        $check = $this->openCheck();
        $this->addItems($check, [
            ['product_variant_id' => $this->suya->id, 'quantity' => 1],   // short
            ['product_variant_id' => $this->beer->id, 'quantity' => 2],   // fine
        ]);

        // No consent to go short: the beer must still reach the bar.
        $this->actingAs($this->user)
            ->post(route('admin.sales.restaurant.checks.fire', $check), ['tenant' => $this->tenant->id])
            ->assertRedirect()
            ->assertSessionHasErrors('items');

        $check->refresh()->load('items');
        $suyaLine = $check->items->firstWhere('product_variant_id', $this->suya->id);
        $beerLine = $check->items->firstWhere('product_variant_id', $this->beer->id);

        $this->assertNull($suyaLine->fired_at, 'The short line stays on the pad.');
        $this->assertNotNull($beerLine->fired_at, 'The stockable line still went.');
        $this->assertSame(1, KitchenOrderTicket::query()->where('sales_order_id', $check->id)->count());
    }

    public function test_confirming_sends_the_short_line_and_lets_the_ingredient_go_negative(): void
    {
        app(PostInventoryMovementAction::class)->execute([
            'tenant_id' => $this->tenant->id, 'inventory_location_id' => $this->grill->id,
            'product_variant_id' => $this->chicken->id, 'movement_type' => InventoryMovementType::StockOut->value,
            'quantity' => 40,
        ]);

        $check = $this->openCheck();
        $this->addItems($check, [['product_variant_id' => $this->suya->id, 'quantity' => 2]]);

        // The server confirmed the warning: the kitchen decides, not the system.
        $this->actingAs($this->user)
            ->post(route('admin.sales.restaurant.checks.fire', $check), [
                'tenant' => $this->tenant->id, 'allow_short' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNotNull($check->refresh()->items->first()->fired_at);
        // 2 portions × 0.5 chicken, taken from an empty shelf.
        $this->assertSame(-1.0, $this->chickenOnHand());
    }

    public function test_going_short_is_not_allowed_outside_the_kitchen(): void
    {
        // Transfers stay strict: a negative bottle count at a store is a mistake, not a
        // kitchen improvising.
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(PostInventoryMovementAction::class)->execute([
            'tenant_id' => $this->tenant->id,
            'inventory_location_id' => $this->grill->id,
            'destination_inventory_location_id' => $this->bar->id,
            'product_variant_id' => $this->chicken->id,
            'movement_type' => InventoryMovementType::TransferOut->value,
            'quantity' => 999,
        ]);
    }

    // ---- helpers -------------------------------------------------------------

    private function openCheck(int $covers = 2): SalesOrder
    {
        $this->actingAs($this->user)
            ->post(route('admin.sales.restaurant.checks.open', $this->table), [
                'tenant' => $this->tenant->id, 'cover_count' => $covers,
            ])
            ->assertRedirect();

        return SalesOrder::query()->where('tenant_id', $this->tenant->id)->latest('id')->firstOrFail();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function addItems(SalesOrder $check, array $items): void
    {
        $this->actingAs($this->user)
            ->post(route('admin.sales.restaurant.checks.items.store', $check), [
                'tenant' => $this->tenant->id, 'items' => $items,
            ])
            ->assertRedirect();
    }

    private function fire(SalesOrder $check): void
    {
        $this->actingAs($this->user)
            ->post(route('admin.sales.restaurant.checks.fire', $check), ['tenant' => $this->tenant->id])
            ->assertRedirect();
    }

    private function chickenOnHand(): float
    {
        return (float) InventoryStockLevel::query()
            ->where('inventory_location_id', $this->grill->id)
            ->where('product_variant_id', $this->chicken->id)
            ->value('quantity_on_hand');
    }

    private function makeVariant(
        string $name,
        string $sku,
        ProductType $type,
        int $priceMinor,
        ?InventoryLocation $station = null,
        ?StockPolicy $policy = null,
    ): ProductVariant {
        $product = Product::query()->create(array_filter([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'product_type' => $type->value,
            'status' => ProductStatus::Active->value,
            'prep_location_id' => $station?->id,
            'stock_policy' => $policy?->value,
        ], fn ($v): bool => $v !== null));

        return ProductVariant::query()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'variant_name' => 'Default',
            'sku' => $sku,
            'status' => ProductStatus::Active->value,
            'selling_price_minor' => $priceMinor,
        ]);
    }
}
