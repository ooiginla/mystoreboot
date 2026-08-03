<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Business\Models\BusinessPaymentAccount;
use Modules\Business\Models\OnlineStore;
use Modules\Business\Support\OnboardingProgress;
use Modules\Finance\Models\FinanceAccount;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

class DashboardOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(string $slug, array $overrides = []): Tenant
    {
        return Tenant::query()->create(array_merge([
            'name' => 'Nudge Shop',
            'slug' => $slug,
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ], $overrides));
    }

    private function addPaymentAccount(Tenant $tenant): void
    {
        $account = FinanceAccount::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'CASH-1001',
            'name' => 'Cash',
            'type' => 'asset',
            'normal_balance' => 'debit',
        ]);

        BusinessPaymentAccount::query()->create([
            'tenant_id' => $tenant->id,
            'finance_account_id' => $account->id,
            'identifier' => 'Cash Till',
            'provider_name' => 'Cash',
            'supported_payment_methods' => ['cash'],
            'status' => 'active',
        ]);
    }

    public function test_dashboard_shows_both_onboarding_nudges_when_nothing_is_set_up(): void
    {
        $tenant = $this->makeTenant('nudge-empty'); // business_type only, no address/contact
        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($user)
            ->get(route('admin.analytics.index', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('Complete your business profile')
            ->assertSee('Set up your online store')
            ->assertSee('0/2')
            ->assertSee('0/5')
            ->assertSee('Continue: Add your business details')
            ->assertSee('Continue: Store basics')
            ->assertSee('#business-profile', false)
            ->assertSee('online_store_section=online-store-basics', false);
    }

    public function test_completed_profile_is_hidden_and_store_shows_partial_progress(): void
    {
        $tenant = $this->makeTenant('nudge-partial', [
            'address' => '1 Market Road',
            'email' => 'owner@nudge.test',
            'phone' => '08012345678',
        ]);
        $this->addPaymentAccount($tenant);

        // Only the "basics" store step is done.
        OnlineStore::query()->create([
            'tenant_id' => $tenant->id,
            'username' => 'nudge-partial',
            'store_name' => 'Nudge Store',
            'theme_primary_color' => '#006554',
            'theme_secondary_color' => '#f59e0b',
        ]);

        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($user)
            ->get(route('admin.analytics.index', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertDontSee('Complete your business profile')
            ->assertSee('Set up your online store')
            ->assertSee('1/5')
            ->assertSee('Continue: Contact details')
            ->assertSee('online_store_section=online-store-contact', false);
    }

    public function test_fully_onboarded_tenant_sees_no_nudges(): void
    {
        $tenant = $this->makeTenant('nudge-done', [
            'address' => '1 Market Road',
            'email' => 'owner@nudge.test',
            'phone' => '08012345678',
        ]);
        $this->addPaymentAccount($tenant);

        OnlineStore::query()->create([
            'tenant_id' => $tenant->id,
            'username' => 'nudge-done',
            'store_name' => 'Nudge Store',
            'theme_primary_color' => '#006554',
            'theme_secondary_color' => '#f59e0b',
            'address' => '1 Market Road',
            'site_email' => 'store@nudge.test',
            'store_phone' => '08012345678',
            'hero_image_path' => 'tenants/x/online-store/heroes/hero.jpg',
            'payment_methods' => ['pay_on_delivery'],
            'shipping_options' => [['location' => 'Lagos', 'description' => '3-5 days', 'price' => 1500]],
        ]);

        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($user)
            ->get(route('admin.analytics.index', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertDontSee('Complete your business profile')
            ->assertDontSee('Set up your online store');
    }

    public function test_onboarding_progress_computes_business_profile_steps(): void
    {
        $tenant = $this->makeTenant('nudge-unit', [
            'address' => '1 Market Road',
            'email' => 'owner@nudge.test',
        ]);

        $incomplete = OnboardingProgress::businessProfile($tenant);
        $this->assertSame(2, $incomplete->total());
        $this->assertSame(1, $incomplete->completed()); // profile filled, no payment account
        $this->assertFalse($incomplete->isComplete());
        $this->assertSame('payment_account', $incomplete->nextStep()['key']);
        $this->assertSame(50, $incomplete->percent());

        $this->addPaymentAccount($tenant);

        $complete = OnboardingProgress::businessProfile($tenant);
        $this->assertTrue($complete->isComplete());
        $this->assertNull($complete->nextStep());
        $this->assertSame(100, $complete->percent());
    }
}
