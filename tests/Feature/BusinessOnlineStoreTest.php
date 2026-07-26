<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Access\Enums\MembershipStatus;
use Modules\Access\Models\Role;
use Modules\Access\Models\TenantMembership;
use Modules\Business\Models\Branch;
use Modules\Business\Models\OnlineStore;
use Modules\Catalog\Enums\CategoryType;
use Modules\Catalog\Models\ProductCategory;
use Modules\Finance\Models\FinanceAccount;
use Modules\Subscriptions\Enums\SubscriptionStatus;
use Modules\Subscriptions\Models\Module;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\TenantModuleEntitlement;
use Modules\Subscriptions\Models\TenantSubscription;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

class BusinessOnlineStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_setup_shows_tenant_switcher_for_user_with_multiple_organizations(): void
    {
        $firstTenant = Tenant::query()->create([
            'name' => 'First Shop',
            'slug' => 'first-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
        $secondTenant = Tenant::query()->create([
            'name' => 'Second Shop',
            'slug' => 'second-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
        $user = User::factory()->create(['is_platform_admin' => false]);

        TenantMembership::query()->create([
            'tenant_id' => $firstTenant->id,
            'user_id' => $user->id,
            'status' => MembershipStatus::Active,
        ]);
        TenantMembership::query()->create([
            'tenant_id' => $secondTenant->id,
            'user_id' => $user->id,
            'status' => MembershipStatus::Active,
        ]);

        $this->actingAs($user)
            ->get(route('admin.business.index', ['tenant' => $secondTenant->id]))
            ->assertOk()
            ->assertSee('name="tenant"', false)
            ->assertSee('First Shop')
            ->assertSee('Second Shop')
            ->assertSee('value="'.$secondTenant->id.'" selected', false)
            ->assertSee('Registration number')
            ->assertSee('Tax identifier')
            ->assertSee('Subscription plan')
            ->assertSee('Opening days')
            ->assertSee('Payment methods')
            ->assertSee('Estimated cost COGS')
            ->assertDontSee('Terms URL')
            ->assertDontSee('Meta title')
            ->assertDontSee('Meta description');
    }

    public function test_online_store_setup_can_be_saved_from_business_setup(): void
    {
        Storage::fake('public');

        $tenant = Tenant::query()->create([
            'name' => 'Web Shop',
            'slug' => 'web-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
            'settings' => [
                'bank_details' => [
                    ['bank_name' => 'Test Bank', 'account_name' => 'Web Shop', 'account_number' => '1234567890', 'status' => 'active'],
                    ['bank_name' => 'Inactive Bank', 'account_name' => 'Web Shop', 'account_number' => '0000000000', 'status' => 'inactive'],
                ],
            ],
        ]);
        $branch = Branch::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'status' => 'active',
            'is_primary' => true,
        ]);
        $category = ProductCategory::query()->create([
            'tenant_id' => $tenant->id,
            'category_type' => CategoryType::Product->value,
            'name' => 'Shoes',
            'slug' => 'shoes',
            'status' => 'active',
        ]);
        $user = User::factory()->create(['is_platform_admin' => true]);
        $bankAccountKey = sha1('Test Bank|Web Shop|1234567890');

        $this->actingAs($user)
            ->get(route('admin.business.online-store.index', ['tenant' => $tenant->id]).'#online-store')
            ->assertOk()
            ->assertSee('Online Store')
            ->assertSee('Online Store Basics')
            ->assertSee('Contact')
            ->assertSee('<select name="country">', false)
            ->assertSee('<option value="Nigeria" selected>Nigeria</option>', false)
            ->assertSee('<option value="Ghana"', false)
            ->assertSee('<option value="United States"', false)
            ->assertSee('Description of Store')
            ->assertSee('Banner Image Text')
            ->assertSee('Privacy Policy')
            ->assertSee('Save pages')
            ->assertSee('By accessing or using Web Shop')
            ->assertSee('We want you to be satisfied with your purchase')
            ->assertSee('respects your privacy')
            ->assertSee('offers delivery to the locations')
            ->assertSee('Save FAQ')
            ->assertSee('How do I place an order?')
            ->assertSee('What payment methods do you accept?')
            ->assertSee('How long will delivery take?')
            ->assertSee('Can I change or cancel my order?')
            ->assertSee('How do I request a return or refund?')
            ->assertSee('Save payment method')
            ->assertSee('Bank account')
            ->assertSee('Manage bank accounts from Business Profile.')
            ->assertSee('Test Bank')
            ->assertDontSee('Save bank account')
            ->assertSee('Add shipping option')
            ->assertSee('Save shipping option')
            ->assertSee('Add FAQ')
            ->assertSee('Save FAQ item')
            ->assertSee('Theme')
            ->assertSee('Section A: Menu Setup')
            ->assertSee('Section B: Slides')
            ->assertSee('No Slides Added Yet.')
            ->assertSee('Add Slide')
            ->assertSee('Pay via Transfer')
            ->assertDontSee('Do not use Paystack')
            ->assertSee('data-self-hosted-paystack-fields', false);

        $this->actingAs($user)
            ->post(route('admin.business.online-store.save'), [
                'tenant_id' => $tenant->id,
                'online_store_section' => 'online-store-theme',
                'username' => 'web-shop',
                'store_name' => 'Web Shop Online',
                'description' => 'Curated shoes and accessories for everyday movement.',
                'logo' => UploadedFile::fake()->image('logo.png', 256, 256),
                'hero_image' => UploadedFile::fake()->image('banner.jpg', 1600, 600),
                'address' => '1 Market Road',
                'city' => 'Ikeja',
                'state' => 'Lagos',
                'country' => 'Nigeria',
                'site_email' => 'store@example.com',
                'store_phone' => '08012345678',
                'store_whatsapp' => '08087654321',
                'hero_image_text' => 'Fresh finds every week',
                'hero_image_description' => 'Shop curated shoes for work and weekends.',
                'hero_image_tag' => 'New arrivals',
                'slides' => [
                    [
                        'image' => UploadedFile::fake()->image('slide-one.jpg', 1600, 600),
                        'hero_image_tag' => 'Slide tag',
                        'hero_image_text' => 'Slide headline',
                        'hero_image_description' => 'Slide description',
                    ],
                    [
                        'image' => UploadedFile::fake()->image('slide-two.jpg', 1600, 600),
                        'hero_image_tag' => 'Second slide',
                        'hero_image_text' => 'Second headline',
                        'hero_image_description' => 'Second description',
                    ],
                ],
                'announcement' => 'Free delivery this week',
                'theme_primary_color' => '#006554',
                'theme_secondary_color' => '#f59e0b',
                'fulfilment_branch_id' => $branch->id,
                'maintenance_mode' => '1',
                'category_ids' => [$category->id],
                'payment_methods' => ['pay_on_delivery', 'bank_account', 'place_order'],
                'paystack_method' => 'storeboot_paystack',
                'bank_account_key' => $bankAccountKey,
                'shipping_options' => [
                    ['location' => 'Lagos', 'description' => '3-5 days', 'price' => '1500'],
                ],
                'socials' => [
                    'instagram' => '@webshop',
                    'tiktok' => '@webshop',
                    'facebook' => 'webshop',
                    'twitter' => '@webshopx',
                    'youtube' => '@webshop',
                    'whatsapp' => '08012345678',
                ],
                'pages' => [
                    'about_us' => 'About the store',
                    'terms_of_use' => 'Terms',
                    'return_policy' => 'Returns accepted within 7 days',
                    'privacy_policy' => 'We protect customer data.',
                    'shipping_information' => 'Ships within 48 hours.',
                ],
                'faqs' => [
                    ['question' => 'Do you deliver?', 'answer' => 'Yes.'],
                ],
            ])
            ->assertRedirect(route('admin.business.online-store.index', ['tenant' => $tenant->id, 'online_store_section' => 'online-store-theme']).'#online-store');

        $store = OnlineStore::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $storefrontUrl = route('storefront.storefront.store.home', $store);

        $this->actingAs($user)
            ->get(route('admin.business.online-store.index', ['tenant' => $tenant->id]).'#online-store')
            ->assertOk()
            ->assertSee('Your store URL')
            ->assertSee($storefrontUrl, false)
            ->assertSee('data-share-store-url="'.$storefrontUrl.'"', false)
            ->assertSee('Visit Store');

        $this->assertSame('web-shop', $store->username);
        $this->assertSame('Web Shop Online', $store->store_name);
        $this->assertSame('Curated shoes and accessories for everyday movement.', $store->description);
        $this->assertStringStartsWith("tenants/{$tenant->id}/online-store/logos/", $store->logo_path);
        $this->assertStringStartsWith("tenants/{$tenant->id}/online-store/heroes/", $store->hero_image_path);
        Storage::disk('public')->assertExists($store->logo_path);
        Storage::disk('public')->assertExists($store->hero_image_path);
        $this->assertSame('Ikeja', $store->city);
        $this->assertSame('Lagos', $store->state);
        $this->assertSame('Nigeria', $store->country);
        $this->assertSame('08087654321', $store->store_whatsapp);
        $this->assertSame('Slide headline', $store->hero_image_text);
        $this->assertSame('Slide description', $store->hero_image_description);
        $this->assertSame('Slide tag', $store->hero_image_tag);
        $this->assertCount(2, $store->slides);
        $this->assertStringStartsWith("tenants/{$tenant->id}/online-store/heroes/", $store->slides[0]['image_path']);
        $this->assertSame('Second headline', $store->slides[1]['hero_image_text']);
        Storage::disk('public')->assertExists($store->slides[0]['image_path']);
        Storage::disk('public')->assertExists($store->slides[1]['image_path']);
        $this->assertSame($branch->id, $store->fulfilment_branch_id);
        $this->assertTrue($store->maintenance_mode);
        $this->assertSame(['pay_on_delivery', 'bank_account', 'place_order', 'storeboot_paystack'], $store->payment_methods);
        $this->assertSame($bankAccountKey, $store->payment_settings['bank_account_key']);
        $this->assertSame('Test Bank', $store->bank_accounts[0]['bank_name']);
        $this->assertSame('Lagos', $store->shipping_options[0]['location']);
        $this->assertSame('3-5 days', $store->shipping_options[0]['description']);
        $this->assertSame('@webshop', $store->social_accounts['instagram']);
        $this->assertSame('@webshopx', $store->social_accounts['twitter']);
        $this->assertSame('@webshop', $store->social_accounts['youtube']);
        $this->assertSame('About the store', $store->pages['about_us']);
        $this->assertSame('We protect customer data.', $store->pages['privacy_policy']);
        $this->assertSame('Ships within 48 hours.', $store->pages['shipping_information']);
        $this->assertSame('Do you deliver?', $store->faqs[0]['question']);
        $this->assertTrue($store->categories()->whereKey($category->id)->exists());

        $this->actingAs($user)
            ->get(route('admin.business.online-store.index', ['tenant' => $tenant->id]).'#online-store')
            ->assertOk()
            ->assertSee('/storage/'.$store->logo_path, false)
            ->assertSee('/storage/'.$store->hero_image_path, false);

        $this->actingAs($user)
            ->post(route('admin.business.online-store.save'), [
                'tenant_id' => $tenant->id,
                'online_store_section' => 'online-store-payments',
                'username' => 'web-shop',
                'store_name' => 'Web Shop Online',
                'theme_primary_color' => '#006554',
                'theme_secondary_color' => '#f59e0b',
                'payment_methods' => ['pay_on_delivery', 'storeboot_paystack', 'self_hosted_paystack'],
                'paystack_method' => 'self_hosted_paystack',
                'paystack' => [
                    'public_key' => 'pk_live_test',
                    'private_key' => 'sk_live_test',
                ],
            ])
            ->assertRedirect(route('admin.business.online-store.index', ['tenant' => $tenant->id, 'online_store_section' => 'online-store-payments']).'#online-store');

        $store->refresh();

        $this->assertSame(['pay_on_delivery', 'self_hosted_paystack'], $store->payment_methods);
        $this->assertFalse(in_array('storeboot_paystack', $store->payment_methods, true));

        $response = $this->actingAs($user)
            ->get(route('admin.business.online-store.index', ['tenant' => $tenant->id]).'#online-store')
            ->assertOk()
            ->assertSee('Settlement Bank Name');
        $this->assertMatchesRegularExpression('/data-paystack-settlement-bank-fields\s+hidden/', $response->getContent());
    }

    public function test_new_online_store_gets_default_policies_and_removable_generic_faqs(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Default Content Shop',
            'slug' => 'default-content-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
            'email' => 'hello@example.com',
        ]);
        $user = User::factory()->create(['is_platform_admin' => true]);
        $payload = [
            'tenant_id' => $tenant->id,
            'online_store_section' => 'online-store-basics',
            'username' => 'default-content-shop',
            'store_name' => 'Default Content Shop',
            'theme_primary_color' => '#006554',
            'theme_secondary_color' => '#f59e0b',
            'payment_methods' => [],
            'paystack_method' => 'none',
        ];

        $this->actingAs($user)
            ->post(route('admin.business.online-store.save'), $payload)
            ->assertRedirect(route('admin.business.online-store.index', [
                'tenant' => $tenant->id,
                'online_store_section' => 'online-store-basics',
            ]).'#online-store');

        $store = OnlineStore::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertStringContainsString('By accessing or using Default Content Shop', $store->pages['terms_of_use']);
        $this->assertStringContainsString('We want you to be satisfied with your purchase', $store->pages['return_policy']);
        $this->assertStringContainsString('respects your privacy', $store->pages['privacy_policy']);
        $this->assertStringContainsString('offers delivery to the locations', $store->pages['shipping_information']);
        $this->assertCount(5, $store->faqs);

        $this->actingAs($user)
            ->post(route('admin.business.online-store.save'), $payload + ['faqs' => []])
            ->assertRedirect();

        $this->assertSame([], $store->refresh()->faqs);
    }

    public function test_superadmin_can_manage_tenant_subscriptions_from_business_setup(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Subscription Shop',
            'slug' => 'subscription-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
        $starterPlan = Plan::query()->create([
            'name' => 'Starter',
            'slug' => 'starter',
            'sort_order' => 1,
            'monthly_price_minor' => 100000,
            'yearly_price_minor' => 1000000,
            'currency_code' => 'NGN',
            'is_active' => true,
        ]);
        $growthPlan = Plan::query()->create([
            'name' => 'Growth',
            'slug' => 'growth',
            'sort_order' => 2,
            'monthly_price_minor' => 200000,
            'yearly_price_minor' => 2000000,
            'currency_code' => 'NGN',
            'is_active' => true,
        ]);
        $superadmin = User::factory()->create(['is_platform_admin' => true]);
        $tenantUser = User::factory()->create(['is_platform_admin' => false]);

        TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $tenantUser->id,
            'status' => MembershipStatus::Active,
        ]);

        $this->actingAs($superadmin)
            ->get(route('admin.business.index', ['tenant' => $tenant->id]).'#subscriptions')
            ->assertOk()
            ->assertSee('Tenant subscriptions')
            ->assertSee('Add subscription');

        $this->actingAs($superadmin)
            ->post(route('admin.business.subscriptions.store'), [
                'tenant_id' => $tenant->id,
                'plan_id' => $starterPlan->id,
                'status' => SubscriptionStatus::Active->value,
                'billing_interval' => 'monthly',
                'trial_ends_at' => null,
                'current_period_starts_at' => '2026-06-01',
                'current_period_ends_at' => '2026-06-30',
                'cancelled_at' => null,
            ])
            ->assertRedirect(route('admin.business.index', ['tenant' => $tenant->id]).'#subscriptions');

        $subscription = TenantSubscription::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame($starterPlan->id, $subscription->plan_id);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame('monthly', $subscription->billing_interval);

        $this->actingAs($superadmin)
            ->get(route('admin.business.index', ['tenant' => $tenant->id]).'#subscriptions')
            ->assertOk()
            ->assertSee('Starter')
            ->assertSee('Edit');

        $this->actingAs($superadmin)
            ->put(route('admin.business.subscriptions.update', $subscription), [
                'tenant_id' => $tenant->id,
                'plan_id' => $growthPlan->id,
                'status' => SubscriptionStatus::GracePeriod->value,
                'billing_interval' => 'yearly',
                'trial_ends_at' => null,
                'current_period_starts_at' => '2026-07-01',
                'current_period_ends_at' => '2027-06-30',
                'cancelled_at' => null,
            ])
            ->assertRedirect(route('admin.business.index', ['tenant' => $tenant->id]).'#subscriptions');

        $subscription->refresh();
        $this->assertSame($growthPlan->id, $subscription->plan_id);
        $this->assertSame(SubscriptionStatus::GracePeriod, $subscription->status);
        $this->assertSame('yearly', $subscription->billing_interval);

        $this->actingAs($tenantUser)
            ->get(route('admin.business.index', ['tenant' => $tenant->id]).'#subscriptions')
            ->assertOk()
            ->assertSee('Tenant subscriptions')
            ->assertSee('Growth')
            ->assertDontSee('Add subscription')
            ->assertDontSee('Save subscription');

        $this->actingAs($tenantUser)
            ->post(route('admin.business.subscriptions.store'), [
                'tenant_id' => $tenant->id,
                'plan_id' => $starterPlan->id,
                'status' => SubscriptionStatus::Active->value,
                'billing_interval' => 'monthly',
            ])
            ->assertForbidden();
    }

    public function test_authorized_administrators_can_toggle_non_core_tenant_modules_and_sidebar_follows_access(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Modular Shop',
            'slug' => 'modular-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
        $plan = Plan::query()->create([
            'name' => 'Modular Plan',
            'slug' => 'modular-plan',
            'sort_order' => 1,
            'monthly_price_minor' => 0,
            'yearly_price_minor' => 0,
            'currency_code' => 'NGN',
            'is_active' => true,
        ]);
        $core = Module::query()->create([
            'name' => 'Business Setup',
            'slug' => 'business',
            'is_core' => true,
            'is_active' => true,
        ]);
        $inventory = Module::query()->create([
            'name' => 'Inventory Management',
            'slug' => 'inventory',
            'is_core' => false,
            'is_active' => true,
        ]);
        $sales = Module::query()->create([
            'name' => 'Sales & Invoicing',
            'slug' => 'sales',
            'is_core' => false,
            'is_active' => true,
        ]);
        $retailPos = Module::query()->where('slug', 'retail-pos')->firstOrFail();
        $plan->modules()->sync([
            $core->id => ['is_enabled' => true],
            $inventory->id => ['is_enabled' => true],
            $sales->id => ['is_enabled' => true],
            $retailPos->id => ['is_enabled' => true],
        ]);
        $subscription = TenantSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'billing_interval' => 'monthly',
        ]);
        $superadmin = User::factory()->create(['is_platform_admin' => true]);
        $administratorRole = Role::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Administrator',
            'slug' => 'administrator',
            'is_system' => true,
        ]);
        $staffRole = Role::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Staff',
            'slug' => 'staff',
            'is_system' => false,
        ]);
        $tenantAdmin = User::factory()->create(['is_platform_admin' => false]);
        $staff = User::factory()->create(['is_platform_admin' => false]);
        TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $tenantAdmin->id,
            'role_id' => $administratorRole->id,
            'status' => MembershipStatus::Active,
        ]);
        TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $staff->id,
            'role_id' => $staffRole->id,
            'status' => MembershipStatus::Active,
        ]);

        $this->actingAs($superadmin)
            ->get(route('admin.business.index', ['tenant' => $tenant->id]).'#subscriptions')
            ->assertOk()
            ->assertSee('Module access')
            ->assertSee('Inventory Management')
            ->assertSee('Core')
            ->assertSee('Inventory &amp; Stock', false)
            ->assertSee('Retail POS')
            ->assertSee('Record Sale');

        $this->actingAs($superadmin)
            ->put(route('admin.business.subscriptions.modules.update', [$subscription, $inventory]), [
                'tenant_id' => $tenant->id,
                'enabled' => false,
            ])
            ->assertRedirect(route('admin.business.index', ['tenant' => $tenant->id]).'#subscriptions');

        $this->assertDatabaseHas('tenant_module_entitlements', [
            'tenant_id' => $tenant->id,
            'module_id' => $inventory->id,
            'is_enabled' => false,
        ]);

        $this->actingAs($superadmin)
            ->put(route('admin.business.subscriptions.modules.update', [$subscription, $retailPos]), [
                'tenant_id' => $tenant->id,
                'enabled' => false,
            ])
            ->assertRedirect(route('admin.business.index', ['tenant' => $tenant->id]).'#subscriptions');

        $response = $this->actingAs($tenantAdmin)
            ->get(route('admin.business.index', ['tenant' => $tenant->id]).'#subscriptions')
            ->assertOk()
            ->assertDontSee('Inventory &amp; Stock', false)
            ->assertDontSee('href="'.route('admin.sales.retail-pos', ['tenant' => $tenant->id]).'"', false)
            ->assertSee('Record Sale')
            ->assertSee('Business setup');
        $this->assertStringContainsString(
            route('admin.business.subscriptions.modules.update', [$subscription, $sales]),
            $response->getContent(),
        );

        $this->actingAs($tenantAdmin)
            ->put(route('admin.business.subscriptions.modules.update', [$subscription, $sales]), [
                'tenant_id' => $tenant->id,
                'enabled' => false,
            ])
            ->assertRedirect(route('admin.business.index', ['tenant' => $tenant->id]).'#subscriptions');

        $this->actingAs($tenantAdmin)
            ->put(route('admin.business.subscriptions.modules.update', [$subscription, $core]), [
                'tenant_id' => $tenant->id,
                'enabled' => false,
            ])
            ->assertForbidden();

        $this->actingAs($staff)
            ->put(route('admin.business.subscriptions.modules.update', [$subscription, $inventory]), [
                'tenant_id' => $tenant->id,
                'enabled' => true,
            ])
            ->assertForbidden();

        $this->assertFalse(TenantModuleEntitlement::query()
            ->where('tenant_id', $tenant->id)
            ->where('module_id', $core->id)
            ->exists());
    }

    public function test_business_bank_details_create_asset_accounts_with_codes(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Bank Asset Shop',
            'slug' => 'bank-asset-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
            'default_tax_rate' => 0,
        ]);
        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($user)
            ->post(route('admin.business.profile.save'), [
                'tenant_id' => $tenant->id,
                'name' => 'Bank Asset Shop',
                'business_type' => 'retail',
                'country_code' => 'NG',
                'timezone' => 'Africa/Lagos',
                'currency_code' => 'NGN',
                'default_tax_rate' => '0',
                'payment_methods' => 'Cash, Bank transfer',
                'bank_details' => [
                    ['bank_name' => 'Asset Bank', 'account_name' => 'Bank Asset Shop', 'account_number' => '1234567890', 'status' => 'active'],
                ],
            ])
            ->assertRedirect(route('admin.business.index', ['tenant' => $tenant->id]).'#business-profile');

        $tenant->refresh();
        $bankAccount = $tenant->settings['bank_details'][0];
        $this->assertSame('BANK-1001', $bankAccount['asset_account_code']);

        $financeAccount = FinanceAccount::query()
            ->where('tenant_id', $tenant->id)
            ->where('code', $bankAccount['asset_account_code'])
            ->firstOrFail();

        $this->assertSame('asset', $financeAccount->type);
        $this->assertSame('Current Assets', $financeAccount->category);
        $this->assertSame('Business bank account used to hold cash and receive payments.', $financeAccount->description);
        $this->assertSame('debit', $financeAccount->normal_balance);
        $this->assertSame('Bank Asset Shop - Asset Bank (1234567890)', $financeAccount->name);
        $this->assertTrue($financeAccount->is_active);
    }

    public function test_business_setup_renders_when_online_store_payment_old_input_is_array(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Array Payment Shop',
            'slug' => 'array-payment-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->withSession([
            '_old_input' => [
                'payment_methods' => ['pay_on_delivery', 'bank_account'],
            ],
        ])
            ->actingAs($user)
            ->get(route('admin.business.online-store.index', ['tenant' => $tenant->id]).'#online-store')
            ->assertOk()
            ->assertDontSee('pay_on_delivery, bank_account')
            ->assertSee('Pay On Delivery')
            ->assertSee('Pay via Transfer');
    }
}
