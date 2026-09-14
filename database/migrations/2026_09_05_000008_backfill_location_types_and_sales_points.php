<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Actions\EnsureLocationTypesAction;

/**
 * Seed configurable location types for every tenant. Idempotent.
 *
 * This migration also used to give each branch a default sales point. Sales points were
 * folded into `inventory_locations.is_sellable_point` on 2026-09-09 and the seeding action
 * removed, so that half is gone — a later migration drops the table outright. Migrations
 * must keep running on a fresh database years from now, so this one no longer reaches for
 * an application class that can be deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        $types = app(EnsureLocationTypesAction::class);

        DB::table('tenants')->orderBy('id')->pluck('id')->each(function ($tenantId) use ($types): void {
            $types->forTenant((string) $tenantId);
        });
    }

    public function down(): void
    {
        // Leave seeded types in place.
    }
};
