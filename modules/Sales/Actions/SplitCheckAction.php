<?php

declare(strict_types=1);

namespace Modules\Sales\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Support\Quantity;
use Modules\Sales\Enums\CheckStatus;
use Modules\Sales\Enums\SalesOrderStatus;
use Modules\Sales\Enums\SalesPaymentStatus;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Support\CheckLines;

/**
 * Moves chosen items — whole lines or part of one — onto a new check at the same table:
 * two couples at one table who want separate bills.
 *
 * Kitchen tickets are left alone. Which bill a dish lands on is a billing decision, and
 * the cook plating it does not need to know. Splitting a bill *evenly* is not this: that
 * is one check paid in several payments.
 */
final class SplitCheckAction
{
    public function __construct(private readonly RecalculateCheckTotalsAction $totals) {}

    /**
     * @param  array<int|string, float|int|string|null>  $moves  item id => quantity to move
     */
    public function execute(SalesOrder $check, array $moves, int $covers = 1): SalesOrder
    {
        if (! $check->isCheck() || ! $check->check_status?->isOpen()) {
            throw ValidationException::withMessages(['check' => 'This check is no longer open.']);
        }

        if ((int) $check->paid_minor > 0) {
            throw ValidationException::withMessages([
                'check' => 'Payment has already been taken on this check. Split a bill before any money is taken.',
            ]);
        }

        $check->load('items.modifiers');
        $live = $check->liveItems()->keyBy('id');

        $moves = collect($moves)
            ->map(fn ($quantity): float => Quantity::round((float) $quantity))
            ->filter(fn (float $quantity): bool => $quantity > 0);

        if ($moves->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Choose at least one item to move to the new bill.']);
        }

        $movedEverything = true;

        foreach ($live as $id => $item) {
            $moving = (float) ($moves[$id] ?? 0);

            if ($moving < (float) $item->quantity - 0.00001) {
                $movedEverything = false;
            }
        }

        foreach ($moves as $id => $quantity) {
            $item = $live->get((int) $id);

            if (! $item) {
                throw ValidationException::withMessages(['items' => 'One of those items is not on this check.']);
            }

            if ($quantity > (float) $item->quantity + 0.00001) {
                throw ValidationException::withMessages([
                    'items' => "There are only ".Quantity::format($item->quantity)." × {$item->item_name} to move.",
                ]);
            }
        }

        if ($movedEverything) {
            throw ValidationException::withMessages([
                'items' => 'That is the whole check — nothing would be left on this bill. Move fewer items.',
            ]);
        }

        return DB::transaction(function () use ($check, $moves, $live, $covers): SalesOrder {
            $rootId = $check->parent_sales_order_id ?: $check->id;
            $root = $rootId === $check->id ? $check : SalesOrder::query()->findOrFail($rootId);
            $covers = max(1, min($covers, max(1, (int) $check->cover_count)));

            $split = SalesOrder::query()->create([
                'tenant_id' => $check->tenant_id,
                'branch_id' => $check->branch_id,
                'service_area_id' => $check->service_area_id,
                'restaurant_table_id' => $check->restaurant_table_id,
                'server_user_id' => $check->server_user_id,
                'cover_count' => $covers,
                'opened_at' => $check->opened_at,
                'check_status' => CheckStatus::Open->value,
                'parent_sales_order_id' => $rootId,
                'inventory_location_id' => $check->inventory_location_id,
                'order_number' => $this->splitNumber($root),
                'invoice_number' => $this->uniqueNumber('INV', $check->tenant_id, 'invoice_number'),
                'receipt_number' => $this->uniqueNumber('RCT', $check->tenant_id, 'receipt_number'),
                'order_status' => SalesOrderStatus::Pending->value,
                'payment_status' => SalesPaymentStatus::Unpaid->value,
                'order_date' => now()->toDateString(),
                'source' => 'restaurant',
                'user_id' => $check->user_id,
                'service_charge_rate' => $check->service_charge_rate,
                'subtotal_minor' => 0,
                'tax_minor' => 0,
                'service_charge_minor' => 0,
                'total_minor' => 0,
                'paid_minor' => 0,
            ]);

            foreach ($moves as $id => $quantity) {
                $item = $live->get((int) $id);
                $line = $quantity >= (float) $item->quantity - 0.00001
                    ? $item
                    : CheckLines::splitLine($item, $quantity);

                $line->update(['sales_order_id' => $split->id]);
            }

            $check->update(['cover_count' => max(1, (int) $check->cover_count - $covers)]);
            $check->reopenIfBilled();

            $this->totals->execute($check);
            $this->totals->execute($split);

            return $split->refresh();
        });
    }

    /** CHK-20260911-0004 → CHK-20260911-0004-B, -C … so the bills read as a family. */
    private function splitNumber(SalesOrder $root): string
    {
        foreach (range('B', 'Z') as $suffix) {
            $candidate = $root->order_number.'-'.$suffix;

            if (! SalesOrder::query()->where('tenant_id', $root->tenant_id)->where('order_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        return $root->order_number.'-'.strtoupper(substr(uniqid(), -4));
    }

    private function uniqueNumber(string $prefix, string $tenantId, string $column): string
    {
        $seq = SalesOrder::query()->where('tenant_id', $tenantId)->count() + 1;

        do {
            $candidate = $prefix.'-'.now()->format('Ymd').'-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
            $seq++;
        } while (SalesOrder::query()->where('tenant_id', $tenantId)->where($column, $candidate)->exists());

        return $candidate;
    }
}
