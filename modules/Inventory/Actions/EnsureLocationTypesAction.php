<?php

declare(strict_types=1);

namespace Modules\Inventory\Actions;

use Illuminate\Support\Collection;
use Modules\Inventory\Enums\InventoryLocationType;
use Modules\Inventory\Models\LocationType;
use Modules\Tenancy\Models\Tenant;

/**
 * Seeds a tenant's configurable location types from the system enum. Tenants can
 * add their own (e.g. "kitchen store", "bar store") on top; the seeded ones are
 * flagged is_system so they cannot be renamed away. Idempotent.
 */
final class EnsureLocationTypesAction
{
    /**
     * Seed the starter set of location types the first time a tenant needs them.
     * Only seeds when the tenant has none, so a tenant's later edits or deletions of
     * these types are never resurrected on the next page load.
     *
     * @return Collection<int, LocationType>
     */
    public function forTenant(Tenant|string $tenant): Collection
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        $existing = LocationType::query()->where('tenant_id', $tenantId)->get();

        if ($existing->isNotEmpty()) {
            return $existing;
        }

        return collect(InventoryLocationType::cases())->map(
            fn (InventoryLocationType $type): LocationType => LocationType::query()->create([
                'tenant_id' => $tenantId,
                'key' => $type->value,
                'label' => $type->label(),
                'is_system' => true,
                'status' => 'active',
            ]),
        )->values();
    }
}
