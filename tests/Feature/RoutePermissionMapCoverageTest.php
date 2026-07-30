<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Modules\Access\Http\Middleware\EnforcePermissions;
use Modules\Access\Support\RoutePermissionMap;
use Tests\TestCase;

class RoutePermissionMapCoverageTest extends TestCase
{
    public function test_the_product_import_route_is_permission_mapped(): void
    {
        $this->assertArrayHasKey(
            'admin.catalog.products.import',
            RoutePermissionMap::map(),
            'The product import route must be listed in RoutePermissionMap or it 403s under RBAC enforcement.',
        );
    }

    public function test_sales_workspaces_use_their_matching_permissions(): void
    {
        $map = RoutePermissionMap::map();

        $this->assertSame('sales.create', $map['admin.sales.index']);
        $this->assertSame('sales.view', $map['admin.sales.orders.index']);
    }

    public function test_every_enforced_admin_route_is_permission_mapped(): void
    {
        $map = RoutePermissionMap::map();

        // Any admin route that reaches EnforcePermissions but is absent from the
        // map is denied by default (403), so an unmapped route is always a bug.
        $unmapped = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with((string) $route->getName(), 'admin.'))
            ->filter(fn ($route): bool => in_array(EnforcePermissions::class, $route->gatherMiddleware(), true))
            ->map(fn ($route): string => (string) $route->getName())
            ->reject(fn (string $name): bool => array_key_exists($name, $map))
            ->unique()
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            [],
            $unmapped,
            'These enforced admin routes are missing from RoutePermissionMap (deny-by-default → 403): '.implode(', ', $unmapped),
        );
    }
}
