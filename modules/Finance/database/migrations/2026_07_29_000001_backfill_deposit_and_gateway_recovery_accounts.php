<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Finance\Actions\EnsureDefaultChartOfAccountsAction;

return new class extends Migration
{
    /**
     * Ensure the Customer Deposits (2310) and Payment Gateway Charge Recovered
     * (4130) accounts exist for every existing tenant. New tenants receive them
     * automatically the first time any journal entry is posted.
     */
    public function up(): void
    {
        if (! Schema::hasTable('tenants') || ! Schema::hasTable('finance_accounts')) {
            return;
        }

        $ensure = app(EnsureDefaultChartOfAccountsAction::class);

        DB::table('tenants')->pluck('id')->each(function ($tenantId) use ($ensure): void {
            $ensure->execute((string) $tenantId);
        });
    }

    public function down(): void
    {
        // The default chart of accounts is not removed on rollback.
    }
};
