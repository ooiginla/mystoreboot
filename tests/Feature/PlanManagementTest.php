<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Subscriptions\Models\Module;
use Modules\Subscriptions\Models\Plan;
use Tests\TestCase;

class PlanManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_view_plan_management_and_see_menu_link(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        Plan::query()->create([
            'name' => 'Starter',
            'slug' => 'starter',
            'monthly_price_minor' => 100000,
            'yearly_price_minor' => 1000000,
            'currency_code' => 'NGN',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.subscriptions.plans.index'))
            ->assertOk()
            ->assertSee('Plans')
            ->assertSee('Manage subscription pricing')
            ->assertSee('Starter')
            ->assertSee(route('admin.subscriptions.plans.index'));
    }

    public function test_non_platform_admin_cannot_view_or_update_plans(): void
    {
        $user = User::factory()->create(['is_platform_admin' => false]);
        $plan = Plan::query()->create([
            'name' => 'Starter',
            'slug' => 'starter',
            'currency_code' => 'NGN',
        ]);

        $this->actingAs($user)
            ->get(route('admin.subscriptions.plans.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('admin.subscriptions.plans.update', $plan), $this->validPayload())
            ->assertForbidden();
    }

    public function test_platform_admin_can_update_plan_and_included_modules(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $core = Module::query()->create([
            'name' => 'Business Setup',
            'slug' => 'business',
            'is_core' => true,
            'is_active' => true,
        ]);
        $catalog = Module::query()->create([
            'name' => 'Catalog',
            'slug' => 'catalog',
            'is_core' => false,
            'is_active' => true,
        ]);
        $finance = Module::query()->create([
            'name' => 'Finance',
            'slug' => 'finance',
            'is_core' => false,
            'is_active' => true,
        ]);
        $plan = Plan::query()->create([
            'name' => 'Starter',
            'slug' => 'starter',
            'monthly_price_minor' => 0,
            'yearly_price_minor' => 0,
            'currency_code' => 'NGN',
            'is_active' => true,
        ]);
        $plan->modules()->attach([$core->id, $catalog->id]);

        $payload = $this->validPayload([
            'name' => 'Growth Plus',
            'slug' => 'growth-plus',
            'monthly_price' => '15000.50',
            'yearly_price' => '150000.00',
            'limits' => '{"branches":5,"users":20}',
            'module_ids' => [$finance->id],
        ]);

        $this->actingAs($admin)
            ->put(route('admin.subscriptions.plans.update', $plan), $payload)
            ->assertRedirect(route('admin.subscriptions.plans.index'))
            ->assertSessionHas('status', 'Growth Plus plan updated.');

        $plan->refresh();
        $this->assertSame('Growth Plus', $plan->name);
        $this->assertSame('growth-plus', $plan->slug);
        $this->assertSame(1500050, $plan->monthly_price_minor);
        $this->assertSame(15000000, $plan->yearly_price_minor);
        $this->assertSame(['branches' => 5, 'users' => 20], $plan->limits);
        $this->assertEqualsCanonicalizing([$core->id, $finance->id], $plan->modules()->pluck('billable_modules.id')->all());
    }

    /** @param array<string, mixed> $overrides */
    private function validPayload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Starter',
            'slug' => 'starter',
            'monthly_price' => '0.00',
            'yearly_price' => '0.00',
            'currency_code' => 'NGN',
            'sort_order' => 10,
            'is_active' => '1',
            'limits' => '',
            'module_ids' => [],
        ], $overrides);
    }
}
