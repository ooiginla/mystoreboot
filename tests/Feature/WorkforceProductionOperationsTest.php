<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\Access\Enums\MembershipStatus;
use Modules\Access\Models\TenantMembership;
use Modules\Business\Models\Branch;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\HrPayroll\Models\HrAttendanceRecord;
use Modules\HrPayroll\Models\HrShiftAssignment;
use Modules\HrPayroll\Models\HrStaff;
use Modules\HrPayroll\Models\HrWorkShift;
use Modules\Inventory\Actions\PostInventoryMovementAction;
use Modules\Inventory\Enums\InventoryLocationType;
use Modules\Inventory\Enums\InventoryMovementType;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Inventory\Models\ProductionOrder;
use Modules\Inventory\Models\ProductionPlan;
use Modules\Inventory\Models\ProductionWasteRecord;
use Modules\Inventory\Models\ProductionWorkOrder;
use Modules\Inventory\Models\Recipe;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class WorkforceProductionOperationsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Branch $branch;

    private InventoryLocation $location;

    private User $user;

    private HrStaff $baker;

    private ProductVariant $flour;

    private ProductVariant $bread;

    private Recipe $recipe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::query()->create([
            'name' => 'Daily Bread', 'slug' => 'daily-bread', 'status' => TenantStatus::Active,
            'business_type' => 'bakery', 'country_code' => 'NG', 'timezone' => 'Africa/Lagos', 'currency_code' => 'NGN',
        ]);
        $this->branch = Branch::query()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Main Bakery', 'code' => 'MAIN', 'status' => 'active', 'is_primary' => true,
        ]);
        $this->location = InventoryLocation::query()->create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'name' => 'Production Store', 'code' => 'PROD',
            'location_type' => InventoryLocationType::Branch->value, 'status' => 'active', 'is_prep_station' => true,
        ]);
        $this->user = User::factory()->create(['is_platform_admin' => true]);
        TenantMembership::query()->create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'branch_id' => $this->branch->id,
            'status' => MembershipStatus::Active, 'joined_at' => now(),
        ]);
        $this->baker = HrStaff::query()->create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'staff_number' => 'STF-001',
            'first_name' => 'Ada', 'last_name' => 'Baker', 'job_title' => 'Head Baker', 'status' => 'active',
        ]);
        $this->flour = $this->variant('Flour', 'FLOUR');
        $this->bread = $this->variant('Bread', 'BREAD', true);
        $this->recipe = Recipe::query()->create([
            'tenant_id' => $this->tenant->id, 'output_product_variant_id' => $this->bread->id,
            'prep_location_id' => $this->location->id, 'name' => 'Bread batch', 'yield_quantity' => 10,
            'version' => 1, 'is_active' => true, 'status' => 'active',
        ]);
        $this->recipe->items()->create([
            'tenant_id' => $this->tenant->id, 'component_product_variant_id' => $this->flour->id,
            'quantity' => 20, 'sort_order' => 0,
        ]);
        app(PostInventoryMovementAction::class)->execute([
            'tenant_id' => $this->tenant->id, 'inventory_location_id' => $this->location->id,
            'product_variant_id' => $this->flour->id, 'movement_type' => InventoryMovementType::OpeningStock->value,
            'quantity' => 100, 'unit_cost_minor' => 200,
        ]);
    }

    public function test_hr_can_schedule_a_shift_and_capture_clock_in_and_out(): void
    {
        $this->postAsUser(route('admin.hr-payroll.workforce.shifts.store'), [
            'branch_id' => $this->branch->id, 'name' => 'Morning', 'starts_at' => '05:00',
            'ends_at' => '12:00', 'colour' => '#027a45', 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $shift = HrWorkShift::query()->firstOrFail();

        $this->postAsUser(route('admin.hr-payroll.workforce.rota.store'), [
            'branch_id' => $this->branch->id, 'hr_staff_ids' => [$this->baker->id],
            'hr_work_shift_id' => $shift->id, 'work_dates' => [today()->toDateString()],
        ])->assertSessionHasNoErrors();
        $assignment = HrShiftAssignment::query()->firstOrFail();

        $this->postAsUser(route('admin.hr-payroll.workforce.attendance.clock-in'), [
            'hr_shift_assignment_id' => $assignment->id,
        ])->assertSessionHasNoErrors();
        $attendance = HrAttendanceRecord::query()->firstOrFail();
        $this->assertNotNull($attendance->clocked_in_at);

        $this->postAsUser(route('admin.hr-payroll.workforce.attendance.clock-out', $attendance), [
            'break_minutes' => 30,
        ])->assertSessionHasNoErrors();
        $this->assertNotNull($attendance->refresh()->clocked_out_at);
        $this->assertSame(30, $attendance->break_minutes);
    }

    public function test_a_scheduled_production_carries_its_shift_and_team_into_the_run_and_reports(): void
    {
        $shift = HrWorkShift::query()->create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'name' => 'Morning',
            'starts_at' => '05:00', 'ends_at' => '12:00', 'colour' => '#027a45', 'is_active' => true,
        ]);
        $worker = HrStaff::query()->create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'staff_number' => 'STF-002',
            'first_name' => 'Bola', 'last_name' => 'Mixer', 'job_title' => 'Baker', 'status' => 'active',
        ]);
        HrShiftAssignment::query()->create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id,
            'hr_staff_id' => $worker->id, 'hr_work_shift_id' => $shift->id,
            'work_date' => today()->toDateString(), 'status' => 'scheduled',
        ]);
        $this->actingAs($this->user)->withSession($this->branchSession())
            ->get(route('admin.inventory.production.planning', ['tenant' => $this->tenant->id]))
            ->assertOk()->assertDontSee('Finished product type')
            ->assertDontSee('Preparation instructions')
            ->assertDontSee('Production team')
            ->assertSee('Add product to this order')
            ->assertSee('"recipe_id":'.$this->recipe->id, false);

        $this->postAsUser(route('admin.inventory.production.planning.store'), [
            'production_station_id' => $this->location->id,
            'hr_work_shift_id' => $shift->id, 'production_lead_staff_id' => $this->baker->id,
            'production_date' => today()->toDateString(), 'wave' => 'Morning wave',
            'items' => [[
                'recipe_id' => $this->recipe->id, 'planned_quantity' => 10,
                'scheduled_time' => null,
            ]],
        ])->assertSessionHasNoErrors();
        $plan = ProductionPlan::query()->firstOrFail();
        $this->assertNull($plan->scheduled_time);
        $this->assertTrue($plan->team()->whereKey($worker->id)->exists());
        $this->actingAs($this->user)->withSession($this->branchSession())
            ->get(route('admin.inventory.production.planning', ['tenant' => $this->tenant->id]))
            ->assertOk()->assertSee('Scheduled lines')->assertSee('Open order');
        $this->actingAs($this->user)->withSession($this->branchSession())
            ->get(route('admin.inventory.production.planning.day', [
                'tenant' => $this->tenant->id, 'date' => today()->toDateString(),
            ]))
            ->assertOk()->assertSeeInOrder(['Time', 'Product', 'Quantity', 'Station', 'Shift'])
            ->assertSee('Edit')->assertSee('Start')->assertSee('Cancel');

        $this->actingAs($this->user)->withSession($this->branchSession())
            ->put(route('admin.inventory.production.planning.update', $plan), [
                'tenant' => $this->tenant->id,
                'production_station_id' => $this->location->id,
                'hr_work_shift_id' => $shift->id,
                'production_lead_staff_id' => $this->baker->id,
                'production_date' => today()->toDateString(),
                'wave' => 'Updated wave',
                'notes' => 'Use the large oven.',
                'items' => [[
                    'recipe_id' => $this->recipe->id, 'planned_quantity' => 10,
                    'scheduled_time' => '09:30',
                ]],
            ])->assertSessionHasNoErrors();
        $this->assertSame('Updated wave', $plan->refresh()->wave);
        $this->assertSame('09:30', $plan->scheduled_time);

        $this->postAsUser(route('admin.inventory.production.planning.start', $plan))->assertSessionHasNoErrors();
        $order = ProductionOrder::query()->with('items')->firstOrFail();
        $this->assertSame($shift->id, $order->hr_work_shift_id);
        $this->assertSame($this->baker->id, $order->production_lead_staff_id);
        $this->assertTrue($order->team()->whereKey($this->baker->id)->exists());
        $this->assertTrue($order->team()->whereKey($worker->id)->exists());
        $this->actingAs($this->user)->withSession($this->branchSession())
            ->put(route('admin.inventory.production.planning.update', $plan), ['tenant' => $this->tenant->id])
            ->assertSessionHasErrors('production_plan');

        $item = $order->items->first();
        $this->postAsUser(route('admin.inventory.production.runs.complete', $order), [
            'actual_yield_quantity' => 10,
            'items' => [$item->id => ['actual_quantity' => 20]],
        ])->assertSessionHasNoErrors();
        $this->assertSame('completed', $plan->refresh()->status);

        $this->actingAs($this->user)->withSession($this->branchSession())
            ->get(route('admin.inventory.production.reports', ['tenant' => $this->tenant->id]))
            ->assertOk()->assertSee('Staff productivity')->assertSee('Ada Baker')
            ->assertSee('Download PDF')->assertSee('Download Excel')->assertSee('Download Word');

        foreach (['pdf' => 'application/pdf', 'excel' => 'application/vnd.ms-excel; charset=UTF-8', 'word' => 'application/msword; charset=UTF-8'] as $format => $contentType) {
            $this->actingAs($this->user)->withSession($this->branchSession())
                ->get(route('admin.inventory.production.reports.download', [
                    'tenant' => $this->tenant->id, 'format' => $format,
                ]))->assertOk()->assertHeader('Content-Type', $contentType);
        }
    }

    public function test_waste_can_be_valued_and_deducted_from_stock(): void
    {
        app(PostInventoryMovementAction::class)->execute([
            'tenant_id' => $this->tenant->id, 'inventory_location_id' => $this->location->id,
            'product_variant_id' => $this->bread->id, 'movement_type' => InventoryMovementType::OpeningStock->value,
            'quantity' => 10, 'unit_cost_minor' => 400,
        ]);
        $order = ProductionOrder::query()->create([
            'tenant_id' => $this->tenant->id,
            'recipe_id' => $this->recipe->id,
            'recipe_version' => $this->recipe->version,
            'output_product_variant_id' => $this->bread->id,
            'source_location_id' => $this->location->id,
            'output_location_id' => $this->location->id,
            'planned_quantity' => 10,
            'actual_yield_quantity' => 10,
            'status' => ProductionOrder::STATUS_COMPLETED,
            'started_at' => now()->subHour(),
            'produced_at' => now(),
        ]);

        $this->actingAs($this->user)->withSession($this->branchSession())
            ->get(route('admin.inventory.production.waste', ['tenant' => $this->tenant->id]))
            ->assertOk()
            ->assertSee('Bread')
            ->assertSee($order->runNumber())
            ->assertDontSee('Flour \/ Default (FLOUR)', false);

        $this->postAsUser(route('admin.inventory.production.waste.store'), [
            'product_variant_id' => $this->bread->id, 'inventory_location_id' => $this->location->id,
            'production_order_id' => $order->id,
            'hr_staff_id' => $this->baker->id, 'waste_date' => today()->toDateString(),
            'quantity' => 2, 'reason' => 'damaged', 'deduct_from_stock' => 1, 'notes' => 'Loaves damaged.',
        ])->assertSessionHasNoErrors();

        $waste = ProductionWasteRecord::query()->firstOrFail();
        $this->assertSame(800, $waste->value_minor);
        $this->assertSame($order->id, $waste->production_order_id);
        $this->assertTrue($waste->deducted_from_stock);
        $this->assertNotNull($waste->inventory_movement_id);
        $this->assertSame(8.0, (float) InventoryStockLevel::query()
            ->where('inventory_location_id', $this->location->id)->where('product_variant_id', $this->bread->id)
            ->value('quantity_on_hand'));
    }

    public function test_waste_rejects_raw_materials_and_products_outside_the_active_tenant_location(): void
    {
        $this->postAsUser(route('admin.inventory.production.waste.store'), [
            'product_variant_id' => $this->flour->id,
            'inventory_location_id' => $this->location->id,
            'waste_date' => today()->toDateString(),
            'quantity' => 2,
            'reason' => 'damaged',
            'deduct_from_stock' => 1,
        ])->assertSessionHasErrors('product_variant_id');

        $otherTenant = Tenant::query()->create([
            'name' => 'Other Bakery', 'slug' => 'other-bakery', 'status' => TenantStatus::Active,
            'business_type' => 'bakery', 'country_code' => 'NG', 'timezone' => 'Africa/Lagos', 'currency_code' => 'NGN',
        ]);
        $otherBranch = Branch::query()->create([
            'tenant_id' => $otherTenant->id, 'name' => 'Other Branch', 'code' => 'OTHER', 'status' => 'active', 'is_primary' => true,
        ]);
        $otherLocation = InventoryLocation::query()->create([
            'tenant_id' => $otherTenant->id, 'branch_id' => $otherBranch->id, 'name' => 'Other Store', 'code' => 'OTHER-STORE',
            'location_type' => InventoryLocationType::Branch->value, 'status' => 'active',
        ]);
        $otherProduct = Product::query()->create([
            'tenant_id' => $otherTenant->id, 'name' => 'Foreign Cake', 'slug' => 'foreign-cake',
            'product_type' => ProductType::Product->value, 'status' => ProductStatus::Active->value,
            'is_finished_product' => true,
        ]);
        $otherVariant = ProductVariant::query()->create([
            'tenant_id' => $otherTenant->id, 'product_id' => $otherProduct->id,
            'variant_name' => 'Default', 'sku' => 'FOREIGN-CAKE', 'status' => ProductStatus::Active->value,
        ]);
        InventoryStockLevel::query()->create([
            'tenant_id' => $otherTenant->id, 'inventory_location_id' => $otherLocation->id,
            'product_variant_id' => $otherVariant->id, 'quantity_on_hand' => 10,
        ]);

        $this->actingAs($this->user)->withSession($this->branchSession())
            ->get(route('admin.inventory.production.waste', ['tenant' => $this->tenant->id]))
            ->assertOk()
            ->assertDontSee('Foreign Cake');

        $this->postAsUser(route('admin.inventory.production.waste.store'), [
            'product_variant_id' => $otherVariant->id,
            'inventory_location_id' => $this->location->id,
            'waste_date' => today()->toDateString(),
            'quantity' => 1,
            'reason' => 'damaged',
        ])->assertSessionHasErrors('product_variant_id');

        $this->assertDatabaseCount('production_waste_records', 0);
    }

    public function test_waste_cannot_be_linked_to_a_run_for_a_different_finished_product(): void
    {
        $cake = $this->variant('Cake', 'CAKE', true);
        app(PostInventoryMovementAction::class)->execute([
            'tenant_id' => $this->tenant->id,
            'inventory_location_id' => $this->location->id,
            'product_variant_id' => $cake->id,
            'movement_type' => InventoryMovementType::OpeningStock->value,
            'quantity' => 5,
            'unit_cost_minor' => 600,
        ]);
        $breadRun = ProductionOrder::query()->create([
            'tenant_id' => $this->tenant->id,
            'recipe_id' => $this->recipe->id,
            'recipe_version' => $this->recipe->version,
            'output_product_variant_id' => $this->bread->id,
            'source_location_id' => $this->location->id,
            'output_location_id' => $this->location->id,
            'planned_quantity' => 10,
            'actual_yield_quantity' => 10,
            'status' => ProductionOrder::STATUS_COMPLETED,
            'started_at' => now()->subHour(),
            'produced_at' => now(),
        ]);

        $this->postAsUser(route('admin.inventory.production.waste.store'), [
            'product_variant_id' => $cake->id,
            'inventory_location_id' => $this->location->id,
            'production_order_id' => $breadRun->id,
            'waste_date' => today()->toDateString(),
            'quantity' => 1,
            'reason' => 'damaged',
        ])->assertSessionHasErrors('production_order_id');

        $this->assertDatabaseCount('production_waste_records', 0);
    }

    public function test_multiple_products_can_be_planned_and_started_as_one_production_order(): void
    {
        $cake = $this->variant('Cake', 'CAKE', true);
        $cakeRecipe = Recipe::query()->create([
            'tenant_id' => $this->tenant->id, 'output_product_variant_id' => $cake->id,
            'prep_location_id' => $this->location->id, 'name' => 'Cake batch', 'yield_quantity' => 5,
            'version' => 1, 'is_active' => true, 'status' => 'active',
        ]);
        $cakeRecipe->items()->create([
            'tenant_id' => $this->tenant->id, 'component_product_variant_id' => $this->flour->id,
            'quantity' => 10, 'sort_order' => 0,
        ]);

        $this->postAsUser(route('admin.inventory.production.planning.store'), [
            'production_station_id' => $this->location->id,
            'production_lead_staff_id' => $this->baker->id,
            'production_date' => today()->toDateString(),
            'items' => [[
                'recipe_id' => $this->recipe->id, 'planned_quantity' => 10,
                'scheduled_time' => '08:00',
            ], [
                'recipe_id' => $cakeRecipe->id, 'planned_quantity' => 5,
                'scheduled_time' => '10:30',
            ]],
        ])->assertSessionHasNoErrors();

        $workOrder = ProductionWorkOrder::query()->with('plans')->firstOrFail();
        $this->assertCount(2, $workOrder->plans);
        $this->assertSame('planned', $workOrder->status);
        $this->assertNull($workOrder->scheduled_time);
        $this->assertEqualsCanonicalizing(['08:00', '10:30'], $workOrder->plans->pluck('scheduled_time')->all());
        $this->assertSame($this->tenant->id, $workOrder->tenant_id);
        $this->assertSame($this->branch->id, $workOrder->branch_id);
        $this->assertSame(1, ProductionWorkOrder::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('branch_id', $this->branch->id)
            ->whereDate('production_date', $workOrder->production_date->toDateString())
            ->count());
        $this->actingAs($this->user)->withSession($this->branchSession())
            ->get(route('admin.inventory.production.orders', [
                'tenant' => $this->tenant->id,
                'from' => $workOrder->production_date->toDateString(),
                'to' => $workOrder->production_date->toDateString(),
            ]))
            ->assertOk()->assertSee($workOrder->order_number)->assertSee('Bread')->assertSee('Cake')
            ->assertDontSee('Start all')->assertSee('Edit')->assertSee('Start')->assertSee('Cancel');

        foreach ($workOrder->plans as $plan) {
            $this->postAsUser(route('admin.inventory.production.planning.start', $plan))->assertSessionHasNoErrors();
        }
        $this->assertSame('in_progress', $workOrder->refresh()->status);
        $this->assertSame(2, ProductionOrder::query()->where('production_work_order_id', $workOrder->id)->count());
        $this->assertSame(2, ProductionPlan::query()->where('production_work_order_id', $workOrder->id)->where('status', 'in_progress')->count());
    }

    private function postAsUser(string $url, array $data = []): TestResponse
    {
        return $this->actingAs($this->user)->withSession($this->branchSession())->post($url, [
            'tenant' => $this->tenant->id, ...$data,
        ]);
    }

    private function branchSession(): array
    {
        return ['active_branch_ids' => [$this->tenant->id => $this->branch->id]];
    }

    private function variant(string $name, string $sku, bool $finished = false): ProductVariant
    {
        $product = Product::query()->create([
            'tenant_id' => $this->tenant->id, 'name' => $name, 'slug' => Str::slug($name),
            'product_type' => ProductType::Product->value, 'status' => ProductStatus::Active->value,
            'is_finished_product' => $finished,
        ]);

        return ProductVariant::query()->create([
            'tenant_id' => $this->tenant->id, 'product_id' => $product->id,
            'variant_name' => 'Default', 'sku' => $sku, 'status' => ProductStatus::Active->value,
        ]);
    }
}
