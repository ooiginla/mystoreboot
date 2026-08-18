<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Modules\Business\Models\Branch;
use Modules\Business\Models\OnlineStore;

/**
 * Point existing online stores that have no fulfilment branch at their primary branch, so
 * online orders reserve and deduct stock (a null fulfilment branch skips inventory entirely).
 */
return new class extends Migration
{
    public function up(): void
    {
        OnlineStore::query()->whereNull('fulfilment_branch_id')->get()->each(function (OnlineStore $store): void {
            $branchId = Branch::query()
                ->where('tenant_id', $store->tenant_id)
                ->orderByDesc('is_primary')
                ->orderBy('id')
                ->value('id');

            if ($branchId) {
                $store->forceFill(['fulfilment_branch_id' => $branchId])->save();
            }
        });
    }

    public function down(): void
    {
        // Leave fulfilment branches in place on rollback.
    }
};
