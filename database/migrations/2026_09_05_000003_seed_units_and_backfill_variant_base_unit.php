<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Actions\EnsureDefaultUnitsAction;

/**
 * Seed the default units of measure for every existing tenant and point each
 * variant's base unit at "each" (today's implicit behaviour: 1 = 1 item). Runs
 * after the units table and the variant columns exist. Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        $action = app(EnsureDefaultUnitsAction::class);

        DB::table('tenants')->orderBy('id')->pluck('id')->each(function ($tenantId) use ($action): void {
            $tenantId = (string) $tenantId;
            $action->forTenant($tenantId);

            $baseUnitId = $action->baseUnitId($tenantId);

            if ($baseUnitId === null) {
                return;
            }

            DB::table('product_variants')
                ->where('tenant_id', $tenantId)
                ->whereNull('base_unit_id')
                ->update(['base_unit_id' => $baseUnitId]);
        });
    }

    public function down(): void
    {
        // Leave seeded units and base-unit assignments in place; dropping the
        // columns/tables is handled by their own migrations' down().
    }
};
