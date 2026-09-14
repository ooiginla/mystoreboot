<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Access\Enums\MembershipStatus;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Inventory\Models\LocationType;
use Modules\Tenancy\Models\Tenant;

final class LocationTypeController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request);
        $request->validate(['label' => ['required', 'string', 'max:80']]);

        $label = trim((string) $request->string('label'));

        LocationType::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'key' => $this->uniqueKey($tenant->id, $label)],
            ['label' => $label, 'is_system' => false, 'status' => 'active'],
        );

        return redirect()->to($this->redirectTo($request))->with('status', 'Location type added.');
    }

    public function update(Request $request, LocationType $locationType): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request);
        abort_unless($locationType->tenant_id === $tenant->id, 403);
        $request->validate(['label' => ['required', 'string', 'max:80']]);

        // The key stays fixed (locations reference it); only the display label changes.
        $locationType->update(['label' => trim((string) $request->string('label'))]);

        return redirect()->to($this->redirectTo($request))->with('status', 'Location type renamed.');
    }

    public function destroy(Request $request, LocationType $locationType): RedirectResponse
    {
        $tenant = $this->tenantFromRequest($request);
        abort_unless($locationType->tenant_id === $tenant->id, 403);

        // Built-in or not, a type can be removed unless a location using it holds stock.
        $locationIds = InventoryLocation::query()
            ->where('tenant_id', $tenant->id)
            ->where('location_type', $locationType->key)
            ->pluck('id');

        $hasStock = $locationIds->isNotEmpty() && InventoryStockLevel::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('inventory_location_id', $locationIds)
            ->where(fn ($q) => $q->where('quantity_on_hand', '>', 0)->orWhere('quantity_reserved', '>', 0))
            ->exists();

        if ($hasStock) {
            throw ValidationException::withMessages([
                'location_type' => 'This type can’t be removed while locations of this type still hold stock.',
            ]);
        }

        $locationType->delete();

        return redirect()->to($this->redirectTo($request))->with('status', 'Location type removed.');
    }

    private function uniqueKey(string $tenantId, string $label): string
    {
        $base = Str::slug($label, '_') ?: 'type';
        $key = $base;
        $i = 2;

        while (LocationType::query()->where('tenant_id', $tenantId)->where('key', $key)->exists()) {
            $key = $base.'_'.$i;
            $i++;
        }

        return $key;
    }

    private function tenantFromRequest(Request $request): Tenant
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

    private function redirectTo(Request $request): string
    {
        $tenantId = $request->string('tenant')->toString();

        return route('admin.inventory.index', $tenantId !== '' ? ['tenant' => $tenantId] : []).'#locations';
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
