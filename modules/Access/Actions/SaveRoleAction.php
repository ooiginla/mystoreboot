<?php

declare(strict_types=1);

namespace Modules\Access\Actions;

use Illuminate\Support\Str;
use Modules\Access\Models\Permission;
use Modules\Access\Models\Role;
use Modules\Access\Support\PermissionCatalogue;
use Modules\Access\Support\RoleSummaryBuilder;

/**
 * Creates or updates a custom/system role from the role editor's structured input
 * (per-module levels + sensitive actions + numeric limits), expanding everything to
 * the atomic permission slugs Storeboot actually enforces, and regenerating the
 * plain-language summary.
 *
 * Protected roles (the Business Owner) cannot be edited here.
 */
final class SaveRoleAction
{
    /**
     * @param  array{
     *     tenant_id: string,
     *     name: string,
     *     description?: string|null,
     *     levels?: array<string, string>,
     *     sensitive?: list<string>,
     *     limits?: array<string, int|float|null>,
     *     is_system?: bool
     * }  $data
     */
    public function execute(array $data, ?Role $role = null, string $currency = 'NGN'): Role
    {
        $slugs = $this->resolveSlugs($data['levels'] ?? [], $data['sensitive'] ?? []);
        $limits = $this->resolveLimits($data['limits'] ?? [], $slugs);

        $role ??= new Role(['tenant_id' => $data['tenant_id']]);

        abort_if($role->is_protected, 403, 'This role is protected and cannot be edited.');

        $role->tenant_id = $data['tenant_id'];
        $role->name = $data['name'];
        $role->description = $data['description'] ?? null;

        if (! $role->exists) {
            $role->slug = $this->uniqueSlug($data['tenant_id'], $data['name']);
            $role->is_system = (bool) ($data['is_system'] ?? false);
            $role->is_protected = false;
            $role->template_key = null;
        }

        $role->limits = $limits ?: null;
        $role->summary = RoleSummaryBuilder::build($slugs, $limits, $currency);
        $role->save();

        $ids = Permission::query()->whereIn('slug', $slugs)->pluck('id')->all();
        $role->permissions()->sync($ids);

        return $role;
    }

    /**
     * @param  array<string, string>  $levels
     * @param  list<string>  $sensitive
     * @return list<string>
     */
    private function resolveSlugs(array $levels, array $sensitive): array
    {
        $modules = PermissionCatalogue::modules();
        $valid = PermissionCatalogue::definitions();
        $slugs = [];

        foreach ($levels as $module => $level) {
            $slugs = array_merge($slugs, $modules[$module]['levels'][$level] ?? []);
        }

        foreach ($sensitive as $slug) {
            if (isset($valid[$slug]) && $valid[$slug]['sensitive']) {
                $slugs[] = $slug;
            }
        }

        return array_values(array_intersect(array_unique($slugs), array_keys($valid)));
    }

    /**
     * Keep only limits whose gating permission is actually granted; money limits arrive
     * in major units and are stored as minor units.
     *
     * @param  array<string, int|float|null>  $input
     * @param  list<string>  $slugs
     * @return array<string, int|float>
     */
    private function resolveLimits(array $input, array $slugs): array
    {
        $catalogue = PermissionCatalogue::limits();
        $set = array_flip($slugs);
        $limits = [];

        foreach ($catalogue as $key => $meta) {
            if (! isset($set[$meta['permission']])) {
                continue;
            }

            $value = $input[$key] ?? null;

            if ($value === null || $value === '' || (float) $value <= 0) {
                continue;
            }

            $limits[$key] = $meta['type'] === 'money'
                ? (int) round((float) $value * 100)
                : (float) $value + 0;
        }

        return $limits;
    }

    private function uniqueSlug(string $tenantId, string $name): string
    {
        $base = Str::slug($name) ?: 'role';
        $slug = $base;
        $counter = 2;

        while (Role::query()->where('tenant_id', $tenantId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
