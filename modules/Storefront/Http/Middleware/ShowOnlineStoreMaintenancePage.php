<?php

declare(strict_types=1);

namespace Modules\Storefront\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Business\Models\OnlineStore;
use Symfony\Component\HttpFoundation\Response;

final class ShowOnlineStoreMaintenancePage
{
    public function handle(Request $request, Closure $next): Response
    {
        $store = $request->route('store');

        if ($store instanceof OnlineStore && $store->maintenance_mode) {
            return response()->view('storefront::maintenance', [
                'store' => $store,
            ]);
        }

        return $next($request);
    }
}
