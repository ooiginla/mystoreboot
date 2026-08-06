<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\Catalog\Actions\StructureProductSheetAction;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Support\SpreadsheetReader;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

class ProductSheetImportTest extends TestCase
{
    use RefreshDatabase;

    private function noAi(): void
    {
        config([
            'services.ai.provider' => 'anthropic',
            'services.anthropic.api_key' => null,
            'services.openai.api_key' => null,
        ]);
    }

    /**
     * @return array{User, Tenant}
     */
    private function fixture(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Sheet Shop',
            'slug' => 'sheet-shop',
            'status' => TenantStatus::Active,
            'business_type' => 'retail',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'NGN',
        ]);
        $user = User::factory()->create(['is_platform_admin' => true]);

        return [$user, $tenant];
    }

    public function test_reader_parses_a_real_xlsx_into_a_grid(): void
    {
        $grid = (new SpreadsheetReader)->read(base_path('tests/Fixtures/sample_products.xlsx'), 'sample_products.xlsx');

        $this->assertSame(['Product ID', 'Product Name', 'Category', 'Price', 'Stock Quantity', 'Status'], $grid[0]);
        $this->assertSame('Wireless Bluetooth Earbuds', $grid[1][1]);
        $this->assertSame('Electronics', $grid[1][2]);
        $this->assertSame('49.99', $grid[1][3]);
    }

    public function test_flat_csv_imports_as_cleaned_draft_products_without_ai(): void
    {
        Storage::fake('public');
        Http::fake();
        $this->noAi();
        [$user, $tenant] = $this->fixture();

        $csv = <<<CSV
        Product ID,Product Name,Category,Price,Stock Quantity,Status
        SKU-101,Wireless Bluetooth Earbuds,Electronics,49.99,120,Active
        SKU-102,Ergonomic Office Chair,Furniture,149.50,35,Active
        SKU-103,Stainless Steel Water Bottle,Kitchen,19.99,200,Active
        CSV;

        $this->actingAs($user)
            ->post(route('admin.catalog.products.import-sheet'), [
                'tenant_id' => $tenant->id,
                'sheet' => UploadedFile::fake()->createWithContent('products.csv', $csv),
            ])
            ->assertRedirect(route('admin.catalog.index', ['tenant' => $tenant->id]).'#products')
            ->assertSessionHasNoErrors();

        Http::assertNothingSent();

        $this->assertSame(3, Product::query()->where('tenant_id', $tenant->id)->count());

        $earbuds = Product::query()->where('tenant_id', $tenant->id)->where('name', 'Wireless Bluetooth Earbuds')->firstOrFail();
        $this->assertSame(ProductStatus::Draft, $earbuds->status);
        $this->assertSame(4999, (int) $earbuds->base_price_minor);
        $this->assertSame('Electronics', $earbuds->category?->name);
        $this->assertSame('SKU-101', $earbuds->variants()->value('sku'));

        $chair = Product::query()->where('tenant_id', $tenant->id)->where('name', 'Ergonomic Office Chair')->firstOrFail();
        $this->assertSame(14950, (int) $chair->base_price_minor);
        $this->assertSame('Furniture', $chair->category?->name);
    }

    public function test_real_xlsx_upload_creates_draft_products_end_to_end(): void
    {
        Storage::fake('public');
        Http::fake();
        $this->noAi();
        [$user, $tenant] = $this->fixture();

        $this->actingAs($user)
            ->post(route('admin.catalog.products.import-sheet'), [
                'tenant_id' => $tenant->id,
                'sheet' => UploadedFile::fake()->createWithContent(
                    'sample_products.xlsx',
                    (string) file_get_contents(base_path('tests/Fixtures/sample_products.xlsx')),
                ),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // 5 product rows in the fixture.
        $this->assertSame(5, Product::query()->where('tenant_id', $tenant->id)->count());
        $this->assertTrue(
            Product::query()->where('tenant_id', $tenant->id)->where('name', 'Smart Fitness Tracker')->exists(),
        );
    }

    public function test_sectioned_layout_is_structured_by_heuristic(): void
    {
        $this->noAi();

        // Mirrors the microscope.xlsx shape: category header, sub-header, model + spec rows.
        $grid = [
            ['Monocular Microscopes Series'],
            ['Model', 'Specification', '', 'Pictures', 'EXW price '],
            ['XSP-01', 'Magnification', '40-640X', '', '15.5'],
            ['', 'Eyepieces', 'H10X, WF16X'],
            ['', 'Objectives', 'Achromatic Objectives: 4X, 10X, 40X'],
            ['XSP-02', 'Magnification', '40-640X', '', '22'],
            ['', 'Eyepieces', 'H10X, WF16X'],
            [],
            ['Binocular Microscopes Series'],
            ['Model', 'Specification', '', 'Pictures', 'EXW price '],
            ['B-01', 'Magnification', '40-1000X', '', '35'],
            ['', 'Head', 'Binocular, 30 inclined'],
        ];

        $products = (new StructureProductSheetAction)->execute($grid, 'NGN');

        $this->assertCount(3, $products);

        $first = $products[0];
        $this->assertSame('XSP-01', $first['name']);
        $this->assertSame('Monocular Microscopes Series', $first['category']);
        $this->assertSame(1550, $first['price_minor']);
        $this->assertTrue($first['has_price']);
        $this->assertStringContainsString('Magnification: 40-640X', $first['specifications']);
        $this->assertStringContainsString('Eyepieces: H10X, WF16X', $first['specifications']);

        $this->assertSame('B-01', $products[2]['name']);
        $this->assertSame('Binocular Microscopes Series', $products[2]['category']);
        $this->assertSame(3500, $products[2]['price_minor']);
    }

    public function test_ai_structuring_is_used_when_configured(): void
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
                    'text' => json_encode(['products' => [[
                        'name' => 'XSP-01 Monocular Microscope',
                        'category' => 'Microscopes',
                        'brand' => 'Acme',
                        'description' => 'A student monocular microscope with 40-640X magnification.',
                        'specifications' => "Magnification: 40-640X\nEyepieces: H10X, WF16X",
                        'sku' => 'XSP-01',
                        'price_minor' => 1550,
                        'has_price' => true,
                        'tags' => ['microscope', 'lab'],
                    ]]]),
                ]],
            ]),
        ]);

        [$user, $tenant] = $this->fixture();

        $this->actingAs($user)
            ->post(route('admin.catalog.products.import-sheet'), [
                'tenant_id' => $tenant->id,
                'sheet' => UploadedFile::fake()->createWithContent('microscopes.csv', "Model,Spec\nXSP-01,stuff\n"),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.anthropic.com'));

        $product = Product::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('XSP-01 Monocular Microscope', $product->name);
        $this->assertSame('Microscopes', $product->category?->name);
        $this->assertSame(1550, (int) $product->base_price_minor);
        $this->assertStringContainsString('Magnification: 40-640X', (string) $product->specifications);
        $this->assertSame('Acme', $product->brand);
    }

    public function test_unreadable_file_reports_a_friendly_message_and_creates_nothing(): void
    {
        Storage::fake('public');
        Http::fake();
        $this->noAi();
        [$user, $tenant] = $this->fixture();

        $this->actingAs($user)
            ->post(route('admin.catalog.products.import-sheet'), [
                'tenant_id' => $tenant->id,
                'sheet' => UploadedFile::fake()->createWithContent('empty.csv', "\n\n"),
            ])
            ->assertRedirect()
            ->assertSessionHas('status', fn ($status): bool => str_contains((string) $status, "couldn't read any products"));

        $this->assertSame(0, Product::query()->where('tenant_id', $tenant->id)->count());
    }
}
