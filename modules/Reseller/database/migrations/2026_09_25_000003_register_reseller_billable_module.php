<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('billable_modules')->updateOrInsert(
            ['slug' => 'reseller'],
            [
                'name' => 'Reseller Store',
                'description' => 'Source products from supplier websites and manage reseller orders.',
                'is_core' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $moduleId = DB::table('billable_modules')->where('slug', 'reseller')->value('id');

        if ($moduleId === null) {
            return;
        }

        DB::table('tenants')->select(['id', 'commerce_mode'])->orderBy('id')->each(
            function (object $tenant) use ($moduleId, $now): void {
                DB::table('tenant_module_entitlements')->updateOrInsert(
                    ['tenant_id' => $tenant->id, 'module_id' => $moduleId],
                    [
                        // Preserve reseller stores already activated before this became
                        // an opt-in tenant module. Every other tenant starts with it off.
                        'is_enabled' => $tenant->commerce_mode === 'reseller',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            },
        );
    }

    public function down(): void
    {
        $moduleId = DB::table('billable_modules')->where('slug', 'reseller')->value('id');

        if ($moduleId !== null) {
            DB::table('tenant_module_entitlements')->where('module_id', $moduleId)->delete();
            DB::table('plan_module_entitlements')->where('module_id', $moduleId)->delete();
        }

        DB::table('billable_modules')->where('slug', 'reseller')->delete();
    }
};
