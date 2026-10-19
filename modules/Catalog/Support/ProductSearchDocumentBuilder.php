<?php

declare(strict_types=1);

namespace Modules\Catalog\Support;

use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductSearchDocument;

final class ProductSearchDocumentBuilder
{
    public function __construct(private readonly CatalogRevisionManager $revisions) {}

    public function rebuild(Product $product, bool $bumpRevision = true): void
    {
        if (! Schema::hasTable('product_search_documents')) {
            return;
        }

        if ($product->trashed()) {
            ProductSearchDocument::query()->where('product_id', $product->id)->delete();

            if ($bumpRevision) {
                $this->revisions->bump((string) $product->tenant_id);
            }

            return;
        }

        $product->load([
            'attributeValues.definition',
            'category',
            'suppliers',
            'tags',
            'variants',
        ]);

        $customFields = collect((array) $product->custom_fields)
            ->flatMap(fn (mixed $field): array => is_array($field)
                ? [(string) ($field['key'] ?? ''), ...array_map('strval', (array) ($field['values'] ?? []))]
                : [(string) $field]);

        $parts = collect([
            $product->name,
            $product->brand,
            strip_tags((string) $product->description),
            $this->stringValue($product->specifications),
            $product->category?->name,
            $product->variants->pluck('variant_name')->implode(' '),
            $product->variants->pluck('sku')->implode(' '),
            $product->variants->pluck('barcode')->filter()->implode(' '),
            $product->suppliers->pluck('name')->implode(' '),
            $product->tags->pluck('name')->implode(' '),
            $product->attributeValues->pluck('value')->implode(' '),
            $product->attributeValues->pluck('definition.name')->filter()->implode(' '),
            $customFields->implode(' '),
        ])
            ->filter(fn (mixed $value): bool => filled($value))
            ->map(fn (mixed $value): string => preg_replace('/\s+/u', ' ', trim((string) $value)) ?? '')
            ->filter()
            ->unique(fn (string $value): string => mb_strtolower($value))
            ->implode(' ');

        ProductSearchDocument::query()->updateOrCreate(
            ['product_id' => $product->id],
            ['tenant_id' => $product->tenant_id, 'search_text' => $parts],
        );

        if ($bumpRevision) {
            $this->revisions->bump((string) $product->tenant_id);
        }
    }

    private function stringValue(mixed $value): string
    {
        if (is_array($value)) {
            return collect($value)->flatten()->map(fn (mixed $part): string => (string) $part)->implode(' ');
        }

        return (string) $value;
    }
}
