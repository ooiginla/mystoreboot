<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Catalog\Enums\CategoryType;
use Modules\Catalog\Models\ProductCategory;
use Modules\Reseller\Actions\ClassifyResellerProducts;
use Modules\Reseller\Models\ResellerProduct;
use Modules\Reseller\Models\ResellerSupplier;
use Modules\Tenancy\Enums\CommerceMode;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class ResellerProductClassificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_products_are_classified_and_tagged_in_one_ai_request(): void
    {
        config()->set('services.ai.provider', 'openai');
        config()->set('services.openai.api_key', 'test-key');
        config()->set('services.openai.base_url', 'https://api.openai.test');
        $tenant = Tenant::query()->create([
            'name' => 'Classification Store',
            'slug' => 'classification-store',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'commerce_mode' => CommerceMode::Reseller,
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
        $clothing = $this->category($tenant, 'Clothing');
        $electronics = $this->category($tenant, 'Electronics');
        $supplier = ResellerSupplier::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Supplier',
            'website_url' => 'https://supplier.example',
        ]);
        $shirt = $this->product($tenant, $supplier, 'Classic Cotton Shirt', 'shirt');
        $speaker = $this->product($tenant, $supplier, 'Portable Bluetooth Speaker', 'speaker');

        Http::fake([
            'https://api.openai.test/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'products' => [
                                ['id' => $shirt->id, 'category_id' => $clothing->id, 'tags' => ['Cotton', 'Casual wear', 'Shirt']],
                                ['id' => $speaker->id, 'category_id' => $electronics->id, 'tags' => ['Bluetooth', 'Portable audio', 'Speaker']],
                            ],
                        ], JSON_THROW_ON_ERROR),
                    ],
                ]],
            ]),
        ]);

        $result = app(ClassifyResellerProducts::class)->execute((string) $tenant->id);

        $this->assertSame(2, $result['processed']);
        $this->assertSame(2, $result['categorized']);
        $this->assertSame(0, $result['pending']);
        $this->assertSame(1, $result['ai_requests']);
        $this->assertSame($clothing->id, $shirt->refresh()->category_id);
        $this->assertSame($electronics->id, $speaker->refresh()->category_id);
        $this->assertEqualsCanonicalizing(['Cotton', 'Casual wear', 'Shirt'], $shirt->tags()->pluck('name')->all());
        $this->assertEqualsCanonicalizing(['Bluetooth', 'Portable audio', 'Speaker'], $speaker->tags()->pluck('name')->all());
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => str_contains((string) $request->body(), 'Classic Cotton Shirt')
            && str_contains((string) $request->body(), 'Portable Bluetooth Speaker'));
    }

    private function category(Tenant $tenant, string $name): ProductCategory
    {
        return ProductCategory::query()->create([
            'tenant_id' => $tenant->id,
            'category_type' => CategoryType::Product,
            'name' => $name,
            'slug' => str($name)->slug(),
            'status' => 'active',
        ]);
    }

    private function product(Tenant $tenant, ResellerSupplier $supplier, string $name, string $slug): ResellerProduct
    {
        return ResellerProduct::query()->create([
            'tenant_id' => $tenant->id,
            'supplier_id' => $supplier->id,
            'product_url' => "https://supplier.example/products/{$slug}",
            'name' => $name,
            'source_price_minor' => 10000,
            'selling_price_minor' => 12000,
            'currency_code' => 'NGN',
            'source_store_name' => 'Supplier',
            'is_visible' => true,
            'last_checked_at' => now(),
        ]);
    }
}
