<?php

declare(strict_types=1);

namespace Modules\Access\Actions;

use Modules\Access\Models\Permission;
use Modules\Access\Support\PermissionCatalogue;

/**
 * Upserts the atomic permission catalogue into the permissions table.
 * Idempotent — safe to run repeatedly (e.g. on deploy / first request).
 */
final class SyncPermissionCatalogueAction
{
    public function execute(): void
    {
        foreach (PermissionCatalogue::definitions() as $slug => $definition) {
            Permission::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'module' => $definition['module'],
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                ],
            );
        }
    }
}
