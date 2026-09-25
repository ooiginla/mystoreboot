<?php

namespace Tests\Feature;

use App\Mail\TenantWelcomeMail;
use App\Mail\VerifyEmailMail;
use App\Models\User;
use App\Support\Geo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Modules\Access\Enums\MembershipStatus;
use Modules\Access\Models\TenantMembership;
use Modules\Business\Models\Branch;
use Modules\Business\Models\OnlineStore;
use Modules\Finance\Models\FinanceExpenseCategory;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Subscriptions\Enums\SubscriptionStatus;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\TenantSubscription;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

class TenantSignupTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_signup_bypasses_recaptcha_but_requires_the_email_code(): void
    {
        Mail::fake();
        $this->app->detectEnvironment(fn (): string => 'local');
        $this->withSession(['_token' => 'local-test-token']);
        config([
            'services.recaptcha.site_key' => 'configured-site-key',
            'services.recaptcha.secret_key' => 'configured-secret-key',
        ]);

        $response = $this->post(route('register.store'), [
            '_token' => 'local-test-token',
            'business_name' => 'Local Retail',
            'business_category' => 'retail',
            'city' => 'Lagos',
            'country' => 'NG',
            'name' => 'Local Owner',
            'email' => 'local@bootup.test',
            'phone' => '+2348011111111',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('verification_email', 'local@bootup.test');

        $user = User::query()->where('email', 'local@bootup.test')->firstOrFail();

        $this->assertNull($user->email_verified_at);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $user->email_verification_code);
        $this->assertTrue($user->email_verification_code_expires_at->isFuture());
        Http::assertNothingSent();
        Mail::assertSent(VerifyEmailMail::class, fn (VerifyEmailMail $mail): bool => $mail->user->is($user)
            && $mail->verificationCode === $user->email_verification_code);

        $this->post(route('verification.verify'), [
            '_token' => 'local-test-token',
            'email' => 'local@bootup.test',
            'code' => $user->email_verification_code,
        ])->assertRedirect(route('onboarding.index'));

        $this->assertNotNull($user->refresh()->email_verified_at);
        $this->assertNull($user->email_verification_code);

        // New tenants are sent to the onboarding wizard before the admin area.
        $this->assertAuthenticatedAs($user);
    }

    public function test_signup_offers_all_iso_countries_and_configures_togo(): void
    {
        Mail::fake();
        $this->app->detectEnvironment(fn (): string => 'local');
        $this->withSession(['_token' => 'togo-signup-token']);

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Afghanistan')
            ->assertSee('<option value="TG"', false)
            ->assertSee('Togo')
            ->assertSee('Vatican City');

        $this->assertCount(249, Geo::countries());
        $this->assertCount(249, array_unique(array_column(Geo::countries(), 'code')));

        $this->post(route('register.store'), [
            '_token' => 'togo-signup-token',
            'business_name' => 'Lome Retail',
            'business_category' => 'retail',
            'city' => 'Lome',
            'country' => 'TG',
            'name' => 'Togo Owner',
            'email' => 'owner@lome.test',
            'phone' => '+22890123456',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('verification.notice'));

        $tenant = Tenant::query()->where('slug', 'lome-retail')->firstOrFail();

        $this->assertSame('TG', $tenant->country_code);
        $this->assertSame('Africa/Lome', $tenant->timezone);
        $this->assertSame('XOF', $tenant->currency_code);
    }

    public function test_bank_setup_can_be_skipped_during_onboarding(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::query()->create([
            'name' => 'Skip Bank Shop',
            'slug' => 'skip-bank-shop',
            'status' => TenantStatus::Trialing,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
            'settings' => ['onboarding' => ['completed' => false, 'step' => 3]],
        ]);
        TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'status' => MembershipStatus::Active,
        ]);

        $this->actingAs($user)
            ->get(route('onboarding.step', ['step' => 3]))
            ->assertOk()
            ->assertSee('Skip bank setup for now');

        $this->post(route('onboarding.bank'), ['skip' => '1'])
            ->assertRedirect(route('onboarding.step', ['step' => 4]));

        $this->assertSame(4, $tenant->refresh()->settings['onboarding']['step']);
    }

    public function test_socials_step_populates_store_socials_whatsapp_and_signup_email(): void
    {
        $user = User::factory()->create(['email' => 'owner@social-shop.test']);
        $tenant = Tenant::query()->create([
            'name' => 'Social Shop',
            'slug' => 'social-shop',
            'status' => TenantStatus::Trialing,
            'business_type' => 'retail',
            'phone' => '+2348011111111',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
            'settings' => ['onboarding' => ['completed' => false, 'step' => 5]],
        ]);
        TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'status' => MembershipStatus::Active,
        ]);
        $store = OnlineStore::query()->create([
            'tenant_id' => $tenant->id,
            'username' => 'social-shop',
            'store_name' => 'Social Shop',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('onboarding.step', ['step' => 5]))
            ->assertOk()
            ->assertSee('Your socials')
            ->assertSee('name="instagram"', false)
            ->assertSee('name="tiktok"', false)
            ->assertSee('name="whatsapp"', false)
            ->assertSee('Skip for now')
            ->assertSee('+2348011111111');

        $this->post(route('onboarding.socials'), [
            'instagram' => '@socialshop',
            'tiktok' => '@socialshoptok',
            'whatsapp' => '+2348099999999',
        ])->assertRedirect(route('onboarding.step', ['step' => 6]));

        $store->refresh();

        $this->assertSame('@socialshop', $store->social_accounts['instagram']);
        $this->assertSame('@socialshoptok', $store->social_accounts['tiktok']);
        $this->assertSame('+2348099999999', $store->social_accounts['whatsapp']);
        $this->assertSame('+2348099999999', $store->store_whatsapp);
        $this->assertSame('owner@social-shop.test', $store->site_email);
        $this->assertSame(6, $tenant->refresh()->settings['onboarding']['step']);

        $this->post(route('onboarding.socials'), ['skip' => '1'])
            ->assertRedirect(route('onboarding.step', ['step' => 6]));

        $store->refresh();
        $this->assertSame('@socialshop', $store->social_accounts['instagram']);
        $this->assertSame('+2348099999999', $store->store_whatsapp);
    }

    public function test_new_tenant_can_sign_up_and_verify_email(): void
    {
        Mail::fake();
        config([
            'services.recaptcha.site_key' => 'test-site-key',
            'services.recaptcha.secret_key' => 'test-secret-key',
            'services.recaptcha.verify_url' => 'https://www.google.com/recaptcha/api/siteverify',
        ]);
        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true]),
        ]);

        $response = $this->post(route('register.store'), [
            'business_name' => 'Bootup Retail',
            'business_category' => 'retail',
            'city' => 'Lagos',
            'country' => 'NG',
            'name' => 'Olu Owner',
            'email' => 'owner@bootup.test',
            'phone' => '+2348012345678',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'g-recaptcha-response' => 'valid-token',
        ]);

        $response->assertRedirect(route('verification.notice'));

        $tenant = Tenant::query()->where('slug', 'bootup-retail')->firstOrFail();
        $user = User::query()->where('email', 'owner@bootup.test')->firstOrFail();
        $subscription = TenantSubscription::query()->with('plan.modules')->where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertSame(TenantStatus::Trialing, $tenant->status);
        $this->assertSame('retail', $tenant->business_type);
        $this->assertSame('Lagos', $tenant->settings['city']);
        $this->assertSame('+2348012345678', $tenant->phone);
        $this->assertSame('owner@bootup.test', $tenant->email);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue(TenantMembership::query()->where('tenant_id', $tenant->id)->where('user_id', $user->id)->exists());
        $this->assertDatabaseHas(Branch::class, [
            'tenant_id' => $tenant->id,
            'name' => 'Head Office',
            'code' => 'HO',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
            'is_primary' => true,
            'status' => 'active',
        ]);
        $this->assertSame(1, Branch::query()->where('tenant_id', $tenant->id)->count());
        $headOffice = Branch::query()->where('tenant_id', $tenant->id)->where('code', 'HO')->firstOrFail();
        $this->assertDatabaseHas(InventoryLocation::class, [
            'tenant_id' => $tenant->id,
            'branch_id' => $headOffice->id,
            'name' => 'Head Office',
            'code' => 'HO',
            'status' => 'active',
        ]);
        $this->assertSame(SubscriptionStatus::Trialing, $subscription->status);
        $this->assertSame('starter', $subscription->plan->slug);
        $this->assertEqualsCanonicalizing(
            ['access', 'analytics', 'business', 'catalog', 'customers', 'finance', 'inventory', 'retail-pos', 'sales', 'storefront', 'subscriptions'],
            $subscription->plan->modules->pluck('slug')->all(),
        );
        $this->assertTrue(FinanceExpenseCategory::query()->where('tenant_id', $tenant->id)->where('code', 'office-supplies')->exists());
        $this->assertTrue(FinanceExpenseCategory::query()->where('tenant_id', $tenant->id)->where('code', 'bank-pos-and-gateway-charges')->exists());

        Mail::assertSent(TenantWelcomeMail::class, fn (TenantWelcomeMail $mail): bool => $mail->tenant->is($tenant) && $mail->user->is($user));

        $verificationCode = $user->email_verification_code;
        $this->assertMatchesRegularExpression('/^\d{6}$/', $verificationCode);
        Mail::assertSent(VerifyEmailMail::class, fn (VerifyEmailMail $mail): bool => $mail->user->is($user)
            && $mail->verificationCode === $verificationCode);

        $this->post(route('login.store'), [
            'email' => 'owner@bootup.test',
            'password' => 'password123',
        ])
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHasErrors('code')
            ->assertSessionHas('verification_email', 'owner@bootup.test');

        $this->assertGuest();

        $this->post(route('verification.verify'), [
            'email' => 'owner@bootup.test',
            'code' => $verificationCode,
        ])->assertRedirect(route('onboarding.index'));

        $this->assertNotNull($user->refresh()->email_verified_at);
        $this->assertNull($user->email_verification_code);

        $this->assertAuthenticatedAs($user);
    }

    public function test_signup_preserves_modules_customized_for_the_starter_plan(): void
    {
        Mail::fake();
        $this->app->detectEnvironment(fn (): string => 'local');
        $this->withSession(['_token' => 'starter-plan-test-token']);
        $starter = Plan::query()->create([
            'name' => 'Starter',
            'slug' => 'starter',
            'monthly_price_minor' => 0,
            'yearly_price_minor' => 0,
            'currency_code' => 'NGN',
            'is_active' => true,
        ]);

        $this->post(route('register.store'), [
            '_token' => 'starter-plan-test-token',
            'business_name' => 'Simple Retail',
            'business_category' => 'retail',
            'city' => 'Lagos',
            'country' => 'NG',
            'name' => 'Simple Owner',
            'email' => 'simple@bootup.test',
            'phone' => '+2348033333333',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('verification.notice'));

        $subscription = TenantSubscription::query()->firstOrFail();

        $this->assertSame($starter->id, $subscription->plan_id);
        $this->assertSame(0, $starter->modules()->count());
    }

    public function test_signup_requires_a_whatsapp_phone_number(): void
    {
        Mail::fake();
        $this->app->detectEnvironment(fn (): string => 'local');
        $this->withSession(['_token' => 'required-phone-test-token']);

        $response = $this->post(route('register.store'), [
            '_token' => 'required-phone-test-token',
            'business_name' => 'No Phone Retail',
            'business_category' => 'retail',
            'city' => 'Lagos',
            'country' => 'NG',
            'name' => 'No Phone Owner',
            'email' => 'no-phone@bootup.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('phone');

        $this->assertFalse(User::query()->where('email', 'no-phone@bootup.test')->exists());
        Mail::assertNothingSent();
    }

    public function test_signup_requires_successful_google_recaptcha_verification(): void
    {
        Mail::fake();
        config([
            'services.recaptcha.site_key' => 'test-site-key',
            'services.recaptcha.secret_key' => 'test-secret-key',
            'services.recaptcha.verify_url' => 'https://www.google.com/recaptcha/api/siteverify',
        ]);
        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response(['success' => false]),
        ]);

        $this->post(route('register.store'), [
            'business_name' => 'Bot Retail',
            'business_category' => 'retail',
            'city' => 'Lagos',
            'country' => 'NG',
            'name' => 'Bot Owner',
            'email' => 'bot@bootup.test',
            'phone' => '+2348022222222',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'g-recaptcha-response' => 'invalid-token',
        ])
            ->assertSessionHasErrors('g-recaptcha-response');

        $this->assertFalse(User::query()->where('email', 'bot@bootup.test')->exists());
        Mail::assertNothingSent();
    }

    public function test_unverified_user_can_resend_verification_email(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create([
            'email' => 'unverified@bootup.test',
            'email_verification_code' => '111111',
            'email_verification_code_expires_at' => now()->addMinutes(5),
        ]);

        $this->post(route('verification.send'), [
            'email' => 'unverified@bootup.test',
        ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $user->refresh();
        $this->assertNotSame('111111', $user->email_verification_code);
        $this->assertTrue($user->email_verification_code_expires_at->isFuture());
        Mail::assertSent(VerifyEmailMail::class, fn (VerifyEmailMail $mail): bool => $mail->user->is($user)
            && $mail->verificationCode === $user->email_verification_code);
    }
}
