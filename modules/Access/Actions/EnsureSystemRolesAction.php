<?php

declare(strict_types=1);

namespace Modules\Access\Actions;

use Modules\Access\Models\Permission;
use Modules\Access\Models\Role;
use Modules\Access\Support\PermissionCatalogue;
use Modules\Access\Support\RoleSummaryBuilder;

/**
 * Ensures every tenant has the default system role templates, each seeded with
 * its real permissions, limits, protection flag and generated summary.
 *
 * Idempotent. The Business Owner role is protected and always granted every
 * permission (so new features are never accidentally locked away from owners).
 */
final class EnsureSystemRolesAction
{
    public function execute(string $tenantId, string $currency = 'NGN'): void
    {
        $permissionIds = Permission::query()->pluck('id', 'slug');

        foreach (PermissionCatalogue::templates() as $key => $template) {
            $slugs = PermissionCatalogue::templatePermissions($key);
            $limits = $template['limits'] ?? [];

            $role = Role::query()->firstOrNew([
                'tenant_id' => $tenantId,
                'slug' => $key,
            ]);

            // Preserve an existing custom name/description if an admin edited a non-protected template;
            // always refresh the protected Business Owner.
            $isNew = ! $role->exists;
            if ($isNew || $template['protected']) {
                $role->name = $template['name'];
                $role->description = $template['description'];
            }

            $role->is_system = true;
            $role->is_protected = $template['protected'];
            $role->template_key = $key;
            $role->limits = $limits ?: null;
            $role->summary = RoleSummaryBuilder::build($slugs, $limits, $currency);
            $role->save();

            $ids = $permissionIds->only($slugs)->values()->all();
            // Protected owner: always sync to full set. Templates: only seed permissions when first created,
            // so later admin customisations are not overwritten.
            if ($template['protected'] || $isNew) {
                $role->permissions()->sync($ids);
            }
        }
    }
}
