<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Subscriptions\Models\Module;
use Modules\Subscriptions\Models\Plan;

/**
 * Register the F&B / Advanced Inventory feature as a billable module. It gates all
 * advanced-inventory UI (units beyond "each", prep stations, and — later — recipes,
 * production, and the restaurant POS). Not core and not on Starter/Growth/Pro, so a
 * basic tenant never sees the advanced surfaces; attached to Enterprise (and the
 * all-modules trial) here.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('billable_modules')->updateOrInsert(
            ['slug' => 'fnb'],
            [
                'name' => 'F&B / Advanced Inventory',
                'description' => 'Units of measure, kitchen prep stations, recipes, production and restaurant POS.',
                'is_core' => false,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        $moduleId = Module::query()->where('slug', 'fnb')->value('id');

        if ($moduleId === null) {
            return;
        }

        Plan::query()->whereIn('slug', ['enterprise', 'all-modules-trial'])->get()
            ->each(function (Plan $plan) use ($moduleId): void {
                $plan->modules()->syncWithoutDetaching([$moduleId => ['is_enabled' => true, 'limits' => null]]);
                $plan->modules()->updateExistingPivot($moduleId, ['is_enabled' => true]);
            });
    }

    public function down(): void
    {
        $moduleId = Module::query()->where('slug', 'fnb')->value('id');

        if ($moduleId !== null) {
            DB::table('plan_module_entitlements')->where('module_id', $moduleId)->delete();
            DB::table('billable_modules')->where('id', $moduleId)->delete();
        }
    }
};
