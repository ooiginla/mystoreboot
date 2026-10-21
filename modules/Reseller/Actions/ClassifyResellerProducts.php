<?php

declare(strict_types=1);

namespace Modules\Reseller\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Catalog\Actions\EnsureDefaultProductCategoryAction;
use Modules\Catalog\Enums\CategoryType;
use Modules\Catalog\Models\ProductCategory;
use Modules\Catalog\Models\ProductTag;
use Modules\Reseller\Models\ResellerProduct;
use Modules\Tenancy\Models\Tenant;
use RuntimeException;
use Throwable;

final class ClassifyResellerProducts
{
    private const BATCH_SIZE = 200;

    public function __construct(
        private readonly EnsureDefaultProductCategoryAction $ensureDefaultCategory,
    ) {}

    /**
     * @param  list<int>|null  $productIds
     * @return array{processed: int, categorized: int, pending: int, tags_created: int, ai_requests: int}
     */
    public function execute(string $tenantId, ?array $productIds = null): array
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $defaultCategory = $this->ensureDefaultCategory->execute($tenantId);
        $categories = ProductCategory::query()
            ->where('tenant_id', $tenantId)
            ->where('category_type', CategoryType::Product->value)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        $customCategories = $categories->reject(fn (ProductCategory $category): bool => $category->is($defaultCategory))->values();
        $products = ResellerProduct::query()
            ->with('tags')
            ->where('tenant_id', $tenantId)
            ->where(function ($query): void {
                $query->whereNull('category_id')->orWhere('classification_status', 'pending');
            })
            ->when($productIds !== null, fn ($query) => $query->whereIn('id', $productIds))
            ->orderBy('id')
            ->get();

        if ($products->isEmpty()) {
            return ['processed' => 0, 'categorized' => 0, 'pending' => 0, 'tags_created' => 0, 'ai_requests' => 0];
        }

        ResellerProduct::query()
            ->whereKey($products->pluck('id'))
            ->whereNull('category_id')
            ->update(['category_id' => $defaultCategory->id]);

        $assignments = collect();
        $aiRequests = 0;

        if ($customCategories->isNotEmpty() && $this->aiIsConfigured()) {
            foreach ($products->chunk(self::BATCH_SIZE) as $batch) {
                try {
                    $this->aiAssignments($tenant, $customCategories, $batch)
                        ->each(fn (array $assignment, string|int $productId) => $assignments->put((int) $productId, $assignment));
                    $aiRequests++;
                } catch (Throwable $exception) {
                    Log::warning('Reseller product classification fell back to local matching.', [
                        'tenant_id' => $tenantId,
                        'message' => $exception->getMessage(),
                    ]);
                }
            }
        }

        $categorized = 0;
        $pending = 0;
        $tagsCreated = 0;

        foreach ($products as $product) {
            $assignment = $assignments->get((int) $product->id);
            $category = $assignment
                ? $customCategories->firstWhere('id', (int) ($assignment['category_id'] ?? 0))
                : null;
            $category ??= $this->matchCategory($product, $customCategories);
            $isPending = $customCategories->isNotEmpty() && ! $category;
            $category ??= $defaultCategory;
            $tagNames = $this->normalizeTags((array) ($assignment['tags'] ?? []));

            if ($tagNames === []) {
                $tagNames = $this->fallbackTags($product, $category);
            }

            DB::transaction(function () use ($product, $category, $tagNames, $isPending, &$tagsCreated): void {
                $tagIds = collect($tagNames)->map(function (string $name) use ($product, &$tagsCreated): int {
                    $slug = Str::slug($name);
                    $tag = ProductTag::query()->firstOrCreate(
                        ['tenant_id' => $product->tenant_id, 'slug' => $slug],
                        ['name' => Str::limit($name, 120, '')],
                    );

                    if ($tag->wasRecentlyCreated) {
                        $tagsCreated++;
                    }

                    return $tag->id;
                });

                $product->update([
                    'category_id' => $category->id,
                    'classification_status' => $isPending ? 'pending' : 'classified',
                    'categorized_at' => $isPending ? null : now(),
                ]);
                $product->tags()->sync($tagIds->all());
            });

            $isPending ? $pending++ : $categorized++;
        }

