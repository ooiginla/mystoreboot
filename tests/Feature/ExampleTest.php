<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Access\Enums\MembershipStatus;
use Modules\Access\Models\TenantMembership;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('admin.business.index'));

        $this->get(route('admin.business.index'))
            ->assertRedirect(route('login'));

        $user = User::factory()->create([
            'is_platform_admin' => true,
        ]);

        $this->actingAs($user)
            ->get(route('admin.business.index'))
            ->assertOk()
            ->assertSee('Organization &amp; Branch Management', false);
    }

    public function test_tenant_users_only_see_their_own_business_setup(): void
    {
        $ownedTenant = Tenant::query()->create([
            'name' => 'Owned Shop',
            'slug' => 'owned-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);

        $otherTenant = Tenant::query()->create([
            'name' => 'Other Shop',
            'slug' => 'other-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'supermarket',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);

        $tenantUser = User::factory()->create([
            'is_platform_admin' => false,
        ]);

        TenantMembership::query()->create([
            'tenant_id' => $ownedTenant->id,
            'user_id' => $tenantUser->id,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);

        $this->actingAs($tenantUser)
            ->get(route('admin.business.index'))
            ->assertOk()
            ->assertSee('Owned Shop')
            ->assertDontSee('Other Shop');

        $this->actingAs($tenantUser)
            ->get(route('admin.business.index', ['tenant' => $otherTenant->id]))
            ->assertForbidden();

        $this->actingAs($tenantUser)
            ->get(route('admin.business.organizations.index'))
            ->assertForbidden();
    }
}
