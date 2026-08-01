<?php

declare(strict_types=1);

namespace Modules\Catalog\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\Catalog\Actions\GenerateProductSeoAction;
use Modules\Catalog\Models\Product;

/**
 * Nightly (or on-demand) refresh of product SEO metadata. Regenerates products
 * that have no SEO yet or were edited since their SEO was last generated.
 * The storefront always renders SEO live as a fallback, so this only upgrades
 * the cached, AI-enriched copy — it is never on the request path.
 */
final class RefreshProductSeoCommand extends Command
{
    protected $signature = 'catalog:refresh-product-seo {--tenant= : Limit to one tenant id} {--all : Regenerate every product, not just stale ones}';

    protected $description = 'Regenerate SEO metadata for new or changed products.';

    public function handle(GenerateProductSeoAction $action): int
    {
        $refreshed = 0;

        Product::query()
            ->with(['category', 'tags'])
            ->when($this->option('tenant'), fn ($query) => $query->where('tenant_id', $this->option('tenant')))
            ->orderBy('id')
            ->chunkById(100, function ($products) use ($action, &$refreshed): void {
                foreach ($products as $product) {
                    if (! $this->option('all') && ! $this->needsRefresh($product)) {
                        continue;
                    }

                    $action->execute($product);
                    $refreshed++;
                }
            });

        $this->info("Refreshed SEO for {$refreshed} product(s).");

        return self::SUCCESS;
    }

    private function needsRefresh(Product $product): bool
    {
        $seo = is_array($product->seo) ? $product->seo : null;

        if ($seo === null || empty($seo['generated_at'])) {
            return true;
        }

        try {
            return $product->updated_at?->gt(Carbon::parse($seo['generated_at'])) ?? true;
        } catch (\Throwable) {
            return true;
        }
    }
}
