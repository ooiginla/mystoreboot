<?php

declare(strict_types=1);

namespace Modules\Finance\Support;

use Illuminate\Database\Eloquent\Builder;
use Modules\Business\Models\BusinessPaymentAccount;
use Modules\Finance\Models\FinanceAccount;

final class PaymentSourceAccounts
{
    /**
     * Cash control accounts that can directly fund non-sales payments.
     *
     * @var list<string>
     */
    private const CASH_ACCOUNT_CODES = ['1000', '1010', '1030'];

    /**
     * @return Builder<FinanceAccount>
     */
    public static function query(string $tenantId): Builder
    {
        return FinanceAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('type', 'asset')
            ->where('is_active', true)
            ->where(function (Builder $query) use ($tenantId): void {
                $query
                    ->whereIn('code', self::CASH_ACCOUNT_CODES)
                    ->orWhere('code', 'like', 'BANK-%')
                    ->orWhereIn('id', BusinessPaymentAccount::query()
                        ->select('finance_account_id')
                        ->where('tenant_id', $tenantId)
                        ->where('status', 'active'));
            });
    }

    public static function allows(?FinanceAccount $account, string $tenantId): bool
    {
        if (! $account
            || $account->tenant_id !== $tenantId
            || ! $account->is_active
            || $account->type !== 'asset') {
            return false;
        }

        if (in_array($account->code, self::CASH_ACCOUNT_CODES, true) || str_starts_with($account->code, 'BANK-')) {
            return true;
        }

        return BusinessPaymentAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('finance_account_id', $account->id)
            ->where('status', 'active')
            ->exists();
    }
}
