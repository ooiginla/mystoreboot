<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Enums\TaxBehavior;
use Modules\Catalog\Models\Product;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

class ProductContentAiTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_description_and_specifications_can_be_generated_with_ai(): void
    {
        config([
            'services.ai.provider' => 'openai',
            'services.openai.api_key' => 'sk-test',
            'services.openai.base_url' => 'https://api.openai.com',
        ]);
        Http::fakeSequence()
            ->push(['choices' => [['message' => ['content' => 'A breathable everyday shirt with a comfortable regular fit.']]]])
            ->push(['choices' => [['message' => ['content' => "Material: Cotton\nFit: Regular"]]]]);

        [$user, $tenant] = $this->fixture();
        $context = [
            'tenant_id' => $tenant->id,
            'name' => 'Classic Shirt',
            'brand' => 'Northwind',
            'category' => 'Apparel',
        ];

        $this->actingAs($user)
            ->postJson(route('admin.catalog.products.ai-content'), $context + [
                'field' => 'description',
                'prompt' => 'breathable cotton and regular fit',
            ])
            ->assertOk()
            ->assertJsonPath('format', 'plain')
            ->assertJsonPath('content', 'A breathable everyday shirt with a comfortable regular fit.');

        $this->postJson(route('admin.catalog.products.ai-content'), $context + [
            'field' => 'specifications',
            'description' => 'A breathable cotton shirt with a regular fit.',
        ])
            ->assertOk()
            ->assertJsonPath('content', "Material: Cotton\nFit: Regular");

        Http::assertSent(fn ($request): bool => str_contains((string) $request['messages'][0]['content'], 'Product name: Classic Shirt'));
    }

    public function test_specifications_are_saved_and_restored_in_the_product_form(): void
    {
        [$user, $tenant] = $this->fixture();

        $this->actingAs($user)
            ->post(route('admin.catalog.products.store'), [
                'tenant_id' => $tenant->id,
                'product_type' => ProductType::Product->value,
                'name' => 'Classic Shirt',
                'description' => 'An everyday shirt.',
                'specifications' => "Material: Cotton\nFit: Regular",
                'base_price' => '2500',
                'tax_behavior' => TaxBehavior::Exempt->value,
                'status' => ProductStatus::Active->value,
            ])
            ->assertRedirect(route('admin.catalog.index', ['tenant' => $tenant->id]));

        $product = Product::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame("Material: Cotton\nFit: Regular", $product->specifications);

        $this->get(route('admin.catalog.index', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('Generate with AI')
            ->assertSee('name="specifications"', false)
            ->assertSee('data-product-specifications', false)
            ->assertSeeInOrder(['data-product-specifications', 'Material: Cotton', 'Fit: Regular'], false)
            ->assertSee('Material: Cotton');
    }

    /** @return array{User, Tenant} */
    private function fixture(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Product AI Shop',
            'slug' => 'product-ai-shop',
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
