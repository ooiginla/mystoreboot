<?php

declare(strict_types=1);

namespace Modules\Access\Support;

use App\Models\User;
use Modules\Access\Enums\MembershipStatus;
use Modules\Access\Models\Role;
use Modules\Access\Models\TenantMembership;
use Modules\Tenancy\Models\Tenant;

/**
 * Central authorization service. Answers "can this user do X in this tenant/branch",
 * exposes role limits, approval requirements, and the per-tenant enforcement switch.
 *
 * Platform admins bypass tenant RBAC entirely. Effective permissions are memoised
 * per request to keep checks cheap.
 */
final class PermissionService
{
    /** @var array<string, list<string>> */
    private array $permissionCache = [];

    /** @var array<string, TenantMembership|null> */
    private array $membershipCache = [];

    public function membership(User $user, Tenant $tenant): ?TenantMembership
    {
        $key = $user->id.':'.$tenant->id;

        if (! array_key_exists($key, $this->membershipCache)) {
            $this->membershipCache[$key] = TenantMembership::query()
                ->with('role.permissions')
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $user->id)
                ->where('status', MembershipStatus::Active->value)
                ->first();
        }

        return $this->membershipCache[$key];
    }

    public function role(User $user, Tenant $tenant): ?Role
    {
        return $this->membership($user, $tenant)?->role;
    }

    /**
     * The effective permission slugs for a user within a tenant.
     *
     * @return list<string>
     */
    public function permissions(User $user, Tenant $tenant): array
    {
        if ($user->is_platform_admin) {
            return array_keys(PermissionCatalogue::definitions());
        }

        $key = $user->id.':'.$tenant->id;

        if (! array_key_exists($key, $this->permissionCache)) {
            $this->permissionCache[$key] = $this->membership($user, $tenant)?->role?->permissionSlugs() ?? [];
        }

        return $this->permissionCache[$key];
    }

    public function has(User $user, Tenant $tenant, string $permission): bool
    {
        if ($user->is_platform_admin) {
            return true;
        }

        return in_array($permission, $this->permissions($user, $tenant), true);
    }

    /**
     * Full check: permission + branch scope.
     */
    public function can(User $user, Tenant $tenant, string $permission, ?int $branchId = null): bool
    {
        if ($user->is_platform_admin) {
            return true;
        }

        if (! $this->has($user, $tenant, $permission)) {
            return false;
        }

        return $this->allowsBranch($user, $tenant, $branchId);
    }

    /**
     * Branch scope. A membership with a null branch_id can act in all branches;
     * otherwise it is confined to its assigned branch.
     */
    public function allowsBranch(User $user, Tenant $tenant, ?int $branchId): bool
    {
        if ($user->is_platform_admin || $branchId === null) {
            return true;
        }

        $membership = $this->membership($user, $tenant);

        if (! $membership) {
            return false;
        }

        return $membership->branch_id === null || (int) $membership->branch_id === $branchId;
    }

    /**
     * The numeric limit configured on the user's role for a limit key (null = unlimited).
     */
    public function limit(User $user, Tenant $tenant, string $key): int|float|null
    {
        if ($user->is_platform_admin) {
            return null;
        }

        return $this->role($user, $tenant)?->limit($key);
    }

    /**
     * Whether a sensitive action must route through the approval workflow,
     * per the tenant's Business Settings.
     */
    public function requiresApproval(Tenant $tenant, string $action): bool
    {
        $approvals = (array) ($tenant->settings['approvals'] ?? []);

        if (! (bool) ($approvals['enabled'] ?? false)) {
            return false;
        }

        return (bool) ($approvals['actions'][$action] ?? false);
    }

    /**
     * The per-tenant enforcement master switch. Defaults to enabled once a tenant
     * has been provisioned with system roles (see the RBAC backfill migration).
     */
    public function enforcementEnabled(Tenant $tenant): bool
    {
        return (bool) ($tenant->settings['rbac_enforced'] ?? false);
    }
}
