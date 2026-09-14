<?php

declare(strict_types=1);

namespace Modules\Inventory\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Enums\ProductType;
use Modules\Inventory\Enums\StockCountStatus;
use Modules\Inventory\Models\InventoryStockLevel;
use Modules\Inventory\Models\StockCount;
use Modules\Inventory\Support\Quantity;

final class OpenStockCountAction
{
    /**
     * Snapshot every stocked line at a location into a fresh count sheet. The snapshot
     * is deliberately taken once, at open: later movements do not move the sheet, which
     * is what makes the variance at posting time meaningful.
     */
    public function execute(
        string $tenantId,
        int $locationId,
        bool $isBlind = true,
        ?int $openedByUserId = null,
        ?string $notes = null,
    ): StockCount
    {
        $open = StockCount::query()
            ->where('tenant_id', $tenantId)
            ->where('inventory_location_id', $locationId)
            ->whereIn('status', [StockCountStatus::Counting->value, StockCountStatus::Review->value])
            ->first();

        if ($open) {
            throw ValidationException::withMessages([
                'inventory_location_id' => "Count {$open->count_number} is still open for this location. Finish or cancel it first.",
            ]);
        }

        return DB::transaction(function () use ($tenantId, $locationId, $isBlind, $openedByUserId, $notes): StockCount {
            $count = StockCount::query()->create([
                'tenant_id' => $tenantId,
                'count_number' => $this->nextNumber($tenantId),
                'inventory_location_id' => $locationId,
                'status' => StockCountStatus::Counting->value,
                'is_blind' => $isBlind,
                'opened_by_user_id' => $openedByUserId,
                'notes' => $notes,
            ]);

            $levels = InventoryStockLevel::query()
                ->with('variant.product')
                ->where('tenant_id', $tenantId)
                ->where('inventory_location_id', $locationId)
                ->get()
                ->filter(fn (InventoryStockLevel $level): bool => $this->isCountable($level));

            foreach ($levels as $level) {
                $count->items()->create([
                    'tenant_id' => $tenantId,
                    'product_variant_id' => $level->product_variant_id,
                    'system_quantity' => Quantity::round((float) $level->quantity_on_hand),
                    'counted_quantity' => null,
                    'unit_cost_minor' => (int) $level->average_cost_minor,
                ]);
            }

            if ($count->items()->count() === 0) {
                throw ValidationException::withMessages([
                    'inventory_location_id' => 'There is no stock recorded at this location yet, so there is nothing to count.',
                ]);
            }

            return $count->load('items.componentVariant.product');
        });
    }

    /**
     * Only physical goods can be counted — services and recipe-depleted menu items hold
     * no stock of their own.
     */
    private function isCountable(InventoryStockLevel $level): bool
    {
        $type = $level->variant?->product?->product_type;

        return $type instanceof ProductType && $type->stockable();
    }

    private function nextNumber(string $tenantId): string
    {
        $prefix = 'CNT-'.now()->format('Ymd').'-';
        $seq = StockCount::query()->where('tenant_id', $tenantId)->where('count_number', 'like', $prefix.'%')->count() + 1;

        do {
            $candidate = $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            $seq++;
        } while (StockCount::query()->where('tenant_id', $tenantId)->where('count_number', $candidate)->exists());

        return $candidate;
    }
}
