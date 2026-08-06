<?php

declare(strict_types=1);

namespace Modules\Catalog\Actions;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Turns a raw spreadsheet grid into a clean list of draft products.
 *
 * Sellers upload wildly different layouts: one product per row, or products
 * grouped under category headers with a block of "Label: Value" spec rows
 * beneath each model. When an AI provider is configured this reads the whole
 * grid, infers the layout, generates any missing descriptions/specifications,
 * and returns structured products. With no AI (or on any failure) it falls back
 * to a deterministic parser that handles both the flat and the sectioned shapes.
 *
 * It never throws — a bulk import must degrade gracefully, not fail wholesale.
 *
 * @phpstan-type StructuredProduct array{name: string, category: string, brand: string, description: string, specifications: string, sku: string, price_minor: int, has_price: bool, tags: list<string>}
 */
final class StructureProductSheetAction
{
    private const MAX_PRODUCTS = 300;

    /**
     * @param  list<list<string>>  $grid
     * @return list<StructuredProduct>
     */
    public function execute(array $grid, string $currencyCode = 'NGN'): array
    {
        $grid = $this->clean($grid);
        if ($grid === []) {
            return [];
        }

        $aiProducts = $this->structureViaAi($grid, $currencyCode);
        if ($aiProducts !== []) {
            return $aiProducts;
        }

        return $this->structureHeuristically($grid);
    }

    /**
     * @param  list<list<string>>  $grid
     * @return list<list<string>>
     */
    private function clean(array $grid): array
    {
        return array_values(array_map(
            static fn (array $row): array => array_map(static fn ($cell): string => trim((string) $cell), $row),
            $grid,
        ));
    }

    // ---------------------------------------------------------------------
    // AI path
    // ---------------------------------------------------------------------

    /**
     * @param  list<list<string>>  $grid
     * @return list<StructuredProduct>
     */
    private function structureViaAi(array $grid, string $currencyCode): array
    {
        $provider = strtolower((string) config('services.ai.provider', 'anthropic'));

        try {
            $text = $provider === 'openai'
                ? $this->viaOpenAi($grid, $currencyCode)
                : $this->viaAnthropic($grid, $currencyCode);
        } catch (Throwable $exception) {
            Log::warning('AI spreadsheet structuring errored.', ['provider' => $provider, 'message' => $exception->getMessage()]);

            return [];
        }

        return $this->normalizeAiProducts($text);
    }

    private function viaAnthropic(array $grid, string $currencyCode): ?string
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
            ->timeout(120)
            ->post(rtrim((string) config('services.anthropic.base_url'), '/').'/v1/messages', [
                'model' => (string) config('services.anthropic.model', 'claude-opus-5'),
                'max_tokens' => 8000,
                'output_config' => [
                    'effort' => 'medium',
                    'format' => ['type' => 'json_schema', 'schema' => $this->schema()],
                ],
                'messages' => [['role' => 'user', 'content' => $this->prompt($grid, $currencyCode)]],
            ]);

        if (! $response->successful() || $response->json('stop_reason') === 'refusal') {
            Log::warning('AI spreadsheet structuring request failed.', [
                'provider' => 'anthropic',
                'status' => $response->status(),
                'error' => substr((string) $response->body(), 0, 300),
            ]);

            return null;
        }

