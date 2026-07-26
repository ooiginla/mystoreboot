<?php

declare(strict_types=1);

namespace Modules\Access\Http\Middleware;

use App\Models\User;
use App\Support\ActiveBranchManager;
use Closure;
use Illuminate\Http\Request;
use Modules\Access\Support\AuditLogger;
use Modules\Access\Support\PermissionService;
use Modules\Access\Support\RoutePermissionMap;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deny-by-default authorization for every admin.* route.
 *
 *  1. Platform admins bypass entirely.
 *  2. If the tenant has enforcement switched off, the request passes (safety valve).
 *  3. Otherwise the route must be mapped in RoutePermissionMap and the user must hold
 *     the required permission(s). Unmapped routes are refused.
 */
final class EnforcePermissions
{
    public function __construct(
        private readonly ActiveBranchManager $branches,
        private readonly PermissionService $permissions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // The auth middleware runs first; if somehow unauthenticated, let it be handled elsewhere.
        if (! $user instanceof User || $user->is_platform_admin) {
            return $next($request);
        }

        $tenant = $this->branches->stateForRequest($request, $user)['tenant'] ?? null;

        // No tenant context to enforce against — nothing to authorize here.
        if (! $tenant) {
            return $next($request);
        }

        // Per-tenant master switch (reversible safety valve for misconfiguration).
        if (! $this->permissions->enforcementEnabled($tenant)) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        $required = RoutePermissionMap::for($routeName);

        if ($required === RoutePermissionMap::ALLOW) {
            return $next($request);
        }

        // Mapped route: allow if the user holds any of the required permissions.
        if ($required !== null) {
            foreach ((array) $required as $slug) {
                if ($this->permissions->has($user, $tenant, $slug)) {
                    return $next($request);
                }
            }
        }

        // Denied (unmapped or lacking permission). Record it and refuse.
        app(AuditLogger::class)->log(
            $tenant->id,
            $user,
            'permission.denied',
            'Blocked from '.($routeName ?? $request->path()).'.',
            'security',
            'route',
            $routeName,
            ['required' => $required === null ? 'unmapped' : $required, 'method' => $request->method()],
        );

        abort(403, 'You do not have permission for this action.');
    }
}
