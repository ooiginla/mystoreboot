<?php

declare(strict_types=1);

namespace Modules\Reseller\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Modules\Reseller\Data\RecoveredProductData;
use Modules\Reseller\Enums\ProductAvailability;

final class ProductPageExtractor
{
    public function extract(string $html, string $pageUrl, string $fallbackStoreName): ?RecoveredProductData
    {
        $document = $this->document($html);
        $xpath = new DOMXPath($document);

        foreach ($xpath->query('//script[contains(translate(@type, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "ld+json")]') ?: [] as $script) {
            $decoded = json_decode(trim($script->textContent), true);

            foreach ($this->productNodes($decoded) as $product) {
                $data = $this->fromJsonLd($product, $pageUrl, $fallbackStoreName);

                if ($data) {
                    return $data;
                }
            }
        }

        return $this->fromOpenGraph($xpath, $pageUrl, $fallbackStoreName);
    }

    /** @return list<string> */
    public function links(string $html, string $pageUrl): array
    {
        $xpath = new DOMXPath($this->document($html));
        $links = [];

        foreach ($xpath->query('//a[@href]') ?: [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $resolved = $this->resolveUrl($node->getAttribute('href'), $pageUrl);

            if ($resolved !== null) {
                $links[] = $resolved;
            }
        }

        return array_values(array_unique($links));
    }

    private function document(string $html): DOMDocument
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $document;
    }

    /** @return list<array<string, mixed>> */
    private function productNodes(mixed $node): array
    {
        if (! is_array($node)) {
            return [];
        }

        $type = $node['@type'] ?? null;
        $types = is_array($type) ? $type : [$type];
        $products = in_array('Product', $types, true) ? [$node] : [];

        foreach (['@graph', 'itemListElement'] as $childKey) {
            foreach ((array) ($node[$childKey] ?? []) as $child) {
                $products = [...$products, ...$this->productNodes(is_array($child) && isset($child['item']) ? $child['item'] : $child)];
            }
        }

        return $products;
    }

    /** @param array<string, mixed> $product */
    private function fromJsonLd(array $product, string $pageUrl, string $fallbackStoreName): ?RecoveredProductData
    {
        $name = trim((string) ($product['name'] ?? ''));
        $offer = $this->offer($product['offers'] ?? []);
        $price = $offer['price'] ?? $offer['lowPrice'] ?? null;
        $currency = strtoupper(trim((string) ($offer['priceCurrency'] ?? '')));

        if ($name === '' || ! is_numeric($price) || strlen($currency) !== 3) {
            return null;
        }

        $image = $product['image'] ?? null;
        $imageUrl = is_array($image) ? ($image['url'] ?? $image[0] ?? null) : $image;
        $brand = $product['brand'] ?? null;
        $brandName = is_array($brand) ? ($brand['name'] ?? null) : $brand;
        $seller = $offer['seller'] ?? $product['manufacturer'] ?? null;
        $sellerName = is_array($seller) ? ($seller['name'] ?? null) : $seller;
        $url = $this->resolveUrl((string) ($product['url'] ?? $pageUrl), $pageUrl) ?? $pageUrl;
        $variants = collect((array) ($product['hasVariant'] ?? []))
            ->filter(fn (mixed $variant): bool => is_array($variant))
            ->values()
            ->all();

        return new RecoveredProductData(
            url: $url,
            name: $name,
            imageUrl: is_string($imageUrl) ? $this->resolveUrl($imageUrl, $pageUrl) : null,
            priceMinor: $this->minor($price),
            previousPriceMinor: isset($offer['highPrice']) && is_numeric($offer['highPrice']) ? $this->minor($offer['highPrice']) : null,
            currencyCode: $currency,
            availability: $this->availability((string) ($offer['availability'] ?? '')),
            description: $this->nullableString($product['description'] ?? null),
            brand: $this->nullableString($brandName),
            category: $this->nullableString($product['category'] ?? null),
            sku: $this->nullableString($product['sku'] ?? null),
            variants: $variants,
            sourceStoreName: $this->nullableString($sellerName) ?? $fallbackStoreName,
            sourceReference: $this->nullableString($product['productID'] ?? $product['sku'] ?? null),
        );
    }

    private function fromOpenGraph(DOMXPath $xpath, string $pageUrl, string $fallbackStoreName): ?RecoveredProductData
    {
        $name = $this->meta($xpath, 'og:title');
        $price = $this->meta($xpath, 'product:price:amount');
        $currency = strtoupper((string) $this->meta($xpath, 'product:price:currency'));

        if (! $name || ! is_numeric($price) || strlen($currency) !== 3) {
            return null;
        }

        return new RecoveredProductData(
            url: $pageUrl,
            name: $name,
            imageUrl: $this->meta($xpath, 'og:image'),
            priceMinor: $this->minor($price),
            previousPriceMinor: null,
            currencyCode: $currency,
            availability: $this->availability((string) $this->meta($xpath, 'product:availability')),
            description: $this->meta($xpath, 'og:description'),
            brand: $this->meta($xpath, 'product:brand'),
            category: null,
            sku: $this->meta($xpath, 'product:retailer_item_id'),
            variants: [],
            sourceStoreName: $fallbackStoreName,
            sourceReference: $this->meta($xpath, 'product:retailer_item_id'),
        );
    }

    /** @return array<string, mixed> */
    private function offer(mixed $offers): array
    {
        if (! is_array($offers)) {
            return [];
        }

        if (array_is_list($offers)) {
            return is_array($offers[0] ?? null) ? $offers[0] : [];
        }

        return $offers;
    }

    private function meta(DOMXPath $xpath, string $property): ?string
    {
        $nodes = $xpath->query(sprintf('//meta[@property="%s" or @name="%s"]/@content', $property, $property));

        return $this->nullableString($nodes?->item(0)?->nodeValue);
    }

    private function minor(mixed $amount): int
    {
        return max(0, (int) round((float) preg_replace('/[^0-9.\-]/', '', (string) $amount) * 100));
    }

    private function availability(string $value): string
    {
        $value = strtolower($value);

        return match (true) {
            str_contains($value, 'instock'), str_contains($value, 'in_stock') => ProductAvailability::InStock->value,
            str_contains($value, 'outofstock'), str_contains($value, 'out_of_stock'), str_contains($value, 'soldout') => ProductAvailability::OutOfStock->value,
            default => ProductAvailability::Unknown->value,
        };
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : $value;
    }

    private function resolveUrl(string $candidate, string $base): ?string
    {
        $candidate = trim($candidate);

        if ($candidate === '' || str_starts_with($candidate, '#') || preg_match('/^(?:mailto|tel|javascript):/i', $candidate)) {
            return null;
        }

        if (filter_var($candidate, FILTER_VALIDATE_URL)) {
            return preg_replace('/#.*$/', '', $candidate);
        }

        $parts = parse_url($base);

        if (! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (str_starts_with($candidate, '//')) {
            return $parts['scheme'].':'.preg_replace('/#.*$/', '', $candidate);
        }

        if (str_starts_with($candidate, '/')) {
            return $origin.preg_replace('/#.*$/', '', $candidate);
        }

        $path = (string) ($parts['path'] ?? '/');
        $directory = str_ends_with($path, '/') ? $path : dirname($path).'/';

        return $origin.'/'.ltrim($directory.$candidate, '/');
    }
}
