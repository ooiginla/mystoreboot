<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('tenants')
            ->select('id')
            ->orderBy('id')
            ->chunk(100, function ($tenants) use ($now): void {
                foreach ($tenants as $tenant) {
                    DB::table('finance_accounts')->updateOrInsert([
                        'tenant_id' => $tenant->id,
                        'code' => '1220',
                    ], [
                        'name' => 'Vendor Advances',
                        'type' => 'asset',
                        'category' => 'Current Assets',
                        'description' => 'Payments made to suppliers before the related goods or services are received.',
                        'normal_balance' => 'debit',
                        'is_system' => true,
                        'is_active' => true,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Keep the account and any supplier-advance postings intact on rollback.
    }
};
