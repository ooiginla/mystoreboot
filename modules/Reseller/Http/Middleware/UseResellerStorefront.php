<?php

declare(strict_types=1);

namespace Modules\Reseller\Http\Middleware;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Business\Models\OnlineStore;
use Modules\Reseller\Http\Controllers\ResellerStorefrontController;
use Modules\Subscriptions\Support\TenantModuleAccess;

final class UseResellerStorefront
{
    public function __construct(private readonly TenantModuleAccess $modules) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $store = $request->route('store');

        if (! $store instanceof OnlineStore
            || ! $store->tenant?->isReseller()
            || ! $this->modules->allows($store->tenant, 'reseller')) {
            return $next($request);
        }

        $controller = app(ResellerStorefrontController::class);
        $route = (string) $request->route()?->getName();

        $response = match (true) {
            str_ends_with($route, '.home') => $controller->home($store, $request),
            str_ends_with($route, '.categories.show') => $controller->category($store, (string) $request->route('categorySlug')),
            str_ends_with($route, '.products.show') => $controller->product($store, (string) $request->route('productSlug')),
            str_ends_with($route, '.checkout') => $controller->checkout($store, $request),
            str_ends_with($route, '.track') => $controller->track($store, $request),
            str_ends_with($route, '.sitemap') => $controller->sitemap($store),
            str_ends_with($route, '.services'),
            str_ends_with($route, '.services.show'),
            str_ends_with($route, '.collections.show') => abort(404),
            default => $next($request),
        };

        return $response instanceof View ? response($response->render()) : $response;
    }
}
