<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Access\Enums\MembershipStatus;
use Modules\Inventory\Actions\OpenStockCountAction;
use Modules\Inventory\Actions\PostStockCountAction;
use Modules\Inventory\Enums\StockCountStatus;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Inventory\Models\StockCount;
use Modules\Inventory\Support\Quantity;
use Modules\Tenancy\Models\Tenant;

final class StockCountController extends Controller
{
    public function index(Request $request): View
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);

        return view('inventory::admin.stock-counts.index', [
            'tenant' => $tenant,
            'tenants' => $tenants,
            'isPlatformAdmin' => $user->is_platform_admin,
            'counts' => StockCount::query()
                ->with(['items', 'location'])
                ->where('tenant_id', $tenant->id)
                ->latest('id')
                ->limit(60)
                ->get(),
            'locations' => InventoryLocation::query()->where('tenant_id', $tenant->id)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, OpenStockCountAction $action): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);

        $validated = $request->validate([
            'inventory_location_id' => ['required', 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $location = InventoryLocation::query()
            ->where('tenant_id', $tenant->id)
            ->findOrFail((int) $validated['inventory_location_id']);

        $count = $action->execute(
            $tenant->id,
            $location->id,
            $request->boolean('is_blind'),
            $user->id,
            $validated['notes'] ?? null,
        );

        return redirect()
            ->to($this->showUrl($request, $count))
            ->with('status', "Count {$count->count_number} opened with {$count->items->count()} lines.");
    }

    public function show(Request $request, StockCount $stockCount): View
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);
        abort_unless($stockCount->tenant_id === $tenant->id, 403);

        $stockCount->load(['items.componentVariant.product', 'items.componentVariant.baseUnit', 'location']);

        return view('inventory::admin.stock-counts.show', [
            'tenant' => $tenant,
            'tenants' => $tenants,
            'isPlatformAdmin' => $user->is_platform_admin,
            'count' => $stockCount,
            'indexUrl' => $this->indexUrl($request),
        ]);
    }

    /**
     * Save the counted quantities. Left blank means "not counted yet" and stays null —
     * a zero is a real count of nothing and posts as a full write-off.
     */
    public function updateItems(Request $request, StockCount $stockCount): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);
        abort_unless($stockCount->tenant_id === $tenant->id, 403);
        abort_unless($stockCount->status->isOpen(), 422);

        $counted = (array) $request->input('counted', []);

        foreach ($stockCount->items as $item) {
            if (! array_key_exists($item->id, $counted)) {
                continue;
            }

            $value = $counted[$item->id];
            $item->update([
                'counted_quantity' => ($value === null || $value === '') ? null : Quantity::round((float) $value),
            ]);
        }

        $stockCount->update(['status' => StockCountStatus::Review->value]);

        return redirect()
            ->to($this->showUrl($request, $stockCount))
            ->with('status', 'Counted quantities saved. Review the variance, then post.');
    }

    public function post(Request $request, StockCount $stockCount, PostStockCountAction $action): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);
        abort_unless($stockCount->tenant_id === $tenant->id, 403);

        $adjusted = $action->execute($stockCount, $user->id);

        return redirect()
            ->to($this->showUrl($request, $stockCount))
            ->with('status', $adjusted === 0
                ? 'Count posted with no variance — the shelf matched the system.'
                : "Count posted. {$adjusted} line(s) adjusted.");
    }

    public function cancel(Request $request, StockCount $stockCount): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request, $tenants, $user);
        abort_unless($stockCount->tenant_id === $tenant->id, 403);
        abort_unless($stockCount->status->isOpen(), 422);

        $stockCount->update(['status' => StockCountStatus::Cancelled->value]);

        return redirect()->to($this->indexUrl($request))->with('status', 'Count cancelled.');
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

        return route('admin.inventory.stock-counts.index', $tenantId !== '' ? ['tenant' => $tenantId] : []);
    }

    private function showUrl(Request $request, StockCount $count): string
    {
        $tenantId = $request->string('tenant')->toString();
        $params = ['stockCount' => $count->id];

        if ($tenantId !== '') {
            $params['tenant'] = $tenantId;
        }

        return route('admin.inventory.stock-counts.show', $params);
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
