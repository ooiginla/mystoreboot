<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Business\Models\Branch;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Enums\TaxBehavior;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Customers\Models\Customer;
use Modules\Inventory\Enums\InventoryLocationType;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Sales\Actions\CreateSalesOrderAction;
use Modules\Sales\Models\SalesTillSession;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class PosCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogue_is_active_till_scoped_cacheable_and_not_server_rendered(): void
    {
        [$tenant, $branch, $location, $user] = $this->openTillContext('POS Cache Shop');
        $variant = $this->product($tenant, 'Red Sneakers', 'SHOE-RED', 125000);
        $this->product($tenant, 'Draft Hat', 'HAT-DRAFT', 50000, ProductStatus::Draft);

        $otherTenant = $this->tenant('Another Shop');
        $this->product($otherTenant, 'Private Item', 'PRIVATE-1', 99900);

        $response = $this->actingAs($user)->getJson(route('admin.sales.retail-pos.catalog', [
            'tenant' => $tenant->id,
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('tenant_id', $tenant->id)
            ->assertJsonPath('user_id', $user->id)
            ->assertJsonPath('branch_id', $branch->id)
            ->assertJsonPath('inventory_location_id', $location->id)
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $variant->id)
            ->assertJsonPath('items.0.name', 'Red Sneakers')
            ->assertJsonPath('items.0.sku', 'SHOE-RED')
            ->assertJsonPath('items.0.price', 1250);

        $etag = (string) $response->headers->get('ETag');
        $this->assertNotSame('', $etag);

        $this->actingAs($user)
            ->withHeader('If-None-Match', $etag)
            ->getJson(route('admin.sales.retail-pos.catalog', ['tenant' => $tenant->id]))
            ->assertStatus(304);

        $this->actingAs($user)
            ->get(route('admin.sales.retail-pos', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('data-catalog-url=', false)
            ->assertSee('Loading catalogue…')
            ->assertDontSee('data-variant-id=', false)
            ->assertDontSee('Private Item');
    }

    public function test_catalogue_revision_invalidates_etag_after_a_price_change(): void
    {
        [$tenant, , , $user] = $this->openTillContext('Revision Shop');
        $variant = $this->product($tenant, 'Coffee', 'COFFEE-1', 25000);

        $first = $this->actingAs($user)->getJson(route('admin.sales.retail-pos.catalog', ['tenant' => $tenant->id]));
        $oldEtag = (string) $first->headers->get('ETag');

        $variant->update(['selling_price_minor' => 30000]);

        $this->actingAs($user)
            ->withHeader('If-None-Match', $oldEtag)
            ->getJson(route('admin.sales.retail-pos.catalog', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertJsonPath('items.0.price', 300)
            ->assertHeaderMissing('Warning');
    }

    public function test_retail_pos_rejects_a_stale_cached_price(): void
    {
        [$tenant, $branch, $location, $user, $till] = $this->openTillContext('Safe Price Shop');
        $variant = $this->product($tenant, 'Tea', 'TEA-1', 40000);
        $customer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Walk-In',
            'last_name' => 'Customer',
            'phone' => 'WALK-IN',
            'status' => 'active',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('price of Tea has changed');

        app(CreateSalesOrderAction::class)->execute([
            'tenant_id' => $tenant->id,
            'source' => 'retail_pos',
            'sales_till_session_id' => $till->id,
            'branch_id' => $branch->id,
            'inventory_location_id' => $location->id,
            'customer_id' => $customer->id,
            'order_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'amount_paid' => '350',
            'shipping' => 0,
            'admin_discount_type' => 'amount',
            'admin_discount_value' => 0,
            'delivery_status' => 'delivered',
            'items' => [[
                'product_variant_id' => $variant->id,
                'quantity' => 1,
                'unit_price' => '350',
            ]],
        ], $user->id);
    }

    /** @return array{Tenant, Branch, InventoryLocation, User, SalesTillSession} */
    private function openTillContext(string $name): array
    {
        $tenant = $this->tenant($name);
        $branch = Branch::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'status' => 'active',
            'is_primary' => true,
        ]);
        $location = InventoryLocation::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'POS Stock',
            'code' => 'POS-STOCK',
            'location_type' => InventoryLocationType::Branch->value,
            'is_sellable_point' => true,
            'status' => 'active',
        ]);
        $user = User::factory()->create(['is_platform_admin' => true]);
        $till = SalesTillSession::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'inventory_location_id' => $location->id,
            'user_id' => $user->id,
            'session_number' => 'TILL-'.str()->random(8),
            'status' => 'open',
            'opening_float_minor' => 0,
            'opened_at' => now(),
        ]);

        return [$tenant, $branch, $location, $user, $till];
    }

    private function tenant(string $name): Tenant
    {
        return Tenant::query()->create([
            'name' => $name,
            'slug' => str($name)->slug().'-'.str()->lower(str()->random(5)),
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
    }

    private function product(
        Tenant $tenant,
        string $name,
        string $sku,
        int $priceMinor,
        ProductStatus $status = ProductStatus::Active,
    ): ProductVariant {
        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.str()->lower(str()->random(5)),
            'product_type' => ProductType::Product->value,
            'base_price_minor' => $priceMinor,
            'tax_behavior' => TaxBehavior::Exempt->value,
            'status' => $status->value,
        ]);

        return ProductVariant::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'variant_name' => 'Default',
            'sku' => $sku,
            'selling_price_minor' => $priceMinor,
            'tax_behavior' => TaxBehavior::Exempt->value,
            'status' => ProductStatus::Active->value,
        ]);
    }
}
