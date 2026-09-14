<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The auto-seeded "General" measurement category (is_default) mixed dimensions
 * (count + weight + volume) and was useless as a per-product measurement. Remove it
 * and its seeded units for every tenant; products fall back to a plain "each" count
 * (base_unit_id nulls out via the FK's ON DELETE SET NULL). Measurement categories
 * are now created only by tenants who need them (advanced inventory / F&B).
 */
return new class extends Migration
{
    public function up(): void
    {
        $generalIds = DB::table('unit_categories')->where('is_default', true)->pluck('id')->all();

        if ($generalIds === []) {
            return;
        }

        // Deleting the units nulls their references (variant base/purchase unit, recipe
        // items, production/requisition item units) via ON DELETE SET NULL.
        DB::table('units_of_measure')->whereIn('unit_category_id', $generalIds)->delete();

        // Deleting the categories nulls products.unit_category_id via ON DELETE SET NULL.
        DB::table('unit_categories')->whereIn('id', $generalIds)->delete();
    }

    public function down(): void
    {
        // The seeded General category is intentionally gone; nothing to restore.
    }
};
