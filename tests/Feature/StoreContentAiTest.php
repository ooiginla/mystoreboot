<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

class StoreContentAiTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_a_plain_description_via_openai(): void
    {
        config(['services.ai.provider' => 'openai', 'services.openai.api_key' => 'sk-test', 'services.openai.base_url' => 'https://api.openai.com']);
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'Fresh groceries and household essentials delivered fast across Lagos.']]],
            ]),
        ]);

        [$user, $tenant] = $this->fixture();

        $this->actingAs($user)
            ->postJson(route('admin.business.online-store.ai-content'), [
                'tenant_id' => $tenant->id,
                'field' => 'description',
                'prompt' => 'we sell groceries',
            ])
            ->assertOk()
            ->assertJsonPath('format', 'plain')
            ->assertJsonPath('content', 'Fresh groceries and household essentials delivered fast across Lagos.');
    }

    public function test_generates_page_html_via_anthropic_and_strips_code_fences(): void
    {
        config(['services.ai.provider' => 'anthropic', 'services.anthropic.api_key' => 'sk-test', 'services.anthropic.base_url' => 'https://api.anthropic.com']);
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => "```html\n<h2>About Us</h2><p>We are a family store.</p>\n```"]],
            ]),
        ]);

        [$user, $tenant] = $this->fixture();

        $response = $this->actingAs($user)
            ->postJson(route('admin.business.online-store.ai-content'), [
                'tenant_id' => $tenant->id,
                'field' => 'about_us',
                'prompt' => 'family run since 2010',
            ])
            ->assertOk()
            ->assertJsonPath('format', 'html');

        $content = $response->json('content');
        $this->assertStringContainsString('<h2>About Us</h2>', $content);
        $this->assertStringNotContainsString('```', $content);
    }

    public function test_returns_422_when_ai_is_not_configured(): void
    {
        config(['services.ai.provider' => 'anthropic', 'services.anthropic.api_key' => null, 'services.openai.api_key' => null]);
        Http::fake();

        [$user, $tenant] = $this->fixture();

        $this->actingAs($user)
            ->postJson(route('admin.business.online-store.ai-content'), [
                'tenant_id' => $tenant->id,
                'field' => 'privacy_policy',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m): bool => str_contains((string) $m, 'not configured'));

        Http::assertNothingSent();
    }

    public function test_rejects_an_unknown_field(): void
    {
        [$user, $tenant] = $this->fixture();

        $this->actingAs($user)
            ->postJson(route('admin.business.online-store.ai-content'), [
                'tenant_id' => $tenant->id,
                'field' => 'hacker_field',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('field');
    }

    /**
     * @return array{User, Tenant}
     */
    private function fixture(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'AI Copy Shop',
            'slug' => 'ai-copy-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
        $user = User::factory()->create(['is_platform_admin' => true]);

        return [$user, $tenant];
    }
}
