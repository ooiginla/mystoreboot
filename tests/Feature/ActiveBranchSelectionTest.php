<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Access\Enums\MembershipStatus;
use Modules\Access\Models\TenantMembership;
use Modules\Business\Models\Branch;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class ActiveBranchSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_store_active_branch_for_session(): void
    {
        $tenant = $this->tenant();
        $user = User::factory()->create();
        $branch = $this->branch($tenant, 'Main Branch');

        TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'status' => MembershipStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($user)
            ->from(route('admin.business.index', ['tenant' => $tenant->id]))
            ->post(route('admin.active-branch.update'), [
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
            ])
            ->assertRedirect(route('admin.business.index', ['tenant' => $tenant->id]))
            ->assertSessionHas('active_tenant_id', $tenant->id)
            ->assertSessionHas('active_branch_ids.'.$tenant->id, $branch->id);
    }

    public function test_admin_shell_prompts_for_branch_when_multiple_are_available(): void
    {
        $tenant = $this->tenant();
        $user = User::factory()->create();
        $mainBranch = $this->branch($tenant, 'Main Branch');
        $annexBranch = $this->branch($tenant, 'Annex Branch');

        TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'status' => MembershipStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('admin.business.index', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('Choose active branch')
            ->assertSee($mainBranch->name)
            ->assertSee($annexBranch->name);
    }

    public function test_admin_shell_uses_stored_active_branch(): void
    {
        $tenant = $this->tenant();
        $user = User::factory()->create();
        $this->branch($tenant, 'Main Branch');
        $annexBranch = $this->branch($tenant, 'Annex Branch');

        TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'status' => MembershipStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession(['active_branch_ids' => [$tenant->id => $annexBranch->id]])
            ->get(route('admin.business.index', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('Active branch')
            ->assertSee('Annex Branch')
            ->assertDontSee('<dialog class="dialog" data-active-branch-dialog>', false);
    }

    public function test_admin_menu_links_keep_active_organization_context(): void
    {
        $tenant = $this->tenant();
        $user = User::factory()->create();
        $branch = $this->branch($tenant, 'Main Branch');

        TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'status' => MembershipStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession([
                'active_tenant_id' => $tenant->id,
                'active_branch_ids' => [$tenant->id => $branch->id],
            ])
            ->get(route('admin.catalog.index', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee(route('admin.inventory.index', ['tenant' => $tenant->id]), false)
            ->assertSee(route('admin.procurement.index', ['tenant' => $tenant->id]), false)
            ->assertSee(route('admin.sales.index', ['tenant' => $tenant->id]), false);
    }

    public function test_branch_scoped_user_cannot_select_another_branch(): void
    {
        $tenant = $this->tenant();
        $user = User::factory()->create();
        $allowedBranch = $this->branch($tenant, 'Allowed Branch');
        $otherBranch = $this->branch($tenant, 'Other Branch');

        TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'branch_id' => $allowedBranch->id,
            'status' => MembershipStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('admin.active-branch.update'), [
                'tenant_id' => $tenant->id,
                'branch_id' => $otherBranch->id,
            ])
            ->assertSessionHasErrors('branch_id')
            ->assertSessionMissing('active_branch_ids.'.$tenant->id);
    }

    private function tenant(): Tenant
    {
        return Tenant::query()->create([
            'name' => 'Branch Shop',
            'slug' => 'branch-shop-'.str()->random(6),
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
    }

    private function branch(Tenant $tenant, string $name): Branch
    {
        return Branch::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'code' => str($name)->slug('-')->upper()->value(),
            'status' => 'active',
            'is_primary' => false,
        ]);
    }
}
