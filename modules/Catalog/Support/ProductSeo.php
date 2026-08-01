<?php

declare(strict_types=1);

namespace Modules\Catalog\Support;

use Illuminate\Support\Str;
use Modules\Business\Models\OnlineStore;
use Modules\Catalog\Models\Product;

/**
 * Builds SEO metadata for the storefront. Prefers the product's cached, possibly
 * AI-enriched `seo` blob, but always falls back to deriving metadata directly
 * from live product data — so every page has correct, current tags even when AI
 * has never run.
 */
final class ProductSeo
{
    /**
     * @return array{title: string, description: string, keywords: string, image_alt: string, tags: list<string>}
     */
    public function forProduct(Product $product, ?OnlineStore $store = null): array
    {
        $stored = is_array($product->seo) ? $product->seo : [];
        $storeName = trim((string) ($store?->store_name ?? ''));
        $name = trim((string) $product->name);
        $category = trim((string) $product->category?->name);
        $brand = trim((string) $product->brand);

        $tags = $this->normalizeList($stored['tags'] ?? null) ?: $product->tags->pluck('name')->map(fn ($t): string => (string) $t)->all();
        $keywords = $this->normalizeList($stored['keywords'] ?? null) ?: $this->deriveKeywords($name, $category, $brand, $tags);

        return [
            'title' => $this->clean($stored['meta_title'] ?? '') ?: $this->deriveTitle($name, $storeName),
            'description' => $this->clean($stored['meta_description'] ?? '') ?: $this->deriveDescription($product, $storeName, $category, $brand),
            'keywords' => implode(', ', $keywords),
            'image_alt' => $this->clean($stored['image_alt'] ?? '') ?: trim($name.($category !== '' ? ' — '.$category : '')),
            'tags' => array_values($tags),
        ];
    }

    /**
     * Store-level SEO for the home page and general storefront pages.
     *
     * @param  list<string>  $categoryNames
     * @return array{title: string, description: string, keywords: string}
     */
    public function forStore(OnlineStore $store, array $categoryNames = []): array
    {
        $name = trim((string) $store->store_name) ?: 'Online store';
        $description = $this->clean((string) $store->description)
            ?: Str::limit("Shop {$name} online — browse products and order with fast checkout and delivery.", 155, '');

        $keywords = $this->deriveKeywords($name, '', '', $categoryNames);

        return [
            'title' => $name,
            'description' => $description,
            'keywords' => implode(', ', $keywords),
        ];
    }

    private function deriveTitle(string $name, string $storeName): string
    {
        $title = $name !== '' ? $name : 'Product';

        if ($storeName !== '') {
            $title .= ' | '.$storeName;
        }

        return Str::limit($title, 60, '');
    }

    private function deriveDescription(Product $product, string $storeName, string $category, string $brand): string
    {
        $body = trim(preg_replace('/\s+/', ' ', strip_tags((string) $product->description)) ?? '');

        if ($body === '') {
            $bits = array_filter([
                'Buy '.trim((string) $product->name),
                $brand !== '' ? 'by '.$brand : null,
                $category !== '' ? 'in '.$category : null,
                $storeName !== '' ? 'at '.$storeName : null,
            ]);
            $body = implode(' ', $bits).'. Order online with fast checkout and delivery.';
        }

        return Str::limit($body, 155, '');
    }

    /**
     * @param  list<string>  $tags
     * @return list<string>
     */
    private function deriveKeywords(string $name, string $category, string $brand, array $tags): array
    {
        return collect([$name, $category, $brand])
            ->merge($tags)
            ->flatMap(fn (string $value): array => array_merge([$value], preg_split('/[\s,]+/', $value) ?: []))
            ->map(fn (string $value): string => strtolower(trim($value)))
            ->filter(fn (string $value): bool => strlen($value) >= 3)
            ->unique()
            ->take(12)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function normalizeList(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[,\n]+/', $value) ?: [];
        }

        return collect(is_array($value) ? $value : [])
            ->map(fn ($v): string => trim((string) $v))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function clean(mixed $value): string
    {
        return trim(strip_tags((string) $value));
    }
}
