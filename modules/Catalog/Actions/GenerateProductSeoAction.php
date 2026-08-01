<?php

declare(strict_types=1);

namespace Modules\Catalog\Actions;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Business\Models\OnlineStore;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Support\ProductSeo;
use Throwable;

/**
 * Generates and caches SEO metadata (meta title, meta description, keywords,
 * tags, image alt) on a product's `seo` column using the configured AI provider.
 *
 * Always stores something: if AI is unavailable or errors, it caches metadata
 * derived directly from the product (via ProductSeo). Rendering never depends on
 * this — the storefront derives SEO live as a fallback — this just upgrades the
 * copy and lets a nightly job keep it fresh.
 */
final class GenerateProductSeoAction
{
    public function __construct(private readonly ProductSeo $productSeo) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(Product $product, ?OnlineStore $store = null): array
    {
        $store ??= OnlineStore::query()->where('tenant_id', $product->tenant_id)->first();
        $provider = strtolower((string) config('services.ai.provider', 'anthropic'));

        $seo = null;

        try {
            $text = $provider === 'openai'
                ? $this->viaOpenAi($product, $store)
                : $this->viaAnthropic($product, $store);
            $seo = $this->parse($text);
        } catch (Throwable $exception) {
            Log::warning('Product SEO generation fell back to direct.', ['product_id' => $product->id, 'message' => $exception->getMessage()]);
        }

        $seo ??= $this->directSeo($product, $store);
        $seo['generated_at'] = now()->toIso8601String();

        // Don't bump updated_at — otherwise the nightly staleness check (updated_at
        // vs seo.generated_at) would re-generate every product every run.
        Product::withoutTimestamps(function () use ($product, $seo): void {
            $product->forceFill(['seo' => $seo])->save();
        });

        return $seo;
    }

    private function viaAnthropic(Product $product, ?OnlineStore $store): ?string
    {
        $apiKey = (string) config('services.anthropic.api_key');

        if ($apiKey === '') {
            return null;
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => (string) config('services.anthropic.version', '2023-06-01'),
            'content-type' => 'application/json',
        ])
            ->timeout(60)
            ->post(rtrim((string) config('services.anthropic.base_url'), '/').'/v1/messages', [
                'model' => (string) config('services.anthropic.model', 'claude-opus-5'),
                'max_tokens' => 700,
                'output_config' => ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => $this->schema()]],
                'messages' => [['role' => 'user', 'content' => $this->prompt($product, $store)]],
            ]);

        if (! $response->successful()) {
            return null;
        }

        return collect($response->json('content') ?? [])->firstWhere('type', 'text')['text'] ?? null;
    }

    private function viaOpenAi(Product $product, ?OnlineStore $store): ?string
    {
        $apiKey = (string) config('services.openai.api_key');

        if ($apiKey === '') {
            return null;
        }

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post(rtrim((string) config('services.openai.base_url'), '/').'/v1/chat/completions', [
                'model' => (string) config('services.openai.model', 'gpt-4o-mini'),
                'messages' => [['role' => 'user', 'content' => $this->prompt($product, $store)]],
                'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => 'product_seo', 'strict' => true, 'schema' => $this->schema()]],
            ]);

        if (! $response->successful()) {
            return null;
        }

        return $response->json('choices.0.message.content');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parse(?string $text): ?array
    {
        $data = is_string($text) ? json_decode($text, true) : null;

        if (! is_array($data) || trim((string) ($data['meta_title'] ?? '')) === '') {
            return null;
        }

        return [
            'meta_title' => trim((string) $data['meta_title']),
            'meta_description' => trim((string) ($data['meta_description'] ?? '')),
            'keywords' => $this->list($data['keywords'] ?? []),
            'tags' => $this->list($data['tags'] ?? []),
            'image_alt' => trim((string) ($data['image_alt'] ?? '')),
            'source' => 'ai',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function directSeo(Product $product, ?OnlineStore $store): array
    {
        $seo = $this->productSeo->forProduct($product, $store);

        return [
            'meta_title' => $seo['title'],
            'meta_description' => $seo['description'],
            'keywords' => array_values(array_filter(array_map('trim', explode(',', $seo['keywords'])))),
            'tags' => $seo['tags'],
            'image_alt' => $seo['image_alt'],
            'source' => 'direct',
        ];
    }

    private function prompt(Product $product, ?OnlineStore $store): string
    {
        $storeName = trim((string) ($store?->store_name ?? ''));
        $lines = array_filter([
            'Store: '.($storeName !== '' ? $storeName : 'an online store').'.',
            'Product name: '.trim((string) $product->name).'.',
            $product->brand ? 'Brand: '.$product->brand.'.' : null,
            $product->category?->name ? 'Category: '.$product->category->name.'.' : null,
            $product->description ? 'Description: '.trim(strip_tags((string) $product->description)).'.' : null,
        ]);

        return implode("\n", $lines)."\n\n".implode(' ', [
            'Write SEO metadata that helps this product rank and get clicks on Google.',
            'meta_title: <= 60 characters, includes the product and store.',
            'meta_description: 140-155 characters, compelling and specific, no quotes.',
            'keywords: 6-12 realistic search terms buyers would type.',
            'tags: 3-6 short lowercase category/style tags.',
            'image_alt: a short, descriptive alt text for the product photo.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'meta_title' => ['type' => 'string'],
                'meta_description' => ['type' => 'string'],
                'keywords' => ['type' => 'array', 'items' => ['type' => 'string']],
                'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                'image_alt' => ['type' => 'string'],
            ],
            'required' => ['meta_title', 'meta_description', 'keywords', 'tags', 'image_alt'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @return list<string>
     */
    private function list(mixed $value): array
    {
        return collect(is_array($value) ? $value : [])
            ->map(fn ($v): string => trim((string) $v))
            ->filter()
            ->unique()
            ->take(12)
            ->values()
            ->all();
    }
}
