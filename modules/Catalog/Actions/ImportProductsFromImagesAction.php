<?php

declare(strict_types=1);

namespace Modules\Catalog\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Modules\Business\Models\OnlineStore;
use Modules\Catalog\Enums\CategoryType;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Enums\TaxBehavior;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductCategory;

/**
 * Bulk-creates DRAFT products from uploaded photos. Each photo is drafted by AI
 * (name/description/price/category/tags) and saved as a Draft product with the
 * photo attached, ready for the merchant to review, price, and publish. Drafts
 * never go live automatically — the merchant activates them from the catalog.
 */
final class ImportProductsFromImagesAction
{
    public function __construct(
        private readonly DraftProductFromImageAction $draftFromImage,
        private readonly SaveProductAction $saveProduct,
        private readonly EnsureDefaultProductCategoryAction $ensureDefaultCategory,
    ) {}

    /**
     * @param  array<int, UploadedFile>  $images
     * @return array{count: int, products: list<Product>}
     */
    public function execute(array $images, string $tenantId, string $currencyCode = 'NGN'): array
    {
        $products = [];

        foreach ($images as $image) {
            if (! $image instanceof UploadedFile || ! $image->isValid()) {
                continue;
            }

            $draft = $this->draftFromImage->execute(
                (string) file_get_contents($image->getRealPath()),
                (string) $image->getMimeType(),
                null,
                $currencyCode,
            );

            $name = $draft['name'] !== '' ? $draft['name'] : $this->fallbackName($image);

            $products[] = $this->saveProduct->execute([
                'tenant_id' => $tenantId,
                'name' => $name,
                'slug' => $this->uniqueSlug($tenantId, $name),
                'product_type' => ProductType::Product->value,
                'description' => $draft['description'] ?: null,
                'has_variants' => false,
                // Price comes back in minor units; SaveProductAction re-multiplies
                // by 100, so hand it a major-unit decimal string.
                'base_price' => number_format($draft['price_minor'] / 100, 2, '.', ''),
                'base_cost_price' => '0',
                'tax_behavior' => TaxBehavior::Exempt->value,
                'status' => ProductStatus::Draft->value,
                'sku' => '',
                'image' => $image,
                // Every imported product gets a category: an existing match, a new
                // AI-suggested one, or Uncategorized when it's unclear / AI is off.
                'category_id' => $this->resolveCategoryId($tenantId, $draft['category']),
                'new_tags' => collect($draft['tags'])->implode(','),
            ]);
        }

        return ['count' => count($products), 'products' => $products];
    }

    /**
     * Resolve the category for an imported product. Matches an existing product
     * category by name (case-insensitive); otherwise creates the AI-suggested
     * one and attaches it to the tenant's storefront(s). Falls back to
     * Uncategorized when the AI gave no usable category.
     */
    private function resolveCategoryId(string $tenantId, string $categoryName): int
    {
        $categoryName = trim($categoryName);

        if ($categoryName === '' || strcasecmp($categoryName, 'uncategorized') === 0) {
            return $this->ensureDefaultCategory->execute($tenantId)->id;
        }

        $existing = ProductCategory::query()
            ->where('tenant_id', $tenantId)
            ->where('category_type', CategoryType::Product->value)
            ->whereRaw('lower(name) = ?', [strtolower($categoryName)])
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        $category = ProductCategory::query()->create([
            'tenant_id' => $tenantId,
            'parent_id' => null,
            'category_type' => CategoryType::Product->value,
            'name' => $categoryName,
            'slug' => $this->uniqueCategorySlug($tenantId, $categoryName),
            'status' => 'active',
        ]);

        // Attach to storefront(s) so imported products remain visible online.
        OnlineStore::query()
            ->where('tenant_id', $tenantId)
            ->each(fn (OnlineStore $store) => $store->categories()->syncWithoutDetaching([$category->id]));

        return (int) $category->id;
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

    private function fallbackName(UploadedFile $image): string
    {
        $base = Str::of($image->getClientOriginalName())
            ->beforeLast('.')
            ->replace(['-', '_'], ' ')
            ->squish()
            ->title()
            ->toString();

        return $base !== '' ? $base : 'Imported product';
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
