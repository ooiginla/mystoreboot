<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Access\Enums\ApprovalStatus;
use Modules\Access\Enums\MembershipStatus;
use Modules\Access\Models\ApprovalRequest;
use Modules\Access\Models\Permission;
use Modules\Access\Models\Role;
use Modules\Access\Models\TenantMembership;
use Modules\Access\Support\ApprovalService;
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
use Modules\Sales\Enums\SalesOrderStatus;
use Modules\Sales\Enums\TableStatus;
use Modules\Sales\Enums\TicketStatus;
use Modules\Sales\Models\KitchenOrderTicket;
use Modules\Sales\Models\ModifierGroup;
use Modules\Sales\Models\RestaurantTable;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderItem;
use Modules\Sales\Models\SalesTillSession;
use Modules\Sales\Models\ServiceArea;
use Modules\Sales\Support\DineInSettings;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

/**
 * Phase 6c (modifiers), 6d (bill, service charge, settle) and 6e (void, split, merge),
 * end to end through the HTTP layer the floor staff use.
 */
final class RestaurantServiceFlowTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Branch $branch;

    private InventoryLocation $grill;

    private InventoryLocation $bar;

    private RestaurantTable $table;

    private RestaurantTable $table2;

    private ProductVariant $suya;    // grill, made to order: 0.5 chicken per portion

    private ProductVariant $beer;    // bar, a bottle counted in its own right

    private ProductVariant $chicken; // raw material

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::query()->create([
            'name' => 'Lagos Grill', 'slug' => 'lagos-grill', 'status' => TenantStatus::Active,
            'business_type' => 'restaurant', 'country_code' => 'NG', 'timezone' => 'Africa/Lagos', 'currency_code' => 'NGN',
            'settings' => ['dine_in' => ['service_charge_rate' => 10]],
        ]);
        $this->branch = Branch::query()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Main', 'code' => 'MAIN', 'status' => 'active', 'is_primary' => true,
        ]);
        $store = $this->location('Kitchen Store', 'KSTORE', sellable: true);
        $area = ServiceArea::query()->create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id,
            'sellable_location_id' => $store->id, 'name' => 'Main Restaurant', 'status' => 'active',
        ]);
        $this->table = RestaurantTable::query()->create([
            'tenant_id' => $this->tenant->id, 'service_area_id' => $area->id, 'name' => 'T1', 'seats' => 4, 'status' => TableStatus::Available->value,
        ]);
        $this->table2 = RestaurantTable::query()->create([
            'tenant_id' => $this->tenant->id, 'service_area_id' => $area->id, 'name' => 'T2', 'seats' => 4, 'status' => TableStatus::Available->value,
        ]);
        $this->grill = $this->location('Grill', 'GRILL', prep: true);
        $this->bar = $this->location('Bar', 'BAR', prep: true);

        $this->chicken = $this->makeVariant('Chicken', 'CHK-R', ProductType::RawMaterial, 0);
        $this->suya = $this->makeVariant('Suya', 'SUYA-1', ProductType::Product, 350000, $this->grill, StockPolicy::Recipe);
        $this->beer = $this->makeVariant('Beer', 'BEER-1', ProductType::Product, 120000, $this->bar);

        $recipe = Recipe::query()->create([
            'tenant_id' => $this->tenant->id, 'output_product_variant_id' => $this->suya->id,
            'name' => 'Suya portion', 'yield_quantity' => 1, 'version' => 1, 'is_active' => true, 'status' => 'active',
        ]);
        $recipe->items()->create([
            'tenant_id' => $this->tenant->id, 'component_product_variant_id' => $this->chicken->id, 'quantity' => 0.5, 'sort_order' => 1,
        ]);

        $this->receive($this->grill, $this->chicken, 40, 50000);
        $this->receive($this->bar, $this->beer, 24, 40000);

        $this->user = User::factory()->create(['is_platform_admin' => true]);

        // Money taken at the table lands in the till of whoever takes it.
        SalesTillSession::query()->create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'user_id' => $this->user->id,
            'session_number' => 'TILL-TEST-0001', 'status' => 'open', 'opening_float_minor' => 0, 'opened_at' => now(),
        ]);
    }

    // ---- 6d: service charge, bill, settle ---------------------------------------------

    public function test_a_new_check_carries_the_service_charge_on_food_and_drink(): void
    {
        $check = $this->openCheck();
        $this->add($check, $this->suya);

        $check->refresh();
        $this->assertSame(10.0, (float) $check->service_charge_rate);
        $this->assertSame(350000, (int) $check->subtotal_minor);
        $this->assertSame(35000, (int) $check->service_charge_minor);
        $this->assertSame(385000, (int) $check->total_minor);
    }

    public function test_the_bill_cannot_be_printed_while_items_are_still_waiting_for_the_kitchen(): void
    {
        $check = $this->openCheck();
        $this->add($check, $this->suya);

        $this->actingAs($this->user)
            ->post(route('admin.sales.restaurant.checks.print', $check), ['tenant' => $this->tenant->id])
            ->assertSessionHasErrors('check');
        $this->assertSame(CheckStatus::Open, $check->refresh()->check_status);

        $this->fire($check);

        $this->actingAs($this->user)
            ->post(route('admin.sales.restaurant.checks.print', $check), ['tenant' => $this->tenant->id])
            ->assertRedirect(route('admin.sales.restaurant.checks.bill', ['order' => $check->id, 'print' => 1, 'tenant' => $this->tenant->id]));

        $this->assertSame(CheckStatus::BillPrinted, $check->refresh()->check_status);

        $this->actingAs($this->user)
            ->get(route('admin.sales.restaurant.checks.bill', ['order' => $check->id, 'tenant' => $this->tenant->id]))
            ->assertOk()
            ->assertSee('Suya')
            ->assertSee('Service charge (10%)')
            ->assertDontSee('DRAFT');
    }

    public function test_payment_is_refused_until_the_bill_has_been_printed(): void
    {
        $check = $this->openCheck();
        $this->add($check, $this->suya);
        $this->fire($check);

        $this->pay($check, 3850)->assertSessionHasErrors('check');
        $this->assertSame(0, (int) $check->refresh()->paid_minor);
    }

    public function test_paying_in_full_settles_the_check_and_sends_the_table_to_cleaning(): void
    {
        $check = $this->openCheck();
        $this->add($check, $this->suya);
        $this->add($check, $this->beer);
        $this->fire($check);
        $this->printBill($check);

        // 3,500 + 1,200 = 4,700 food and drink; 470 service; 5,170 in all.
        $this->pay($check, 5170)->assertRedirect(route('admin.sales.restaurant.floor', ['tenant' => $this->tenant->id]));

        $check->refresh();
        $this->assertSame(CheckStatus::Settled, $check->check_status);
        $this->assertSame(SalesOrderStatus::Completed, $check->order_status);
        $this->assertSame(TableStatus::Dirty, $this->table->refresh()->status);

        // Service charge is its own revenue line.
        $this->assertSame(47000, $this->ledger('4050', 'credit_minor'));
        $this->assertSame(470000, $this->ledger('4000', 'credit_minor'));

        // The chicken left once, when the kitchen was sent the order — not again at payment.
        $this->assertSame(39.5, $this->onHand($this->grill, $this->chicken));
        // The bottle left the bar's shelf when the bill was paid.
        $this->assertSame(23.0, $this->onHand($this->bar, $this->beer));
        // COGS: half a chicken (25,000) plus a bottle (40,000).
        $this->assertSame(65000, $this->ledger('EXP-5000', 'debit_minor'));
    }

    public function test_a_part_payment_leaves_the_rest_to_pay_and_the_table_occupied(): void
    {
        $check = $this->openCheck();
        $this->add($check, $this->suya);
        $this->fire($check);
        $this->printBill($check);

        $this->pay($check, 2000)->assertSessionHasNoErrors();

        $check->refresh();
        $this->assertSame(CheckStatus::BillPrinted, $check->check_status);
        $this->assertSame(185000, $check->balance_minor);
        $this->assertSame(TableStatus::Occupied, $this->table->refresh()->status);
    }

    public function test_adding_an_item_after_printing_reopens_the_bill(): void
    {
        $check = $this->openCheck();
        $this->add($check, $this->suya);
        $this->fire($check);
        $this->printBill($check);

        $this->add($check, $this->beer);

        $check->refresh();
        $this->assertSame(CheckStatus::Open, $check->check_status);
        $this->assertNull($check->bill_printed_at);
    }

    public function test_a_business_that_deducts_at_payment_consumes_the_ingredients_once_when_paid(): void
    {
        $this->tenant->update(['settings' => DineInSettings::merge($this->tenant, ['depletion' => DineInSettings::DEPLETE_AT_SETTLE])]);

        $check = $this->openCheck();
        $this->add($check, $this->suya);
        $this->fire($check);
        $this->assertSame(40.0, $this->onHand($this->grill, $this->chicken));

        $this->printBill($check);
        $this->pay($check, 3850)->assertSessionHasNoErrors();

        $this->assertSame(39.5, $this->onHand($this->grill, $this->chicken));
    }

    public function test_the_service_charge_can_be_waived_on_one_bill(): void
    {
        $check = $this->openCheck();
        $this->add($check, $this->suya);

        $this->actingAs($this->user)
            ->patch(route('admin.sales.restaurant.checks.service-charge', $check), ['tenant' => $this->tenant->id, 'waive' => 1])
            ->assertSessionHasNoErrors();

        $check->refresh();
        $this->assertSame(0, (int) $check->service_charge_minor);
        $this->assertSame(350000, (int) $check->total_minor);
    }

    public function test_a_cleared_table_goes_back_to_available(): void
    {
        $check = $this->openCheck();
        $this->add($check, $this->suya);
        $this->fire($check);
        $this->printBill($check);
        $this->pay($check, 3850);

        $this->actingAs($this->user)
            ->post(route('admin.sales.restaurant.tables.clean', $this->table), ['tenant' => $this->tenant->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(TableStatus::Available, $this->table->refresh()->status);
    }

    public function test_restaurant_settings_set_the_rate_for_tables_seated_afterwards(): void
    {
        $this->actingAs($this->user)
            ->put(route('admin.sales.restaurant.settings.update'), [
                'tenant' => $this->tenant->id, 'service_charge_rate' => 12.5, 'depletion' => DineInSettings::DEPLETE_AT_FIRE,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(12.5, DineInSettings::serviceChargeRate($this->tenant->refresh()));
        $this->assertSame(12.5, (float) $this->openCheck()->service_charge_rate);
    }

    // ---- 6c: modifiers -------------------------------------------------------------------

    public function test_a_required_choice_must_be_made_before_the_dish_goes_on_the_check(): void
    {
        $this->chickenChoices();
        $check = $this->openCheck();

        $this->actingAs($this->user)
            ->post(route('admin.sales.restaurant.checks.items.store', $check), [
                'tenant' => $this->tenant->id,
                'items' => [['product_variant_id' => $this->suya->id, 'quantity' => 1]],
            ])
            ->assertSessionHasErrors('items');

        $this->assertSame(0, $check->items()->count());
    }

    public function test_a_choice_changes_the_price_and_different_choices_stay_on_separate_lines(): void
    {
        [$extra, $regular] = $this->chickenChoices();
        $check = $this->openCheck();

        $this->add($check, $this->suya, [$extra]);
        $this->add($check, $this->suya, [$extra]);
        $this->add($check, $this->suya, [$regular]);

        $lines = $check->items()->with('modifiers')->orderBy('id')->get();
        $this->assertCount(2, $lines, 'the same choice merges; a different choice does not');
        $this->assertSame(2.0, (float) $lines[0]->quantity);
        $this->assertSame(400000, (int) $lines[0]->unit_price_minor);
        $this->assertSame('Extra chicken', $lines[0]->modifiers->first()->option_name);
        $this->assertSame(350000, (int) $lines[1]->unit_price_minor);
    }

    public function test_extra_chicken_takes_more_chicken_off_the_grill_and_the_kitchen_sees_it(): void
    {
        [$extra] = $this->chickenChoices();
        $check = $this->openCheck();
        $this->add($check, $this->suya, [$extra]);
        $this->fire($check);

        // 0.5 in the recipe + 0.5 extra.
        $this->assertSame(39.0, $this->onHand($this->grill, $this->chicken));
        $this->assertSame(50000, (int) $check->items()->first()->consumed_cost_minor);

        $this->actingAs($this->user)
            ->get(route('admin.sales.kds.station', ['station' => $this->grill->id, 'tenant' => $this->tenant->id]))
            ->assertOk()
            ->assertSee('Extra chicken');
    }

    public function test_the_modifier_page_lists_groups_and_the_pad_offers_them(): void
    {
        $this->chickenChoices();
        $check = $this->openCheck();

        $this->actingAs($this->user)
            ->get(route('admin.sales.restaurant.modifiers.index', ['tenant' => $this->tenant->id]))
            ->assertOk()
            ->assertSee('Chicken portion')
            ->assertSee('Suya');

        $this->actingAs($this->user)
            ->get(route('admin.sales.restaurant.check', ['order' => $check->id, 'tenant' => $this->tenant->id]))
            ->assertOk()
            ->assertSee('Extra chicken');
    }

    // ---- 6e: void, split, merge ----------------------------------------------------------

    public function test_voiding_a_sent_dish_takes_it_off_the_bill_and_records_the_waste(): void
    {
        $check = $this->openCheck();
        $this->add($check, $this->suya);
        $this->fire($check);
        $item = $check->items()->firstOrFail();

        $this->voidItem($check, $item)->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertNotNull($item->voided_at);
        $this->assertSame('Kitchen error', $item->void_reason);
        $this->assertSame(0, (int) $check->refresh()->total_minor);
        // The chicken was cooked; it is not put back.
        $this->assertSame(39.5, $this->onHand($this->grill, $this->chicken));
        $this->assertSame(25000, $this->ledger('EXP-6050', 'debit_minor'));
        $this->assertSame(TicketStatus::Cancelled, KitchenOrderTicket::query()->firstOrFail()->status);
    }

    public function test_part_of_a_line_can_be_voided(): void
    {
        $check = $this->openCheck();
        $this->add($check, $this->beer, [], 3);
        $this->fire($check);
        $item = $check->items()->firstOrFail();

        $this->voidItem($check, $item, 1)->assertSessionHasNoErrors();

        $live = $check->items()->whereNull('voided_at')->get();
        $gone = $check->items()->whereNotNull('voided_at')->get();
        $this->assertSame(2.0, (float) $live->sole()->quantity);
        $this->assertSame(1.0, (float) $gone->sole()->quantity);
        $this->assertSame(240000, (int) $check->refresh()->subtotal_minor);
        // The flat beer was opened; it leaves the bar now, as waste.
        $this->assertSame(23.0, $this->onHand($this->bar, $this->beer));
    }

    public function test_an_item_not_yet_sent_is_removed_not_voided(): void
    {
        $check = $this->openCheck();
        $this->add($check, $this->suya);

        $this->voidItem($check, $check->items()->firstOrFail())->assertSessionHasErrors('items');
    }

    public function test_voiding_the_whole_check_closes_it_and_sends_the_table_to_cleaning(): void
    {
        $check = $this->openCheck();
        $this->add($check, $this->suya);
        $this->add($check, $this->beer);
        $this->fire($check);

        $this->actingAs($this->user)
            ->post(route('admin.sales.restaurant.checks.void', $check), ['tenant' => $this->tenant->id, 'reason' => 'Walked out without paying'])
            ->assertSessionHasNoErrors();

        $check->refresh();
        $this->assertSame(CheckStatus::Voided, $check->check_status);
        $this->assertSame(2, $check->items()->whereNotNull('voided_at')->count());
        $this->assertSame(TableStatus::Dirty, $this->table->refresh()->status);
    }

    public function test_a_split_bill_is_paid_separately_and_the_table_frees_only_when_both_are_paid(): void
    {
        $check = $this->openCheck(4);
        $this->add($check, $this->suya);
        $this->add($check, $this->beer);
        $this->fire($check);
        $beerLine = $check->items()->where('product_variant_id', $this->beer->id)->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('admin.sales.restaurant.checks.split', $check), [
                'tenant' => $this->tenant->id, 'moves' => [$beerLine->id => 1], 'covers' => 1,
            ])
            ->assertSessionHasNoErrors();

        $split = SalesOrder::query()->where('parent_sales_order_id', $check->id)->firstOrFail();
        $this->assertSame($this->table->id, $split->restaurant_table_id);
        $this->assertSame(132000, (int) $split->total_minor);
        $this->assertSame(385000, (int) $check->refresh()->total_minor);

        $this->actingAs($this->user)
            ->get(route('admin.sales.restaurant.floor', ['tenant' => $this->tenant->id]))
            ->assertSee('2 bills');

        $this->printBill($check);
        $this->pay($check, 3850);
        $this->assertSame(TableStatus::Occupied, $this->table->refresh()->status, 'the beer drinker has not paid yet');

        $this->printBill($split);
        $this->pay($split, 1320);
        $this->assertSame(TableStatus::Dirty, $this->table->refresh()->status);
    }

    public function test_merging_brings_the_other_bill_over_and_closes_it(): void
    {
        $first = $this->openCheck();
        $this->add($first, $this->suya);
        $second = $this->openCheck(2, $this->table2);
        $this->add($second, $this->beer);
        $this->fire($second);

        $this->actingAs($this->user)
            ->post(route('admin.sales.restaurant.checks.merge', $first), ['tenant' => $this->tenant->id, 'source_check_id' => $second->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $first->items()->count());
        $this->assertSame(470000, (int) $first->refresh()->subtotal_minor);
        $this->assertSame(CheckStatus::Voided, $second->refresh()->check_status);
        // The beer's kitchen ticket now follows the guests to T1.
        $this->assertSame($first->id, KitchenOrderTicket::query()->firstOrFail()->sales_order_id);
        $this->assertSame(TableStatus::Dirty, $this->table2->refresh()->status);
    }

    public function test_a_void_waits_for_a_manager_when_the_business_requires_approval(): void
    {
        $this->requireVoidApproval();
        $check = $this->openCheck();
        $this->add($check, $this->suya);
        $this->fire($check);
        $item = $check->items()->firstOrFail();

        $server = $this->member('Server', ['pos.restaurant.operate', 'sales.void']);
        $manager = $this->member('Manager', ['pos.restaurant.operate', 'sales.void', 'sales.void.approve']);

        $this->actingAs($server)
            ->post(route('admin.sales.restaurant.checks.items.void', ['order' => $check->id, 'item' => $item->id]), [
                'tenant' => $this->tenant->id, 'reason' => 'Quality complaint',
            ])
            ->assertSessionHasNoErrors();

        // Still on the bill, visibly waiting — and the bill cannot go out until decided.
        $this->assertNull($item->refresh()->voided_at);
        $request = ApprovalRequest::query()->where('type', 'sales_void')->sole();
        $this->assertSame(ApprovalStatus::Pending, $request->status);

        $this->actingAs($this->user)
            ->get(route('admin.sales.restaurant.check', ['order' => $check->id, 'tenant' => $this->tenant->id]))
            ->assertSee('Void waiting for manager');
        $this->actingAs($this->user)
            ->post(route('admin.sales.restaurant.checks.print', $check), ['tenant' => $this->tenant->id])
            ->assertSessionHasErrors('check');

        app(ApprovalService::class)->approve($request, $manager);

        $item->refresh();
        $this->assertNotNull($item->voided_at);
        $this->assertSame($manager->id, (int) $item->voided_by_user_id);
        $this->assertSame(0, (int) $check->refresh()->total_minor);
    }

    public function test_a_rejected_void_leaves_the_line_on_the_bill_and_stops_blocking_it(): void
    {
        $this->requireVoidApproval();
        $check = $this->openCheck();
        $this->add($check, $this->suya);
        $this->fire($check);
        $item = $check->items()->firstOrFail();
        $server = $this->member('Server', ['pos.restaurant.operate', 'sales.void']);

        $this->actingAs($server)
            ->post(route('admin.sales.restaurant.checks.items.void', ['order' => $check->id, 'item' => $item->id]), [
                'tenant' => $this->tenant->id, 'reason' => 'Guest changed their mind',
            ]);

        app(ApprovalService::class)->reject(ApprovalRequest::query()->sole(), $this->user, 'It was eaten.');

        $this->assertNull($item->refresh()->voided_at);
        $this->printBill($check);
        $this->assertSame(CheckStatus::BillPrinted, $check->refresh()->check_status);
    }

    // ---- helpers -------------------------------------------------------------------------

    private function requireVoidApproval(): void
    {
        $settings = $this->tenant->settings ?? [];
        $settings['approvals'] = ['enabled' => true, 'actions' => ['sales_void' => true]];
        $this->tenant->update(['settings' => $settings]);
    }

    /**
     * A staff member of this business holding exactly these permissions.
     *
     * @param  list<string>  $permissions
     */
    private function member(string $roleName, array $permissions): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create([
            'tenant_id' => $this->tenant->id, 'name' => $roleName, 'slug' => str($roleName)->slug()->value(),
        ]);

        foreach ($permissions as $slug) {
            $role->permissions()->attach(Permission::query()->firstOrCreate(
                ['slug' => $slug],
                ['module' => 'sales', 'name' => $slug, 'description' => $slug],
            ));
        }

        TenantMembership::query()->create([
            'tenant_id' => $this->tenant->id, 'user_id' => $user->id, 'role_id' => $role->id,
            'status' => MembershipStatus::Active->value, 'joined_at' => now(),
        ]);

        return $user;
    }

    /**
     * "Chicken portion": pick exactly one — Extra chicken (+500, +0.5 chicken) or Regular.
     *
     * @return array{0: int, 1: int} the option ids
     */
    private function chickenChoices(): array
    {
        $this->actingAs($this->user)
            ->post(route('admin.sales.restaurant.modifiers.store'), [
                'tenant' => $this->tenant->id,
                'name' => 'Chicken portion',
                'is_required' => 1,
                'max_select' => 1,
                'options' => [
                    ['name' => 'Extra chicken', 'price' => '500', 'component_product_variant_id' => $this->chicken->id, 'component_quantity' => '0.5'],
                    ['name' => 'Regular', 'price' => '0'],
                ],
                'product_ids' => [$this->suya->product_id],
            ])
            ->assertSessionHasNoErrors();

        $group = ModifierGroup::query()->with('options')->firstOrFail();

        return [$group->options[0]->id, $group->options[1]->id];
    }

    private function openCheck(int $covers = 2, ?RestaurantTable $table = null): SalesOrder
    {
        $this->actingAs($this->user)
            ->post(route('admin.sales.restaurant.checks.open', $table ?? $this->table), [
                'tenant' => $this->tenant->id, 'cover_count' => $covers,
            ])
            ->assertSessionHasNoErrors();

        return SalesOrder::query()->where('tenant_id', $this->tenant->id)->latest('id')->firstOrFail();
    }

    /**
     * @param  array<int, int>  $modifiers
     */
    private function add(SalesOrder $check, ProductVariant $variant, array $modifiers = [], int $quantity = 1): void
    {
        $this->actingAs($this->user)
            ->post(route('admin.sales.restaurant.checks.items.store', $check), [
                'tenant' => $this->tenant->id,
                'items' => [['product_variant_id' => $variant->id, 'quantity' => $quantity, 'modifiers' => $modifiers]],
            ])
            ->assertSessionHasNoErrors();
    }

    private function fire(SalesOrder $check): void
    {
        $this->actingAs($this->user)
            ->post(route('admin.sales.restaurant.checks.fire', $check), ['tenant' => $this->tenant->id])
            ->assertSessionHasNoErrors();
    }

    private function printBill(SalesOrder $check): void
    {
        $this->actingAs($this->user)
            ->post(route('admin.sales.restaurant.checks.print', $check), ['tenant' => $this->tenant->id])
            ->assertSessionHasNoErrors();
    }

    private function pay(SalesOrder $check, float $amount): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user)
            ->post(route('admin.sales.restaurant.checks.payments.store', $check), [
                'tenant' => $this->tenant->id, 'payment_method' => 'Cash', 'amount' => $amount,
            ]);
    }

    private function voidItem(SalesOrder $check, SalesOrderItem $item, ?float $quantity = null): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user)
            ->post(route('admin.sales.restaurant.checks.items.void', ['order' => $check->id, 'item' => $item->id]), array_filter([
                'tenant' => $this->tenant->id, 'reason' => 'Kitchen error', 'quantity' => $quantity,
            ], fn ($v) => $v !== null));
    }

    private function ledger(string $code, string $side): int
    {
        return (int) DB::table('finance_journal_lines')
            ->join('finance_accounts', 'finance_accounts.id', '=', 'finance_journal_lines.finance_account_id')
            ->where('finance_accounts.tenant_id', $this->tenant->id)
            ->where('finance_accounts.code', $code)
            ->sum('finance_journal_lines.'.$side);
    }

    private function onHand(InventoryLocation $location, ProductVariant $variant): float
    {
        return (float) InventoryStockLevel::query()
            ->where('inventory_location_id', $location->id)
            ->where('product_variant_id', $variant->id)
            ->value('quantity_on_hand');
    }

    private function receive(InventoryLocation $location, ProductVariant $variant, float $quantity, int $unitCostMinor): void
    {
        app(PostInventoryMovementAction::class)->execute([
            'tenant_id' => $this->tenant->id, 'inventory_location_id' => $location->id,
            'product_variant_id' => $variant->id, 'movement_type' => InventoryMovementType::OpeningStock->value,
            'quantity' => $quantity, 'unit_cost_minor' => $unitCostMinor,
        ]);
    }

    private function location(string $name, string $code, bool $sellable = false, bool $prep = false): InventoryLocation
    {
        return InventoryLocation::query()->create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'name' => $name, 'code' => $code,
            'location_type' => InventoryLocationType::StoreRoom->value, 'status' => 'active',
            'is_sellable_point' => $sellable, 'is_prep_station' => $prep,
        ]);
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
