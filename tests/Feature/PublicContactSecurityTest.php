<?php

namespace Tests\Feature;

use App\Mail\ContactMessageMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicContactSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.recaptcha.site_key' => null,
            'services.recaptcha.secret_key' => null,
        ]);
    }

    public function test_contact_message_uses_fixed_storeboot_from_and_to_addresses(): void
    {
        Mail::fake();

        $this->post(route('contact.submit'), [
            'name' => 'Ada Visitor',
            'email' => 'ada@example.com',
            'from' => 'attacker@storeboot.com',
            'to' => 'victim@example.com',
            'subject' => 'A genuine question',
            'message' => 'Please tell me more about Storeboot.',
        ])->assertRedirect(route('contact'));

        Mail::assertSent(ContactMessageMail::class, function (ContactMessageMail $mail): bool {
            return $mail->hasFrom('contact@storeboot.com', 'Storeboot Website')
                && $mail->hasTo('support@storeboot.com')
                && $mail->hasReplyTo('ada@example.com', 'Ada Visitor');
        });
    }

    public function test_contact_honeypot_discards_submission_without_sending_mail(): void
    {
        Mail::fake();

        $this->post(route('contact.submit'), [
            'company_website' => 'https://spam.example',
            'name' => 'Spam Bot',
            'email' => 'bot@example.com',
            'subject' => 'Spam',
            'message' => 'Spam content',
        ])->assertRedirect(route('contact'));

        Mail::assertNothingSent();
    }

    public function test_contact_requires_successful_recaptcha_when_configured(): void
    {
        Mail::fake();
        config([
            'services.recaptcha.site_key' => 'public-test-site-key',
            'services.recaptcha.secret_key' => 'private-test-secret-key',
            'services.recaptcha.verify_url' => 'https://www.google.com/recaptcha/api/siteverify',
        ]);
        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response(['success' => false]),
        ]);

        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('public-test-site-key')
            ->assertDontSee('private-test-secret-key')
            ->assertSee('name="company_website"', false);

        $this->from(route('contact'))->post(route('contact.submit'), [
            'name' => 'Ada Visitor',
            'email' => 'ada@example.com',
            'subject' => 'Question',
            'message' => 'Hello',
            'g-recaptcha-response' => 'invalid-token',
        ])
            ->assertRedirect(route('contact'))
            ->assertSessionHasErrors('g-recaptcha-response');

        Mail::assertNothingSent();
    }

    public function test_contact_endpoint_is_rate_limited(): void
    {
        Mail::fake();
        $payload = [
            'company_website' => 'https://spam.example',
            'name' => 'Spam Bot',
            'email' => 'bot@example.com',
            'message' => 'Spam content',
        ];

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('contact.submit'), $payload)->assertRedirect();
        }

        $this->post(route('contact.submit'), $payload)->assertTooManyRequests();
        Mail::assertNothingSent();
    }
}
