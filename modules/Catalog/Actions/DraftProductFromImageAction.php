<?php

declare(strict_types=1);

namespace Modules\Catalog\Actions;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turns a product photo (and any caption) into draft catalog fields using a
 * vision model. Returns name, description, category, a price guess, and tags.
 *
 * Supports two providers behind the AI_PROVIDER switch — "anthropic" (Claude)
 * and "openai" (ChatGPT). This never throws: a bulk import of many photos must
 * not fail wholesale on a single API hiccup, and it must still work when no key
 * is configured (it then returns a blank draft for the merchant to complete).
 */
final class DraftProductFromImageAction
{
    /**
     * @return array{name: string, description: string, category: string, price_minor: int, has_price: bool, tags: list<string>}
     */
    public function execute(string $imageContents, string $mimeType, ?string $caption = null, string $currencyCode = 'NGN'): array
    {
        $provider = strtolower((string) config('services.ai.provider', 'anthropic'));
        $mimeType = $this->normalizeMediaType($mimeType);
        $base64 = base64_encode($imageContents);
        $prompt = $this->prompt($caption, $currencyCode);

        try {
            $text = $provider === 'openai'
                ? $this->draftViaOpenAi($base64, $mimeType, $prompt)
                : $this->draftViaAnthropic($base64, $mimeType, $prompt);
        } catch (Throwable $exception) {
            Log::warning('AI product draft errored.', ['provider' => $provider, 'message' => $exception->getMessage()]);

            return $this->blankDraft();
        }

        return $this->normalize($text);
    }

    private function draftViaAnthropic(string $base64, string $mimeType, string $prompt): ?string
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
                'max_tokens' => 1024,
                'output_config' => [
                    'effort' => 'low',
                    'format' => ['type' => 'json_schema', 'schema' => $this->schema()],
                ],
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mimeType, 'data' => $base64]],
                        ['type' => 'text', 'text' => $prompt],
                    ],
                ]],
            ]);

        if (! $this->ok($response, 'anthropic') || ($response->json('stop_reason') === 'refusal')) {
            return null;
        }

        return collect($response->json('content') ?? [])->firstWhere('type', 'text')['text'] ?? null;
    }

    private function draftViaOpenAi(string $base64, string $mimeType, string $prompt): ?string
    {
        $apiKey = (string) config('services.openai.api_key');

        if ($apiKey === '') {
            return null;
        }

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post(rtrim((string) config('services.openai.base_url'), '/').'/v1/chat/completions', [
                'model' => (string) config('services.openai.model', 'gpt-4o-mini'),
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        ['type' => 'image_url', 'image_url' => ['url' => "data:{$mimeType};base64,{$base64}"]],
                    ],
                ]],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => ['name' => 'product_draft', 'strict' => true, 'schema' => $this->schema()],
                ],
            ]);

        if (! $this->ok($response, 'openai') || filled($response->json('choices.0.message.refusal'))) {
            return null;
        }

        return $response->json('choices.0.message.content');
    }

    private function ok(Response $response, string $provider): bool
    {
        if ($response->successful()) {
            return true;
        }

        Log::warning('AI product draft request failed.', [
            'provider' => $provider,
            'status' => $response->status(),
            'error' => substr((string) $response->body(), 0, 300),
        ]);

        return false;
    }

    /**
     * @return array{name: string, description: string, category: string, price_minor: int, has_price: bool, tags: list<string>}
     */
    private function normalize(?string $text): array
    {
        $data = is_string($text) ? json_decode($text, true) : null;

        if (! is_array($data)) {
            return $this->blankDraft();
        }

        return [
            'name' => trim((string) ($data['name'] ?? '')),
            'description' => trim((string) ($data['description'] ?? '')),
            'category' => trim((string) ($data['category'] ?? '')),
            'price_minor' => max(0, (int) ($data['price_minor'] ?? 0)),
            'has_price' => (bool) ($data['has_price'] ?? false),
            'tags' => collect($data['tags'] ?? [])
                ->map(fn ($tag): string => trim((string) $tag))
                ->filter()
                ->take(5)
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{name: string, description: string, category: string, price_minor: int, has_price: bool, tags: list<string>}
     */
    private function blankDraft(): array
    {
        return [
            'name' => '',
            'description' => '',
            'category' => '',
            'price_minor' => 0,
            'has_price' => false,
            'tags' => [],
        ];
    }

    private function prompt(?string $caption, string $currencyCode): string
    {
        $caption = trim((string) $caption);
        $captionLine = $caption !== ''
            ? "The seller's caption for this photo is: \"{$caption}\". Use it, especially for the price."
            : 'There is no caption.';

        return <<<PROMPT
        You are an e-commerce cataloguer preparing a product listing from a seller's photo.
        {$captionLine}

        Produce:
        - name: a concise, specific product name (no emojis, no price).
        - description: 1-2 plain sentences a shopper would find useful.
        - category: a short category label (e.g. "Footwear", "Skincare", "Beverages").
        - price_minor: the price in MINOR units of {$currencyCode} (e.g. 15000.00 -> 1500000). Use the caption if it states a price such as "15k" or "₦15,000". If no price is stated or visible, use 0.
        - has_price: true only if you found an explicit price.
        - tags: up to 5 short lowercase keywords.

        If the photo is not a sellable product, still return your best guess with has_price false.
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'category' => ['type' => 'string'],
                'price_minor' => ['type' => 'integer'],
                'has_price' => ['type' => 'boolean'],
                'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['name', 'description', 'category', 'price_minor', 'has_price', 'tags'],
            'additionalProperties' => false,
        ];
    }

    private function normalizeMediaType(string $mimeType): string
    {
        $mimeType = strtolower(trim($mimeType));

        return in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)
            ? $mimeType
            : 'image/jpeg';
    }
}
