<?php

declare(strict_types=1);

namespace Modules\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ActiveBranchManager;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Access\Models\Role;
use Modules\Access\Models\SecurityAuditLog;
use Modules\Access\Models\TenantMembership;
use Modules\Access\Support\AccessRiskAnalyzer;
use Modules\Access\Support\PermissionCatalogue;
use Modules\Tenancy\Models\Tenant;

final class AccessReviewController extends Controller
{
    public function index(Request $request, AccessRiskAnalyzer $analyzer): View
    {
        /** @var User $user */
        $user = $request->user();
        $tenant = app(ActiveBranchManager::class)->stateForRequest($request, $user)['tenant'] ?? null;

        abort_unless($tenant instanceof Tenant, 403);

        $roles = Role::query()
            ->with('permissions')
            ->where('tenant_id', $tenant->id)
            ->withCount('memberships')
            ->orderByDesc('is_protected')
            ->orderBy('name')
            ->get();

        $memberships = TenantMembership::query()
            ->with(['user', 'role', 'branch'])
            ->where('tenant_id', $tenant->id)
            ->get();

        $totalPermissions = count(PermissionCatalogue::definitions());

        $auditLogs = SecurityAuditLog::query()
            ->with('actor')
            ->where('tenant_id', $tenant->id)
            ->latest('created_at')
            ->limit(40)
            ->get();

        return view('access::admin.access-review', [
            'tenant' => $tenant,
            'roles' => $roles,
            'memberships' => $memberships->sortBy(fn (TenantMembership $m) => $m->user?->name)->values(),
            'findings' => $analyzer->analyze($roles, $memberships),
            'totalPermissions' => $totalPermissions,
            'auditLogs' => $auditLogs,
        ]);
    }
}
