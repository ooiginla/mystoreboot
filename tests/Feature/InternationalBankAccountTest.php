<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Access\Enums\MembershipStatus;
use Modules\Access\Models\TenantMembership;
use Modules\Business\Actions\SavePaymentAccountAction;
use Modules\Business\Models\BusinessPaymentAccount;
use Modules\Business\Models\OnlineStore;
use Modules\Business\Support\BankAccountNameVerification;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

class InternationalBankAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_name_is_manual_only_when_country_and_currency_are_both_outside_nigeria(): void
    {
        $this->assertFalse(BankAccountNameVerification::required(new Tenant([
            'country_code' => 'GH', 'currency_code' => 'GHS',
        ])));
        $this->assertTrue(BankAccountNameVerification::required(new Tenant([
            'country_code' => 'NG', 'currency_code' => 'GHS',
        ])));
        $this->assertTrue(BankAccountNameVerification::required(new Tenant([
            'country_code' => 'GH', 'currency_code' => 'NGN',
        ])));
    }

    public function test_international_payment_account_accepts_a_manual_name_without_paystack_resolution(): void
    {
        Http::fake();
        $tenant = Tenant::query()->create([
            'name' => 'Ghana Shop',
            'slug' => 'ghana-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'GH',
            'currency_code' => 'GHS',
            'timezone' => 'Africa/Accra',
        ]);
        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($user)
            ->get(route('admin.business.index', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('data-manual-name="1"', false)
            ->assertSee('placeholder="Enter account name"', false);

        $data = [
            'tenant_id' => $tenant->id,
            'identifier' => 'Main bank',
            'provider_name' => 'International Bank',
            'bank_code' => 'MANUAL',
            'account_number' => 'GH12-34567890',
            'account_name' => 'Ghana Shop Limited',
            'account_type' => 'normal',
            'supported_payment_methods' => ['Transfer'],
            'status' => 'active',
        ];

        $this->actingAs($user)
            ->post(route('admin.business.payment-accounts.store'), $data)
            ->assertRedirect();
        $this->assertDatabaseHas(BusinessPaymentAccount::class, [
            'tenant_id' => $tenant->id,
            'provider_name' => 'International Bank',
            'account_number' => 'GH12-34567890',
            'account_name' => 'Ghana Shop Limited',
        ]);
        Http::assertNothingSent();

        $this->actingAs($user)
            ->post(route('admin.business.payment-accounts.store'), array_merge($data, [
                'identifier' => 'Missing name', 'account_name' => '',
            ]))
            ->assertSessionHasErrors('account_name');

        app(SavePaymentAccountAction::class)->execute(array_merge($data, [
            'identifier' => 'Onboarding bank',
        ]));
        Http::assertNothingSent();
    }

    public function test_international_online_settlement_keeps_the_entered_account_name(): void
    {
        Http::fake();
        $tenant = Tenant::query()->create([
            'name' => 'Ghana Store',
            'slug' => 'ghana-store',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'GH',
            'currency_code' => 'GHS',
            'timezone' => 'Africa/Accra',
        ]);
        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($user)->post(route('admin.business.online-store.save'), [
            'tenant_id' => $tenant->id,
            'username' => 'ghana-store',
            'store_name' => 'Ghana Store',
            'theme_primary_color' => '#005f73',
            'theme_secondary_color' => '#ee9b00',
            'paystack_method' => 'storeboot_paystack',
            'settlement_bank_account' => [
                'bank_name' => 'International Bank',
                'account_number' => 'GH12-34567890',
                'account_name' => 'Ghana Store Limited',
            ],
        ])->assertRedirect();

        $store = OnlineStore::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('Ghana Store Limited', $store->payment_settings['settlement_bank_account']['account_name']);
        $this->assertSame('GH12-34567890', $store->payment_settings['settlement_bank_account']['account_number']);
        Http::assertNothingSent();
    }

    public function test_international_onboarding_bank_accepts_a_manual_name_and_account_number(): void
    {
        Http::fake();
        $tenant = Tenant::query()->create([
            'name' => 'Accra Shop',
            'slug' => 'accra-shop',
            'status' => TenantStatus::Trialing,
            'business_type' => 'retail',
            'country_code' => 'GH',
            'currency_code' => 'GHS',
            'timezone' => 'Africa/Accra',
            'settings' => ['onboarding' => ['completed' => false, 'step' => 3]],
        ]);
        $user = User::factory()->create();
        TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'status' => MembershipStatus::Active,
        ]);

        $this->actingAs($user)
            ->get(route('onboarding.step', ['step' => 3]))
            ->assertOk()
            ->assertSee('data-manual-name="1"', false);
        $this->post(route('onboarding.bank'), [
            'bank_name' => 'International Bank',
            'account_number' => 'GH12-34567890',
            'account_name' => 'Accra Shop Limited',
            'store_payment_methods' => ['pay_on_delivery'],
        ])->assertRedirect(route('onboarding.step', ['step' => 4]));

        $this->assertDatabaseHas(BusinessPaymentAccount::class, [
            'tenant_id' => $tenant->id,
            'account_number' => 'GH12-34567890',
            'account_name' => 'Accra Shop Limited',
        ]);
        Http::assertNothingSent();
    }
}
