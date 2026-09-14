<?php

declare(strict_types=1);

namespace Modules\Business\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Tenancy\Models\Tenant;

final class DeleteTenantAction
{
    /**
     * Permanently remove a tenant and all tenant-owned data.
     *
     * Most tenant tables have a cascading foreign key to tenants. These legacy
     * tables intentionally or historically used an indexed UUID without that
     * foreign key, so they must be purged explicitly.
     */
    private const UNCONSTRAINED_TENANT_TABLES = [
        'wallet_transactions',
        'wallet_withdrawals',
        'wallets',
        'approval_requests',
        'security_audit_logs',
    ];

    public function execute(Tenant $tenant): void
    {
        $tenantId = (string) $tenant->id;

        DB::transaction(function () use ($tenant, $tenantId): void {
            foreach (self::UNCONSTRAINED_TENANT_TABLES as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                    DB::table($table)->where('tenant_id', $tenantId)->delete();
                }
            }

            // Tenant uses SoftDeletes, but this operation is explicitly permanent.
            // The database cascade removes every directly and indirectly owned row.
            $tenant->forceDelete();
        });
    }
}
