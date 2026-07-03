<?php

declare(strict_types=1);

namespace Modules\Business\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Access\Models\Role;
use Modules\Finance\Models\FinanceAccount;
use Modules\Subscriptions\Enums\SubscriptionStatus;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\TenantSubscription;
use Modules\Tenancy\Enums\TenantStatus;
use Modules\Tenancy\Models\Tenant;

final class SaveBusinessProfileAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?Tenant $tenant = null): Tenant
    {
        return DB::transaction(function () use ($data, $tenant): Tenant {
            $tenant ??= new Tenant([
                'slug' => $this->uniqueSlug((string) ($data['slug'] ?? $data['name'])),
                'status' => TenantStatus::Trialing,
            ]);

            $logoPath = $tenant->logo_path;

            if (! $tenant->exists && ! $tenant->getKey()) {
                $tenant->id = (string) Str::uuid();
            }

            if (($data['logo'] ?? null) instanceof UploadedFile) {
                $logoPath = $data['logo']->store("tenants/{$tenant->id}/business/logos", 'public');
            }

            $bankDetails = $this->normalizeBankDetails($data['bank_details'] ?? []);

            $settings = array_merge($tenant->settings ?? [], [
                'brand_color' => $data['brand_color'] ?? null,
                'payment_methods' => $this->normalizePaymentMethods($data['payment_methods'] ?? null),
                'bank_details' => $bankDetails,
                'maintenance_mode' => (bool) ($data['maintenance_mode'] ?? false),
            ]);
            unset($settings['seo']);

            if (array_key_exists('use_estimated_cost_for_cogs', $data)) {
                $settings['use_estimated_cost_for_cogs'] = (bool) $data['use_estimated_cost_for_cogs'];
            }

            $tenant->fill([
                'name' => $data['name'],
                'slug' => $tenant->exists ? $tenant->slug : $this->uniqueSlug((string) ($data['slug'] ?: $data['name'])),
                'business_type' => $data['business_type'],
                'registration_number' => $data['registration_number'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'website' => $data['website'] ?? null,
                'address' => $data['address'] ?? null,
                'logo_path' => $logoPath,
                'country_code' => strtoupper((string) $data['country_code']),
                'timezone' => $data['timezone'],
                'currency_code' => strtoupper((string) $data['currency_code']),
                'tax_identifier' => $data['tax_identifier'] ?? null,
                'default_tax_rate' => $data['default_tax_rate'],
                'opening_hours' => $this->normalizeOpeningHours($data['opening_hours'] ?? []),
                'settings' => $settings,
            ]);

            $tenant->save();

            $bankDetails = $this->ensureBankDetailAssetAccounts($tenant, $bankDetails);
            $tenant->settings = array_merge($tenant->settings ?? [], [
                'bank_details' => $bankDetails,
            ]);
            $tenant->save();

            if (! $tenant->roles()->exists()) {
                $this->createDefaultRoles($tenant);
            }

            if (isset($data['plan_id'])) {
                $this->syncSubscription($tenant, (int) $data['plan_id']);
            }

            return $tenant->refresh();
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $bankDetails
     * @return list<array{bank_name: string, account_name: string, account_number: string, status: string, asset_account_code: string|null}>
     */
    private function normalizeBankDetails(array $bankDetails): array
    {
        return collect($bankDetails)
            ->filter(fn (array $account): bool => trim((string) ($account['bank_name'] ?? '')) !== '' && trim((string) ($account['account_number'] ?? '')) !== '')
            ->map(fn (array $account): array => [
                'bank_name' => trim((string) $account['bank_name']),
                'account_name' => trim((string) ($account['account_name'] ?? '')),
                'account_number' => trim((string) $account['account_number']),
                'status' => in_array(($account['status'] ?? 'active'), ['active', 'inactive'], true) ? (string) ($account['status'] ?? 'active') : 'active',
                'asset_account_code' => trim((string) ($account['asset_account_code'] ?? '')) ?: null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{bank_name: string, account_name: string, account_number: string, status: string, asset_account_code: string|null}>  $bankDetails
     * @return list<array{bank_name: string, account_name: string, account_number: string, status: string, asset_account_code: string}>
     */
    private function ensureBankDetailAssetAccounts(Tenant $tenant, array $bankDetails): array
    {
        return collect($bankDetails)
            ->map(function (array $account) use ($tenant): array {
                $financeAccount = $this->existingBankFinanceAccount($tenant->id, $account['asset_account_code']);

                if (! $financeAccount) {
                    $financeAccount = FinanceAccount::query()->create([
                        'tenant_id' => $tenant->id,
                        'code' => $this->nextBankAssetAccountCode($tenant->id),
                        'name' => $this->bankAssetAccountName($account),
                        'type' => 'asset',
                        'category' => 'Current Assets',
                        'description' => 'Business bank account used to hold cash and receive payments.',
                        'normal_balance' => 'debit',
                        'is_system' => false,
                        'is_active' => $account['status'] === 'active',
                    ]);
                } else {
                    $financeAccount->fill([
                        'name' => $this->bankAssetAccountName($account),
                        'type' => 'asset',
                        'category' => 'Current Assets',
                        'description' => 'Business bank account used to hold cash and receive payments.',
                        'normal_balance' => 'debit',
                        'is_active' => $account['status'] === 'active',
                    ])->save();
                }

                $account['asset_account_code'] = $financeAccount->code;

                return $account;
            })
            ->values()
            ->all();
    }

    private function existingBankFinanceAccount(string $tenantId, ?string $code): ?FinanceAccount
    {
        if (! $code) {
            return null;
        }

        return FinanceAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('code', $code)
            ->where('type', 'asset')
            ->first();
    }

    private function nextBankAssetAccountCode(string $tenantId): string
    {
        $numbers = FinanceAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('code', 'like', 'BANK-%')
            ->pluck('code')
            ->map(fn (string $code): int => (int) preg_replace('/\D+/', '', $code))
            ->filter(fn (int $number): bool => $number > 0);

        $next = max(1000, (int) ($numbers->max() ?? 1000)) + 1;

        do {
            $code = 'BANK-'.$next;
            $next++;
        } while (FinanceAccount::query()->where('tenant_id', $tenantId)->where('code', $code)->exists());

        return $code;
    }

    /**
     * @param  array{bank_name: string, account_name: string, account_number: string}  $account
     */
    private function bankAssetAccountName(array $account): string
    {
        $accountName = $account['account_name'] !== '' ? $account['account_name'].' - ' : '';

        return trim($accountName.$account['bank_name'].' ('.$account['account_number'].')');
    }

    /**
     * @return list<string>
     */
    private function normalizePaymentMethods(?string $methods): array
    {
        $fallback = ['Cash', 'Bank transfer', 'POS/Card', 'Cheque'];

        if (! $methods) {
            return $fallback;
        }

        $values = collect(explode(',', $methods))
            ->map(fn (string $method): string => trim($method))
            ->filter()
            ->unique(fn (string $method): string => strtolower($method))
            ->values()
            ->all();

        return $values !== [] ? $values : $fallback;
    }

    private function uniqueSlug(string $value): string
    {
        $base = Str::slug($value) ?: 'business';
        $slug = $base;
        $counter = 2;

        while (Tenant::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * @param  array<string, mixed>  $hours
     * @return array<string, array{is_open: bool, opens_at: string|null, closes_at: string|null}>
     */
    private function normalizeOpeningHours(array $hours): array
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        return collect($days)
            ->mapWithKeys(function (string $day) use ($hours): array {
                $value = $hours[$day] ?? [];
                $isOpen = (bool) ($value['is_open'] ?? false);

                return [
                    $day => [
                        'is_open' => $isOpen,
                        'opens_at' => $isOpen ? ($value['opens_at'] ?? '09:00') : null,
                        'closes_at' => $isOpen ? ($value['closes_at'] ?? '17:00') : null,
                    ],
                ];
            })
            ->all();
    }

    private function createDefaultRoles(Tenant $tenant): void
    {
        $roles = [
            'Business Owner' => 'business-owner',
            'Branch Manager' => 'branch-manager',
            'Cashier / Sales Staff' => 'cashier-sales-staff',
            'Accountant' => 'accountant',
            'Inventory Officer' => 'inventory-officer',
            'HR / Admin Officer' => 'hr-admin-officer',
        ];

        foreach ($roles as $name => $slug) {
            Role::query()->firstOrCreate([
                'tenant_id' => $tenant->id,
                'slug' => $slug,
            ], [
                'name' => $name,
                'is_system' => true,
            ]);
        }
    }

    private function syncSubscription(Tenant $tenant, int $planId): void
    {
        if (! Plan::query()->whereKey($planId)->exists()) {
            return;
        }

        TenantSubscription::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plan_id' => $planId,
                'status' => $this->subscriptionStatusForTenant($tenant),
                'billing_interval' => 'monthly',
                'trial_ends_at' => $tenant->trial_ends_at,
                'current_period_starts_at' => now(),
                'current_period_ends_at' => now()->addMonth(),
                'cancelled_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    private function subscriptionStatusForTenant(Tenant $tenant): SubscriptionStatus
    {
        return match ($tenant->status) {
            TenantStatus::Active => SubscriptionStatus::Active,
            TenantStatus::Inactive => SubscriptionStatus::Paused,
            TenantStatus::Suspended => SubscriptionStatus::Suspended,
            TenantStatus::Cancelled => SubscriptionStatus::Cancelled,
            default => SubscriptionStatus::Trialing,
        };
    }
}
