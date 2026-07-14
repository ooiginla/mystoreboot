<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Modules\Finance\Actions\EnsureDefaultChartOfAccountsAction;
use Modules\Tenancy\Models\Tenant;

return new class extends Migration
{
    public function up(): void
    {
        $ensureDefaults = app(EnsureDefaultChartOfAccountsAction::class);

        Tenant::query()
            ->select('id')
            ->orderBy('id')
            ->chunk(100, function ($tenants) use ($ensureDefaults): void {
                foreach ($tenants as $tenant) {
                    $ensureDefaults->execute($tenant->id);
                }
            });
    }

    public function down(): void
    {
        // Non-destructive seed migration. Businesses may customize these categories after creation.
    }
};
