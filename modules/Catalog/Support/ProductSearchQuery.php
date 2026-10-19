<?php

declare(strict_types=1);

namespace Modules\Catalog\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductSearchDocument;

final class ProductSearchQuery
{
    /** @var array<string, bool> */
    private array $documentCoverage = [];

    public function apply(Builder $query, string $tenantId, string $search): Builder
    {
        $search = preg_replace('/\s+/u', ' ', trim($search)) ?? '';

        if ($search === '') {
            return $query->orderByDesc('products.id');
        }

        $prefix = $this->like($search).'%';
        $contains = '%'.$this->like($search).'%';
        $hasCompleteDocuments = $this->hasCompleteDocuments($tenantId);
        $driver = DB::getDriverName();
        $booleanSearch = $this->booleanSearch($search);
        $useMysqlFullText = $driver === 'mysql' && $hasCompleteDocuments && $booleanSearch !== '';
        $useDocumentLike = $driver !== 'mysql' && $hasCompleteDocuments;

        if ($useMysqlFullText || $useDocumentLike) {
            $query->leftJoin('product_search_documents as product_search', function ($join): void {
                $join->on('product_search.product_id', '=', 'products.id')
                    ->on('product_search.tenant_id', '=', 'products.tenant_id');
            })->select('products.*');
        }

        $query->where(function (Builder $matches) use ($search, $prefix, $contains, $useMysqlFullText, $useDocumentLike, $booleanSearch): void {
            $matches
                ->where('products.name', 'like', $prefix)
                ->orWhere('products.brand', 'like', $prefix)
                ->orWhereHas('variants', fn ($variant) => $variant
                    ->where('sku', $search)
                    ->orWhere('barcode', $search)
                    ->orWhere('sku', 'like', $prefix)
                    ->orWhere('barcode', 'like', $prefix)
                    ->orWhere('variant_name', 'like', $prefix));

            if ($useMysqlFullText) {
                $matches->orWhereRaw('MATCH(product_search.search_text) AGAINST (? IN BOOLEAN MODE)', [$booleanSearch]);
            } elseif ($useDocumentLike) {
                $matches->orWhere('product_search.search_text', 'like', $contains);
            } else {
                $this->applyLegacyContainsFallback($matches, $contains);
            }
        });

        $query->orderByRaw(
            'CASE
                WHEN EXISTS (
                    SELECT 1 FROM product_variants exact_variant
                    WHERE exact_variant.product_id = products.id
                      AND exact_variant.deleted_at IS NULL
                      AND (exact_variant.sku = ? OR exact_variant.barcode = ?)
                ) THEN 0
                WHEN products.name = ? THEN 1
                WHEN products.name LIKE ? THEN 2
                WHEN EXISTS (
                    SELECT 1 FROM product_variants prefix_variant
                    WHERE prefix_variant.product_id = products.id
                      AND prefix_variant.deleted_at IS NULL
                      AND (prefix_variant.sku LIKE ? OR prefix_variant.barcode LIKE ?)
                ) THEN 3
                ELSE 4
            END',
            [$search, $search, $search, $prefix, $prefix, $prefix],
        );

        if ($useMysqlFullText) {
            $query->orderByRaw('MATCH(product_search.search_text) AGAINST (? IN BOOLEAN MODE) DESC', [$booleanSearch]);
        }

        return $query->orderByDesc('products.id');
    }

    private function applyLegacyContainsFallback(Builder $query, string $contains): void
    {
        $query->orWhere('products.name', 'like', $contains)
            ->orWhere('products.brand', 'like', $contains)
            ->orWhereHas('category', fn ($category) => $category->where('name', 'like', $contains))
            ->orWhereHas('variants', fn ($variant) => $variant
                ->where('sku', 'like', $contains)
                ->orWhere('barcode', 'like', $contains)
                ->orWhere('variant_name', 'like', $contains))
            ->orWhereHas('suppliers', fn ($supplier) => $supplier->where('name', 'like', $contains))
            ->orWhereHas('tags', fn ($tag) => $tag->where('name', 'like', $contains))
            ->orWhereHas('attributeValues', fn ($attribute) => $attribute->where('value', 'like', $contains));
    }

    private function hasCompleteDocuments(string $tenantId): bool
    {
        if (! Schema::hasTable('product_search_documents')) {
            return false;
        }

        return $this->documentCoverage[$tenantId] ??= ProductSearchDocument::query()
            ->where('tenant_id', $tenantId)
            ->count() === Product::query()->where('tenant_id', $tenantId)->count();
    }

    private function booleanSearch(string $search): string
    {
        return collect(preg_split('/[^\pL\pN]+/u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [])
            // InnoDB's default FULLTEXT minimum token length is three characters.
            // Shorter searches stay on the safe relationship/LIKE fallback.
            ->filter(fn (string $term): bool => mb_strlen($term) >= 3)
            ->map(fn (string $term): string => '+'.$term.'*')
            ->implode(' ');
    }

    private function like(string $value): string
    {
        return addcslashes($value, '\\%_');
    }
}
