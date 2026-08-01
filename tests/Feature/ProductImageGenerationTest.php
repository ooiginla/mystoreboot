<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Enums\TaxBehavior;
use Modules\Catalog\Models\Product;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

class ProductImageGenerationTest extends TestCase
{
    use RefreshDatabase;

    private const ONE_PIXEL_PNG_B64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    public function test_generates_and_attaches_an_image_for_a_product_without_one(): void
    {
        Storage::fake('public');
        config(['services.openai.api_key' => 'sk-test', 'services.openai.base_url' => 'https://api.openai.com', 'services.openai.image_model' => 'gpt-image-1']);
        Http::fake([
            'api.openai.com/v1/images/generations' => Http::response(['data' => [['b64_json' => self::ONE_PIXEL_PNG_B64]]]),
        ]);

        [$user, $product] = $this->fixture();
        $this->assertNull($product->image_path);

        $this->actingAs($user)
            ->post(route('admin.catalog.products.generate-image', $product))
            ->assertRedirect(route('admin.catalog.index', ['tenant' => $product->tenant_id]).'#products')
            ->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertNotNull($product->image_path);
        Storage::disk('public')->assertExists($product->image_path);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'images/generations'));
    }

    public function test_errors_gracefully_when_no_openai_key_is_configured(): void
    {
        Storage::fake('public');
        config(['services.openai.api_key' => null]);
        Http::fake();

        [$user, $product] = $this->fixture();

        $this->actingAs($user)
            ->from(route('admin.catalog.index', ['tenant' => $product->tenant_id]))
            ->post(route('admin.catalog.products.generate-image', $product))
            ->assertRedirect()
            ->assertSessionHasErrors('product_image');

        Http::assertNothingSent();
        $this->assertNull($product->refresh()->image_path);
    }

    /**
     * @return array{User, Product}
     */
    private function fixture(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Image Shop',
            'slug' => 'image-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Peak Milk 20g',
            'slug' => 'peak-milk-20g',
            'product_type' => ProductType::Product->value,
            'base_price_minor' => 150000,
            'tax_behavior' => TaxBehavior::Exempt->value,
            'status' => ProductStatus::Active->value,
            'image_path' => null,
        ]);
        $user = User::factory()->create(['is_platform_admin' => true]);

        return [$user, $product];
    }
}
