<?php

declare(strict_types=1);

namespace Modules\Catalog\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductOption;
use Modules\Catalog\Models\ProductVariant;

final class SaveProductAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?Product $product = null): Product
    {
        return DB::transaction(function () use ($data, $product): Product {
            $product ??= new Product;
            $imagePath = $product->image_path;

            if (($data['image'] ?? null) instanceof UploadedFile) {
                $imagePath = $data['image']->store("tenants/{$data['tenant_id']}/catalog/products", 'public');
            }

            $product->fill([
                'tenant_id' => $data['tenant_id'],
                'category_id' => $data['category_id'] ?? null,
                'name' => $data['name'],
                'slug' => $data['slug'],
                'brand' => $data['brand'] ?? null,
                'product_type' => $data['product_type'],
                'description' => $data['description'] ?? null,
                'has_variants' => (bool) ($data['has_variants'] ?? false),
                'base_price_minor' => $this->moneyToMinor($data['base_price'] ?? 0),
                'base_cost_price_minor' => $this->moneyToMinor($data['base_cost_price'] ?? 0),
                'discount_price_minor' => isset($data['discount_price']) ? $this->moneyToMinor($data['discount_price']) : null,
                'tax_behavior' => $data['tax_behavior'],
                'tax_rate' => $data['tax_rate'] ?? null,
                'image_path' => $imagePath,
                'status' => $data['status'],
            ]);

            $product->save();

            if (($data['has_variants'] ?? false) && $product->product_type === ProductType::Product) {
                $optionValueMap = $this->syncOptions($product, $data);
                $this->syncVariantRows($product, $data, $optionValueMap);
            } else {
                $product->options()->delete();
                $this->syncDefaultVariant($product, $data);
            }

            $product->tags()->sync($data['tag_ids'] ?? []);
            $product->attributeValues()->sync($data['attribute_value_ids'] ?? []);

            return $product->refresh()->load(['category', 'options.values', 'variants.optionValues.option', 'tags', 'attributeValues.definition']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncDefaultVariant(Product $product, array $data): ProductVariant
    {
        $variant = $product->variants()->oldest('id')->first() ?? new ProductVariant([
            'tenant_id' => $product->tenant_id,
            'product_id' => $product->id,
        ]);

        $variant->fill([
            'tenant_id' => $product->tenant_id,
            'product_id' => $product->id,
            'variant_name' => $product->name,
            'sku' => $data['sku'] ?: $this->generateSku($product),
            'barcode' => $data['barcode'] ?? null,
            'selling_price_minor' => $this->moneyToMinor($data['base_price'] ?? 0),
            'cost_price_minor' => $this->moneyToMinor($data['base_cost_price'] ?? 0),
            'discount_price_minor' => isset($data['discount_price']) ? $this->moneyToMinor($data['discount_price']) : null,
            'tax_behavior' => $data['tax_behavior'],
            'tax_rate' => $data['tax_rate'] ?? null,
            'status' => $data['status'],
        ]);

        $variant->save();
        $product->variants()
            ->whereKeyNot($variant->id)
            ->delete();

        return $variant;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, list<int>>
     */
    private function syncOptions(Product $product, array $data): array
    {
        $optionRows = collect((array) ($data['options'] ?? []))
            ->map(function (array $row): array {
                return [
                    'name' => trim((string) ($row['name'] ?? '')),
                    'values' => collect(explode(',', (string) ($row['values'] ?? '')))
                        ->map(fn (string $value): string => trim($value))
                        ->filter()
                        ->unique(fn (string $value): string => strtolower($value))
                        ->values()
                        ->all(),
                ];
            })
            ->filter(fn (array $row): bool => $row['name'] !== '' && $row['values'] !== [])
            ->values();

        if ($optionRows->isEmpty()) {
            $product->options()->delete();

            return [];
        }

        $keptOptionIds = [];
        $signatureMap = [];

        foreach ($optionRows as $optionIndex => $row) {
            $option = $product->options()
                ->whereRaw('lower(name) = ?', [strtolower($row['name'])])
                ->first() ?? new ProductOption(['product_id' => $product->id]);

            $option->fill([
                'product_id' => $product->id,
                'name' => $row['name'],
                'sort_order' => $optionIndex,
            ]);
            $option->save();
            $keptOptionIds[] = $option->id;

            $keptValueIds = [];

            foreach ($row['values'] as $valueIndex => $value) {
                $optionValue = $option->values()
                    ->whereRaw('lower(value) = ?', [strtolower($value)])
                    ->first() ?? $option->values()->make();

                $optionValue->fill([
                    'product_option_id' => $option->id,
                    'value' => $value,
                    'sort_order' => $valueIndex,
                ]);
                $optionValue->save();
                $keptValueIds[] = $optionValue->id;
                $signatureMap[$this->optionSignaturePart($row['name'], $value)] = $optionValue->id;
            }

            $option->values()->whereKeyNot($keptValueIds)->delete();
        }

        $product->options()->whereKeyNot($keptOptionIds)->delete();

        return collect((array) ($data['variants'] ?? []))
            ->mapWithKeys(function (array $variant) use ($signatureMap): array {
                $signature = trim((string) ($variant['option_signature'] ?? ''));

                if ($signature === '') {
                    return [];
                }

                $valueIds = collect(explode('|', $signature))
                    ->map(fn (string $part): string => trim($part))
                    ->filter()
                    ->map(fn (string $part): ?int => $signatureMap[$part] ?? null)
                    ->filter()
                    ->values()
                    ->all();

                return $valueIds === [] ? [] : [$signature => $valueIds];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, list<int>>  $optionValueMap
     */
    private function syncVariantRows(Product $product, array $data, array $optionValueMap = []): void
    {
        $rows = collect((array) ($data['variants'] ?? []))
            ->filter(fn (array $row): bool => trim((string) ($row['variant_name'] ?? '')) !== '')
            ->values();

        if ($rows->isEmpty()) {
            $this->syncDefaultVariant($product, $data);

            return;
        }

        $keptIds = [];

        foreach ($rows as $index => $row) {
            $variant = isset($row['id'])
                ? $product->variants()->whereKey($row['id'])->first()
                : null;

            $variant ??= new ProductVariant([
                'tenant_id' => $product->tenant_id,
                'product_id' => $product->id,
            ]);

            $variant->fill([
                'tenant_id' => $product->tenant_id,
                'product_id' => $product->id,
                'variant_name' => $row['variant_name'],
                'sku' => $row['sku'] ?: $this->generateSku($product, (string) $row['variant_name'], $index + 1),
                'barcode' => $row['barcode'] ?? null,
                'selling_price_minor' => $this->moneyToMinor($row['selling_price'] ?? $data['base_price'] ?? 0),
                'cost_price_minor' => $this->moneyToMinor($row['cost_price'] ?? $data['base_cost_price'] ?? 0),
                'discount_price_minor' => isset($row['discount_price']) ? $this->moneyToMinor($row['discount_price']) : null,
                'tax_behavior' => $data['tax_behavior'],
                'tax_rate' => $data['tax_rate'] ?? null,
                'status' => $row['status'] ?? $data['status'],
            ]);

            $variant->save();
            $variant->optionValues()->sync($optionValueMap[$row['option_signature'] ?? ''] ?? []);
            $keptIds[] = $variant->id;
        }

        $product->variants()
            ->whereKeyNot($keptIds)
            ->delete();
    }

    private function optionSignaturePart(string $option, string $value): string
    {
        return trim($option).':'.trim($value);
    }

    private function generateSku(Product $product, ?string $name = null, int $seed = 1): string
    {
        $prefix = strtoupper(Str::slug(Str::limit($name ?: $product->name, 18, ''), ''));
        $prefix = $prefix !== '' ? $prefix : 'ITEM';
        $candidate = $prefix.'-'.$product->id.($seed > 1 ? '-'.$seed : '');
        $counter = 2;

        while (ProductVariant::query()->where('tenant_id', $product->tenant_id)->where('sku', $candidate)->exists()) {
            $candidate = $prefix.'-'.$product->id.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }

    private function moneyToMinor(mixed $value): int
    {
        return (int) round(((float) (is_string($value) ? str_replace(',', '', $value) : ($value ?: 0))) * 100);
    }
}
