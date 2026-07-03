<?php

namespace Tests\Feature;

use App\Mail\TenantWelcomeMail;
use App\Mail\VerifyEmailMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\Access\Models\TenantMembership;
use Modules\Subscriptions\Enums\SubscriptionStatus;
use Modules\Subscriptions\Models\TenantSubscription;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

class TenantSignupTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_tenant_can_sign_up_and_verify_email(): void
    {
        Mail::fake();

        $response = $this->post(route('register.store'), [
            'business_name' => 'Bootup Retail',
            'business_category' => 'retail',
            'city' => 'Lagos',
            'country' => 'NG',
            'name' => 'Olu Owner',
            'email' => 'owner@bootup.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('login'));

        $tenant = Tenant::query()->where('slug', 'bootup-retail')->firstOrFail();
        $user = User::query()->where('email', 'owner@bootup.test')->firstOrFail();
        $subscription = TenantSubscription::query()->with('plan.modules')->where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertSame(TenantStatus::Trialing, $tenant->status);
        $this->assertSame('retail', $tenant->business_type);
        $this->assertSame('Lagos', $tenant->settings['city']);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue(TenantMembership::query()->where('tenant_id', $tenant->id)->where('user_id', $user->id)->exists());
        $this->assertSame(SubscriptionStatus::Trialing, $subscription->status);
        $this->assertGreaterThan(0, $subscription->plan->modules->count());

        Mail::assertSent(TenantWelcomeMail::class, fn (TenantWelcomeMail $mail): bool => $mail->tenant->is($tenant) && $mail->user->is($user));

        $verificationUrl = null;
        Mail::assertSent(VerifyEmailMail::class, function (VerifyEmailMail $mail) use ($user, &$verificationUrl): bool {
            $verificationUrl = $mail->verificationUrl;

            return $mail->user->is($user);
        });

        $this->assertNotNull($verificationUrl);

        $this->post(route('login.store'), [
            'email' => 'owner@bootup.test',
            'password' => 'password123',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email')
            ->assertSessionHas('unverified_email', 'owner@bootup.test');

        $this->assertGuest();

        $this->get($verificationUrl)
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Email verified. You can now sign in.');

        $this->assertNotNull($user->refresh()->email_verified_at);

        $this->post(route('login.store'), [
            'email' => 'owner@bootup.test',
            'password' => 'password123',
        ])->assertRedirect(route('admin.business.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_unverified_user_can_resend_verification_email(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create([
            'email' => 'unverified@bootup.test',
        ]);

        $this->post(route('verification.send'), [
            'email' => 'unverified@bootup.test',
        ])
            ->assertRedirect()
            ->assertSessionHas('status');

        Mail::assertSent(VerifyEmailMail::class, fn (VerifyEmailMail $mail): bool => $mail->user->is($user));
    }
}
