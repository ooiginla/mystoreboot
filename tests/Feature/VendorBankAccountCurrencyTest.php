<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Procurement\Models\Vendor;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class VendorBankAccountCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_currency_is_a_dropdown_defaulted_to_the_store_currency(): void
    {
        $tenant = $this->tenant('GHS');
        $user = User::factory()->create(['is_platform_admin' => true]);

        $response = $this->actingAs($user)
            ->get(route('admin.procurement.index', ['tenant' => $tenant->id]))
            ->assertOk();

        $this->assertMatchesRegularExpression(
            '/<select name="bank_accounts\\[0\\]\\[currency_code\\]">.*?<option value="GHS" selected>/s',
            $response->getContent(),
        );
    }

    public function test_editing_a_vendor_preserves_its_saved_bank_account_currency(): void
    {
        $tenant = $this->tenant('GHS');
        $user = User::factory()->create(['is_platform_admin' => true]);
        $vendor = Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Overseas Supplier',
            'lead_time_days' => 0,
        ]);
        $vendor->bankAccounts()->create([
            'tenant_id' => $tenant->id,
            'bank_name' => 'International Bank',
            'account_name' => $vendor->name,
            'account_number' => '0012345678',
            'currency_code' => 'USD',
            'is_primary' => true,
        ]);

        $response = $this->actingAs($user)
            ->get(route('admin.procurement.index', ['tenant' => $tenant->id]))
            ->assertOk();

        $this->assertMatchesRegularExpression(
            '/id="vendor-edit-'.$vendor->id.'".*?<select name="bank_accounts\\[0\\]\\[currency_code\\]">.*?<option value="USD" selected>/s',
            $response->getContent(),
        );
    }

    public function test_vendor_view_lists_all_bank_accounts_under_a_dedicated_tab(): void
    {
        $tenant = $this->tenant('NGN');
        $user = User::factory()->create(['is_platform_admin' => true]);
        $vendor = Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Multi Bank Supplier',
            'lead_time_days' => 0,
        ]);
        $vendor->bankAccounts()->createMany([
            [
                'tenant_id' => $tenant->id,
                'bank_name' => 'Primary Bank',
                'account_name' => $vendor->name,
                'account_number' => '1111111111',
                'currency_code' => 'NGN',
                'is_primary' => true,
            ],
            [
                'tenant_id' => $tenant->id,
                'bank_name' => 'Dollar Bank',
                'account_name' => $vendor->name,
                'account_number' => '2222222222',
                'currency_code' => 'USD',
                'is_primary' => false,
            ],
        ]);

        $response = $this->actingAs($user)
            ->get(route('admin.procurement.index', ['tenant' => $tenant->id]))
            ->assertOk();

        $this->assertMatchesRegularExpression(
            '/class="vendor-dialog-tabs".*?'
            .'data-local-tab-target="vendor-bank-accounts-'.$vendor->id.'".*?'
            .'id="vendor-bank-accounts-'.$vendor->id.'".*?'
            .'Primary Bank.*?1111111111.*?NGN.*?Primary.*?'
            .'Dollar Bank.*?2222222222.*?USD.*?Additional/s',
            $response->getContent(),
        );
        $response->assertSee('<span class="vendor-lead-tag">0-day lead</span>', false);
        $response->assertSee('class="vendor-lead-badge"', false);
        $response->assertSee('class="btn vendor-view-action"', false);
        $response->assertSee('class="btn vendor-edit-action"', false);
    }

    public function test_vendor_bank_account_rejects_an_unsupported_currency(): void
    {
        $tenant = $this->tenant('NGN');
        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($user)
            ->from(route('admin.procurement.index', ['tenant' => $tenant->id]))
            ->post(route('admin.procurement.vendors.store'), [
                'tenant_id' => $tenant->id,
                'name' => 'Local Supplier',
                'bank_accounts' => [[
                    'bank_name' => 'Example Bank',
                    'account_name' => 'Local Supplier',
                    'account_number' => '0123456789',
                    'currency_code' => 'ZZZ',
                    'is_primary' => true,
                ]],
            ])
            ->assertRedirect(route('admin.procurement.index', ['tenant' => $tenant->id]))
            ->assertSessionHasErrors('bank_accounts.0.currency_code');

        $this->assertDatabaseMissing('vendors', ['tenant_id' => $tenant->id, 'name' => 'Local Supplier']);
    }

    private function tenant(string $currencyCode): Tenant
    {
        return Tenant::query()->create([
            'name' => 'Vendor Currency Shop',
            'slug' => 'vendor-currency-shop-'.str()->random(6),
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => $currencyCode,
        ]);
    }
}
