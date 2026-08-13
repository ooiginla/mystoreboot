<?php

declare(strict_types=1);

namespace Modules\Business\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper over Paystack's bank directory + account-resolution endpoints, used
 * to power the settlement-account picker (searchable bank list → auto bank code →
 * validated account name). The platform secret key is used; results are cached.
 */
final class PaystackDirectory
{
    private function secret(): ?string
    {
        return config('services.paystack.secret_key') ?: null;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.paystack.base_url', 'https://api.paystack.co'), '/');
    }

    public function configured(): bool
    {
        return filled($this->secret());
    }

    public function isTestMode(): bool
    {
        return str_starts_with((string) $this->secret(), 'sk_test');
    }

    /**
     * The list of banks for a currency, as [['name' => ..., 'code' => ...], ...], cached for a day.
     *
     * @return list<array{name: string, code: string}>
     */
    public function banks(string $currency = 'NGN'): array
    {
        if (! $this->configured()) {
            return [];
        }

        $banks = Cache::remember("paystack.banks.{$currency}", now()->addDay(), function () use ($currency): array {
            $banks = [];
            $page = 1;

            do {
                $response = Http::withToken($this->secret())
                    ->acceptJson()
                    ->get($this->baseUrl().'/bank', [
                        'currency' => $currency,
                        'perPage' => 100,
                        'page' => $page,
                    ]);

                if (! $response->ok() || ! (bool) $response->json('status')) {
                    break;
                }

                $rows = (array) $response->json('data', []);
                foreach ($rows as $row) {
                    if (! empty($row['name']) && ! empty($row['code'])) {
                        $banks[] = ['name' => (string) $row['name'], 'code' => (string) $row['code']];
                    }
                }

                $page++;
            } while (count($rows) === 100 && $page <= 6);

            // De-duplicate (Paystack lists some banks under multiple channels) and sort by name.
            $banks = collect($banks)->unique('code')->sortBy('name')->values()->all();

            return $banks;
        });

        // In Paystack test mode, surface the Test Bank (code 001) so local testing isn't
        // capped by the 3-real-resolves-per-day limit. Never shown with live keys.
        if ($this->isTestMode() && ! collect($banks)->contains('code', '001')) {
            array_unshift($banks, ['name' => 'Test Bank (Paystack test)', 'code' => '001']);
        }

        return $banks;
    }

    public function bankName(string $bankCode, string $currency = 'NGN'): ?string
    {
        foreach ($this->banks($currency) as $bank) {
            if ($bank['code'] === $bankCode) {
                return $bank['name'];
            }
        }

        return null;
    }

    public function isValidBankCode(string $bankCode, string $currency = 'NGN'): bool
    {
        return $this->bankName($bankCode, $currency) !== null;
    }

    /**
     * Resolve an account number against a bank code.
     *
     * @return array{ok: bool, account_name?: string, message?: string}
     */
    public function resolveAccount(string $accountNumber, string $bankCode): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'message' => 'Bank verification is not available yet.'];
        }

        $response = Http::withToken($this->secret())
            ->acceptJson()
            ->get($this->baseUrl().'/bank/resolve', [
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
            ]);

        $name = (string) $response->json('data.account_name', '');

        if ($response->ok() && (bool) $response->json('status') && $name !== '') {
            return ['ok' => true, 'account_name' => $name];
        }

        $paystackMessage = (string) $response->json('message', '');

        $message = match (true) {
            $response->status() === 429 => 'Bank verification limit reached. Paystack test mode allows only 3 real resolutions per day — use the test bank “Test Bank” (code 001), or switch to live keys.',
            $response->status() === 401 => 'Bank verification is misconfigured (invalid Paystack key).',
            $paystackMessage !== '' => $paystackMessage,
            default => 'We could not verify this account. Check the number and bank.',
        };

        return ['ok' => false, 'message' => $message];
    }

    /**
     * Create (or update, if $subaccountCode is given) a Paystack subaccount for a merchant's
     * settlement bank. Direct settlement means Paystack pays the subaccount's share to this
     * bank on its schedule.
     *
     * `percentage_charge` defaults to 0: Storeboot's cut is the per-transaction gateway
     * charge (PAYMENT_GATEWAY_CHARGE), applied as a flat `transaction_charge` at checkout,
     * which overrides the subaccount default anyway.
     *
     * @param  array{business_name: string, bank_code: string, account_number: string, percentage_charge?: float, subaccount_code?: ?string}  $data
     * @return array{ok: bool, subaccount_code?: string, message?: string}
     */
    public function createOrUpdateSubaccount(array $data): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'message' => 'Paystack is not configured.'];
        }

        $payload = [
            'business_name' => $data['business_name'],
            'bank_code' => $data['bank_code'],
            'account_number' => $data['account_number'],
            'percentage_charge' => $data['percentage_charge'] ?? 0,
        ];

        $existing = $data['subaccount_code'] ?? null;

        $response = $existing
            ? Http::withToken($this->secret())->acceptJson()->put($this->baseUrl().'/subaccount/'.$existing, $payload)
            : Http::withToken($this->secret())->acceptJson()->post($this->baseUrl().'/subaccount', $payload);

        if ($response->ok() && (bool) $response->json('status')) {
            return ['ok' => true, 'subaccount_code' => (string) ($response->json('data.subaccount_code') ?: $existing)];
        }

        return ['ok' => false, 'message' => (string) $response->json('message', '') ?: 'Could not set up the settlement subaccount.'];
    }
}
