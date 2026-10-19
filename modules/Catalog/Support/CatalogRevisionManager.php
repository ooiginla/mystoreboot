<?php

declare(strict_types=1);

namespace Modules\Catalog\Support;

use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Models\CatalogRevision;

final class CatalogRevisionManager
{
    public function current(string $tenantId): int
    {
        if (! Schema::hasTable('catalog_revisions')) {
            return 1;
        }

        return (int) (CatalogRevision::query()->whereKey($tenantId)->value('revision') ?? 1);
    }

    public function bump(string $tenantId): int
    {
        if (! Schema::hasTable('catalog_revisions')) {
            return 1;
        }

        $revision = CatalogRevision::query()->firstOrCreate(
            ['tenant_id' => $tenantId],
            ['revision' => 1],
        );
        $revision->increment('revision');

        return (int) $revision->fresh()->revision;
    }
}
