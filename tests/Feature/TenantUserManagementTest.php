<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Access\Enums\MembershipStatus;
use Modules\Access\Models\Permission;
use Modules\Access\Models\Role;
use Modules\Access\Models\TenantMembership;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class TenantUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_user_information_and_disable_organization_access(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $tenant = $this->tenant();
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.test',
            'email_verified_at' => now(),
        ]);
        $membership = TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.business.index', ['tenant' => $tenant->id]).'#users')
            ->assertOk()
            ->assertSee('Edit user and access')
            ->assertSee('data-membership-email="old@example.test"', false)
            ->assertSee('New password')
            ->assertSee('Organization access');

        $this->actingAs($admin)
            ->put(route('admin.access.memberships.update', $membership), [
                'name' => 'Updated Name',
                'email' => 'updated@example.test',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
                'role_id' => null,
                'branch_id' => null,
                'is_active' => '0',
            ])
            ->assertRedirect(route('admin.business.index', ['tenant' => $tenant->id]).'#users')
            ->assertSessionHas('status', 'User information and access updated for Updated Name.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.test',
            'email_verified_at' => null,
        ]);
        $this->assertDatabaseHas('tenant_memberships', [
            'id' => $membership->id,
            'status' => MembershipStatus::Inactive->value,
        ]);
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_last_active_business_owner_cannot_be_disabled(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $tenant = $this->tenant();
        $owner = User::factory()->create();
        $ownerRole = Role::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Business Owner',
            'slug' => 'business-owner',
            'is_system' => true,
            'is_protected' => true,
        ]);
        $membership = TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'role_id' => $ownerRole->id,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);

        $this->actingAs($admin)
            ->put(route('admin.access.memberships.update', $membership), [
                'name' => 'Changed Owner',
                'email' => $owner->email,
                'role_id' => $ownerRole->id,
                'branch_id' => null,
                'is_active' => '0',
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('users', [
            'id' => $owner->id,
            'name' => $owner->name,
        ]);
        $this->assertDatabaseHas('tenant_memberships', [
            'id' => $membership->id,
            'status' => MembershipStatus::Active->value,
        ]);
    }

    public function test_business_owner_can_see_and_update_an_organization_users_password(): void
    {
        $tenant = $this->tenant();
        $owner = User::factory()->create();
        $staff = User::factory()->create(['password' => 'old-password-123']);
        $ownerRole = Role::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Business Owner',
            'slug' => 'business-owner',
            'is_system' => true,
            'is_protected' => true,
        ]);
        $manageUsers = Permission::query()->where('slug', 'users.manage')->firstOrFail();
        $ownerRole->permissions()->attach($manageUsers);

        TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'role_id' => $ownerRole->id,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);
        $staffMembership = TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $staff->id,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get(route('admin.business.index', ['tenant' => $tenant->id]).'#users')
            ->assertOk()
            ->assertSee('New password')
            ->assertSee('Confirm new password');

        $this->put(route('admin.access.memberships.update', $staffMembership), [
            'name' => $staff->name,
            'email' => $staff->email,
            'password' => 'owner-reset-123',
            'password_confirmation' => 'owner-reset-123',
            'role_id' => null,
            'branch_id' => null,
            'is_active' => '1',
        ])->assertRedirect(route('admin.business.index', ['tenant' => $tenant->id]).'#users');

        $this->assertTrue(Hash::check('owner-reset-123', $staff->fresh()->password));
    }

    private function tenant(): Tenant
    {
        return Tenant::query()->create([
            'name' => 'Access Test Shop',
            'slug' => 'access-test-shop-'.str()->random(8),
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
    }
}
