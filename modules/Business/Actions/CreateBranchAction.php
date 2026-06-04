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
    public function execute(array $data): Branch
    {
        return DB::transaction(function () use ($data): Branch {
            if ((bool) ($data['is_primary'] ?? false)) {
                Branch::query()
                    ->where('tenant_id', $data['tenant_id'])
                    ->update(['is_primary' => false]);
            }

            return Branch::query()->create([
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
            ]);
        });
    }
}
