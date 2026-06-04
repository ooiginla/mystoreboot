<?php

declare(strict_types=1);

namespace Modules\Business\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Access\Models\Role;
use Modules\Subscriptions\Models\Plan;
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
                'status' => 'trialing',
            ]);

            $logoPath = $tenant->logo_path;

            if (($data['logo'] ?? null) instanceof UploadedFile) {
                $logoPath = $data['logo']->store('business-logos', 'public');
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
                'settings' => array_merge($tenant->settings ?? [], [
                    'brand_color' => $data['brand_color'] ?? null,
                ]),
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

        DB::table('tenant_subscriptions')->updateOrInsert(
            ['tenant_id' => $tenant->id],
            [
                'plan_id' => $planId,
                'status' => $tenant->status,
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
}
