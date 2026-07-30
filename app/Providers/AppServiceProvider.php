<?php

namespace App\Providers;

use App\Models\User;
use App\Support\ActiveBranchManager;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Modules\Access\Support\PermissionCatalogue;
use Modules\Access\Support\PermissionService;
use Modules\Catalog\Actions\EnsureDefaultProductCategoryAction;
use Modules\Tenancy\Models\Tenant;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PermissionService::class);
    }

    public function boot(): void
    {
        $permissions = null;

        Tenant::created(function (Tenant $tenant): void {
            if (Schema::hasTable('product_categories')) {
                app(EnsureDefaultProductCategoryAction::class)->execute($tenant->id);
            }
        });

        // Gate integration: any catalogue permission slug becomes a Gate ability,
        // so @can('sales.view') and $user->can('sales.view') hide/deny UI at the
        // permission level (branch scope is enforced server-side on the action).
        Gate::before(function (?User $user, string $ability) use (&$permissions) {
            if (! $user instanceof User) {
                return null;
            }

            $permissions ??= PermissionCatalogue::definitions();

            if (! array_key_exists($ability, $permissions)) {
                return null; // not one of ours — let normal gates/policies resolve
            }

            if ($user->is_platform_admin) {
                return true;
            }

            $tenant = $this->activeTenant($user);

            if (! $tenant) {
                return false;
            }

            $permissionService = app(PermissionService::class);

            // Mirror the route middleware's safety valve (EnforcePermissions): when a
            // tenant has not opted into RBAC enforcement, permissions are not gated
            // anywhere — UI or routes — so legacy/unmigrated tenants keep full access.
            if (! $permissionService->enforcementEnabled($tenant)) {
                return true;
            }

            return $permissionService->has($user, $tenant, $ability);
        });

        // @permission('slug') ... @endpermission — sugar over @can for readability.
        Blade::if('permission', fn (string $ability): bool => Gate::allows($ability));
    }

    /**
     * Resolve (and memoise for the request) the viewer's active tenant.
     */
    private function activeTenant(User $user): ?Tenant
    {
        static $cache = [];

        if (array_key_exists($user->id, $cache)) {
            return $cache[$user->id];
        }

        return $cache[$user->id] = app(ActiveBranchManager::class)
            ->stateForRequest(request(), $user)['tenant'];
    }
}
