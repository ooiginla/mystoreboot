<?php

declare(strict_types=1);

namespace Modules\Sales\Support;

use Illuminate\Support\Collection;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Enums\TaxBehavior;
use Modules\Catalog\Models\ProductVariant;
use Modules\Sales\Models\SalesTillSession;

final class PosCatalog
{
    /**
     * @return array{items: list<array<string, int|float|string|null>>, categories: list<array{id: int, name: string}>}
     */
    public function forTill(SalesTillSession $till): array
    {
        $variants = ProductVariant::query()
            ->with(['product.category', 'product.taxes'])
            ->where('tenant_id', $till->tenant_id)
            ->where('status', ProductStatus::Active->value)
            ->whereHas('product', fn ($query) => $query
                ->where('product_type', ProductType::Product->value)
                ->where('status', ProductStatus::Active->value))
            ->orderBy('sku')
            ->get();

        $items = $variants->map(function (ProductVariant $variant): array {
            $product = $variant->product;
            $selectedTaxRate = $product?->taxes?->sum(
                fn ($tax): float => (float) $tax->rate,
            ) ?? 0.0;
            $taxRate = $variant->tax_behavior === TaxBehavior::Taxable
                ? (float) ($selectedTaxRate > 0 ? $selectedTaxRate : ($variant->tax_rate ?? $product?->tax_rate ?? 0))
                : 0.0;
            $name = (string) $product?->name;

            if (filled($variant->variant_name) && $variant->variant_name !== 'Default') {
                $name .= ' · '.$variant->variant_name;
            }

            $imagePath = $variant->image_path ?: $product?->image_path;
            $category = $product?->category;

            return [
                'id' => (int) $variant->id,
                'name' => $name,
                'sku' => (string) $variant->sku,
                'barcode' => $variant->barcode,
                'brand' => $product?->brand,
                'category_id' => $category?->id ? (int) $category->id : 0,
                'category_name' => $category?->name,
                'price' => ((int) $variant->selling_price_minor) / 100,
                'tax_rate' => $taxRate,
                'image_url' => $imagePath ? asset('storage/'.ltrim((string) $imagePath, '/')) : null,
                'hue' => (int) (sprintf('%u', crc32($name !== '' ? $name : (string) $variant->sku)) % 360),
            ];
        })->values();

        return [
            'items' => $items->all(),
            'categories' => $this->categories($items)->all(),
        ];
    }

    /**
     * @param  Collection<int, array<string, int|float|string|null>>  $items
     * @return Collection<int, array{id: int, name: string}>
     */
    private function categories(Collection $items): Collection
    {
        return $items
            ->filter(fn (array $item): bool => (int) $item['category_id'] > 0 && filled($item['category_name']))
            ->map(fn (array $item): array => [
                'id' => (int) $item['category_id'],
                'name' => (string) $item['category_name'],
            ])
            ->unique('id')
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }
}
