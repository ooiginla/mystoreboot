<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Support;

use Illuminate\Support\Collection;
use Modules\Subscriptions\Models\Module;
use Modules\Subscriptions\Models\TenantModuleEntitlement;
use Modules\Subscriptions\Models\TenantSubscription;
use Modules\Tenancy\Models\Tenant;

final class TenantModuleAccess
{
    /**
     * @return Collection<int, array{module: Module, enabled: bool, source: string}>
     */
    public function states(Tenant $tenant): Collection
    {
        $modules = Module::query()
            ->orderByDesc('is_core')
            ->orderBy('name')
            ->get();
        $subscription = TenantSubscription::query()
            ->with('plan.modules')
            ->where('tenant_id', $tenant->id)
            ->latest('id')
            ->first();
        $overrides = TenantModuleEntitlement::query()
            ->where('tenant_id', $tenant->id)
            ->get()
            ->keyBy('module_id');
        $planModules = $subscription?->plan?->modules
            ?->filter(fn (Module $module): bool => (bool) ($module->pivot->is_enabled ?? true))
            ->keyBy('id') ?? collect();
        $subscriptionAllowsModules = $subscription?->status->allowsModuleAccess() ?? true;

        return $modules->map(function (Module $module) use ($subscription, $subscriptionAllowsModules, $overrides, $planModules): array {
            if ($module->is_core) {
                return ['module' => $module, 'enabled' => true, 'source' => 'core'];
            }

            if (! $module->is_active || ! $subscriptionAllowsModules) {
                return ['module' => $module, 'enabled' => false, 'source' => $module->is_active ? 'subscription' : 'inactive'];
            }

            $override = $overrides->get($module->id);
            if ($override) {
                return ['module' => $module, 'enabled' => (bool) $override->is_enabled, 'source' => 'tenant'];
            }

            // Legacy tenants without a subscription retain their existing access.
            $enabled = $subscription ? $planModules->has($module->id) : true;

            return ['module' => $module, 'enabled' => $enabled, 'source' => 'plan'];
        })->values();
    }

    /**
     * @return Collection<int, string>
     */
    public function enabledSlugs(Tenant $tenant): Collection
    {
        return $this->states($tenant)
            ->filter(fn (array $state): bool => $state['enabled'])
            ->map(fn (array $state): string => $state['module']->slug)
            ->values();
    }

    public function allows(Tenant $tenant, string $moduleSlug): bool
    {
        $states = $this->states($tenant);

        // Keep newly registered code modules visible until their billable-module
        // record is installed, instead of unexpectedly removing navigation.
        if (! $states->contains(fn (array $state): bool => $state['module']->slug === $moduleSlug)) {
            return true;
        }

        return $states->contains(
            fn (array $state): bool => $state['module']->slug === $moduleSlug && $state['enabled'],
        );
    }
}
