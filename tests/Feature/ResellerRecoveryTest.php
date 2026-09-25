<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Reseller\Enums\PricingMode;
use Modules\Reseller\Enums\ScanStatus;
use Modules\Reseller\Models\ResellerProduct;
use Modules\Reseller\Models\ResellerSetting;
use Modules\Reseller\Models\ResellerSupplier;
use Modules\Reseller\Services\RecoverSupplierProducts;
use Modules\Subscriptions\Models\Module;
use Modules\Subscriptions\Models\TenantModuleEntitlement;
use Modules\Tenancy\Enums\CommerceMode;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class ResellerRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_recovery_discovers_products_calculates_prices_and_respects_currency(): void
    {
        [$tenant, $supplier] = $this->fixture();
        ResellerSetting::query()->create([
            'tenant_id' => $tenant->id,
            'pricing_mode' => PricingMode::Combined,
            'percentage_markup_basis_points' => 1000,
            'fixed_markup_minor' => 10000,
            'auto_publish_products' => true,
        ]);

        Http::fake([
            'https://supplier.example' => Http::response('<a href="/products/shirt">Shirt</a><a href="/products/bag">Bag</a>', 200),
            'https://supplier.example/products/shirt' => Http::response($this->productHtml([
                'name' => 'Everyday Shirt',
                'sku' => 'SHIRT-1',
                'image' => 'https://supplier.example/images/shirt.jpg',
                'category' => 'Clothing',
                'brand' => ['name' => 'Example Brand'],
                'offers' => [
                    '@type' => 'Offer',
                    'price' => '10000.00',
                    'highPrice' => '12000.00',
                    'priceCurrency' => 'NGN',
                    'availability' => 'https://schema.org/InStock',
                    'seller' => ['name' => 'Supplier Store'],
                ],
            ]), 200),
            'https://supplier.example/products/bag' => Http::response($this->productHtml([
                'name' => 'Dollar Bag',
                'sku' => 'BAG-1',
                'offers' => [
                    '@type' => 'Offer',
                    'price' => '50.00',
                    'priceCurrency' => 'USD',
                    'availability' => 'https://schema.org/InStock',
                ],
            ]), 200),
        ]);

        $result = app(RecoverSupplierProducts::class)->execute($supplier);

        $this->assertSame(['created' => 2, 'updated' => 0, 'pages' => 3, 'errors' => 0], $result);
        $this->assertSame(ScanStatus::Successful, $supplier->refresh()->last_scan_status);

        $shirt = ResellerProduct::query()->where('sku', 'SHIRT-1')->firstOrFail();
        $this->assertSame(1000000, $shirt->source_price_minor);
        $this->assertSame(1110000, $shirt->selling_price_minor);
        $this->assertSame(1330000, $shirt->selling_previous_price_minor);
        $this->assertTrue($shirt->is_visible);
        $this->assertSame('Supplier Store', $shirt->source_store_name);

        $bag = ResellerProduct::query()->where('sku', 'BAG-1')->firstOrFail();
        $this->assertSame('USD', $bag->currency_code);
        $this->assertFalse($bag->is_visible);
    }

    public function test_failed_scan_does_not_mark_existing_products_missing(): void
    {
        [$tenant, $supplier] = $this->fixture();
        ResellerSetting::query()->create(['tenant_id' => $tenant->id]);
        $product = ResellerProduct::query()->create([
            'tenant_id' => $tenant->id,
            'supplier_id' => $supplier->id,
            'product_url' => 'https://supplier.example/products/existing',
            'name' => 'Existing Product',
            'source_price_minor' => 10000,
            'selling_price_minor' => 11000,
            'currency_code' => 'NGN',
            'source_store_name' => 'Supplier',
            'is_visible' => true,
            'last_checked_at' => now()->subDay(),
        ]);
        Http::fake(['https://supplier.example' => Http::response('Unavailable', 503)]);

        $result = app(RecoverSupplierProducts::class)->execute($supplier);

        $this->assertSame(1, $result['errors']);
        $this->assertSame(ScanStatus::Failed, $supplier->refresh()->last_scan_status);
        $this->assertSame(0, $product->refresh()->missing_scan_count);
        $this->assertTrue($product->is_visible);
    }

    /** @return array{Tenant, ResellerSupplier} */
    private function fixture(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Recovery Store',
            'slug' => 'recovery-store-'.str()->random(6),
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'commerce_mode' => CommerceMode::Reseller,
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
        $module = Module::query()->where('slug', 'reseller')->firstOrFail();
        TenantModuleEntitlement::query()->create([
            'tenant_id' => $tenant->id,
            'module_id' => $module->id,
            'is_enabled' => true,
        ]);
        $supplier = ResellerSupplier::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Supplier',
            'website_url' => 'https://supplier.example',
        ]);

        return [$tenant, $supplier];
    }

    /** @param array<string, mixed> $product */
    private function productHtml(array $product): string
    {
        $payload = json_encode(['@context' => 'https://schema.org', '@type' => 'Product', ...$product], JSON_THROW_ON_ERROR);

        return '<html><head><script type="application/ld+json">'.$payload.'</script></head><body></body></html>';
    }
}
