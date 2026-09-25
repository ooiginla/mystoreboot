<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Middleware;

use App\Support\ActiveBranchManager;
use Closure;
use Illuminate\Http\Request;
use Modules\Subscriptions\Support\TenantModuleAccess;
use Modules\Tenancy\Enums\CommerceMode;
use Symfony\Component\HttpFoundation\Response;

final class RequireStandardCommerceMode
{
    public function __construct(
        private readonly ActiveBranchManager $branches,
        private readonly TenantModuleAccess $modules,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->branches->stateForRequest($request, $request->user())['tenant'] ?? null;

        abort_if(
            $tenant
                && $tenant->commerce_mode === CommerceMode::Reseller
                && $this->modules->allows($tenant, 'reseller'),
            403,
            'Native catalogue management is unavailable for reseller stores.',
        );

        return $next($request);
    }
}
