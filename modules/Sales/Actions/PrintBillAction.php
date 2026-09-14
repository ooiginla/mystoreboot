<?php

declare(strict_types=1);

namespace Modules\Sales\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Sales\Enums\CheckStatus;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Support\PendingVoids;

/**
 * Presents the bill. From here the guest sees a number, so that number has to be final:
 * nothing may still be waiting to go to the kitchen, and no void may be undecided.
 * Adding, voiding, splitting or merging afterwards reopens the check and the bill has to
 * be printed again.
 */
final class PrintBillAction
{
    public function __construct(private readonly RecalculateCheckTotalsAction $totals) {}

    public function execute(SalesOrder $check): SalesOrder
    {
        if (! $check->isCheck() || ! $check->check_status?->isOpen()) {
            throw ValidationException::withMessages(['check' => 'This check is no longer open.']);
        }

        $check->load('items');

        if ($check->liveItems()->isEmpty()) {
            throw ValidationException::withMessages(['check' => 'There is nothing on this check to bill.']);
        }

        $unsent = $check->unfiredItems()->count();

        if ($unsent > 0) {
            throw ValidationException::withMessages([
                'check' => $unsent === 1
                    ? '1 item has not been sent to the kitchen. Send it or remove it before printing the bill.'
                    : "{$unsent} items have not been sent to the kitchen. Send or remove them before printing the bill.",
            ]);
        }

        if (PendingVoids::forCheck($check)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'check' => 'A void on this check is waiting for a manager. Print the bill once it has been decided.',
            ]);
        }

        $check = $this->totals->execute($check);

        $check->update([
            'check_status' => CheckStatus::BillPrinted->value,
            'bill_printed_at' => now(),
        ]);

        return $check->refresh();
    }
}
