<?php

declare(strict_types=1);

namespace Modules\Sales\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Sales\Enums\CheckStatus;
use Modules\Sales\Enums\SalesOrderStatus;
use Modules\Sales\Enums\TableStatus;
use Modules\Sales\Models\KitchenOrderTicket;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderItem;
use Modules\Sales\Support\CheckTables;

/**
 * Brings everything from one check onto another — a party that moves from the bar to a
 * table, or two bills a group decides to pay as one. The emptied check is closed with
 * reason "merged", never deleted, so the history of where each item was ordered stays.
 *
 * Kitchen tickets follow the items: food still being cooked should go to where the
 * guests are now sitting.
 */
final class MergeChecksAction
{
    public function __construct(private readonly RecalculateCheckTotalsAction $totals) {}

    public function execute(SalesOrder $target, SalesOrder $source, ?User $by = null): SalesOrder
    {
        if (! $target->isCheck() || ! $source->isCheck() || $target->tenant_id !== $source->tenant_id) {
            throw ValidationException::withMessages(['check' => 'Only two restaurant checks of the same business can be merged.']);
        }

        if ($target->id === $source->id) {
            throw ValidationException::withMessages(['check' => 'Choose a different check to merge with.']);
        }

        if (! $target->check_status?->isOpen() || ! $source->check_status?->isOpen()) {
            throw ValidationException::withMessages(['check' => 'Both checks must still be open to merge them.']);
        }

        if ((int) $source->paid_minor > 0) {
            throw ValidationException::withMessages([
                'check' => "Money has already been taken on {$source->order_number}. Settle or refund it before merging.",
            ]);
        }

        return DB::transaction(function () use ($target, $source, $by): SalesOrder {
            SalesOrderItem::query()->where('sales_order_id', $source->id)->update(['sales_order_id' => $target->id]);
            KitchenOrderTicket::query()->where('sales_order_id', $source->id)->update(['sales_order_id' => $target->id]);

            // Anything split off the source now belongs to the target's family.
            SalesOrder::query()
                ->where('parent_sales_order_id', $source->id)
                ->update(['parent_sales_order_id' => $target->parent_sales_order_id ?: $target->id]);

            $target->update(['cover_count' => (int) $target->cover_count + (int) $source->cover_count]);

            $source->update([
                'check_status' => CheckStatus::Voided->value,
                'order_status' => SalesOrderStatus::Cancelled->value,
                'bill_printed_at' => null,
                'notes' => trim(($source->notes ? $source->notes."\n" : '')
                    ."Merged into {$target->order_number}".($by ? " by {$by->name}" : '').'.'),
            ]);

            $target->reopenIfBilled();
            $this->totals->execute($source);
            $this->totals->execute($target);

            if ($source->restaurant_table_id && $source->restaurant_table_id !== $target->restaurant_table_id) {
                // The guests moved on from that table; it needs clearing before reuse.
                CheckTables::releaseIfClear($source->table()->first(), TableStatus::Dirty);
            }

            return $target->refresh();
        });
    }
}
