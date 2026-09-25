<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Modules\Reseller\Enums\PricingMode;
use Modules\Reseller\Enums\ScanStatus;
use Modules\Reseller\Jobs\RecoverSupplierProductsJob;
use Modules\Reseller\Models\ResellerProduct;
use Modules\Reseller\Models\ResellerSetting;
use Modules\Reseller\Models\ResellerSupplier;
use Modules\Subscriptions\Models\Module;
use Modules\Subscriptions\Models\TenantModuleEntitlement;
use Modules\Tenancy\Enums\CommerceMode;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class ResellerAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_can_activate_reseller_mode_manage_supplier_and_pricing(): void
    {
        $user = User::factory()->create(['is_platform_admin' => true]);
        $tenant = $this->tenant('reseller-admin');
        $this->setResellerModule($tenant, true);

        $this->actingAs($user)
            ->get(route('admin.reseller.index', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('Activate reseller mode');

        $this->actingAs($user)
            ->put(route('admin.reseller.mode.update'), [
                'tenant_id' => $tenant->id,
                'commerce_mode' => CommerceMode::Reseller->value,
            ])
            ->assertRedirect(route('admin.reseller.index', ['tenant' => $tenant->id]));

        $this->assertTrue($tenant->refresh()->isReseller());
        $this->assertDatabaseHas('reseller_settings', ['tenant_id' => $tenant->id]);

        $this->actingAs($user)
            ->get(route('admin.catalog.index', ['tenant' => $tenant->id]))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('admin.reseller.suppliers.store'), [
                'tenant_id' => $tenant->id,
                'name' => 'Supplier One',
                'website_url' => 'https://supplier-one.example/',
                'contact_email' => 'orders@supplier-one.example',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $supplier = ResellerSupplier::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('https://supplier-one.example', $supplier->website_url);

        Queue::fake();
        $this->actingAs($user)
            ->post(route('admin.reseller.suppliers.scan.schedule', ['supplier' => $supplier, 'tenant' => $tenant->id]))
            ->assertRedirect()
            ->assertSessionHas('status', "A product recovery scan for {$supplier->name} was scheduled and queued.");
        Queue::assertPushed(RecoverSupplierProductsJob::class, fn (RecoverSupplierProductsJob $job): bool => $job->supplierId === $supplier->id);

        Http::fake(['https://supplier-one.example' => Http::response('<html><body>No products yet</body></html>')]);
        $this->actingAs($user)
            ->post(route('admin.reseller.suppliers.scan', ['supplier' => $supplier, 'tenant' => $tenant->id]))
            ->assertRedirect()
            ->assertSessionHas('status', 'Scan complete for Supplier One: 0 created, 0 updated, 1 pages checked, 0 errors.');
        $this->assertSame(ScanStatus::Successful, $supplier->refresh()->last_scan_status);

        ResellerProduct::query()->create([
            'tenant_id' => $tenant->id,
            'supplier_id' => $supplier->id,
            'product_url' => 'https://supplier-one.example/products/shirt',
            'name' => 'Shirt',
            'source_price_minor' => 1000000,
            'selling_price_minor' => 1100000,
            'currency_code' => 'NGN',
            'source_store_name' => 'Supplier One',
            'last_checked_at' => now(),
        ]);

        $this->actingAs($user)
            ->put(route('admin.reseller.settings.update'), [
                'tenant_id' => $tenant->id,
                'pricing_mode' => PricingMode::Combined->value,
                'percentage_markup' => '15.00',
                'fixed_markup' => '2000.00',
                'auto_publish_products' => '1',
                'scan_frequency' => 'twice_daily',
                'show_source_store' => '1',
                'stale_after_hours' => 24,
                'hide_after_missing_scans' => 3,
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Reseller settings saved. 1 products repriced.');

        $settings = ResellerSetting::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame(PricingMode::Combined, $settings->pricing_mode);
        $this->assertSame(1500, $settings->percentage_markup_basis_points);
        $this->assertSame(200000, $settings->fixed_markup_minor);
        $this->assertSame(1350000, ResellerProduct::query()->firstOrFail()->selling_price_minor);

        $this->actingAs($user)
            ->get(route('admin.reseller.suppliers.index', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('Supplier One')
            ->assertSee('Add supplier website')
            ->assertSee('add-supplier-website-dialog')
            ->assertSee('Next run')
            ->assertSee('Last run')
            ->assertSee('Schedule scan')
            ->assertSee('Scan now')
            ->assertSee('reseller-scan-overlay')
            ->assertSee('data-scan-now-form', false);

        $supplier->update(['last_scan_status' => 'running']);

        $this->actingAs($user)
            ->get(route('admin.reseller.suppliers.index', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('Running now');

        $this->actingAs($user)
            ->get(route('admin.reseller.products.index', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('₦13,500.00')
            ->assertSee('Search product or supplier')
            ->assertSee('Publish Shirt')
            ->assertSee('Exclude Shirt');

        $this->actingAs($user)
            ->get(route('admin.reseller.products.index', ['tenant' => $tenant->id, 'product_search' => 'Shirt']))
            ->assertOk()
            ->assertSee('Supplier One');

        $this->actingAs($user)
            ->get(route('admin.reseller.products.index', ['tenant' => $tenant->id, 'product_search' => 'Supplier One']))
            ->assertOk()
            ->assertSee('Shirt');

        $this->actingAs($user)
            ->get(route('admin.reseller.products.index', ['tenant' => $tenant->id, 'product_search' => 'Not Found']))
            ->assertOk()
            ->assertDontSee('₦13,500.00')
            ->assertSee('No sourced products match your search.');

        foreach (['orders.index', 'payments.index', 'settings.index'] as $route) {
            $this->actingAs($user)
                ->get(route("admin.reseller.{$route}", ['tenant' => $tenant->id]))
                ->assertOk();
        }
    }

    public function test_supplier_records_cannot_be_updated_across_tenants(): void
    {
        $user = User::factory()->create(['is_platform_admin' => true]);
        $tenant = $this->tenant('tenant-one', CommerceMode::Reseller);
        $otherTenant = $this->tenant('tenant-two', CommerceMode::Reseller);
        $this->setResellerModule($tenant, true);
        $this->setResellerModule($otherTenant, true);
        $supplier = ResellerSupplier::query()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Supplier',
            'website_url' => 'https://other.example',
        ]);

        $this->actingAs($user)
            ->put(route('admin.reseller.suppliers.update', $supplier), [
                'tenant' => $tenant->id,
                'tenant_id' => $tenant->id,
                'name' => 'Hijacked',
                'website_url' => 'https://hijacked.example',
                'is_active' => '1',
            ])
            ->assertNotFound();

        $this->assertSame('Other Supplier', $supplier->refresh()->name);
    }

    public function test_reseller_menu_and_routes_only_appear_when_module_is_enabled(): void
    {
        $user = User::factory()->create(['is_platform_admin' => true]);
        $tenant = $this->tenant('module-gated-store');
        $this->setResellerModule($tenant, false);

        $this->actingAs($user)
            ->get(route('admin.reseller.index', ['tenant' => $tenant->id]))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('admin.analytics.index', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertDontSee('Reseller Setup');

        $this->setResellerModule($tenant, true);

        $this->actingAs($user)
            ->get(route('admin.analytics.index', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('Reseller Setup');

        $this->actingAs($user)
            ->get(route('admin.reseller.index', ['tenant' => $tenant->id]))
            ->assertOk();
    }

    private function setResellerModule(Tenant $tenant, bool $enabled): void
    {
        $module = Module::query()->where('slug', 'reseller')->firstOrFail();

        TenantModuleEntitlement::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'module_id' => $module->id],
            ['is_enabled' => $enabled],
        );
    }

    private function tenant(string $slug, CommerceMode $mode = CommerceMode::Standard): Tenant
    {
        return Tenant::query()->create([
            'name' => str($slug)->headline(),
            'slug' => $slug,
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'commerce_mode' => $mode,
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
    }
}
