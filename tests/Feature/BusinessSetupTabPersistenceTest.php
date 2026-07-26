<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Business\Models\Branch;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class BusinessSetupTabPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_setup_saves_redirect_back_to_their_originating_tabs(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $tenant = Tenant::query()->create([
            'name' => 'Setup Shop',
            'slug' => 'setup-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.business.branches.store'), [
                'tenant_id' => $tenant->id,
                'name' => 'City Store',
                'code' => 'CITY',
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.business.index', ['tenant' => $tenant->id]).'#branches');

        $branch = Branch::query()->where('tenant_id', $tenant->id)->where('code', 'CITY')->firstOrFail();

        $this->post(route('admin.business.departments.store'), [
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'Sales',
            'code' => 'SALES',
            'status' => 'active',
        ])->assertRedirect(route('admin.business.index', ['tenant' => $tenant->id]).'#departments');

        $this->post(route('admin.access.roles.store'), [
            'tenant_id' => $tenant->id,
            'name' => 'Store Supervisor',
            'slug' => 'store-supervisor',
        ])->assertRedirect(route('admin.business.index', ['tenant' => $tenant->id]).'#roles');

        $this->post(route('admin.access.tenant-users.store'), [
            'tenant_id' => $tenant->id,
            'name' => 'Jane Manager',
            'email' => 'jane@example.test',
            'password' => 'password123',
            'branch_id' => $branch->id,
        ])->assertRedirect(route('admin.business.index', ['tenant' => $tenant->id]).'#users');
    }
}
