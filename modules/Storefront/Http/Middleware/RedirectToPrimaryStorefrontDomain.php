<?php

declare(strict_types=1);

namespace Modules\Storefront\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Business\Models\OnlineStore;
use Modules\Storefront\Support\StorefrontUrl;
use Modules\Tenancy\Models\TenantDomain;
use Symfony\Component\HttpFoundation\Response;

final class RedirectToPrimaryStorefrontDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethodSafe()) {
            return $next($request);
        }

        $store = $request->route('store');

        if (! $store instanceof OnlineStore) {
            return $next($request);
        }

        $primary = TenantDomain::query()
            ->where('tenant_id', $store->tenant_id)
            ->where('is_primary', true)
            ->where('status', TenantDomain::STATUS_ACTIVE)
            ->first();

        if (! $primary || $request->getHost() === $primary->domain) {
            return $next($request);
        }

        $routeName = (string) $request->route()?->getName();

        if (! preg_match('/\.(?:subdomain|custom)\.(.+)$/', $routeName, $matches)) {
            return $next($request);
        }

        $parameters = $request->route()?->parameters() ?? [];
        unset($parameters['store'], $parameters['customDomain']);
        $url = StorefrontUrl::route($store, $matches[1], $parameters);
        $query = $request->getQueryString();

        return redirect()->away($url.($query ? '?'.$query : ''), 301);
    }
}
