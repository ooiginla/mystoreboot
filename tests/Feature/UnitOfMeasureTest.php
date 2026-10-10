<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Inventory\Actions\EnsureDefaultUnitsAction;
use Modules\Inventory\Models\UnitCategory;
use Modules\Inventory\Models\UnitOfMeasure;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class UnitOfMeasureTest extends TestCase
{
    use RefreshDatabase;

    public function test_units_can_be_added_updated_and_removed_unless_in_use(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Units Co', 'slug' => 'units-co', 'status' => TenantStatus::Active,
            'business_type' => 'restaurant', 'country_code' => 'NG', 'timezone' => 'Africa/Lagos', 'currency_code' => 'NGN',
        ]);
        app(EnsureDefaultUnitsAction::class)->forTenant($tenant->id);
        $category = UnitCategory::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $user = User::factory()->create(['is_platform_admin' => true]);

        // Add a custom unit with a base factor.
        $this->actingAs($user)->post(route('admin.inventory.units.store'), [
            'tenant' => $tenant->id, 'unit_category_id' => $category->id,
            'code' => 'bag', 'name' => 'Bag', 'dimension' => 'count', 'to_base_factor' => 100,
        ])->assertRedirect();

        $bag = UnitOfMeasure::query()->where('tenant_id', $tenant->id)->where('code', 'bag')->firstOrFail();
        $this->assertSame('100.000000', (string) $bag->to_base_factor);

        // Update it.
        $this->actingAs($user)->put(route('admin.inventory.units.update', $bag->id), [
            'tenant' => $tenant->id, 'unit_category_id' => $category->id,
            'code' => 'bag', 'name' => 'Big Bag', 'dimension' => 'count', 'to_base_factor' => 120,
        ])->assertRedirect();
        $this->assertSame('Big Bag', $bag->refresh()->name);
        $this->assertSame('120.000000', (string) $bag->to_base_factor);

        // Unused unit can be removed.
        $this->actingAs($user)->delete(route('admin.inventory.units.destroy', $bag->id), ['tenant' => $tenant->id])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('units_of_measure', ['id' => $bag->id]);

        // A unit referenced by a product cannot be removed.
        $ea = UnitOfMeasure::query()->where('tenant_id', $tenant->id)->where('code', 'pc')->firstOrFail();
        $product = Product::query()->create([
            'tenant_id' => $tenant->id, 'name' => 'Thing', 'slug' => 'thing',
            'product_type' => ProductType::Product->value, 'status' => ProductStatus::Active->value,
        ]);
        ProductVariant::query()->create([
            'tenant_id' => $tenant->id, 'product_id' => $product->id, 'variant_name' => 'Default',
            'sku' => 'THING-1', 'base_unit_id' => $ea->id, 'status' => ProductStatus::Active->value,
        ]);

        $this->actingAs($user)->delete(route('admin.inventory.units.destroy', $ea->id), ['tenant' => $tenant->id])
            ->assertSessionHasErrors('unit');
        $this->assertDatabaseHas('units_of_measure', ['id' => $ea->id]);
    }

    public function test_unit_categories_can_be_created_and_are_guarded_from_deletion_while_used(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Cat Co', 'slug' => 'cat-co', 'status' => TenantStatus::Active,
            'business_type' => 'retail', 'country_code' => 'NG', 'timezone' => 'Africa/Lagos', 'currency_code' => 'NGN',
        ]);
        app(EnsureDefaultUnitsAction::class)->forTenant($tenant->id); // creates "General"
        $user = User::factory()->create(['is_platform_admin' => true]);

        // Create a category.
        $this->actingAs($user)->post(route('admin.inventory.unit-categories.store'), [
            'tenant' => $tenant->id, 'name' => 'Okin Biscuit Measurement',
        ])->assertRedirect();
        $category = UnitCategory::query()->where('tenant_id', $tenant->id)->where('name', 'Okin Biscuit Measurement')->firstOrFail();

        // Add a unit to it.
        $this->actingAs($user)->post(route('admin.inventory.units.store'), [
            'tenant' => $tenant->id, 'unit_category_id' => $category->id,
            'code' => 'lg_carton', 'name' => 'Large carton', 'dimension' => 'count', 'to_base_factor' => 100,
        ])->assertRedirect();

        $this->actingAs($user)
            ->get(route('admin.inventory.units.index', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSeeInOrder([
                'New measurement category',
                'Group your measurements into categories',
                'Okin Biscuit Measurement',
            ])
            ->assertSee('aria-label="Update unit"', false)
            ->assertSee('aria-label="Delete unit"', false)
            ->assertSee('<details class="panel unit-accordion" name="unit-categories" data-unit-accordion>', false)
            ->assertSee('1 unit')
            ->assertSee('data-unit-accordion][open]', false);

        // Cannot delete a category that still has units.
        $this->actingAs($user)->delete(route('admin.inventory.unit-categories.destroy', $category->id), ['tenant' => $tenant->id])
            ->assertSessionHasErrors('category');

        // The default (General) category can never be deleted.
        $general = UnitCategory::query()->where('tenant_id', $tenant->id)->where('is_default', true)->firstOrFail();
        $this->actingAs($user)->delete(route('admin.inventory.unit-categories.destroy', $general->id), ['tenant' => $tenant->id])
            ->assertSessionHasErrors('category');

        // Remove the unit, then the category deletes cleanly.
        UnitOfMeasure::query()->where('unit_category_id', $category->id)->delete();
        $this->actingAs($user)->delete(route('admin.inventory.unit-categories.destroy', $category->id), ['tenant' => $tenant->id])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('unit_categories', ['id' => $category->id]);
    }

    public function test_soft_deleted_products_do_not_prevent_unit_category_deletion(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Archived Products Co', 'slug' => 'archived-products-co', 'status' => TenantStatus::Active,
            'business_type' => 'restaurant', 'country_code' => 'NG', 'timezone' => 'Africa/Lagos', 'currency_code' => 'NGN',
        ]);
        $user = User::factory()->create(['is_platform_admin' => true]);
        $category = UnitCategory::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Archived measurement',
            'is_default' => false,
        ]);
        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Archived ingredient',
            'slug' => 'archived-ingredient',
            'product_type' => ProductType::RawMaterial->value,
            'status' => ProductStatus::Active->value,
            'unit_category_id' => $category->id,
        ]);
        $product->delete();

        $this->actingAs($user)
            ->delete(route('admin.inventory.unit-categories.destroy', $category->id), ['tenant' => $tenant->id])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('unit_categories', ['id' => $category->id]);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'unit_category_id' => null,
        ]);
    }

    public function test_unit_codes_can_be_reused_in_different_categories(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Bulk Goods Co', 'slug' => 'bulk-goods-co', 'status' => TenantStatus::Active,
            'business_type' => 'restaurant', 'country_code' => 'NG', 'timezone' => 'Africa/Lagos', 'currency_code' => 'NGN',
        ]);
        $user = User::factory()->create(['is_platform_admin' => true]);
        $sugar = UnitCategory::query()->create([
            'tenant_id' => $tenant->id, 'name' => 'Sugar', 'is_default' => false,
        ]);
        $flour = UnitCategory::query()->create([
            'tenant_id' => $tenant->id, 'name' => 'Flour', 'is_default' => false,
        ]);

        foreach ([[$sugar, 60], [$flour, 80]] as [$category, $bagFactor]) {
            $this->actingAs($user)->post(route('admin.inventory.units.store'), [
                'tenant' => $tenant->id, 'unit_category_id' => $category->id,
                'code' => 'kg', 'name' => 'Kilogram', 'dimension' => 'weight',
                'to_base_factor' => 1, 'is_base_for_dimension' => true,
            ])->assertRedirect()->assertSessionHasNoErrors();

            $this->actingAs($user)->post(route('admin.inventory.units.store'), [
                'tenant' => $tenant->id, 'unit_category_id' => $category->id,
                'code' => 'bag', 'name' => 'Bag', 'dimension' => 'weight',
                'to_base_factor' => $bagFactor,
            ])->assertRedirect()->assertSessionHasNoErrors();
        }

        $this->assertDatabaseHas('units_of_measure', [
            'unit_category_id' => $sugar->id, 'code' => 'bag', 'to_base_factor' => 60,
        ]);
        $this->assertDatabaseHas('units_of_measure', [
            'unit_category_id' => $flour->id, 'code' => 'bag', 'to_base_factor' => 80,
        ]);

        $this->actingAs($user)->post(route('admin.inventory.units.store'), [
            'tenant' => $tenant->id, 'unit_category_id' => $sugar->id,
            'code' => 'kg', 'name' => 'Duplicate kilogram', 'dimension' => 'weight',
            'to_base_factor' => 1,
        ])->assertSessionHasErrors('code');
    }
}
