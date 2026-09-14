<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Business\Models\Branch;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Inventory\Models\Recipe;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class FinishedProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_finished_product_then_manage_and_edit_its_recipe(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'FP Co', 'slug' => 'fp-co', 'status' => TenantStatus::Active,
            'business_type' => 'restaurant', 'country_code' => 'NG', 'timezone' => 'Africa/Lagos', 'currency_code' => 'NGN',
        ]);
        Branch::query()->create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'MAIN', 'status' => 'active', 'is_primary' => true]);
        $user = User::factory()->create(['is_platform_admin' => true]);

        $make = function (string $name, string $sku, ProductType $type) use ($tenant): ProductVariant {
            $product = Product::query()->create([
                'tenant_id' => $tenant->id, 'name' => $name, 'slug' => \Illuminate\Support\Str::slug($name),
                'product_type' => $type->value, 'status' => ProductStatus::Active->value,
            ]);

            return ProductVariant::query()->create([
                'tenant_id' => $tenant->id, 'product_id' => $product->id, 'variant_name' => 'Default',
                'sku' => $sku, 'status' => ProductStatus::Active->value,
            ]);
        };
        $jollof = $make('Jollof Plate', 'JOLLOF-P', ProductType::Product);
        $rice = $make('Rice', 'RICE-F', ProductType::RawMaterial);

        // Not a finished product yet → detail page 404s.
        $this->actingAs($user)->get(route('admin.inventory.production.show', ['product' => $jollof->product_id, 'tenant' => $tenant->id]))
            ->assertNotFound();

        // Add it as a finished product.
        $this->actingAs($user)->post(route('admin.inventory.production.finished-products.store'), [
            'tenant' => $tenant->id, 'product_variant_id' => $jollof->id,
        ])->assertRedirect();
        $this->assertTrue(Product::query()->whereKey($jollof->product_id)->value('is_finished_product'));

        // Detail page now loads.
        $this->actingAs($user)->get(route('admin.inventory.production.show', ['product' => $jollof->product_id, 'tenant' => $tenant->id]))
            ->assertOk()->assertSee('Recipe')->assertSee('Menu margins');

        // Add a recipe for it.
        $this->actingAs($user)->post(route('admin.inventory.production.recipes.store'), [
            'tenant' => $tenant->id,
            'name' => 'Jollof recipe',
            'output_product_variant_id' => $jollof->id,
            'yield_quantity' => 10,
            'items' => [['component_product_variant_id' => $rice->id, 'quantity' => 20]],
        ])->assertRedirect();

        $recipe = Recipe::query()->where('output_product_variant_id', $jollof->id)->firstOrFail();
        $this->assertSame(1, $recipe->items()->count());

        // Edit the recipe (change yield + ingredient qty).
        $this->actingAs($user)->put(route('admin.inventory.production.recipes.update', $recipe->id), [
            'tenant' => $tenant->id,
            'name' => 'Jollof recipe v2',
            'output_product_variant_id' => $jollof->id,
            'yield_quantity' => 12,
            'items' => [['component_product_variant_id' => $rice->id, 'quantity' => 25]],
        ])->assertRedirect();

        $recipe->refresh();
        $this->assertSame('Jollof recipe v2', $recipe->name);
        $this->assertSame(2, (int) $recipe->version);
        $this->assertSame(25.0, (float) $recipe->items()->first()->quantity);

        $this->actingAs($user)->get(route('admin.inventory.production.show', ['product' => $jollof->product_id, 'tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('Jollof recipe v2')
            ->assertSee('Remove recipe')
            ->assertSeeInOrder(['Menu margins &amp; food cost', 'Sell price', 'Cost / unit', 'Margin', 'Margin %', 'Food cost %'], false)
            ->assertDontSee('href="#margins"', false);
    }
}
