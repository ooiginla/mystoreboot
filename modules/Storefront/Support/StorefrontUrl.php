<?php

declare(strict_types=1);

namespace Modules\Storefront\Support;

use Illuminate\Http\Request;
use Modules\Business\Models\OnlineStore;

final class StorefrontUrl
{
    /**
     * Generate a public URL for a storefront route.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function route(
        OnlineStore $store,
        string $name = 'home',
        array $parameters = [],
        bool $absolute = true,
    ): string {
        $baseDomain = trim((string) config('storefront.base_domain'));
        $usesSubdomain = $baseDomain !== '' && filled($store->subdomain);
        $routeName = $usesSubdomain
            ? 'storefront.storefront.subdomain.'.$name
            : 'storefront.storefront.store.'.$name;
        $storeParameter = $usesSubdomain ? $store->subdomain : $store;
        $url = route($routeName, ['store' => $storeParameter, ...$parameters], $absolute);

        if (! $absolute || ! $usesSubdomain) {
            return $url;
        }

        $scheme = trim((string) config('storefront.scheme', 'https'));

        return preg_replace('/^https?:\/\//', $scheme.'://', $url) ?? $url;
    }

    public static function canonicalForRequest(OnlineStore $store, Request $request): string
    {
        $route = $request->route();
        $routeName = $route?->getName() ?? '';

        if (! preg_match('/\.(?:subdomain|store)\.(.+)$/', $routeName, $matches)) {
            return self::route($store);
        }

        $parameters = $route?->parameters() ?? [];
        unset($parameters['store']);

        $url = self::route($store, $matches[1], $parameters);
        $page = max(1, $request->integer('page', 1));

        return $page > 1 ? $url.'?page='.$page : $url;
    }
}
