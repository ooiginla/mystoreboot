<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Access\Enums\MembershipStatus;
use Modules\Access\Models\Role;
use Modules\Access\Models\TenantMembership;
use Modules\Business\Models\Branch;
use Modules\Business\Models\Department;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class OrganizationDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_permanently_delete_an_organization_and_all_tenant_rows(): void
    {
        $tenant = $this->tenant('Delete Me', 'delete-me');
        $survivor = $this->tenant('Keep Me', 'keep-me');
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $member = User::factory()->create();

        $branch = Branch::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Main',
            'code' => 'MAIN',
            'status' => 'active',
            'is_primary' => true,
        ]);
        Department::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'Operations',
            'code' => 'OPS',
            'status' => 'active',
        ]);
        $role = Role::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Owner',
            'slug' => 'owner',
            'is_system' => false,
        ]);
        TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $member->id,
            'role_id' => $role->id,
            'status' => MembershipStatus::Active->value,
        ]);
        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Tenant Product',
            'slug' => 'tenant-product',
            'product_type' => ProductType::Product->value,
            'status' => ProductStatus::Active->value,
        ]);
        ProductVariant::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'variant_name' => 'Default',
            'sku' => 'DELETE-001',
            'status' => ProductStatus::Active->value,
        ]);

        DB::table('approval_requests')->insert([
            'tenant_id' => $tenant->id,
            'type' => 'expense',
            'status' => 'pending',
            'title' => 'Delete with tenant',
            'requested_by' => $member->id,
        ]);
        DB::table('security_audit_logs')->insert([
            'tenant_id' => $tenant->id,
            'action' => 'test.created',
            'description' => 'Delete with tenant',
        ]);
        DB::table('security_audit_logs')->insert([
            'tenant_id' => $survivor->id,
            'action' => 'test.created',
            'description' => 'Keep with tenant',
        ]);
        $walletId = DB::table('wallets')->insertGetId([
            'tenant_id' => $tenant->id,
            'currency_code' => 'NGN',
        ]);
        DB::table('wallet_transactions')->insert([
            'tenant_id' => $tenant->id,
            'wallet_id' => $walletId,
            'direction' => 'credit',
            'state' => 'available',
            'category' => 'adjustment',
            'amount_minor' => 1000,
            'currency_code' => 'NGN',
        ]);
        DB::table('wallet_withdrawals')->insert([
            'tenant_id' => $tenant->id,
            'wallet_id' => $walletId,
            'amount_minor' => 500,
            'gateway_fee_minor' => 0,
            'platform_fee_minor' => 0,
            'total_debit_minor' => 500,
            'currency_code' => 'NGN',
            'status' => 'pending',
            'reference' => 'DELETE-WITHDRAWAL-001',
        ]);

        $this->actingAs($admin)
            ->withSession([
                'active_tenant_id' => $tenant->id,
                'active_branch_ids' => [$tenant->id => $branch->id],
            ])
            ->delete(route('admin.business.organizations.destroy', $tenant), [
                'confirmation' => $tenant->name,
            ])
            ->assertRedirect(route('admin.business.organizations.index'))
            ->assertSessionHas('status')
            ->assertSessionMissing('active_tenant_id');

        $this->assertNull(Tenant::withTrashed()->find($tenant->id));
        $this->assertNotNull(Tenant::query()->find($survivor->id));
        $this->assertNotNull(User::query()->find($member->id), 'Shared user accounts must not be deleted.');
        $this->assertDatabaseHas('security_audit_logs', ['tenant_id' => $survivor->id]);

        foreach (Schema::getTableListing(null, false) as $table) {
            if (Schema::hasColumn($table, 'tenant_id')) {
                $this->assertSame(
                    0,
                    DB::table($table)->where('tenant_id', $tenant->id)->count(),
                    "Tenant rows remain in {$table}.",
                );
            }
        }
    }

    public function test_organization_deletion_requires_a_platform_admin_and_exact_name(): void
    {
        $tenant = $this->tenant('Protected Organization', 'protected-organization');
        $regularUser = User::factory()->create();
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($regularUser)
            ->delete(route('admin.business.organizations.destroy', $tenant), [
                'confirmation' => $tenant->name,
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->from(route('admin.business.organizations.show', $tenant))
            ->delete(route('admin.business.organizations.destroy', $tenant), [
                'confirmation' => 'wrong name',
            ])
            ->assertRedirect(route('admin.business.organizations.show', $tenant))
            ->assertSessionHasErrors('confirmation');

        $this->assertNotNull(Tenant::query()->find($tenant->id));
    }

    public function test_organization_pages_offer_the_guarded_delete_dialog(): void
    {
        $tenant = $this->tenant('Dialog Organization', 'dialog-organization');
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.business.organizations.index'))
            ->assertOk()
            ->assertSee('data-dialog-open="delete-organization-'.$tenant->id.'"', false)
            ->assertSee(route('admin.business.organizations.destroy', $tenant), false)
            ->assertSee('Permanently delete organization');

        $this->actingAs($admin)
            ->get(route('admin.business.organizations.show', $tenant))
            ->assertOk()
            ->assertSee('Delete organization')
            ->assertSee('name="confirmation"', false);
    }

    public function test_organizations_can_be_searched_and_made_active(): void
    {
        $first = $this->tenant('Alpha Retail', 'alpha-retail');
        $second = $this->tenant('Lagoon Restaurant', 'lagoon-restaurant');
        $second->update([
            'email' => 'hello@lagoon.example',
            'address' => 'Victoria Island',
        ]);
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)
            ->withSession(['active_tenant_id' => $first->id])
            ->get(route('admin.business.organizations.index', [
                'organization_search' => 'lagoon',
            ]))
            ->assertOk()
            ->assertSee('name="organization_search"', false)
            ->assertSee('data-organization-search', false)
            ->assertSee('form.requestSubmit()', false)
            ->assertSee('value="lagoon"', false)
            ->assertSee('data-organization-id="'.$second->id.'"', false)
            ->assertDontSee('data-organization-id="'.$first->id.'"', false)
            ->assertSee(route('admin.business.organizations.activate', $second), false);

        $this->actingAs($admin)
            ->withSession(['active_tenant_id' => $first->id])
            ->post(route('admin.business.organizations.activate', $second), [
                'organization_search' => 'lagoon',
            ])
            ->assertRedirect(route('admin.business.organizations.index', [
                'organization_search' => 'lagoon',
            ]))
            ->assertSessionHas('active_tenant_id', $second->id)
            ->assertSessionHas('status', "{$second->name} is now the active organization.");

        $regularUser = User::factory()->create();
        $this->actingAs($regularUser)
            ->post(route('admin.business.organizations.activate', $first))
            ->assertForbidden();
    }

    private function tenant(string $name, string $slug): Tenant
    {
        return Tenant::query()->create([
            'name' => $name,
            'slug' => $slug,
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
    }
}
