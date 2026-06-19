<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Access\Enums\MembershipStatus;
use Modules\Access\Models\TenantMembership;
use Modules\Business\Models\Branch;
use Modules\Business\Models\OnlineStore;
use Modules\Catalog\Enums\CategoryType;
use Modules\Catalog\Models\ProductCategory;
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
            ->assertSee('value="'.$secondTenant->id.'" selected', false);
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

        $this->actingAs($user)
            ->get(route('admin.business.index', ['tenant' => $tenant->id]).'#online-store')
            ->assertOk()
            ->assertSee('Online Store')
            ->assertSee('Online Store Basics')
            ->assertSee('Description of Store')
            ->assertSee('Banner Image Text')
            ->assertSee('Privacy Policy')
            ->assertSee('Save payment method')
            ->assertSee('Add bank account')
            ->assertSee('Save bank account')
            ->assertSee('Add shipping option')
            ->assertSee('Save shipping option')
            ->assertSee('Add FAQ')
            ->assertSee('Save FAQ item')
            ->assertSee('Pay via Transfer')
            ->assertDontSee('Do not use Paystack')
            ->assertSee('data-self-hosted-paystack-fields', false);

        $this->actingAs($user)
            ->post(route('admin.business.online-store.save'), [
                'tenant_id' => $tenant->id,
                'username' => 'web-shop',
                'store_name' => 'Web Shop Online',
                'description' => 'Curated shoes and accessories for everyday movement.',
                'logo' => UploadedFile::fake()->image('logo.png', 256, 256),
                'hero_image' => UploadedFile::fake()->image('banner.jpg', 1600, 600),
                'address' => '1 Market Road',
                'site_email' => 'store@example.com',
                'store_phone' => '08012345678',
                'store_whatsapp' => '08087654321',
                'hero_image_text' => 'Fresh finds every week',
                'hero_image_description' => 'Shop curated shoes for work and weekends.',
                'hero_image_tag' => 'New arrivals',
                'announcement' => 'Free delivery this week',
                'theme_primary_color' => '#006554',
                'theme_secondary_color' => '#f59e0b',
                'fulfilment_branch_id' => $branch->id,
                'maintenance_mode' => '1',
                'category_ids' => [$category->id],
                'payment_methods' => ['pay_on_delivery', 'bank_account', 'place_order'],
                'paystack_method' => 'storeboot_paystack',
                'bank_accounts' => [
                    ['bank_name' => 'Test Bank', 'account_name' => 'Web Shop', 'account_number' => '1234567890'],
                ],
                'shipping_options' => [
                    ['location' => 'Lagos', 'price' => '1500'],
                ],
                'socials' => [
                    'instagram' => '@webshop',
                    'tiktok' => '@webshop',
                    'facebook' => 'webshop',
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
            ->assertRedirect(route('admin.business.index', ['tenant' => $tenant->id]).'#online-store');

        $store = OnlineStore::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertSame('web-shop', $store->username);
        $this->assertSame('Web Shop Online', $store->store_name);
        $this->assertSame('Curated shoes and accessories for everyday movement.', $store->description);
        $this->assertStringStartsWith("tenants/{$tenant->id}/online-store/logos/", $store->logo_path);
        $this->assertStringStartsWith("tenants/{$tenant->id}/online-store/heroes/", $store->hero_image_path);
        Storage::disk('public')->assertExists($store->logo_path);
        Storage::disk('public')->assertExists($store->hero_image_path);
        $this->assertSame('08087654321', $store->store_whatsapp);
        $this->assertSame('Fresh finds every week', $store->hero_image_text);
        $this->assertSame('Shop curated shoes for work and weekends.', $store->hero_image_description);
        $this->assertSame('New arrivals', $store->hero_image_tag);
        $this->assertSame($branch->id, $store->fulfilment_branch_id);
        $this->assertTrue($store->maintenance_mode);
        $this->assertSame(['pay_on_delivery', 'bank_account', 'place_order', 'storeboot_paystack'], $store->payment_methods);
        $this->assertSame('Test Bank', $store->bank_accounts[0]['bank_name']);
        $this->assertSame('Lagos', $store->shipping_options[0]['location']);
        $this->assertSame('@webshop', $store->social_accounts['instagram']);
        $this->assertSame('About the store', $store->pages['about_us']);
        $this->assertSame('We protect customer data.', $store->pages['privacy_policy']);
        $this->assertSame('Ships within 48 hours.', $store->pages['shipping_information']);
        $this->assertSame('Do you deliver?', $store->faqs[0]['question']);
        $this->assertTrue($store->categories()->whereKey($category->id)->exists());

        $this->actingAs($user)
            ->get(route('admin.business.index', ['tenant' => $tenant->id]).'#online-store')
            ->assertOk()
            ->assertSee('/storage/'.$store->logo_path, false)
            ->assertSee('/storage/'.$store->hero_image_path, false);

        $this->actingAs($user)
            ->post(route('admin.business.online-store.save'), [
                'tenant_id' => $tenant->id,
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
            ->assertRedirect(route('admin.business.index', ['tenant' => $tenant->id]).'#online-store');

        $store->refresh();

        $this->assertSame(['pay_on_delivery', 'self_hosted_paystack'], $store->payment_methods);
        $this->assertFalse(in_array('storeboot_paystack', $store->payment_methods, true));
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
            ->get(route('admin.business.index', ['tenant' => $tenant->id]).'#online-store')
            ->assertOk()
            ->assertSee('pay_on_delivery, bank_account')
            ->assertSee('Pay via Transfer');
    }
}
