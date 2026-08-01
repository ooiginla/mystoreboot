<?php

declare(strict_types=1);

namespace Modules\Inventory\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Inventory\Models\InventoryStockLevel;

/**
 * Manages soft stock reservations for pending orders. A reservation increases
 * quantity_reserved without changing quantity_on_hand, so available stock
 * (on_hand - reserved) drops immediately and cannot be sold twice. The
 * reservation is later released — either converted to a real StockOut when the
 * order is completed, or freed when the order is cancelled or expires.
 */
final class AdjustInventoryReservationAction
{
    public function reserve(string $tenantId, int $locationId, int $variantId, int $quantity, ?string $itemName = null): void
    {
        if ($quantity <= 0) {
            return;
        }

        DB::transaction(function () use ($tenantId, $locationId, $variantId, $quantity, $itemName): void {
            $stockLevel = $this->lockedStockLevel($tenantId, $locationId, $variantId);

            if ($stockLevel->quantity_available < $quantity) {
                $available = max(0, (int) $stockLevel->quantity_available);
                $displayName = filled($itemName) ? '“'.trim((string) $itemName).'”' : 'this item';
                $message = $available === 0
                    ? "Sorry, {$displayName} has just sold out. Please remove it from your cart to continue."
                    : "Sorry, only {$available} of {$displayName} is available, but your cart has {$quantity}. Please reduce the quantity or remove it to continue.";

                throw ValidationException::withMessages([
                    'items' => $message,
                ]);
            }

            $stockLevel->quantity_reserved += $quantity;
            $stockLevel->save();
        });
    }

    public function release(string $tenantId, int $locationId, int $variantId, int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        DB::transaction(function () use ($tenantId, $locationId, $variantId, $quantity): void {
            $stockLevel = InventoryStockLevel::query()
                ->where('tenant_id', $tenantId)
                ->where('inventory_location_id', $locationId)
                ->where('product_variant_id', $variantId)
                ->lockForUpdate()
                ->first();

            if (! $stockLevel) {
                return;
            }

            $stockLevel->quantity_reserved = max(0, (int) $stockLevel->quantity_reserved - $quantity);
            $stockLevel->save();
        });
    }

    /**
     * Resolve an active inventory location for a branch, honouring a preferred
     * location id when it is itself active for that branch.
     */
    public function resolveActiveLocationId(string $tenantId, int $branchId, ?int $preferredLocationId = null): ?int
    {
        $location = InventoryLocation::query()
            ->where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->where('status', 'active')
            ->when($preferredLocationId, fn ($query) => $query->whereKey($preferredLocationId))
            ->first();

        $location ??= InventoryLocation::query()
            ->where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->where('status', 'active')
            ->orderBy('id')
            ->first();

        return $location?->id;
    }

    private function lockedStockLevel(string $tenantId, int $locationId, int $variantId): InventoryStockLevel
    {
        return InventoryStockLevel::query()
            ->where('tenant_id', $tenantId)
            ->where('inventory_location_id', $locationId)
            ->where('product_variant_id', $variantId)
            ->lockForUpdate()
            ->firstOrCreate([
                'tenant_id' => $tenantId,
                'inventory_location_id' => $locationId,
                'product_variant_id' => $variantId,
            ]);
    }
}
