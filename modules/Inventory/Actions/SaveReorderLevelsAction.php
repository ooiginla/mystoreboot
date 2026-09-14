<?php

declare(strict_types=1);

namespace Modules\Inventory\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Models\ProductVariant;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Inventory\Models\UnitOfMeasure;
use Modules\Inventory\Support\UnitConverter;

/**
 * Saves reorder levels and quantities for item × location rows. Each row's numbers
 * are typed in a chosen unit (2.5 kg) and stored in the item's base unit (2500 g) —
 * the unit stock is held in — so the low-stock check compares like with like.
 *
 * A blank value means 0, i.e. not monitored. A 0/0 row with no stock record yet is
 * skipped rather than creating an empty stock line for an item never kept there.
 */
final class SaveReorderLevelsAction
{
    public function __construct(private readonly UnitConverter $converter) {}

    /**
     * @param  array<int, array<string, mixed>>  $rows  each: inventory_location_id, product_variant_id, unit_id?, reorder_level?, reorder_quantity?
     * @return int rows whose values actually changed
     */
    public function execute(string $tenantId, array $rows): int
    {
        $locationIds = InventoryLocation::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', array_map('intval', array_column($rows, 'inventory_location_id')))
            ->pluck('id')
            ->flip();

        $variants = ProductVariant::query()
            ->with('product:id,name,unit_category_id')
            ->where('tenant_id', $tenantId)
            ->whereIn('id', array_map('intval', array_column($rows, 'product_variant_id')))
            ->get()
            ->keyBy('id');

        $units = UnitOfMeasure::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', array_map('intval', array_filter(array_column($rows, 'unit_id'))))
            ->get()
            ->keyBy('id');

        $errors = [];
        $writes = [];

        foreach ($rows as $i => $row) {
            $locationId = (int) $row['inventory_location_id'];
            $variant = $variants->get((int) $row['product_variant_id']);

            if (! $locationIds->has($locationId) || ! $variant) {
                $errors["rows.{$i}.product_variant_id"] = 'That item or location does not belong to this business.';

                continue;
            }

            $factor = 1.0;

            if (! empty($row['unit_id'])) {
                $unit = $units->get((int) $row['unit_id']);

                if (! $unit || ! $unit->isConvertible() || (int) $unit->unit_category_id !== (int) $variant->product?->unit_category_id) {
                    $errors["rows.{$i}.unit_id"] = "That unit is not one {$variant->product?->name} is measured in.";

                    continue;
                }

                $factor = (float) $unit->to_base_factor;
            }

            $writes[] = [
                $locationId,
                $variant->id,
                $this->converter->round((float) ($row['reorder_level'] ?? 0) * $factor),
                $this->converter->round((float) ($row['reorder_quantity'] ?? 0) * $factor),
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($tenantId, $writes): int {
            $saved = 0;

            foreach ($writes as [$locationId, $variantId, $level, $quantity]) {
                $key = [
                    'tenant_id' => $tenantId,
                    'inventory_location_id' => $locationId,
                    'product_variant_id' => $variantId,
                ];

                $stock = InventoryStockLevel::query()->where($key)->first();

                if (! $stock) {
                    if ($level == 0.0 && $quantity == 0.0) {
                        continue;
                    }

                    $stock = InventoryStockLevel::query()->create($key);
                }

                if ((float) $stock->reorder_level === $level && (float) $stock->reorder_quantity === $quantity) {
                    continue;
                }

                $stock->update(['reorder_level' => $level, 'reorder_quantity' => $quantity]);
                $saved++;
            }

            return $saved;
        });
    }
}
