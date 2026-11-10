<?php

declare(strict_types=1);

namespace Modules\Storefront\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Business\Models\OnlineStore;
use Modules\Tenancy\Models\TenantDomain;
use Symfony\Component\HttpFoundation\Response;

final class ResolveCustomStorefrontDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $hostname = strtolower($request->getHost());
        $domain = $request->attributes->get('tenant_domain');

        if (! $domain instanceof TenantDomain || $domain->domain !== $hostname) {
            $domain = TenantDomain::query()
                ->where('domain', $hostname)
                ->where('domain_type', TenantDomain::TYPE_CUSTOM)
                ->where('status', TenantDomain::STATUS_ACTIVE)
                ->whereHas('tenant', fn ($query) => $query->whereIn('status', ['active', 'trialing']))
                ->first();
        }

        if (! $domain) {
            abort(404, 'This domain is not connected to an active Storeboot storefront.');
        }

        $store = OnlineStore::query()
            ->where('tenant_id', $domain->tenant_id)
            ->where('is_active', true)
            ->firstOrFail();

        $route = $request->route();
        $route?->forgetParameter('customDomain');
        $route?->setParameter('store', $store);

        return $next($request);
    }
}