        return collect($response->json('content') ?? [])->firstWhere('type', 'text')['text'] ?? null;
    }

    private function viaOpenAi(array $grid, string $currencyCode): ?string
    {
        $apiKey = (string) config('services.openai.api_key');
        if ($apiKey === '') {
            return null;
        }

        $response = Http::withToken($apiKey)
            ->timeout(120)
            ->post(rtrim((string) config('services.openai.base_url'), '/').'/v1/chat/completions', [
                'model' => (string) config('services.openai.model', 'gpt-4o-mini'),
                'messages' => [['role' => 'user', 'content' => $this->prompt($grid, $currencyCode)]],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => ['name' => 'product_sheet', 'strict' => true, 'schema' => $this->schema()],
                ],
            ]);

        if (! $response->successful() || filled($response->json('choices.0.message.refusal'))) {
            Log::warning('AI spreadsheet structuring request failed.', [
                'provider' => 'openai',
                'status' => $response->status(),
                'error' => substr((string) $response->body(), 0, 300),
            ]);

            return null;
        }

        return $response->json('choices.0.message.content');
    }

    /**
     * @return list<StructuredProduct>
     */
    private function normalizeAiProducts(?string $text): array
    {
        $data = is_string($text) ? json_decode($text, true) : null;
        $rows = is_array($data['products'] ?? null) ? $data['products'] : (is_array($data) ? $data : []);

        $products = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $products[] = [
                'name' => Str::limit($name, 180, ''),
                'category' => trim((string) ($row['category'] ?? '')),
                'brand' => trim((string) ($row['brand'] ?? '')),
                'description' => trim((string) ($row['description'] ?? '')),
                'specifications' => trim((string) ($row['specifications'] ?? '')),
                'sku' => trim((string) ($row['sku'] ?? '')),
                'price_minor' => max(0, (int) round((float) ($row['price_minor'] ?? 0))),
                'has_price' => (bool) ($row['has_price'] ?? false),
                'tags' => collect($row['tags'] ?? [])
                    ->map(fn ($tag): string => trim((string) $tag))
                    ->filter()
                    ->take(6)
                    ->values()
                    ->all(),
            ];

            if (count($products) >= self::MAX_PRODUCTS) {
                break;
            }
        }

        return $products;
    }

    /**
     * @param  list<list<string>>  $grid
     */
    private function prompt(array $grid, string $currencyCode): string
    {
        $tsv = $this->gridToText($grid);

        return <<<PROMPT
        You are an expert e-commerce data cleaner importing a seller's product spreadsheet.
        The rows below are TAB-separated, prefixed with their row number. Layouts vary:
        - One product per row with a header row (e.g. Product Name, Category, Price, SKU, Status).
        - Products grouped under a CATEGORY HEADER row, where a "Model" column lists each product and
          the rows beneath it are "Label: Value" specifications until the next model or category.

        Read the whole sheet, infer the layout, and return every distinct sellable product. For each:
        - name: a clean, specific product name (use the model/name; no price, no emojis).
        - category: the most fitting category. Prefer a section/category header when present; otherwise
          infer a sensible one. Reuse the same wording for products that share a category.
        - brand: the brand if evident, else "".
        - description: a concise 1-2 sentence shopper-facing description. If missing from the sheet,
          write one from the available details. Do not invent facts not supported by the data.
        - specifications: plain text, one "Label: Value" per line, gathered from the product's spec rows.
          If none exist, leave "".
        - sku: the SKU/code/ID if present, else "".
        - price_minor: the price in MINOR units of {$currencyCode} (e.g. 49.99 -> 4999). Clean currency
          symbols, commas and spaces. If no price is given, 0.
        - has_price: true only when the sheet stated a real price for this product.
        - tags: up to 6 short lowercase keywords.

        Ignore header rows, totals, notes, and empty separators. Return only the products array.

        SHEET:
        {$tsv}
        PROMPT;
    }

    /**
     * @param  list<list<string>>  $grid
     */
    private function gridToText(array $grid): string
    {
        $lines = [];
        $used = 0;
        foreach ($grid as $index => $row) {
            $line = 'R'.($index + 1).': '.implode("\t", $row);
            $line = Str::limit($line, 600, '');
            $lines[] = $line;
            $used += strlen($line);
            if ($used > 24000) {
                $lines[] = '... (truncated)';
                break;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'products' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'category' => ['type' => 'string'],
                            'brand' => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'specifications' => ['type' => 'string'],
                            'sku' => ['type' => 'string'],
                            'price_minor' => ['type' => 'integer'],
                            'has_price' => ['type' => 'boolean'],
                            'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                        'required' => ['name', 'category', 'brand', 'description', 'specifications', 'sku', 'price_minor', 'has_price', 'tags'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['products'],
            'additionalProperties' => false,
        ];
    }

    // ---------------------------------------------------------------------
    // Heuristic fallback (no AI)
    // ---------------------------------------------------------------------

    /**
     * @param  list<list<string>>  $grid
     * @return list<StructuredProduct>
     */
    private function structureHeuristically(array $grid): array
    {
        $flat = $this->detectFlatHeader($grid);

        return $flat !== null
            ? $this->parseFlat($grid, $flat['row'], $flat['map'])
            : $this->parseSectioned($grid);
    }

    /**
     * Look for a header row whose "name" column is populated on most rows below
     * it — that signals a classic one-product-per-row table.
     *
     * @param  list<list<string>>  $grid
     * @return array{row: int, map: array<string, int>}|null
     */
    private function detectFlatHeader(array $grid): ?array
    {
        $limit = min(count($grid), 8);
        for ($r = 0; $r < $limit; $r++) {
            $map = $this->mapHeaderColumns($grid[$r]);
            if (! isset($map['name'])) {
                continue;
            }

            $nameCol = $map['name'];
            $nonBlank = 0;
            $filled = 0;
            for ($i = $r + 1; $i < count($grid); $i++) {
                if ($this->isBlank($grid[$i])) {
                    continue;
                }
                $nonBlank++;
                if (trim($grid[$i][$nameCol] ?? '') !== '') {
                    $filled++;
                }
            }

            if ($nonBlank > 0 && ($filled / $nonBlank) >= 0.6) {
                return ['row' => $r, 'map' => $map];
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $headerRow
     * @return array<string, int>
     */
    private function mapHeaderColumns(array $headerRow): array
    {
        $patterns = [
            'name' => '/\b(product\s*name|name|title|model|item)\b/i',
            'category' => '/\b(category|categories|type|group|series|department)\b/i',
            'price' => '/\b(price|cost|amount|exw|rate|unit\s*price)\b/i',
            'sku' => '/\b(sku|code|product\s*id|item\s*(id|code|no)|barcode|part\s*(no|number))\b/i',
            'brand' => '/\b(brand|make|manufacturer)\b/i',
            'description' => '/\b(description|desc|details?|summary)\b/i',
            'stock' => '/\b(stock|qty|quantity|inventory|units)\b/i',
        ];

        $map = [];
        foreach ($headerRow as $index => $cell) {
            $cell = trim((string) $cell);
            if ($cell === '') {
                continue;
            }
            foreach ($patterns as $key => $pattern) {
                if (! isset($map[$key]) && preg_match($pattern, $cell) === 1) {
                    $map[$key] = $index;
                }
            }
        }

        return $map;
    }

    /**
     * @param  list<list<string>>  $grid
     * @param  array<string, int>  $map
     * @return list<StructuredProduct>
     */
    private function parseFlat(array $grid, int $headerRow, array $map): array
    {
        $known = array_values($map);
        $header = $grid[$headerRow];
        $products = [];
        $get = static fn (array $row, ?int $col): string => $col !== null ? trim((string) ($row[$col] ?? '')) : '';

        for ($i = $headerRow + 1; $i < count($grid); $i++) {
            $row = $grid[$i];
            if ($this->isBlank($row)) {
                continue;
            }

            $name = $get($row, $map['name'] ?? null);
            if ($name === '') {
                continue;
            }

            $specs = [];
            foreach ($row as $col => $value) {
                $value = trim((string) $value);
                if ($value === '' || in_array($col, $known, true)) {
                    continue;
                }
                $label = trim($header[$col] ?? '');
                $specs[] = ($label !== '' ? $label.': ' : '').$value;
            }

            [$priceMinor, $hasPrice] = $this->parseMoney($get($row, $map['price'] ?? null));
            $category = $get($row, $map['category'] ?? null);

            $products[] = [
                'name' => Str::limit($name, 180, ''),
                'category' => $category,
                'brand' => $get($row, $map['brand'] ?? null),
                'description' => $get($row, $map['description'] ?? null),
                'specifications' => implode("\n", $specs),
                'sku' => $get($row, $map['sku'] ?? null),
                'price_minor' => $priceMinor,
                'has_price' => $hasPrice,
                'tags' => $category !== '' ? [strtolower($category)] : [],
            ];

            if (count($products) >= self::MAX_PRODUCTS) {
                break;
            }
        }

        return $products;
    }

    /**
     * Sectioned layout: category header rows, "Model/Specification/Price" sub-headers,
     * then a model row followed by spec continuation rows.
     *
     * @param  list<list<string>>  $grid
     * @return list<StructuredProduct>
     */
    private function parseSectioned(array $grid): array
    {
        $products = [];
        $current = null;
        $category = '';
        $modelCol = 0;
        $specNameCol = 1;
        $specValCol = 2;
        $priceCol = null;

        $flush = function () use (&$current, &$products): void {
            if ($current !== null && $current['name'] !== '') {
                $current['specifications'] = implode("\n", $current['specs']);
                unset($current['specs']);
                $products[] = $current;
            }
            $current = null;
        };

        foreach ($grid as $row) {
            if ($this->isBlank($row)) {
                continue;
            }

            $nonEmpty = array_values(array_filter($row, static fn ($cell): bool => trim((string) $cell) !== ''));

            // Sub-header row: defines the columns for the section below it.
            if ($this->looksLikeSubHeader($row)) {
                [$modelCol, $specNameCol, $specValCol, $priceCol] = $this->sectionColumns($row);

                continue;
            }

            // Category / section header: a single populated cell.
            if (count($nonEmpty) === 1) {
                $flush();
                $category = trim((string) $nonEmpty[0]);

                continue;
            }

            $model = trim($row[$modelCol] ?? '');
            $specName = trim($row[$specNameCol] ?? '');
            $specValue = trim($row[$specValCol] ?? '');
            $specLine = $specName !== '' ? ($specValue !== '' ? $specName.': '.$specValue : $specName) : '';

            if ($model !== '') {
                // New product row.
                $flush();
                [$priceMinor, $hasPrice] = $this->parseMoney($priceCol !== null ? ($row[$priceCol] ?? '') : '');
                $current = [
                    'name' => Str::limit($model, 180, ''),
                    'category' => $category,
                    'brand' => '',
                    'description' => '',
                    'specifications' => '',
                    'sku' => '',
                    'price_minor' => $priceMinor,
                    'has_price' => $hasPrice,
                    'tags' => $category !== '' ? [strtolower($category)] : [],
                    'specs' => $specLine !== '' ? [$specLine] : [],
                ];

                continue;
            }

            // Specification continuation row.
            if ($current !== null && $specLine !== '') {
                $current['specs'][] = $specLine;
            }

            if (count($products) >= self::MAX_PRODUCTS) {
                break;
            }
        }

        $flush();

        return array_slice($products, 0, self::MAX_PRODUCTS);
    }

    /**
     * @param  list<string>  $row
     */
    private function looksLikeSubHeader(array $row): bool
    {
        $joined = strtolower(implode(' ', $row));
        $hasModel = preg_match('/\b(model|item|product)\b/', $joined) === 1;
        $hasSpec = preg_match('/\b(specification|spec|feature|parameter)\b/', $joined) === 1;
        $hasPrice = preg_match('/\b(price|exw|cost)\b/', $joined) === 1;

        return ($hasModel && $hasSpec) || ($hasSpec && $hasPrice);
    }

    /**
     * @param  list<string>  $row
     * @return array{0: int, 1: int, 2: int, 3: int|null}
     */
    private function sectionColumns(array $row): array
    {
        $modelCol = 0;
        $specNameCol = 1;
        $priceCol = null;

        foreach ($row as $index => $cell) {
            $cell = strtolower(trim((string) $cell));
            if ($cell === '') {
                continue;
            }
            if (preg_match('/\b(model|item|product)\b/', $cell) === 1) {
                $modelCol = $index;
            } elseif (preg_match('/\b(specification|spec|feature|parameter)\b/', $cell) === 1) {
                $specNameCol = $index;
            } elseif ($priceCol === null && preg_match('/\b(price|exw|cost)\b/', $cell) === 1) {
                $priceCol = $index;
            }
        }

        return [$modelCol, $specNameCol, $specNameCol + 1, $priceCol];
    }

    /**
     * @return array{0: int, 1: bool}
     */
    private function parseMoney(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [0, false];
        }

        $multiplier = 1.0;
        if (preg_match('/([0-9][0-9.,\s]*)\s*k\b/i', $raw) === 1) {
            $multiplier = 1000.0;
        }

        $clean = preg_replace('/[^0-9.]/', '', str_replace(',', '', $raw)) ?? '';
        if ($clean === '' || ! is_numeric($clean)) {
            return [0, false];
        }

        $value = (float) $clean * $multiplier;

        return [(int) round($value * 100), $value > 0];
    }

    /**
     * @param  list<string>  $row
     */
    private function isBlank(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
