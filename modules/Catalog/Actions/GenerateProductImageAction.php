<?php

declare(strict_types=1);

namespace Modules\Catalog\Actions;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Catalog\Models\Product;
use RuntimeException;

/**
 * Generates a product photo with OpenAI's image API when a product has none,
 * stores it on the public disk, and sets it as the product's image.
 *
 * Image generation is OpenAI-only (Anthropic has no text-to-image API), so this
 * always uses the OpenAI key regardless of AI_PROVIDER. Throws a RuntimeException
 * with a friendly message when the key is missing or the request fails.
 */
final class GenerateProductImageAction
{
    public function execute(Product $product): string
    {
        $apiKey = (string) config('services.openai.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('AI image generation requires an OpenAI API key. Add OPENAI_API_KEY to enable it.');
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(120)
                ->post(rtrim((string) config('services.openai.base_url'), '/').'/v1/images/generations', [
                    'model' => (string) config('services.openai.image_model', 'gpt-image-1'),
                    'prompt' => $this->prompt($product),
                    'size' => '1024x1024',
                    'n' => 1,
                ]);
        } catch (\Throwable $exception) {
            Log::warning('AI product image request errored.', ['message' => $exception->getMessage()]);

            throw new RuntimeException('Could not reach the image service. Please try again.');
        }

        if (! $response->successful()) {
            Log::warning('AI product image request failed.', ['status' => $response->status(), 'error' => substr((string) $response->body(), 0, 300)]);

            throw new RuntimeException('The image service could not generate an image right now. Please try again.');
        }

        $bytes = $this->extractImageBytes($response->json('data.0') ?? []);

        if ($bytes === null) {
            throw new RuntimeException('The image service returned no image. Please try again.');
        }

        $path = "tenants/{$product->tenant_id}/catalog/products/ai-".Str::lower(Str::random(24)).'.png';
        Storage::disk('public')->put($path, $bytes);

        $product->forceFill(['image_path' => $path])->save();

        return $path;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractImageBytes(array $data): ?string
    {
        // gpt-image-1 returns base64; dall-e-3 may return a URL — handle both.
        if (! empty($data['b64_json'])) {
            $decoded = base64_decode((string) $data['b64_json'], true);

            return $decoded === false ? null : $decoded;
        }

        if (! empty($data['url'])) {
            $image = Http::timeout(60)->get((string) $data['url']);

            return $image->successful() ? $image->body() : null;
        }

        return null;
    }

    private function prompt(Product $product): string
    {
        $name = trim((string) $product->name) ?: 'a retail product';
        $description = trim((string) $product->description);
        $category = trim((string) $product->category?->name);

        return trim(implode(' ', array_filter([
            "Professional e-commerce product photo of {$name}.",
            $description !== '' ? $description : null,
            $category !== '' ? "Category: {$category}." : null,
            'Centered on a clean, softly-lit plain background. No text, no watermark, no logo.',
        ])));
    }
}
