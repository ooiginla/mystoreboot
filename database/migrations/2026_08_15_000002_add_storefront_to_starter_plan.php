<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Modules\Subscriptions\Models\Module;
use Modules\Subscriptions\Models\Plan;

/**
 * The Basic (starter) plan advertises an online store, customers/invoicing and analytics,
 * but the plan's entitlements were missing those modules — so those menus (notably Online
 * Store) never showed for Basic-plan tenants. Attach them to the existing plan.
 */
return new class extends Migration
{
    public function up(): void
    {
        $plan = Plan::query()->where('slug', 'starter')->first();

        if (! $plan) {
            return;
        }

        $moduleIds = Module::query()
            ->whereIn('slug', ['storefront', 'customers', 'analytics'])
            ->pluck('id');

        foreach ($moduleIds as $moduleId) {
            $plan->modules()->syncWithoutDetaching([$moduleId => ['is_enabled' => true, 'limits' => null]]);
            // Force-enable in case a disabled entitlement row already existed.
            $plan->modules()->updateExistingPivot($moduleId, ['is_enabled' => true]);
        }
    }

    public function down(): void
    {
        // Leave the entitlements in place — removing them would hide menus again.
    }
};
