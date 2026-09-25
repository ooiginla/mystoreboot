<?php

declare(strict_types=1);

namespace Modules\Reseller\Http\Middleware;

use App\Support\ActiveBranchManager;
use Closure;
use Illuminate\Http\Request;
use Modules\Subscriptions\Support\TenantModuleAccess;
use Symfony\Component\HttpFoundation\Response;

final class RequireResellerModule
{
    public function __construct(
        private readonly ActiveBranchManager $branches,
        private readonly TenantModuleAccess $modules,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->branches->stateForRequest($request, $request->user())['tenant'] ?? null;

        abort_unless($tenant && $this->modules->allows($tenant, 'reseller'), 403, 'The Reseller Store module is not enabled.');

        return $next($request);
    }
}
