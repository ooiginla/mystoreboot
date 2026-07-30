<?php

declare(strict_types=1);

namespace Modules\Catalog\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Enums\TaxBehavior;
use Modules\Catalog\Models\Product;

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
                // Keep the AI's category guess and tags as tags so nothing is lost;
                // the merchant assigns a real category during review.
                'new_tags' => collect($draft['tags'])
                    ->when($draft['category'] !== '', fn ($tags) => $tags->push($draft['category']))
                    ->implode(','),
            ]);
        }

        return ['count' => count($products), 'products' => $products];
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
