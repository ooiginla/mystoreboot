<?php

declare(strict_types=1);

namespace Modules\Sales\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Modules\Access\Enums\MembershipStatus;
use Modules\Sales\Models\SalesOrder;
use Modules\Tenancy\Models\Tenant;

/**
 * Tenant resolution and URLs shared by the restaurant controllers, so billing, voids
 * and modifiers resolve the business exactly the way the floor does.
 */
trait ResolvesRestaurantTenant
{
    private function tenantFromRequest(Request $request, ?EloquentCollection &$tenants, ?User &$user): Tenant
    {
        /** @var User $user */
        $user = $request->user();
        $tenants = $this->visibleTenantsFor($user);

        $tenantId = $request->string('tenant')->toString();
        if ($tenantId !== '') {
            abort_unless($tenants->contains('id', $tenantId), 403);
            $tenant = Tenant::query()->find($tenantId);
        } else {
            $tenant = $tenants->first();
        }

        abort_if(! $tenant, 403);

        return $tenant;
    }

    /**
     * The order must be a restaurant check belonging to the business in the request.
     */
    private function checkFor(Request $request, SalesOrder $order, ?EloquentCollection &$tenants = null, ?User &$user = null): Tenant
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);
        abort_unless($order->tenant_id === $tenant->id, 403);
        abort_unless($order->isCheck(), 404);

        return $tenant;
    }

    private function floorUrl(Request $request): string
    {
        $tenantId = $request->string('tenant')->toString();

        return route('admin.sales.restaurant.floor', $tenantId !== '' ? ['tenant' => $tenantId] : []);
    }

    private function checkUrl(Request $request, SalesOrder $check): string
    {
        $tenantId = $request->string('tenant')->toString();
        $params = ['order' => $check->id];

        if ($tenantId !== '') {
            $params['tenant'] = $tenantId;
        }

        return route('admin.sales.restaurant.check', $params);
    }

    /**
     * @return EloquentCollection<int, Tenant>
     */
    private function visibleTenantsFor(User $user): EloquentCollection
    {
        if ($user->is_platform_admin) {
            return Tenant::query()->orderBy('name')->get();
        }

        return Tenant::query()
            ->whereHas('memberships', fn ($query) => $query->where('user_id', $user->id)->where('status', MembershipStatus::Active->value))
            ->orderBy('name')
            ->get();
    }
}
