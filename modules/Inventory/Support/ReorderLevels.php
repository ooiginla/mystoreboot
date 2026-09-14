<?php

declare(strict_types=1);

namespace Modules\Inventory\Support;

use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Models\ProductVariant;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Inventory\Models\UnitOfMeasure;

/**
 * Read-side data for the reorder-level screens: which units an item's level can be
 * typed in, and the current level / quantity / availability per item and location.
 * Everything here is in the item's base unit (factor 1), the unit stock is held in;
 * the screens divide by a unit's factor for display and the save action multiplies.
 */
final class ReorderLevels
{
    /**
     * Units an item can be measured in, smallest first. An item with no measurement
     * category gets a single pseudo-unit (no id) so the screens never special-case it.
     *
     * @return list<array{id: ?int, code: string, factor: float}>
     */
    public static function unitsOf(ProductVariant $variant): array
    {
        $units = ($variant->product?->unitCategory?->units ?? collect())
            ->filter(fn (UnitOfMeasure $unit): bool => $unit->isConvertible())
            ->sortBy(fn (UnitOfMeasure $unit): float => (float) $unit->to_base_factor)
            ->map(fn (UnitOfMeasure $unit): array => [
                'id' => $unit->id,
                'code' => $unit->code,
                'factor' => (float) $unit->to_base_factor,
            ])
            ->values()
            ->all();

        return $units !== [] ? $units : [[
            'id' => null,
            'code' => $variant->baseUnit?->code ?? 'pc',
            'factor' => 1.0,
        ]];
    }

    /**
     * @return array<int, list<array{id: ?int, code: string, factor: float}>>
     */
    public static function unitsFor(string $tenantId): array
    {
        return ProductVariant::query()
            ->with(['product.unitCategory.units', 'baseUnit'])
            ->where('tenant_id', $tenantId)
            ->whereHas('product', fn ($query) => $query->whereIn(
                'product_type',
                array_map(fn (ProductType $type): string => $type->value, ProductType::stockable()),
            ))
            ->get()
            ->mapWithKeys(fn (ProductVariant $variant): array => [$variant->id => self::unitsOf($variant)])
            ->all();
    }

    /**
     * @return array<int, array<int, array{level: float, qty: float, available: float}>>
     */
    public static function levelsFor(string $tenantId): array
    {
        $levels = [];

        InventoryStockLevel::query()
            ->where('tenant_id', $tenantId)
            ->get(['product_variant_id', 'inventory_location_id', 'quantity_on_hand', 'quantity_reserved', 'reorder_level', 'reorder_quantity'])
            ->each(function (InventoryStockLevel $stock) use (&$levels): void {
                $levels[$stock->product_variant_id][$stock->inventory_location_id] = [
                    'level' => (float) $stock->reorder_level,
                    'qty' => (float) $stock->reorder_quantity,
                    'available' => $stock->quantity_available,
                ];
            });

        return $levels;
    }
}
