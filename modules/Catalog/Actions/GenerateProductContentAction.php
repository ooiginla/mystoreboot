<?php

declare(strict_types=1);

namespace Modules\Catalog\Actions;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Tenancy\Models\Tenant;
use RuntimeException;
use Throwable;

final class GenerateProductContentAction
{
    /**
     * @param  array{name?: string, brand?: string, category?: string, description?: string, specifications?: string}  $context
     * @return array{content: string, format: string}
     */
    public function execute(string $field, string $prompt, array $context, Tenant $tenant): array
    {
        $instruction = $this->instruction($field, $prompt, $context, $tenant);
        $provider = strtolower((string) config('services.ai.provider', 'anthropic'));

        try {
            $text = $provider === 'openai'
                ? $this->generateViaOpenAi($instruction)
                : $this->generateViaAnthropic($instruction);
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('AI product-content generation errored.', [
                'provider' => $provider,
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException('The AI service could not generate product content right now. Please try again.');
        }

        $text = trim(strip_tags($text));
        $text = preg_replace('/^```[a-zA-Z]*\s*/', '', $text) ?? $text;
        $text = preg_replace('/\s*```$/', '', $text) ?? $text;
        $text = trim($text);

        if ($text === '') {
            throw new RuntimeException('The AI service returned no content. Please try again.');
        }

        return ['content' => $text, 'format' => 'plain'];
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
                'max_tokens' => 1000,
                'output_config' => ['effort' => 'low'],
                'messages' => [['role' => 'user', 'content' => $instruction]],
            ]);

        if (! $response->successful()) {
            Log::warning('AI product-content request failed.', [
                'provider' => 'anthropic',
                'status' => $response->status(),
                'error' => substr((string) $response->body(), 0, 300),
            ]);

            throw new RuntimeException('The AI service could not generate product content right now. Please try again.');
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
            Log::warning('AI product-content request failed.', [
                'provider' => 'openai',
                'status' => $response->status(),
                'error' => substr((string) $response->body(), 0, 300),
            ]);

            throw new RuntimeException('The AI service could not generate product content right now. Please try again.');
        }

        return (string) ($response->json('choices.0.message.content') ?? '');
    }

    /**
     * @param  array{name?: string, brand?: string, category?: string, description?: string, specifications?: string}  $context
     */
    private function instruction(string $field, string $prompt, array $context, Tenant $tenant): string
    {
        $lines = [
            'Store: '.(trim((string) $tenant->name) ?: 'Online store'),
            'Product name: '.(trim((string) ($context['name'] ?? '')) ?: '[not provided]'),
            'Brand: '.(trim((string) ($context['brand'] ?? '')) ?: '[not provided]'),
            'Category: '.(trim((string) ($context['category'] ?? '')) ?: '[not provided]'),
        ];

        if ($field === 'description' && trim((string) ($context['specifications'] ?? '')) !== '') {
            $lines[] = 'Known specifications: '.trim((string) $context['specifications']);
        }

        if ($field === 'specifications' && trim((string) ($context['description'] ?? '')) !== '') {
            $lines[] = 'Known description: '.trim((string) $context['description']);
        }

        $lines[] = trim($prompt) !== ''
            ? 'Seller notes: '.trim($prompt)
            : 'The seller supplied no additional notes.';
        $lines[] = $field === 'specifications'
            ? 'Write useful product specifications as plain text, one "Label: Value" item per line. Include only details supported by the supplied context; do not invent measurements, materials, compatibility, certifications, or technical claims.'
            : 'Write a concise, persuasive product description in 2 to 4 sentences. Focus on shopper benefits and use only details supported by the supplied context.';
        $lines[] = 'Return only the requested content as plain text. Do not use markdown, headings, quotes, or a preamble.';

        return implode("\n", $lines);
    }
}
