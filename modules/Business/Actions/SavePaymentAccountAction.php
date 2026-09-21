<?php

declare(strict_types=1);

namespace Modules\Business\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Business\Models\BusinessPaymentAccount;
use Modules\Business\Support\BankAccountNameVerification;
use Modules\Business\Support\PaystackDirectory;
use Modules\Finance\Models\FinanceAccount;
use Modules\Tenancy\Models\Tenant;

/**
 * Creates or updates a business payment (receiving) account and its linked finance
 * account. Nigerian/NGN accounts with a bank code and 10-digit account number are
 * verified with Paystack; other profiles supply the account name themselves.
 *
 * Shared by Business Setup and the onboarding wizard.
 */
final class SavePaymentAccountAction
{
    public function __construct(private readonly PaystackDirectory $paystack) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?BusinessPaymentAccount $paymentAccount = null): BusinessPaymentAccount
    {
        $tenant = Tenant::query()->findOrFail($data['tenant_id']);
        if (! BankAccountNameVerification::required($tenant)
            && filled($data['account_number'] ?? null)
            && trim((string) ($data['account_name'] ?? '')) === '') {
            throw ValidationException::withMessages(['account_name' => 'Enter the account name.']);
        }
        if (BankAccountNameVerification::required($tenant)
            && filled($data['bank_code'] ?? null)
            && preg_match('/^[0-9]{10}$/', (string) ($data['account_number'] ?? ''))) {
            $currency = $tenant->currency_code ?: 'NGN';

            if (! $this->paystack->isValidBankCode((string) $data['bank_code'], $currency)) {
                throw ValidationException::withMessages(['bank_code' => 'Select a valid bank.']);
            }

            $result = $this->paystack->resolveAccount((string) $data['account_number'], (string) $data['bank_code']);

            if (! $result['ok']) {
                throw ValidationException::withMessages(['account_number' => $result['message'] ?? 'We could not verify this account.']);
            }

            $data['account_name'] = $result['account_name'];
            $data['provider_name'] = $this->paystack->bankName((string) $data['bank_code'], $currency) ?: ($data['provider_name'] ?? null);
        }

        $accountType = $data['account_type'] ?? 'normal';
        $status = $data['status'] ?? 'active';
        $category = $accountType === 'virtual' ? 'Payment Wallets / Virtual Accounts' : 'Bank & Payment Accounts';

        $paymentAccount ??= new BusinessPaymentAccount(['tenant_id' => $data['tenant_id']]);
        $financeAccount = $paymentAccount->financeAccount ?: FinanceAccount::query()->create([
            'tenant_id' => $data['tenant_id'],
            'code' => $this->nextPaymentFinanceAccountCode($data['tenant_id']),
            'name' => (string) $data['identifier'],
            'type' => 'asset',
            'category' => $category,
            'description' => 'Payment receiving account managed from Business Profile.',
            'normal_balance' => 'debit',
            'is_system' => false,
            'is_active' => $status === 'active',
        ]);

        $financeAccount->fill([
            'name' => (string) $data['identifier'],
            'type' => 'asset',
            'category' => $category,
            'description' => 'Payment receiving account managed from Business Profile.',
            'normal_balance' => 'debit',
            'is_active' => $status === 'active',
        ])->save();

        $paymentAccount->fill([
            'tenant_id' => $data['tenant_id'],
            'branch_id' => $data['branch_id'] ?? null,
            'finance_account_id' => $financeAccount->id,
            'identifier' => trim((string) $data['identifier']),
            'account_name' => trim((string) ($data['account_name'] ?? '')) ?: null,
            'provider_name' => trim((string) $data['provider_name']),
            'bank_code' => trim((string) ($data['bank_code'] ?? '')) ?: null,
            'account_number' => trim((string) ($data['account_number'] ?? '')) ?: null,
            'account_type' => $accountType,
            'supported_payment_methods' => $data['supported_payment_methods'] ?? ['Transfer'],
            'status' => $status,
        ])->save();

        $paymentAccount = $paymentAccount->refresh();
        $this->syncTenantPaymentSettings($tenant);

        return $paymentAccount;
    }

    private function syncTenantPaymentSettings(Tenant $tenant): void
    {
        $accounts = BusinessPaymentAccount::query()
            ->with('financeAccount')
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->orderBy('identifier')
            ->get();
        $methods = collect(['Cash'])
            ->merge($tenant->settings['payment_methods'] ?? [])
            ->merge($accounts->flatMap(fn (BusinessPaymentAccount $account): array => $account->supported_payment_methods ?? []))
            ->map(fn (string $method): string => $this->canonicalPaymentMethod($method))
            ->unique()
            ->values()
            ->all();
        $bankDetails = $accounts
            ->filter(fn (BusinessPaymentAccount $account): bool => $account->supports('Transfer'))
            ->map(fn (BusinessPaymentAccount $account): array => [
                'bank_name' => $account->provider_name,
                'account_name' => $account->account_name ?: $account->identifier,
                'account_number' => $account->account_number ?: '',
                'status' => $account->status,
                'asset_account_code' => $account->financeAccount?->code,
            ])
            ->values()
            ->all();

        $tenant->settings = array_merge($tenant->settings ?? [], [
            'payment_methods' => $methods,
            'bank_details' => $bankDetails,
        ]);
        $tenant->save();
    }

    private function canonicalPaymentMethod(string $method): string
    {
        $method = strtolower(trim($method));

        return match (true) {
            str_contains($method, 'card'), str_contains($method, 'pos') => 'Card',
            str_contains($method, 'cheque'), str_contains($method, 'check') => 'Cheque',
            str_contains($method, 'transfer'), str_contains($method, 'bank') => 'Transfer',
            default => 'Cash',
        };
    }

    private function nextPaymentFinanceAccountCode(string $tenantId): string
    {
        $numbers = FinanceAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('code', 'like', 'PMT-%')
            ->pluck('code')
            ->map(fn (string $code): int => (int) preg_replace('/\D+/', '', $code))
            ->filter(fn (int $number): bool => $number > 0);

        $next = max(1000, (int) ($numbers->max() ?? 1000)) + 1;

        do {
            $code = 'PMT-'.$next;
            $next++;
        } while (FinanceAccount::query()->where('tenant_id', $tenantId)->where('code', $code)->exists());

        return $code;
    }
}
