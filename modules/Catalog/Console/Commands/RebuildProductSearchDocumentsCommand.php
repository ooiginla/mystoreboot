<?php

declare(strict_types=1);

namespace Modules\Catalog\Console\Commands;

use Illuminate\Console\Command;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Support\CatalogRevisionManager;
use Modules\Catalog\Support\ProductSearchDocumentBuilder;
use Modules\Tenancy\Models\Tenant;

final class RebuildProductSearchDocumentsCommand extends Command
{
    protected $signature = 'catalog:rebuild-search {--tenant= : Rebuild one tenant UUID only} {--chunk=200 : Products processed per chunk}';

    protected $description = 'Safely rebuild the denormalized product full-text search documents';

    public function handle(ProductSearchDocumentBuilder $documents, CatalogRevisionManager $revisions): int
    {
        $tenantId = trim((string) $this->option('tenant'));
        $chunk = max(25, min(1000, (int) $this->option('chunk')));

        if ($tenantId !== '' && ! Tenant::query()->whereKey($tenantId)->exists()) {
            $this->error('The requested tenant does not exist.');

            return self::FAILURE;
        }

        $query = Product::query()
            ->when($tenantId !== '', fn ($query) => $query->where('tenant_id', $tenantId))
            ->orderBy('id');
        $tenantIds = [];
        $count = 0;

        $query->chunkById($chunk, function ($products) use ($documents, &$tenantIds, &$count): void {
            foreach ($products as $product) {
                $documents->rebuild($product, false);
                $tenantIds[(string) $product->tenant_id] = true;
                $count++;
            }

            $this->line("Indexed {$count} product(s)...");
        });

        foreach (array_keys($tenantIds) as $indexedTenantId) {
            $revisions->bump($indexedTenantId);
        }

        $this->info("Product search documents are current for {$count} product(s).");

        return self::SUCCESS;
    }
}
