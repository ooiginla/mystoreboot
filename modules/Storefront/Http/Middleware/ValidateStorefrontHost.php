<?php

declare(strict_types=1);

namespace Modules\Storefront\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Tenancy\Models\TenantDomain;
use Symfony\Component\HttpFoundation\Response;

final class ValidateStorefrontHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());
        $baseDomain = strtolower(trim((string) config('storefront.base_domain'))) ?: 'storeboot.test';
        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $reservedDomains = collect((array) config('storefront.reserved_domains', []))
            ->map(fn (mixed $domain): string => strtolower(rtrim(trim((string) $domain), '.')))
            ->filter();
        $reservedSubdomains = collect((array) config('storefront.reserved_subdomains', []))
            ->map(fn (mixed $subdomain): string => strtolower(trim((string) $subdomain)))
            ->filter();
        $routeName = (string) $request->route()?->getName();
        $hostSuffix = '.'.$baseDomain;
        $platformSubdomain = str_ends_with($host, $hostSuffix)
            ? substr($host, 0, -strlen($hostSuffix))
            : null;

        $isCentralPlatformHost = in_array($host, array_filter([$appHost, $baseDomain, 'localhost', '127.0.0.1']), true)
            || $reservedDomains->contains($host)
            || ($platformSubdomain !== null
                && ! str_contains($platformSubdomain, '.')
                && $reservedSubdomains->contains($platformSubdomain));

        if ($isCentralPlatformHost) {
            return $next($request);
        }

        $isManagedStorefrontHost = str_ends_with($host, $hostSuffix)
            && str_starts_with($routeName, 'storefront.storefront.subdomain.');

        if ($isManagedStorefrontHost) {
            return $next($request);
        }

        $domain = TenantDomain::query()->where('domain', $host)->first();

        if (! $domain
            || $domain->status !== TenantDomain::STATUS_ACTIVE
            || ! $domain->tenant?->status->allowsTenantAccess()
            || ! str_starts_with($routeName, 'storefront.storefront.custom.')) {
            abort(404, 'This host is not connected to an active Storeboot storefront.');
        }

        $request->attributes->set('tenant_domain', $domain);

        return $next($request);
    }
}
