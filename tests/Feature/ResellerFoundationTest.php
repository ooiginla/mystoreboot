<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Modules\Reseller\Actions\CalculateResellerPrice;
use Modules\Reseller\Enums\PricingMode;
use Modules\Reseller\Models\ResellerSetting;
use Modules\Reseller\Models\ResellerSupplier;
use Modules\Reseller\Support\SupplierScanSchedule;
use Modules\Tenancy\Enums\CommerceMode;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class ResellerFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reseller_schema_is_loaded_and_tenant_scoped(): void
    {
        foreach ([
            'reseller_settings',
            'reseller_suppliers',
            'reseller_products',
            'reseller_orders',
            'reseller_order_items',
            'reseller_payments',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected {$table} to exist.");
            $this->assertTrue(Schema::hasColumn($table, 'tenant_id'), "Expected {$table} to be tenant scoped.");
        }

        $tenant = $this->tenant();
        $this->assertSame(CommerceMode::Standard, $tenant->commerce_mode);
        $this->assertFalse($tenant->isReseller());

        $tenant->update(['commerce_mode' => CommerceMode::Reseller]);
        $this->assertTrue($tenant->refresh()->isReseller());

        $supplier = ResellerSupplier::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Example Supplier',
            'website_url' => 'https://supplier.example',
        ]);

        $this->assertTrue($supplier->tenant->is($tenant));
    }

    public function test_percentage_fixed_and_combined_prices_use_minor_units(): void
    {
        $tenant = $this->tenant();
        $settings = ResellerSetting::query()->create([
            'tenant_id' => $tenant->id,
            'pricing_mode' => PricingMode::Percentage,
            'percentage_markup_basis_points' => 1500,
            'fixed_markup_minor' => 200000,
        ]);
        $calculator = app(CalculateResellerPrice::class);

        $this->assertSame(1150000, $calculator->execute(1000000, $settings));

        $settings->pricing_mode = PricingMode::Fixed;
        $this->assertSame(1200000, $calculator->execute(1000000, $settings));

        $settings->pricing_mode = PricingMode::Combined;
        $this->assertSame(1350000, $calculator->execute(1000000, $settings));

        $this->expectException(InvalidArgumentException::class);
        $calculator->execute(-1, $settings);
    }

    public function test_supplier_scan_schedule_reports_the_next_hourly_dispatch(): void
    {
        Carbon::setTestNow('2026-09-25 10:00:00');
        $tenant = $this->tenant();
        $settings = ResellerSetting::query()->create([
            'tenant_id' => $tenant->id,
            'scan_frequency' => 'twice_daily',
        ]);
        $supplier = ResellerSupplier::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Scheduled Supplier',
            'website_url' => 'https://scheduled.example',
            'last_scanned_at' => now(),
        ]);
        $schedule = app(SupplierScanSchedule::class);

        $this->assertSame('twice_daily', $schedule->frequency($supplier, $settings));
        $this->assertFalse($schedule->isDue($supplier, $settings));
        $this->assertSame('2026-09-25 22:00:00', $schedule->nextDispatchAt($supplier, $settings)?->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    private function tenant(): Tenant
    {
        return Tenant::query()->create([
            'name' => 'Reseller Test Store',
            'slug' => 'reseller-test-store-'.str()->random(8),
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
    }
}
