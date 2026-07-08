<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Access\Enums\MembershipStatus;
use Modules\Access\Models\TenantMembership;
use Modules\Business\Models\Branch;
use Modules\Tenancy\Models\Tenant;

final class ActiveBranchManager
{
    /**
     * @return array{
     *     tenant: Tenant|null,
     *     branches: Collection<int, Branch>,
     *     activeBranch: Branch|null,
     *     shouldPrompt: bool,
     * }
     */
    public function stateForRequest(Request $request, ?User $user): array
    {
        if (! $user instanceof User) {
            return $this->emptyState();
        }

        $tenant = $this->resolveTenant($request, $user);

        if (! $tenant) {
            return $this->emptyState();
        }

        $branches = $this->branchesFor($user, $tenant);

        if ($branches->isEmpty()) {
            $this->forget($request, $tenant->id);

            return [
                'tenant' => $tenant,
                'branches' => $branches,
                'activeBranch' => null,
                'shouldPrompt' => false,
            ];
        }

        $storedBranchId = $this->storedBranchId($request, $tenant->id);
        $activeBranch = $storedBranchId
            ? $branches->firstWhere('id', $storedBranchId)
            : null;

        if (! $activeBranch && $branches->count() === 1) {
            $activeBranch = $branches->first();
            $this->set($request, $tenant->id, (int) $activeBranch->id);
        }

        if ($storedBranchId && ! $activeBranch) {
            $this->forget($request, $tenant->id);
        }

        return [
            'tenant' => $tenant,
            'branches' => $branches,
            'activeBranch' => $activeBranch,
            'shouldPrompt' => $branches->count() > 1 && ! $activeBranch,
        ];
    }

    public function canUseBranch(User $user, string $tenantId, int $branchId): bool
    {
        return $this->branchesFor($user, Tenant::query()->findOrFail($tenantId))
            ->contains('id', $branchId);
    }

    public function set(Request $request, string $tenantId, int $branchId): void
    {
        $activeBranches = $request->session()->get('active_branch_ids', []);
        $activeBranches[$tenantId] = $branchId;

        $request->session()->put('active_tenant_id', $tenantId);
        $request->session()->put('active_branch_ids', $activeBranches);
    }

    public function forget(Request $request, string $tenantId): void
    {
        $activeBranches = $request->session()->get('active_branch_ids', []);
        unset($activeBranches[$tenantId]);

        $request->session()->put('active_branch_ids', $activeBranches);
    }

    /**
     * @return Collection<int, Branch>
     */
    public function branchesFor(User $user, Tenant $tenant): Collection
    {
        $query = Branch::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->orderByDesc('is_primary')
            ->orderBy('name');

        if ($user->is_platform_admin) {
            return $query->get();
        }

        $membership = TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->where('status', MembershipStatus::Active->value)
            ->first();

        if (! $membership) {
            return collect();
        }

        if ($membership->branch_id) {
            $query->whereKey($membership->branch_id);
        }

        return $query->get();
    }

    private function storedBranchId(Request $request, string $tenantId): ?int
    {
        $branchId = $request->session()->get("active_branch_ids.{$tenantId}");

        return $branchId ? (int) $branchId : null;
    }

    private function resolveTenant(Request $request, User $user): ?Tenant
    {
        $visibleTenants = $this->visibleTenantsFor($user);
        $tenantId = $request->string('tenant')->toString();

        if ($tenantId !== '') {
            $tenant = $visibleTenants->firstWhere('id', $tenantId);

            if ($tenant) {
                $request->session()->put('active_tenant_id', $tenant->id);
            }

            return $tenant;
        }

        $storedTenantId = $request->session()->get('active_tenant_id');
        $storedTenant = $storedTenantId ? $visibleTenants->firstWhere('id', $storedTenantId) : null;

        if ($storedTenant) {
            return $storedTenant;
        }

        $tenant = $visibleTenants->first();

        if ($tenant) {
            $request->session()->put('active_tenant_id', $tenant->id);
        }

        return $tenant;
    }

    /**
     * @return EloquentCollection<int, Tenant>
     */
    private function visibleTenantsFor(User $user): EloquentCollection
    {
        if ($user->is_platform_admin) {
            return Tenant::query()->orderBy('name')->get();
        }

        return Tenant::query()
            ->whereHas('memberships', fn ($query) => $query->where('user_id', $user->id)->where('status', MembershipStatus::Active->value))
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array{tenant: null, branches: Collection<int, Branch>, activeBranch: null, shouldPrompt: false}
     */
    private function emptyState(): array
    {
        return [
            'tenant' => null,
            'branches' => collect(),
            'activeBranch' => null,
            'shouldPrompt' => false,
        ];
    }
}
