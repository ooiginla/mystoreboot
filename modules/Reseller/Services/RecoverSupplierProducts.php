<?php

declare(strict_types=1);

namespace Modules\Reseller\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\Reseller\Actions\CalculateResellerPrice;
use Modules\Reseller\Data\RecoveredProductData;
use Modules\Reseller\Enums\ProductAvailability;
use Modules\Reseller\Enums\ScanStatus;
use Modules\Reseller\Models\ResellerProduct;
use Modules\Reseller\Models\ResellerSetting;
use Modules\Reseller\Models\ResellerSupplier;
use Modules\Subscriptions\Support\TenantModuleAccess;
use Modules\Tenancy\Enums\CommerceMode;
use RuntimeException;
use Throwable;

final class RecoverSupplierProducts
{
    public function __construct(
        private readonly SafeSupplierUrl $safeUrl,
        private readonly ProductPageExtractor $extractor,
        private readonly CalculateResellerPrice $priceCalculator,
        private readonly TenantModuleAccess $modules,
    ) {}

    /** @return array{created: int, updated: int, pages: int, errors: int} */
    public function execute(ResellerSupplier $supplier): array
    {
        $supplier->loadMissing('tenant');

        if ($supplier->tenant->commerce_mode !== CommerceMode::Reseller
            || ! $this->modules->allows($supplier->tenant, 'reseller')
            || ! $supplier->is_active) {
            throw new RuntimeException('The supplier is not active for a reseller tenant.');
        }

        $this->safeUrl->assert($supplier->website_url);
        $settings = ResellerSetting::query()->firstOrCreate(['tenant_id' => $supplier->tenant_id]);
        $configuration = (array) $supplier->scan_configuration;
        $maximumPages = min(200, max(1, (int) ($configuration['maximum_pages'] ?? 50)));
        $starts = collect((array) ($configuration['starting_urls'] ?? [$supplier->website_url]))
            ->filter(fn (mixed $url): bool => is_string($url) && $this->safeUrl->sameHost($url, $supplier->website_url))
            ->values()
            ->all();
        $queue = $starts === [] ? [$supplier->website_url] : $starts;
        $visited = [];
        $seenProductIds = [];
        $created = 0;
        $updated = 0;
        $errors = 0;

        $supplier->update([
            'last_scan_status' => ScanStatus::Running,
            'last_scan_message' => null,
        ]);

        try {
            while ($queue !== [] && count($visited) < $maximumPages) {
                $url = array_shift($queue);

                if (isset($visited[$url])) {
                    continue;
                }

                $visited[$url] = true;

                try {
                    $response = Http::accept('text/html,application/xhtml+xml')
                        ->withUserAgent('Storeboot Product Recovery/1.0')
                        ->timeout(20)
                        ->get($url);

                    if (! $response->successful()) {
                        $errors++;

                        continue;
                    }

                    $html = $response->body();
                    $recovered = $this->extractor->extract($html, $url, $supplier->name);

                    if ($recovered) {
                        [$product, $wasCreated] = $this->upsert($supplier, $settings, $recovered);
                        $seenProductIds[] = $product->id;
                        $wasCreated ? $created++ : $updated++;
                    }

                    foreach ($this->extractor->links($html, $url) as $link) {
                        if ($this->safeUrl->sameHost($link, $supplier->website_url) && ! isset($visited[$link])) {
                            $queue[] = $link;
                        }
                    }
                } catch (ConnectionException) {
                    $errors++;
                }
            }

            if ($errors === 0) {
                $this->markMissingProducts($supplier, $settings, $seenProductIds);
            }

            $status = $errors === 0 ? ScanStatus::Successful : (count($seenProductIds) > 0 ? ScanStatus::Partial : ScanStatus::Failed);
            $summary = "{$created} created, {$updated} updated, ".count($visited).' pages checked';

            if ($errors > 0) {
                $summary .= ", {$errors} errors";
            }

            $supplier->update([
                'last_scanned_at' => now(),
                'last_scan_status' => $status,
                'last_scan_message' => $summary,
            ]);

            return ['created' => $created, 'updated' => $updated, 'pages' => count($visited), 'errors' => $errors];
        } catch (Throwable $exception) {
            $supplier->update([
                'last_scanned_at' => now(),
                'last_scan_status' => ScanStatus::Failed,
                'last_scan_message' => str($exception->getMessage())->limit(1000)->toString(),
            ]);

            throw $exception;
        }
    }

    /** @return array{ResellerProduct, bool} */
    private function upsert(
        ResellerSupplier $supplier,
        ResellerSetting $settings,
        RecoveredProductData $data,
    ): array {
        $existing = ResellerProduct::query()
            ->where('tenant_id', $supplier->tenant_id)
            ->where('supplier_id', $supplier->id)
            ->where('product_url', $data->url)
            ->first();
        $isPublishable = $data->currencyCode === strtoupper((string) $supplier->tenant->currency_code)
            && $data->availability !== ProductAvailability::OutOfStock->value;
        $autoPublish = $supplier->auto_publish_products ?? $settings->auto_publish_products;
        $sellingPrice = $this->priceCalculator->execute($data->priceMinor, $settings);
        $sellingPreviousPrice = $data->previousPriceMinor === null
            ? null
            : $this->priceCalculator->execute($data->previousPriceMinor, $settings);
        $attributes = [
            'source_product_reference' => $data->sourceReference,
            'name' => $data->name,
            'main_image_url' => $data->imageUrl,
            'source_price_minor' => $data->priceMinor,
            'source_previous_price_minor' => $data->previousPriceMinor,
            'selling_price_minor' => $sellingPrice,
            'selling_previous_price_minor' => $sellingPreviousPrice > $sellingPrice ? $sellingPreviousPrice : null,
            'currency_code' => $data->currencyCode,
            'availability' => $data->availability,
            'short_description' => $data->description,
            'brand' => $data->brand,
            'category' => $data->category,
            'sku' => $data->sku,
            'variants' => $data->variants,
            'source_store_name' => $data->sourceStoreName,
            'last_checked_at' => now(),
            'missing_scan_count' => 0,
            'content_fingerprint' => hash('sha256', json_encode($data, JSON_THROW_ON_ERROR)),
        ];

        if (! $existing) {
            $existing = ResellerProduct::query()->create([
                'tenant_id' => $supplier->tenant_id,
                'supplier_id' => $supplier->id,
                'product_url' => $data->url,
                'is_visible' => $autoPublish && $isPublishable,
                ...$attributes,
            ]);

            return [$existing, true];
        }

        if (! $existing->is_excluded) {
            $attributes['is_visible'] = $existing->is_visible && $isPublishable;
        }

        $existing->update($attributes);

        return [$existing, false];
    }

    /** @param list<int> $seenProductIds */
    private function markMissingProducts(
        ResellerSupplier $supplier,
        ResellerSetting $settings,
        array $seenProductIds,
    ): void {
        ResellerProduct::query()
            ->where('tenant_id', $supplier->tenant_id)
            ->where('supplier_id', $supplier->id)
            ->when($seenProductIds !== [], fn ($query) => $query->whereNotIn('id', $seenProductIds))
            ->each(function (ResellerProduct $product) use ($settings): void {
                $missing = $product->missing_scan_count + 1;
                $product->update([
                    'missing_scan_count' => $missing,
                    'is_visible' => $missing >= $settings->hide_after_missing_scans ? false : $product->is_visible,
                    'availability' => $missing >= $settings->hide_after_missing_scans
                        ? ProductAvailability::Unknown
                        : $product->availability,
                ]);
            });
    }
}
