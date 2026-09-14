<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Modules\Access\Enums\MembershipStatus;
use Modules\Inventory\Models\InventoryBatch;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Tenancy\Models\Tenant;

final class BatchTraceController extends Controller
{
    /**
     * Searchable list of lots. This is the recall entry point: an inspector or supplier
     * gives you a batch number and you need to find it fast, including lots that have
     * already been fully consumed.
     */
    public function index(Request $request): View
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);

        $search = trim($request->string('q')->toString());
        $locationId = $request->integer('location');
        $filter = $request->string('show')->toString();

        $batches = InventoryBatch::query()
            ->with(['location', 'variant.product', 'allocations.movement'])
            ->where('tenant_id', $tenant->id)
            ->when($search !== '', fn ($query) => $query->where(function ($inner) use ($search): void {
                $inner->where('batch_number', 'like', '%'.$search.'%')
                    ->orWhereHas('variant', fn ($v) => $v->where('sku', 'like', '%'.$search.'%'))
                    ->orWhereHas('variant.product', fn ($p) => $p->where('name', 'like', '%'.$search.'%'));
            }))
            ->when($locationId > 0, fn ($query) => $query->where('inventory_location_id', $locationId))
            ->when($filter === 'in-stock', fn ($query) => $query->where('quantity_remaining', '>', 0))
            ->when($filter === 'exhausted', fn ($query) => $query->where('quantity_remaining', '<=', 0))
            ->when($filter === 'expiring', fn ($query) => $query
                ->where('quantity_remaining', '>', 0)
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '<=', now()->addDays(30)))
            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expiry_date')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return view('inventory::admin.batches.index', [
            'tenant' => $tenant,
            'tenants' => $tenants,
            'isPlatformAdmin' => $user->is_platform_admin,
            'batches' => $batches,
            'locations' => InventoryLocation::query()->where('tenant_id', $tenant->id)->orderBy('name')->get(),
            'search' => $search,
            'selectedLocationId' => $locationId > 0 ? $locationId : null,
            'filter' => $filter,
        ]);
    }

    /**
     * Where did this lot go? Shows the receipt that created it, every draw against it,
     * and — following transfers — the downstream lots it became in other stores.
     */
    public function show(Request $request, InventoryBatch $batch): View
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);
        abort_unless($batch->tenant_id === $tenant->id, 403);

        $batch->load([
            'location', 'variant.product', 'variant.baseUnit',
            'allocations.movement.location', 'allocations.movement.destinationLocation',
            'sourceBatch.location',
        ]);

        return view('inventory::admin.batches.show', [
            'tenant' => $tenant,
            'tenants' => $tenants,
            'isPlatformAdmin' => $user->is_platform_admin,
            'batch' => $batch,
            'downstream' => $this->downstreamChain($batch),
            'indexUrl' => $this->indexUrl($request),
        ]);
    }

    /**
     * Walk child lots breadth-first, tagging each with its depth so the view can indent
     * the chain. Guarded against cycles, which a corrupted source link could otherwise
     * turn into an infinite walk.
     *
     * @return Collection<int, array{batch: InventoryBatch, depth: int}>
     */
    private function downstreamChain(InventoryBatch $batch): Collection
    {
        $chain = collect();
        $seen = [$batch->id => true];
        $queue = [[$batch, 0]];

        while ($queue !== []) {
            [$current, $depth] = array_shift($queue);

            $children = $current->childBatches()
                ->with(['location', 'allocations.movement'])
                ->orderBy('id')
                ->get();

            foreach ($children as $child) {
                if (isset($seen[$child->id])) {
                    continue;
                }

                $seen[$child->id] = true;
                $chain->push(['batch' => $child, 'depth' => $depth + 1]);
                $queue[] = [$child, $depth + 1];
            }
        }

        return $chain;
    }

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

    private function indexUrl(Request $request): string
    {
        $tenantId = $request->string('tenant')->toString();

        return route('admin.inventory.batches.index', $tenantId !== '' ? ['tenant' => $tenantId] : []);
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
