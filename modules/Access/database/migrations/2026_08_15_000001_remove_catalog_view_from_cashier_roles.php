<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Modules\Access\Models\Permission;
use Modules\Access\Models\Role;

/**
 * The Cashier / Sales Staff role no longer sees Products & Services by default (catalog.view
 * removed from the template). Existing cashier roles were seeded with it, so detach it here.
 * The POS keeps working — it loads its own product list under the sales permissions.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permissionId = Permission::query()->where('slug', 'catalog.view')->value('id');

        if (! $permissionId) {
            return;
        }

        Role::query()->where('template_key', 'cashier')->get()
            ->each(fn (Role $role) => $role->permissions()->detach($permissionId));
    }

    public function down(): void
    {
        $permissionId = Permission::query()->where('slug', 'catalog.view')->value('id');

        if (! $permissionId) {
            return;
        }

        Role::query()->where('template_key', 'cashier')->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$permissionId]));
    }
};
