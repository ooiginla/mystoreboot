<?php

declare(strict_types=1);

namespace Modules\Sales\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Sales\Enums\CheckStatus;
use Modules\Sales\Enums\TableStatus;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Support\CheckTables;

/**
 * Takes one payment against a printed bill. Part-payments and mixed methods go through
 * the ordinary payment path; "split evenly" is just several of these, not several checks.
 *
 * When the last payment clears the balance the sale completes — revenue, service charge
 * and COGS are booked — the check is settled, and the table goes to "needs cleaning".
 */
final class SettleCheckPaymentAction
{
    public function __construct(
        private readonly RecordSalesPaymentAction $payments,
        private readonly CompleteSalesOrderAction $complete,
    ) {}

    /**
     * @param  array<string, mixed>  $data  payment_method, amount, and optionally
     *                                      business_payment_account_id, sales_till_session_id, reference_number
     */
    public function execute(SalesOrder $check, array $data, User $user): SalesOrder
    {
        if (! $check->isCheck() || ! $check->check_status?->isOpen()) {
            throw ValidationException::withMessages(['check' => 'This check is no longer open.']);
        }

        if ($check->check_status !== CheckStatus::BillPrinted) {
            throw ValidationException::withMessages([
                'check' => 'Print the bill before taking payment, so the guest pays the amount they were shown.',
            ]);
        }

        return DB::transaction(function () use ($check, $data, $user): SalesOrder {
            $this->payments->execute($check, [
                ...$data,
                'payment_date' => now()->toDateString(),
            ], $user->id);

            $check->refresh();

            if ($check->balance_minor > 0) {
                return $check;
            }

            $this->complete->execute($check);

            $check->refresh()->update([
                'check_status' => CheckStatus::Settled->value,
                'settled_at' => now(),
            ]);

            // Guests have eaten here, so the table needs clearing before it is seated again.
            CheckTables::releaseIfClear($check->table, TableStatus::Dirty);

            return $check->refresh();
        });
    }
}
