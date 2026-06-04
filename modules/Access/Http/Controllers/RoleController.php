<?php

declare(strict_types=1);

namespace Modules\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Modules\Access\Actions\CreateRoleAction;
use Modules\Access\Actions\CreateTenantUserAction;
use Modules\Access\Http\Requests\RoleRequest;
use Modules\Access\Http\Requests\TenantUserRequest;
use Modules\Access\Models\TenantMembership;

final class RoleController extends Controller
{
    public function store(RoleRequest $request, CreateRoleAction $action): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());

        $role = $action->execute($request->validated());

        return redirect()
            ->route('admin.business.index', ['tenant' => $role->tenant_id])
            ->with('status', "Role {$role->name} created.");
    }

    public function storeTenantUser(TenantUserRequest $request, CreateTenantUserAction $action): RedirectResponse
    {
        $this->authorizeTenantIdAccess($request->user(), $request->string('tenant_id')->toString());

        $user = $action->execute($request->validated());

        return redirect()
            ->route('admin.business.index', ['tenant' => $request->string('tenant_id')->toString()])
            ->with('status', "User {$user->name} added to this organization.");
    }

    private function authorizeTenantIdAccess(?User $user, string $tenantId): void
    {
        abort_unless($user instanceof User, 403);

        if ($user->is_platform_admin) {
            return;
        }

        abort_unless(
            TenantMembership::query()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->exists(),
            403,
        );
    }
}
