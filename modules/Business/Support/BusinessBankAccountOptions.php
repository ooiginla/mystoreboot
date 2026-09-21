<?php

declare(strict_types=1);

namespace Modules\Business\Support;

use Illuminate\Support\Collection;
use Modules\Business\Models\BusinessPaymentAccount;
use Modules\Tenancy\Models\Tenant;

final class BusinessBankAccountOptions
{
    /**
     * @return Collection<int, array{key: string, bank_name: string, account_name: string, account_number: string}>
     */
    public static function forTenant(?Tenant $tenant): Collection
    {
        if (! $tenant) {
            return collect();
        }

        $paymentAccounts = BusinessPaymentAccount::query()
            ->where('tenant_id', $tenant->id)
            ->get();

        if ($paymentAccounts->isNotEmpty()) {
            return $paymentAccounts
                ->filter(fn (BusinessPaymentAccount $account): bool => $account->status === 'active' && $account->supports('Transfer'))
                ->map(fn (BusinessPaymentAccount $account): ?array => self::option([
                    'bank_name' => $account->provider_name,
                    'account_name' => $account->account_name ?: $account->identifier,
                    'account_number' => $account->account_number,
                ]))
                ->filter()
                ->values();
        }

        return collect($tenant->settings['bank_details'] ?? [])
            ->filter(fn ($account): bool => is_array($account) && ($account['status'] ?? 'active') === 'active')
            ->map(fn (array $account): ?array => self::option($account))
            ->filter()
            ->values();
    }

    /**
     * @param  array<string, mixed>  $account
     * @return array{key: string, bank_name: string, account_name: string, account_number: string}|null
     */
    private static function option(array $account): ?array
    {
        $bankName = trim((string) ($account['bank_name'] ?? ''));
        $accountName = trim((string) ($account['account_name'] ?? ''));
        $accountNumber = trim((string) ($account['account_number'] ?? ''));

        if ($bankName === '' || $accountNumber === '') {
            return null;
        }

        return [
            'key' => sha1(implode('|', [$bankName, $accountName, $accountNumber])),
            'bank_name' => $bankName,
            'account_name' => $accountName,
            'account_number' => $accountNumber,
        ];
    }
}
