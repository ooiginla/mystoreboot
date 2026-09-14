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

/**
 * Closes a check that is never going to be paid — a table seated by mistake, guests who
 * left before ordering, a duplicate opened on the wrong table — and frees the table.
 *
 * Only a check with **nothing sent to the kitchen** can be cancelled here. Once a round
 * has fired the food has been cooked and, for tenants that deplete at fire, the
 * ingredients are already gone; discarding that quietly would hide real waste. Voiding a
 * fired check needs a reason and manager approval, which is 6e.
 */
final class CancelCheckAction
{
    public function execute(SalesOrder $check, ?User $user = null, ?string $reason = null): SalesOrder
    {
        if (! $check->isCheck()) {
            throw ValidationException::withMessages(['check' => 'That order is not a restaurant check.']);
        }

        if (! $check->check_status?->isOpen()) {
            throw ValidationException::withMessages(['check' => 'This check is already closed.']);
        }

        $check->loadMissing('items', 'table');

        if ($check->firedItems()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'check' => 'This check has items already sent to the kitchen, so it cannot simply be cancelled. '
                    .'Food that has been cooked has to be voided with a reason so it is recorded as waste.',
            ]);
        }

        if ((int) $check->paid_minor > 0) {
            throw ValidationException::withMessages([
                'check' => 'Money has already been taken against this check. Refund it before cancelling.',
            ]);
        }

        return DB::transaction(function () use ($check, $user, $reason): SalesOrder {
            $note = trim((string) ($reason ?? ''));

            $check->update([
                'check_status' => CheckStatus::Voided->value,
                'order_status' => SalesOrderStatus::Cancelled->value,
                'notes' => trim(($check->notes ? $check->notes."\n" : '').'Check cancelled'
                    .($user ? ' by '.$user->name : '')
                    .($note !== '' ? ': '.$note : '.')),
            ]);

            // Nothing was ever served, so the table goes straight back to available
            // rather than through cleaning — unless a split bill is still open on it.
            \Modules\Sales\Support\CheckTables::releaseIfClear($check->table, TableStatus::Available);

            return $check->refresh();
        });
    }
}
