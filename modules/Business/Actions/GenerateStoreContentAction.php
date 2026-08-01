<?php

declare(strict_types=1);

namespace Modules\Business\Actions;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Business\Models\OnlineStore;
use Modules\Tenancy\Models\Tenant;
use RuntimeException;
use Throwable;

/**
 * Generates store copy (the store description and the About/Terms/Returns/
 * Privacy/Shipping pages) with the configured AI provider from a short prompt.
 *
 * The description comes back as plain text; the pages come back as simple HTML
 * limited to the tags the storefront's SafeRichText sanitizer allows.
 */
final class GenerateStoreContentAction
{
    private const PLAIN_FIELDS = ['description'];

    /**
     * @return array{content: string, format: string}
     */
    public function execute(string $field, string $prompt, ?OnlineStore $store, Tenant $tenant): array
    {
        $provider = strtolower((string) config('services.ai.provider', 'anthropic'));
        $format = in_array($field, self::PLAIN_FIELDS, true) ? 'plain' : 'html';
        $instruction = $this->instruction($field, $prompt, $store, $tenant, $format);

        try {
            $text = $provider === 'openai'
                ? $this->generateViaOpenAi($instruction)
                : $this->generateViaAnthropic($instruction);
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('AI store-content generation errored.', ['provider' => $provider, 'message' => $exception->getMessage()]);

            throw new RuntimeException('The AI service could not generate content right now. Please try again.');
        }

        $text = $this->clean($text, $format);

        if ($text === '') {
            throw new RuntimeException('The AI service returned no content. Please try again.');
        }

        return ['content' => $text, 'format' => $format];
    }

    private function generateViaAnthropic(string $instruction): string
    {
        $apiKey = (string) config('services.anthropic.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('AI is not configured. Add an ANTHROPIC_API_KEY (or switch AI_PROVIDER to openai) to enable this.');
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => (string) config('services.anthropic.version', '2023-06-01'),
            'content-type' => 'application/json',
        ])
            ->timeout(60)
            ->post(rtrim((string) config('services.anthropic.base_url'), '/').'/v1/messages', [
                'model' => (string) config('services.anthropic.model', 'claude-opus-5'),
                'max_tokens' => 1200,
                'output_config' => ['effort' => 'low'],
                'messages' => [['role' => 'user', 'content' => $instruction]],
            ]);

        if (! $response->successful()) {
            Log::warning('AI store-content request failed.', ['provider' => 'anthropic', 'status' => $response->status(), 'error' => substr((string) $response->body(), 0, 300)]);

            throw new RuntimeException('The AI service could not generate content right now. Please try again.');
        }

        return (string) (collect($response->json('content') ?? [])->firstWhere('type', 'text')['text'] ?? '');
    }

    private function generateViaOpenAi(string $instruction): string
    {
        $apiKey = (string) config('services.openai.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('AI is not configured. Add an OPENAI_API_KEY (or switch AI_PROVIDER to anthropic) to enable this.');
        }

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post(rtrim((string) config('services.openai.base_url'), '/').'/v1/chat/completions', [
                'model' => (string) config('services.openai.model', 'gpt-4o-mini'),
                'messages' => [['role' => 'user', 'content' => $instruction]],
            ]);

        if (! $response->successful()) {
            Log::warning('AI store-content request failed.', ['provider' => 'openai', 'status' => $response->status(), 'error' => substr((string) $response->body(), 0, 300)]);

            throw new RuntimeException('The AI service could not generate content right now. Please try again.');
        }

        return (string) ($response->json('choices.0.message.content') ?? '');
    }

    private function instruction(string $field, string $prompt, ?OnlineStore $store, Tenant $tenant, string $format): string
    {
        $storeName = trim((string) ($store?->store_name ?: $tenant->name)) ?: 'the store';
        $businessType = trim((string) ($tenant->business_type ?? ''));
        $country = trim((string) ($tenant->country_code ?? ''));
        $hint = trim($prompt);
        $hintLine = $hint !== '' ? "The store owner's note: \"{$hint}\"." : 'The store owner gave no extra note — infer sensible, generic content.';
        $context = "Store name: {$storeName}.".($businessType !== '' ? " Business type: {$businessType}." : '').($country !== '' ? " Country: {$country}." : '');

        $task = match ($field) {
            'description' => 'Write a concise, warm store description of 2 to 3 sentences that a shopper sees on the storefront.',
            'about_us' => 'Write an "About Us" page: who the store is, what it sells, and why shoppers should trust it. 2 to 4 short paragraphs.',
            'terms_of_use' => 'Write a clear "Terms of Use" page covering acceptable use, orders, pricing, and liability in plain language.',
            'return_policy' => 'Write a fair "Return Policy" page covering eligibility, timeframe, condition of items, and how to request a return.',
            'privacy_policy' => 'Write a "Privacy Policy" page covering what data is collected, how it is used, and how it is protected.',
            'shipping_information' => 'Write a "Shipping Information" page covering delivery areas, timeframes, costs, and tracking.',
            default => 'Write helpful, professional store page content.',
        };

        if ($format === 'plain') {
            return implode("\n", [
                $context,
                $hintLine,
                $task,
                'Return ONLY the description as plain text. No markdown, no quotes, no headings, no preamble.',
            ]);
        }

        return implode("\n", [
            $context,
            $hintLine,
            $task,
            'Return clean HTML using ONLY these tags: <p>, <h2>, <h3>, <ul>, <ol>, <li>, <strong>, <em>, <a>. '
                .'Do not include <html>, <head>, <body>, inline styles, markdown, or code fences. Start directly with the content. '
                .'Where the policy would need specifics the store must confirm (exact days, fees, contact email), use clear placeholders in [square brackets].',
        ]);
    }

    private function clean(string $text, string $format): string
    {
        $text = trim($text);

        // Strip any accidental ```html ... ``` fences.
        $text = preg_replace('/^```[a-zA-Z]*\s*/', '', $text) ?? $text;
        $text = preg_replace('/\s*```$/', '', $text) ?? $text;
        $text = trim($text);

        if ($format === 'plain') {
            // Collapse to plain text (no stray tags) for the short description.
            $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? $text);
        }

        return $text;
    }
}
