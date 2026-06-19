<?php

declare(strict_types=1);

namespace Modules\Business\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Business\Models\Branch;

final class CreateBranchAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?Branch $branch = null): Branch
    {
        return DB::transaction(function () use ($data, $branch): Branch {
            if ((bool) ($data['is_primary'] ?? false)) {
                Branch::query()
                    ->where('tenant_id', $data['tenant_id'])
                    ->when($branch, fn ($query) => $query->whereKeyNot($branch->id))
                    ->update(['is_primary' => false]);
            }

            $values = [
                'tenant_id' => $data['tenant_id'],
                'name' => $data['name'],
                'code' => strtoupper((string) $data['code']),
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'timezone' => $data['timezone'] ?? null,
                'currency_code' => isset($data['currency_code']) ? strtoupper((string) $data['currency_code']) : null,
                'default_tax_rate' => $data['default_tax_rate'] ?? null,
                'is_primary' => (bool) ($data['is_primary'] ?? false),
                'status' => $data['status'],
                'settings' => array_merge($branch?->settings ?? [], [
                    'delivery_methods' => $this->normalizeDeliveryMethods($data['delivery_methods'] ?? []),
                ]),
            ];

            if ($branch) {
                $branch->update($values);

                return $branch->refresh();
            }

            return Branch::query()->create($values);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $methods
     * @return list<array{name: string, price: string, status: string}>
     */
    private function normalizeDeliveryMethods(array $methods): array
    {
        return collect($methods)
            ->filter(fn (array $method): bool => trim((string) ($method['name'] ?? '')) !== '')
            ->map(fn (array $method): array => [
                'name' => trim((string) $method['name']),
                'price' => number_format((float) ($method['price'] ?? 0), 2, '.', ''),
                'status' => in_array(($method['status'] ?? 'active'), ['active', 'inactive'], true) ? (string) ($method['status'] ?? 'active') : 'active',
            ])
            ->values()
            ->all();
    }
}
