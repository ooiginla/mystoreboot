<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Business\Models\OnlineStore;
use Modules\Storefront\Support\StorefrontUrl;
use Modules\Subscriptions\Enums\SubscriptionStatus;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\TenantSubscription;
use Modules\Tenancy\Contracts\DomainDnsResolver;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Models\TenantDomain;
use Tests\TestCase;

final class CustomDomainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('storefront.base_domain', 'storeboot.test');
        config()->set('storefront.scheme', 'https');
        config()->set('storefront.custom_domain_target', 'custom.storeboot.test');
    }

    public function test_custom_domain_tab_connects_and_normalizes_a_domain(): void
    {
        [$tenant, $store] = $this->storeFixture();
        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($user)
            ->get(route('admin.business.online-store.index', [
                'tenant' => $tenant->id,
                'online_store_section' => 'online-store-domains',
            ]))
            ->assertOk()
            ->assertSee('Custom Domains')
            ->assertSee('acme.storeboot.test')
            ->assertSee('Connect your own domain');

        $this->actingAs($user)
            ->post(route('admin.business.online-store.domains.store'), [
                'tenant_id' => $tenant->id,
                'domain' => 'https://SHOP.Acme.com/catalog?from=storeboot',
            ])
            ->assertRedirectContains('online_store_section=online-store-domains')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tenant_domains', [
            'tenant_id' => $tenant->id,
            'domain' => 'shop.acme.com',
            'domain_type' => TenantDomain::TYPE_CUSTOM,
            'status' => TenantDomain::STATUS_PENDING,
            'is_primary' => false,
        ]);
        $this->assertDatabaseHas('tenant_domains', [
            'tenant_id' => $tenant->id,
            'domain' => 'acme.storeboot.test',
            'domain_type' => TenantDomain::TYPE_STOREBOOT,
            'status' => TenantDomain::STATUS_ACTIVE,
            'is_primary' => true,
        ]);
        $this->assertSame('https://acme.storeboot.test', rtrim(StorefrontUrl::route($store), '/'));
    }

    public function test_reserved_and_duplicate_domains_are_rejected_without_disclosing_the_other_tenant(): void
    {
        [$tenant] = $this->storeFixture('acme', 'Acme');
        [$otherTenant] = $this->storeFixture('other', 'Other');
        $user = User::factory()->create(['is_platform_admin' => true]);
        TenantDomain::query()->create([
            'tenant_id' => $otherTenant->id,
            'domain' => 'claimed.example.com',
            'domain_type' => TenantDomain::TYPE_CUSTOM,
            'status' => TenantDomain::STATUS_PENDING,
        ]);

        $this->actingAs($user)
            ->from(route('admin.business.online-store.index', ['tenant' => $tenant->id]))
            ->post(route('admin.business.online-store.domains.store'), [
                'tenant_id' => $tenant->id,
                'domain' => 'app.storeboot.test',
            ])
            ->assertSessionHasErrors([
                'domain' => 'Storeboot-owned domains cannot be connected as custom domains.',
            ]);

        $this->actingAs($user)
            ->from(route('admin.business.online-store.index', ['tenant' => $tenant->id]))
            ->post(route('admin.business.online-store.domains.store'), [
                'tenant_id' => $tenant->id,
                'domain' => 'claimed.example.com',
            ])
            ->assertSessionHasErrors([
                'domain' => 'This domain is already connected to another Storeboot business.',
            ]);

        TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'domain' => 'first.acme.com',
            'domain_type' => TenantDomain::TYPE_CUSTOM,
            'status' => TenantDomain::STATUS_PENDING,
        ]);
        $this->actingAs($user)
            ->post(route('admin.business.online-store.domains.store'), [
                'tenant_id' => $tenant->id,
                'domain' => 'second.acme.com',
            ])
            ->assertSessionHasErrors([
                'domain' => 'This Storeboot business already has a custom domain. Remove it before connecting another one.',
            ]);
    }

    public function test_starter_plan_cannot_connect_a_custom_domain(): void
    {
        [$tenant] = $this->storeFixture();
        $user = User::factory()->create(['is_platform_admin' => true]);
        $plan = Plan::query()->create([
            'name' => 'Starter',
            'slug' => 'starter',
            'is_active' => true,
        ]);
        TenantSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'billing_interval' => 'monthly',
        ]);

        $this->actingAs($user)
            ->post(route('admin.business.online-store.domains.store'), [
                'tenant_id' => $tenant->id,
                'domain' => 'shop.acme.com',
            ])
            ->assertSessionHasErrors([
                'domain' => 'Custom domains are not available on the Starter plan.',
            ]);
    }

    public function test_failed_dns_verification_is_recorded_and_can_be_retried(): void
    {
        [$tenant] = $this->storeFixture();
        $user = User::factory()->create(['is_platform_admin' => true]);
        $domain = TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'domain' => 'shop.acme.com',
            'domain_type' => TenantDomain::TYPE_CUSTOM,
            'status' => TenantDomain::STATUS_PENDING,
            'verification_token' => 'test-token',
            'ssl_status' => TenantDomain::SSL_PENDING,
        ]);
        $this->app->instance(DomainDnsResolver::class, new class implements DomainDnsResolver
        {
            public function txtRecords(string $hostname): array
            {
                return [];
            }

            public function cnameRecords(string $hostname): array
            {
                return [];
            }

            public function addresses(string $hostname): array
            {
                return [];
            }
        });

        $this->actingAs($user)
            ->post(route('admin.business.online-store.domains.verify', $domain))
            ->assertSessionHasErrors('domain');

        $domain->refresh();
        $this->assertSame(TenantDomain::STATUS_FAILED, $domain->status);
        $this->assertNotNull($domain->last_dns_check_at);
        $this->assertStringContainsString('TXT record was not found', $domain->failure_reason);
    }

    public function test_domain_can_be_verified_activated_made_primary_routed_and_removed(): void
    {
        [$tenant, $store] = $this->storeFixture();
        $user = User::factory()->create(['is_platform_admin' => true]);
        $domain = TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'domain' => 'shop.acme.com',
            'domain_type' => TenantDomain::TYPE_CUSTOM,
            'status' => TenantDomain::STATUS_PENDING,
            'verification_token' => 'test-token',
            'ssl_status' => TenantDomain::SSL_PENDING,
            'is_primary' => false,
        ]);
        $dns = new class implements DomainDnsResolver
        {
            public function txtRecords(string $hostname): array
            {
                return $hostname === '_storeboot.acme.com'
                    ? ['storeboot-verification=test-token']
                    : [];
            }

            public function cnameRecords(string $hostname): array
            {
                return $hostname === 'shop.acme.com' ? ['custom.storeboot.test'] : [];
            }

            public function addresses(string $hostname): array
            {
                return [];
            }
        };
        $this->app->instance(DomainDnsResolver::class, $dns);

        $this->actingAs($user)
            ->post(route('admin.business.online-store.domains.verify', $domain))
            ->assertSessionHasNoErrors();

        $domain->refresh();
        $this->assertSame(TenantDomain::STATUS_VERIFIED, $domain->status);
        $this->assertSame(TenantDomain::SSL_PROVISIONING, $domain->ssl_status);
        $this->assertNotNull($domain->verified_at);

        $this->actingAs($user)
            ->post(route('admin.business.online-store.domains.activate', $domain))
            ->assertSessionHasNoErrors();
        $this->actingAs($user)
            ->post(route('admin.business.online-store.domains.primary', $domain))
            ->assertSessionHasNoErrors();

        $domain->refresh();
        $this->assertTrue($domain->is_primary);
        $this->assertSame(TenantDomain::STATUS_ACTIVE, $domain->status);
        $this->assertSame('https://shop.acme.com', rtrim(StorefrontUrl::route($store), '/'));

        $this->get('https://shop.acme.com/')
            ->assertOk()
            ->assertSee('Acme Store');
        $this->get('https://shop.acme.com/login')->assertNotFound();
        $this->get('https://unknown-example.com/login')->assertNotFound();
        $this->get('https://acme.storeboot.test/login')->assertNotFound();

        $tenant->forceFill(['status' => TenantStatus::Suspended])->save();
        $this->get('https://shop.acme.com/')->assertNotFound();
        $tenant->forceFill(['status' => TenantStatus::Active])->save();
        $this->get('https://acme.storeboot.test/')
            ->assertRedirect('https://shop.acme.com');

        $this->actingAs($user)
            ->delete('http://localhost'.route('admin.business.online-store.domains.destroy', $domain, false))
            ->assertRedirectContains('online_store_section=online-store-domains')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('tenant_domains', ['domain' => 'shop.acme.com']);
        $this->assertDatabaseHas('tenant_domains', [
            'domain' => 'acme.storeboot.test',
            'is_primary' => true,
        ]);
    }

    /** @return array{Tenant, OnlineStore} */
    private function storeFixture(string $subdomain = 'acme', string $name = 'Acme Store'): array
    {
        $tenant = Tenant::query()->create([
            'name' => $name,
            'slug' => $subdomain,
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
        $store = OnlineStore::query()->create([
            'tenant_id' => $tenant->id,
            'username' => $subdomain,
            'subdomain' => $subdomain,
            'store_name' => $name,
            'theme_primary_color' => '#005f73',
            'theme_secondary_color' => '#ee9b00',
            'payment_methods' => [],
            'pages' => [],
            'faqs' => [],
            'is_active' => true,
            'maintenance_mode' => false,
        ]);

        return [$tenant, $store];
    }
}
