<?php

declare(strict_types=1);

namespace Modules\Inventory\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Enums\InventoryMovementType;
use Modules\Inventory\Enums\StockCountStatus;
use Modules\Inventory\Models\StockCount;
use Modules\Inventory\Models\StockCountItem;

final class PostStockCountAction
{
    public function __construct(private readonly PostInventoryMovementAction $postMovement) {}

    /**
     * Turn a finished count sheet into stock reality.
     *
     * Each line posts its *variance* (counted − snapshot) rather than forcing on-hand to
     * the counted figure. If sales or transfers happened while the count was in progress
     * those movements survive, and only the discrepancy the counter actually found is
     * applied. The adjustment movements carry the normal shrinkage/gain GL treatment.
     */
    public function execute(StockCount $count, ?int $postedByUserId = null): int
    {
        if ($count->status === StockCountStatus::Posted) {
            throw ValidationException::withMessages(['status' => 'This count has already been posted.']);
        }

        if (! $count->status->isOpen()) {
            throw ValidationException::withMessages(['status' => 'Only an open count can be posted.']);
        }

        $count->loadMissing('items');

        if ($count->countedLineCount() === 0) {
            throw ValidationException::withMessages([
                'status' => 'Enter at least one counted quantity before posting.',
            ]);
        }

        return DB::transaction(function () use ($count, $postedByUserId): int {
            $posted = 0;

            foreach ($count->items as $item) {
                $variance = $item->variance();

                if (! $item->isCounted() || $variance === 0.0) {
                    continue;
                }

                $this->postVariance($count, $item, $variance);
                $posted++;
            }

            $count->update([
                'status' => StockCountStatus::Posted->value,
                'posted_by_user_id' => $postedByUserId,
                'posted_at' => now(),
            ]);

            return $posted;
        });
    }

    private function postVariance(StockCount $count, StockCountItem $item, float $variance): void
    {
        $type = $variance > 0 ? InventoryMovementType::AdjustmentIn : InventoryMovementType::AdjustmentOut;

        $this->postMovement->execute([
            'tenant_id' => $count->tenant_id,
            'inventory_location_id' => $count->inventory_location_id,
            'product_variant_id' => $item->product_variant_id,
            'movement_type' => $type->value,
            'quantity' => abs($variance),
            'unit_cost_minor' => (int) $item->unit_cost_minor,
            'reference_type' => 'stock_count',
            'reference_id' => $count->id,
            'reference_number' => $count->count_number,
            'notes' => 'Stock count variance: counted '.$item->counted_quantity.' against system '.$item->system_quantity.'.',
            'occurred_at' => now(),
        ]);
    }
}
