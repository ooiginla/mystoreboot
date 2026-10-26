<?php

declare(strict_types=1);

namespace Modules\Customers\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Customers\Models\Customer;
use Modules\Customers\Models\CustomerBalanceAdjustment;
use Modules\Finance\Actions\PostJournalEntryAction;

final class AdjustCustomerBalanceAction
{
    public function __construct(private readonly PostJournalEntryAction $postJournalEntry) {}

    /** @param array<string, mixed> $data */
    public function execute(Customer $customer, array $data, User $actor): CustomerBalanceAdjustment
    {
        return DB::transaction(function () use ($customer, $data, $actor): CustomerBalanceAdjustment {
            $locked = Customer::query()
                ->where('tenant_id', $customer->tenant_id)
                ->lockForUpdate()
                ->findOrFail($customer->id);
            $amountMinor = (int) round((float) $data['amount'] * 100);
            $before = max(0, (int) $locked->account_balance_minor);
            $after = $data['direction'] === 'increase'
                ? $before + $amountMinor
                : $before - $amountMinor;

            if ($after < 0) {
                throw ValidationException::withMessages([
                    'amount' => 'The decrease cannot be more than the customer’s current outstanding balance.',
                ]);
            }

            $adjustment = CustomerBalanceAdjustment::query()->create([
                'tenant_id' => $locked->tenant_id,
                'customer_id' => $locked->id,
                'recorded_by' => $actor->id,
                'adjustment_date' => $data['adjustment_date'],
                'direction' => $data['direction'],
                'amount_minor' => $amountMinor,
                'balance_before_minor' => $before,
                'balance_after_minor' => $after,
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
            ]);
            $locked->update(['account_balance_minor' => $after]);

            $increase = $data['direction'] === 'increase';
            $this->postJournalEntry->execute(
                $locked->tenant_id,
                (string) $data['adjustment_date'],
                'Customer balance adjustment: '.$data['reason'],
                [
                    ['account_code' => '1100', 'debit_minor' => $increase ? $amountMinor : 0, 'credit_minor' => $increase ? 0 : $amountMinor, 'party_type' => 'customer', 'party_id' => $locked->id],
                    ['account_code' => '3400', 'debit_minor' => $increase ? 0 : $amountMinor, 'credit_minor' => $increase ? $amountMinor : 0, 'party_type' => 'customer', 'party_id' => $locked->id],
                ],
                'customer_balance_adjustment',
                $adjustment->id,
                $data['direction'],
            );

            return $adjustment->refresh();
        });
    }
}
