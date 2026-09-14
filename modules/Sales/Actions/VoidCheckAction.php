<?php

declare(strict_types=1);

namespace Modules\Sales\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Sales\Enums\CheckStatus;
use Modules\Sales\Enums\SalesOrderStatus;
use Modules\Sales\Enums\TableStatus;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Support\CheckTables;

/**
 * Voids a whole check after food has gone out — a table that walked, a bill written
 * on the wrong table. Unsent lines are simply dropped (nothing was cooked); every sent
 * line is voided as waste exactly as a single-line void would be.
 *
 * A check with nothing sent is cancelled, not voided — see CancelCheckAction.
 */
final class VoidCheckAction
{
    public function __construct(
        private readonly VoidCheckItemAction $voidItem,
        private readonly RecalculateCheckTotalsAction $totals,
    ) {}

    public function execute(SalesOrder $check, User $by, string $reason): SalesOrder
    {
        if (! $check->isCheck() || ! $check->check_status?->isOpen()) {
            throw ValidationException::withMessages(['check' => 'This check is no longer open.']);
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Give a reason for voiding the check.']);
        }

        if ((int) $check->paid_minor > 0) {
            throw ValidationException::withMessages([
                'check' => 'Money has already been taken against this check. Refund it before voiding.',
            ]);
        }

        return DB::transaction(function () use ($check, $by, $reason): SalesOrder {
            $check->load('items');

            foreach ($check->unfiredItems() as $item) {
                $item->delete();
            }

            foreach ($check->firedItems() as $item) {
                $this->voidItem->execute($item, $by, $reason);
            }

            $check->refresh()->update([
                'check_status' => CheckStatus::Voided->value,
                'order_status' => SalesOrderStatus::Cancelled->value,
                'bill_printed_at' => null,
                'notes' => trim(($check->notes ? $check->notes."\n" : '')."Check voided by {$by->name}: {$reason}"),
            ]);

            $this->totals->execute($check);

            // Guests sat and were served, so the table needs clearing.
            CheckTables::releaseIfClear($check->table, TableStatus::Dirty);

            return $check->refresh();
        });
    }
}
