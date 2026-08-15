<?php

declare(strict_types=1);

namespace Modules\Inventory\Actions;

use Illuminate\Support\Collection;
use Modules\Business\Models\Branch;
use Modules\Inventory\Enums\InventoryLocationType;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Tenancy\Models\Tenant;

final class EnsureInventoryLocationsAction
{
    public function forBranch(Branch $branch): InventoryLocation
    {
        $location = InventoryLocation::query()->firstOrNew([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
        ]);

        if (! $location->exists) {
            $location->name = $branch->name;
            $location->code = $this->availableCode($branch);
            $location->location_type = InventoryLocationType::Branch->value;
        }

        // A branch location must be usable as soon as Inventory is enabled.
        // This also repairs legacy branch locations that were deactivated.
        $location->status = 'active';
        $location->save();

        return $location->refresh();
    }

    /**
     * @return Collection<int, InventoryLocation>
     */
    public function forTenant(Tenant|string $tenant): Collection
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return Branch::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('id')
            ->get()
            ->map(fn (Branch $branch): InventoryLocation => $this->forBranch($branch));
    }

    private function availableCode(Branch $branch): ?string
    {
        $code = filled($branch->code) ? (string) $branch->code : null;

        if ($code === null) {
            return null;
        }

        return InventoryLocation::query()
            ->where('tenant_id', $branch->tenant_id)
            ->where('code', $code)
            ->exists()
                ? null
                : $code;
    }
}
