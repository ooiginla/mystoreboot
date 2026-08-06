<?php

declare(strict_types=1);

namespace Modules\Catalog\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Business\Models\OnlineStore;
use Modules\Catalog\Enums\CategoryType;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Enums\TaxBehavior;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductCategory;
use Modules\Catalog\Support\SpreadsheetReader;
use Throwable;

/**
 * Bulk-creates DRAFT products from an uploaded CSV/Excel file. The sheet is read
 * into a grid, AI-structured/cleaned into products (with categories, prices,
 * specs, and generated descriptions where missing), then each product is saved
 * as a Draft for the merchant to review, price-check, and publish. Nothing goes
 * live automatically, and a single bad row never fails the whole import.
 */
final class ImportProductsFromSheetAction
{
    public function __construct(
        private readonly SpreadsheetReader $reader,
        private readonly StructureProductSheetAction $structure,
        private readonly SaveProductAction $saveProduct,
        private readonly EnsureDefaultProductCategoryAction $ensureDefaultCategory,
    ) {}

    /**
     * @return array{count: int, products: list<Product>}
     */
    public function execute(UploadedFile $file, string $tenantId, string $currencyCode = 'NGN'): array
    {
        $grid = $this->reader->read($file->getRealPath(), (string) $file->getClientOriginalName());
        $structured = $this->structure->execute($grid, $currencyCode);

        $products = [];
        $categoryCache = [];

        foreach ($structured as $item) {
            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            try {
                $products[] = $this->saveProduct->execute([
                    'tenant_id' => $tenantId,
                    'name' => $name,
                    'slug' => $this->uniqueSlug($tenantId, $name),
                    'brand' => ($item['brand'] ?? '') !== '' ? $item['brand'] : null,
                    'product_type' => ProductType::Product->value,
                    'description' => ($item['description'] ?? '') !== '' ? $item['description'] : null,
                    'specifications' => ($item['specifications'] ?? '') !== '' ? $item['specifications'] : null,
                    'has_variants' => false,
                    // Price comes back in minor units; SaveProductAction multiplies
                    // by 100, so hand it a major-unit decimal string.
                    'base_price' => number_format(((int) ($item['price_minor'] ?? 0)) / 100, 2, '.', ''),
                    'base_cost_price' => '0',
                    'tax_behavior' => TaxBehavior::Exempt->value,
                    'status' => ProductStatus::Draft->value,
                    'sku' => (string) ($item['sku'] ?? ''),
                    'category_id' => $this->resolveCategoryId($tenantId, (string) ($item['category'] ?? ''), $categoryCache),
                    'new_tags' => collect($item['tags'] ?? [])->implode(','),
                ]);
            } catch (Throwable $exception) {
                Log::warning('Sheet import: a product row could not be saved.', [
                    'tenant_id' => $tenantId,
                    'name' => $name,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return ['count' => count($products), 'products' => $products];
    }

    /**
     * Resolve (and cache) the category for an imported product: match an existing
     * one by name, else create the suggested category and attach it to the
     * storefront(s), else fall back to Uncategorized.
     *
     * @param  array<string, int>  $cache
     */
    private function resolveCategoryId(string $tenantId, string $categoryName, array &$cache): int
    {
        $categoryName = trim($categoryName);

        if ($categoryName === '' || strcasecmp($categoryName, 'uncategorized') === 0) {
            return $cache['__default'] ??= $this->ensureDefaultCategory->execute($tenantId)->id;
        }

        $key = strtolower($categoryName);
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $existing = ProductCategory::query()
            ->where('tenant_id', $tenantId)
            ->where('category_type', CategoryType::Product->value)
            ->whereRaw('lower(name) = ?', [$key])
            ->first();

        if ($existing) {
            return $cache[$key] = (int) $existing->id;
        }

        $category = ProductCategory::query()->create([
            'tenant_id' => $tenantId,
            'parent_id' => null,
            'category_type' => CategoryType::Product->value,
            'name' => Str::limit($categoryName, 120, ''),
            'slug' => $this->uniqueCategorySlug($tenantId, $categoryName),
            'status' => 'active',
        ]);

        OnlineStore::query()
            ->where('tenant_id', $tenantId)
            ->each(fn (OnlineStore $store) => $store->categories()->syncWithoutDetaching([$category->id]));

        return $cache[$key] = (int) $category->id;
    }

    private function uniqueCategorySlug(string $tenantId, string $name): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $counter = 2;

        while (ProductCategory::query()->where('tenant_id', $tenantId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function uniqueSlug(string $tenantId, string $name): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $counter = 2;

        while (Product::query()->where('tenant_id', $tenantId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
