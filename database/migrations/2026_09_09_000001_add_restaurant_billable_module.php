<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Subscriptions\Models\Module;
use Modules\Subscriptions\Models\Plan;

/**
 * Register table-service dining as its own billable module, separate from `fnb`.
 *
 * The two are genuinely independent purchases: a central kitchen or supermarket may want
 * recipes, units and stock control with no table service at all, while a small restaurant
 * may want tables and kitchen screens without advanced inventory. Bundling them would
 * make one impossible to sell without the other.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('billable_modules')->updateOrInsert(
            ['slug' => 'restaurant'],
            [
                'name' => 'Restaurant POS',
                'description' => 'Tables and service areas, open checks, kitchen order tickets and kitchen display screens.',
                'is_core' => false,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        $moduleId = Module::query()->where('slug', 'restaurant')->value('id');

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
        $moduleId = Module::query()->where('slug', 'restaurant')->value('id');

        if ($moduleId !== null) {
            DB::table('plan_module_entitlements')->where('module_id', $moduleId)->delete();
            DB::table('billable_modules')->where('id', $moduleId)->delete();
        }
    }
};
