<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductTag;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

class ProductPhotoImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploaded_photos_become_ai_drafted_draft_products(): void
    {
        Storage::fake('public');
        config([
            'services.ai.provider' => 'anthropic',
            'services.anthropic.api_key' => 'sk-test',
            'services.anthropic.base_url' => 'https://api.anthropic.com',
            'services.anthropic.model' => 'claude-opus-5',
        ]);
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'stop_reason' => 'end_turn',
                'content' => [[
                    'type' => 'text',
                    'text' => json_encode([
                        'name' => 'Peak Milk 20g',
                        'description' => 'Instant powdered milk sachet.',
                        'category' => 'Beverages',
                        'price_minor' => 150000,
                        'has_price' => true,
                        'tags' => ['milk', 'dairy'],
                    ]),
                ]],
            ]),
        ]);

        [$user, $tenant] = $this->fixture();

        $this->actingAs($user)
            ->post(route('admin.catalog.products.import'), [
                'tenant_id' => $tenant->id,
                'images' => [UploadedFile::fake()->image('random-name.jpg')],
            ])
            ->assertRedirect(route('admin.catalog.index', ['tenant' => $tenant->id]).'#products')
            ->assertSessionHasNoErrors();

        $product = Product::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('Peak Milk 20g', $product->name);
        $this->assertSame(ProductStatus::Draft, $product->status);
        $this->assertSame(ProductType::Product, $product->product_type);
        $this->assertSame(150000, (int) $product->base_price_minor);
        $this->assertNotNull($product->image_path);
        // A default variant carries the drafted price.
        $this->assertSame(150000, (int) $product->variants()->value('selling_price_minor'));
        // The AI category becomes a real, assigned category (never left blank).
        $this->assertNotNull($product->category_id);
        $this->assertSame('Beverages', $product->category?->name);
    }

    public function test_photos_can_be_drafted_with_an_openai_key(): void
    {
        Storage::fake('public');
        config([
            'services.ai.provider' => 'openai',
            'services.openai.api_key' => 'sk-openai-test',
            'services.openai.base_url' => 'https://api.openai.com',
            'services.openai.model' => 'gpt-4o-mini',
        ]);
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'refusal' => null,
                        'content' => json_encode([
                            'name' => 'Ankara Midi Dress',
                            'description' => 'Handmade Ankara print midi dress.',
                            'category' => 'Fashion',
                            'price_minor' => 1200000,
                            'has_price' => true,
                            'tags' => ['ankara', 'dress'],
                        ]),
                    ],
                ]],
            ]),
        ]);

        [$user, $tenant] = $this->fixture();

        $this->actingAs($user)
            ->post(route('admin.catalog.products.import'), [
                'tenant_id' => $tenant->id,
                'images' => [UploadedFile::fake()->image('img_2043.jpg')],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Hit OpenAI, not Anthropic.
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'api.openai.com'));

        $product = Product::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('Ankara Midi Dress', $product->name);
        $this->assertSame(ProductStatus::Draft, $product->status);
        $this->assertSame(1200000, (int) $product->base_price_minor);
        $this->assertSame('Fashion', $product->category?->name);
    }

    public function test_import_still_creates_drafts_when_no_ai_key_is_configured(): void
    {
        Storage::fake('public');
        Http::fake();
        config([
            'services.ai.provider' => 'anthropic',
            'services.anthropic.api_key' => null,
            'services.openai.api_key' => null,
        ]);

        [$user, $tenant] = $this->fixture();

        $this->actingAs($user)
            ->post(route('admin.catalog.products.import'), [
                'tenant_id' => $tenant->id,
                'images' => [UploadedFile::fake()->image('blue-ankara-dress.jpg')],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Http::assertNothingSent();

        $product = Product::query()->where('tenant_id', $tenant->id)->firstOrFail();
        // Name falls back to a tidied filename; merchant fills the rest in review.
        $this->assertSame('Blue Ankara Dress', $product->name);
        $this->assertSame(ProductStatus::Draft, $product->status);
        $this->assertSame(0, (int) $product->base_price_minor);
        // AI unavailable → still categorized, under Uncategorized (never blank).
        $this->assertSame('Uncategorized', $product->category?->name);
        $this->assertNotNull($product->image_path);
    }

    /**
     * @return array{User, Tenant}
     */
    private function fixture(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Import Shop',
            'slug' => 'import-shop',
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
