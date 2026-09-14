<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

/**
 * Seed the default units of measure for every existing tenant and point each
 * variant's base unit at "each" (the implicit behaviour at the time: 1 = 1 item).
 * Runs after the units table and the variant columns exist. Idempotent.
 *
 * Self-contained on purpose. This used to call EnsureDefaultUnitsAction, but that
 * action later learned to create unit categories — a table that only exists from
 * 2026_09_06_000004 — so upgrading a database with tenants failed here. A migration
 * must describe the schema as it was on its own date, never today's application code.
 *
 * These seeded units are grouped into a "General" category by 2026_09_06_000004 and
 * removed again by 2026_09_06_000006; the net result for an upgraded database is the
 * same as for a fresh one.
 */
return new class extends Migration
{
    /**
     * The starter units as they stood on 2026-09-05:
     * code => [name, dimension, to_base_factor|null, is_base_for_dimension].
     */
    private const UNITS = [
        'ea' => ['Each', 'count', 1.0, true],
        'g' => ['Gram', 'weight', 1.0, true],
        'kg' => ['Kilogram', 'weight', 1000.0, false],
        'ml' => ['Millilitre', 'volume', 1.0, true],
        'L' => ['Litre', 'volume', 1000.0, false],
        'pack' => ['Pack', 'count', null, false],
        'carton' => ['Carton', 'count', null, false],
        'box' => ['Box', 'count', null, false],
    ];

    public function up(): void
    {
        DB::table('tenants')->orderBy('id')->pluck('id')->each(function ($tenantId): void {
            $tenantId = (string) $tenantId;
            $now = now();

            foreach (self::UNITS as $code => [$name, $dimension, $factor, $isBase]) {
                $exists = DB::table('units_of_measure')
                    ->where('tenant_id', $tenantId)
                    ->where('code', $code)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('units_of_measure')->insert([
                    'tenant_id' => $tenantId,
                    'code' => $code,
                    'name' => $name,
                    'dimension' => $dimension,
                    'to_base_factor' => $factor,
                    'is_base_for_dimension' => $isBase,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $baseUnitId = DB::table('units_of_measure')
                ->where('tenant_id', $tenantId)
                ->where('code', 'ea')
                ->value('id');

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
