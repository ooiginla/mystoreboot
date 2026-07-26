<?php

declare(strict_types=1);

namespace Modules\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Access\Actions\CreateTenantUserAction;
use Modules\Access\Actions\SaveRoleAction;
use Modules\Access\Enums\MembershipStatus;
use Modules\Access\Http\Requests\RoleEditorRequest;
use Modules\Access\Http\Requests\TenantUserRequest;
use Modules\Access\Models\Role;
use Modules\Access\Models\TenantMembership;
use Modules\Access\Support\AuditLogger;
use Modules\Access\Support\PermissionCatalogue;
use Modules\Business\Models\Branch;
use Modules\Tenancy\Models\Tenant;

final class RoleController extends Controller
{
    /**
     * Show the role editor for a brand new role (optionally pre-filled from a template).
     */
    public function create(Request $request): View
    {
        $tenant = $this->tenant($request->string('tenant')->toString());
        $this->authorizeManageRoles($request->user(), $tenant);

        $template = $request->string('template')->toString();
        $slugs = $template !== '' ? PermissionCatalogue::templatePermissions($template) : [];
        $meta = PermissionCatalogue::templates()[$template] ?? null;

        return view('access::admin.role-editor', $this->editorData($tenant, [
            'role' => null,
            'name' => $meta['name'] ?? '',
            'description' => $meta['description'] ?? '',
            'currentSlugs' => $slugs,
            'currentLimits' => $meta['limits'] ?? [],
        ]));
    }

    public function store(RoleEditorRequest $request, SaveRoleAction $action): RedirectResponse
    {
        $tenant = $this->tenant($request->string('tenant_id')->toString());
        $this->authorizeManageRoles($request->user(), $tenant);

        $role = $action->execute([
            'tenant_id' => $tenant->id,
            'name' => $request->string('name')->toString(),
            'description' => $request->input('description'),
            'levels' => $request->levels(),
            'sensitive' => (array) $request->input('sensitive', []),
            'limits' => (array) $request->input('limits', []),
        ], null, $tenant->currency_code ?: 'NGN');

        app(AuditLogger::class)->log($tenant->id, $request->user(), 'role.created', "Created role “{$role->name}”.", 'access', 'role', (string) $role->id);

        return $this->redirectToRoles($tenant, "Role “{$role->name}” created.");
    }

    public function edit(Request $request, Role $role): View
    {
        $tenant = $this->tenant($role->tenant_id);
        $this->authorizeManageRoles($request->user(), $tenant);
        abort_if($role->is_protected, 403, 'The Business Owner role is protected and cannot be edited.');

        return view('access::admin.role-editor', $this->editorData($tenant, [
            'role' => $role,
            'name' => $role->name,
            'description' => $role->description,
            'currentSlugs' => $role->permissionSlugs(),
            'currentLimits' => $role->limits ?? [],
        ]));
    }

    public function update(RoleEditorRequest $request, Role $role, SaveRoleAction $action): RedirectResponse
    {
        $tenant = $this->tenant($role->tenant_id);
        $this->authorizeManageRoles($request->user(), $tenant);
        abort_unless($request->string('tenant_id')->toString() === $role->tenant_id, 403);

        $role = $action->execute([
            'tenant_id' => $tenant->id,
            'name' => $request->string('name')->toString(),
            'description' => $request->input('description'),
            'levels' => $request->levels(),
            'sensitive' => (array) $request->input('sensitive', []),
            'limits' => (array) $request->input('limits', []),
        ], $role, $tenant->currency_code ?: 'NGN');

        app(AuditLogger::class)->log($tenant->id, $request->user(), 'role.updated', "Updated role “{$role->name}”.", 'access', 'role', (string) $role->id);

        return $this->redirectToRoles($tenant, "Role “{$role->name}” updated.");
    }

    public function duplicate(Request $request, Role $role): RedirectResponse
    {
        $tenant = $this->tenant($role->tenant_id);
        $this->authorizeManageRoles($request->user(), $tenant);

        $copy = new Role([
            'tenant_id' => $tenant->id,
            'name' => $this->uniqueCopyName($tenant->id, $role->name),
            'slug' => $this->uniqueSlug($tenant->id, $role->name.' copy'),
            'description' => $role->description,
            'is_system' => false,
            'is_protected' => false,
            'template_key' => null,
            'limits' => $role->limits,
            'summary' => $role->summary,
        ]);
        $copy->save();
        $copy->permissions()->sync($role->permissions()->pluck('permissions.id')->all());

        return redirect()
            ->route('admin.access.roles.edit', $copy)
            ->with('status', "Created “{$copy->name}”. Adjust and save.");
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $tenant = $this->tenant($role->tenant_id);
        $this->authorizeManageRoles($request->user(), $tenant);

        abort_if($role->is_protected, 403, 'The Business Owner role cannot be deleted.');

        $inUse = TenantMembership::query()->where('role_id', $role->id)->exists();
        abort_if($inUse, 422, 'Reassign the users on this role before deleting it.');

        $name = $role->name;
        $role->permissions()->detach();
        $role->delete();

        app(AuditLogger::class)->log($tenant->id, $request->user(), 'role.deleted', "Deleted role “{$name}”.", 'access', 'role', (string) $role->id);

        return $this->redirectToRoles($tenant, "Role “{$name}” deleted.");
    }