        return [
            'processed' => $products->count(),
            'categorized' => $categorized,
            'pending' => $pending,
            'tags_created' => $tagsCreated,
            'ai_requests' => $aiRequests,
        ];
    }

    private function aiIsConfigured(): bool
    {
        $provider = strtolower((string) config('services.ai.provider', 'anthropic'));

        return trim((string) config("services.{$provider}.api_key")) !== '';
    }

    /**
     * @param  Collection<int, ProductCategory>  $categories
     * @param  Collection<int, ResellerProduct>  $products
     * @return Collection<string, array{category_id: int, tags: list<string>}>
     */
    private function aiAssignments(Tenant $tenant, Collection $categories, Collection $products): Collection
    {
        $payload = [
            'categories' => $categories->map(fn (ProductCategory $category): array => ['id' => $category->id, 'name' => $category->name])->all(),
            'products' => $products->map(fn (ResellerProduct $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'brand' => $product->brand,
                'source_category' => $product->category,
                'description' => Str::limit(strip_tags((string) $product->short_description), 240, ''),
            ])->all(),
        ];
        $instruction = "Classify every product into exactly one of the supplied merchant categories. Use only a supplied category ID. Also create 3 to 6 concise shopper search tags per product, based only on its data. Return strict JSON only in this shape: {\"products\":[{\"id\":1,\"category_id\":2,\"tags\":[\"tag\"]}]}. Store: {$tenant->name}. Input: ".json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $provider = strtolower((string) config('services.ai.provider', 'anthropic'));
        $text = $provider === 'openai'
            ? $this->requestOpenAi($instruction)
            : $this->requestAnthropic($instruction);
        $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text)) ?? $text);
        $decoded = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        $validProductIds = $products->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $validCategoryIds = $categories->pluck('id')->map(fn ($id): int => (int) $id)->all();

        return collect($decoded['products'] ?? [])
            ->filter(fn ($item): bool => is_array($item)
                && in_array((int) ($item['id'] ?? 0), $validProductIds, true)
                && in_array((int) ($item['category_id'] ?? 0), $validCategoryIds, true))
            ->mapWithKeys(fn (array $item): array => [(string) ((int) $item['id']) => [
                'category_id' => (int) $item['category_id'],
                'tags' => $this->normalizeTags((array) ($item['tags'] ?? [])),
            ]]);
    }

    private function requestOpenAi(string $instruction): string
    {
        $response = Http::withToken((string) config('services.openai.api_key'))
            ->timeout(90)
            ->post(rtrim((string) config('services.openai.base_url'), '/').'/v1/chat/completions', [
                'model' => (string) config('services.openai.model', 'gpt-4o-mini'),
                'max_tokens' => 8000,
                'response_format' => ['type' => 'json_object'],
                'messages' => [['role' => 'user', 'content' => $instruction]],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI classification request failed.');
        }

        return (string) $response->json('choices.0.message.content');
    }

    private function requestAnthropic(string $instruction): string
    {
        $response = Http::withHeaders([
            'x-api-key' => (string) config('services.anthropic.api_key'),
            'anthropic-version' => (string) config('services.anthropic.version', '2023-06-01'),
            'content-type' => 'application/json',
        ])->timeout(90)->post(rtrim((string) config('services.anthropic.base_url'), '/').'/v1/messages', [
            'model' => (string) config('services.anthropic.model', 'claude-opus-5'),
            'max_tokens' => 8000,
            'messages' => [['role' => 'user', 'content' => $instruction]],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Anthropic classification request failed.');
        }

        return (string) (collect($response->json('content') ?? [])->firstWhere('type', 'text')['text'] ?? '');
    }

    /** @param Collection<int, ProductCategory> $categories */
    private function matchCategory(ResellerProduct $product, Collection $categories): ?ProductCategory
    {
        $haystack = Str::lower(implode(' ', array_filter([$product->name, $product->brand, $product->category])));

        return $categories
            ->map(fn (ProductCategory $category): array => [
                'category' => $category,
                'score' => collect(preg_split('/[^a-z0-9]+/i', Str::lower($category->name)) ?: [])
                    ->filter(fn (string $word): bool => strlen($word) >= 3 && str_contains($haystack, $word))
                    ->count(),
            ])
            ->sortByDesc('score')
            ->first(fn (array $match): bool => $match['score'] > 0)['category'] ?? null;
    }

    /** @return list<string> */
    private function fallbackTags(ResellerProduct $product, ProductCategory $category): array
    {
        $phrases = collect([$product->brand, $product->category, $category->name])
            ->filter(fn ($value): bool => filled($value) && Str::lower((string) $value) !== 'uncategorized');
        $words = collect(preg_split('/[^\pL\pN]+/u', (string) $product->name) ?: [])
            ->filter(fn (string $word): bool => mb_strlen($word) >= 3)
            ->reject(fn (string $word): bool => in_array(Str::lower($word), ['and', 'for', 'the', 'with', 'from'], true));

        return $this->normalizeTags($phrases->merge($words)->all());
    }

    /** @return list<string> */
    private function normalizeTags(array $tags): array
    {
        return collect($tags)
            ->filter(fn ($tag): bool => is_scalar($tag))
            ->map(fn ($tag): string => Str::limit(trim(strip_tags((string) $tag)), 120, ''))
            ->filter(fn (string $tag): bool => $tag !== '' && Str::slug($tag) !== '')
            ->unique(fn (string $tag): string => Str::lower($tag))
            ->take(6)
            ->values()
            ->all();
    }
}