    public function storeTenantUser(TenantUserRequest $request, CreateTenantUserAction $action): RedirectResponse
    {
        $tenant = $this->tenant($request->string('tenant_id')->toString());
        $this->authorizeManageUsers($request->user(), $tenant);

        $user = $action->execute($request->validated());

        return redirect()
            ->to(route('admin.business.index', ['tenant' => $tenant->id]).'#users')
            ->with('status', "User {$user->name} added to this organization.");
    }

    public function updateMembership(Request $request, TenantMembership $membership): RedirectResponse
    {
        $tenant = $this->tenant($membership->tenant_id);
        $this->authorizeManageUsers($request->user(), $tenant);

        $data = $request->validate([
            'role_id' => ['nullable', Rule::exists('roles', 'id')->where('tenant_id', $tenant->id)],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('tenant_id', $tenant->id)],
        ]);

        // Lockout protection: never strip the last active Business Owner of the owner role.
        $ownerRole = Role::query()->where('tenant_id', $tenant->id)->where('slug', 'business-owner')->first();

        if ($ownerRole && (int) $membership->role_id === $ownerRole->id && (int) ($data['role_id'] ?? 0) !== $ownerRole->id) {
            $otherOwners = TenantMembership::query()
                ->where('tenant_id', $tenant->id)
                ->where('role_id', $ownerRole->id)
                ->where('status', MembershipStatus::Active->value)
                ->where('id', '!=', $membership->id)
                ->exists();

            abort_unless($otherOwners, 422, 'This is the last Business Owner. Assign another owner before changing this one.');
        }

        $membership->update([
            'role_id' => $data['role_id'] ?: null,
            'branch_id' => $data['branch_id'] ?: null,
        ]);

        $newRole = $data['role_id'] ? Role::find($data['role_id'])?->name : 'no role';
        app(AuditLogger::class)->log($tenant->id, $request->user(), 'membership.updated', "Set {$membership->user->name}'s role to {$newRole}.", 'access', 'membership', (string) $membership->id, [
            'role_id' => $data['role_id'] ?: null,
            'branch_id' => $data['branch_id'] ?: null,
        ]);

        return redirect()
            ->to(route('admin.business.index', ['tenant' => $tenant->id]).'#users')
            ->with('status', "Access updated for {$membership->user->name}.");
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function editorData(Tenant $tenant, array $state): array
    {
        $slugs = (array) $state['currentSlugs'];
        $modules = PermissionCatalogue::modules();
        $levels = [];

        foreach ($modules as $key => $module) {
            $levels[$key] = PermissionCatalogue::resolveLevel($key, $slugs);
        }

        return [
            'tenant' => $tenant,
            'role' => $state['role'],
            'roleName' => old('name', $state['name']),
            'roleDescription' => old('description', $state['description']),
            'modules' => $modules,
            'definitions' => PermissionCatalogue::definitions(),
            'catalogueLimits' => PermissionCatalogue::limits(),
            'currentLevels' => old('levels', $levels),
            'currentSensitive' => old('sensitive', array_values(array_intersect($slugs, $this->sensitiveSlugs()))),
            'currentLimitValues' => $this->limitDisplayValues($state['currentLimits']),
            'currency' => $tenant->currency_code ?: 'NGN',
        ];
    }

    /**
     * Convert stored limits (money in minor units) to editor display values (major units).
     *
     * @param  array<string, int|float>  $limits
     * @return array<string, int|float>
     */
    private function limitDisplayValues(array $limits): array
    {
        $catalogue = PermissionCatalogue::limits();
        $out = [];

        foreach ($limits as $key => $value) {
            $out[$key] = ($catalogue[$key]['type'] ?? null) === 'money' ? $value / 100 : $value;
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function sensitiveSlugs(): array
    {
        return array_keys(array_filter(
            PermissionCatalogue::definitions(),
            static fn (array $d): bool => $d['sensitive'],
        ));
    }

    private function redirectToRoles(Tenant $tenant, string $status): RedirectResponse
    {
        return redirect()
            ->to(route('admin.business.index', ['tenant' => $tenant->id]).'#roles')
            ->with('status', $status);
    }

    private function tenant(string $tenantId): Tenant
    {
        return Tenant::query()->findOrFail($tenantId);
    }

    private function authorizeManageRoles(?User $user, Tenant $tenant): void
    {
        abort_unless($user instanceof User, 403);
        abort_unless($user->is_platform_admin || $user->hasPermission($tenant, 'roles.manage'), 403);
    }

    private function authorizeManageUsers(?User $user, Tenant $tenant): void
    {
        abort_unless($user instanceof User, 403);
        abort_unless(
            $user->is_platform_admin
                || $user->hasPermission($tenant, 'users.manage')
                || $user->hasPermission($tenant, 'users.invite'),
            403,
        );
    }

    private function uniqueCopyName(string $tenantId, string $name): string
    {
        $candidate = $name.' (copy)';
        $counter = 2;

        while (Role::query()->where('tenant_id', $tenantId)->where('name', $candidate)->exists()) {
            $candidate = $name.' (copy '.$counter.')';
            $counter++;
        }

        return $candidate;
    }

    private function uniqueSlug(string $tenantId, string $name): string
    {
        $base = \Illuminate\Support\Str::slug($name) ?: 'role';
        $slug = $base;
        $counter = 2;

        while (Role::query()->where('tenant_id', $tenantId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
