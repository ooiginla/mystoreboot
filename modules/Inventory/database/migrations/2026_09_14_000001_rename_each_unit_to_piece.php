<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The default count unit becomes "Piece" (pc) instead of "Each" (ea).
 *
 * Renamed in place, so every product, recipe line and requisition already pointing at the
 * unit keeps pointing at it — no data moves. Codes are unique per business, so a business
 * that already made its own "pc" keeps code "ea" and only takes the new name; the screens
 * show "pc" for either code.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('units_of_measure')->where('code', 'ea')->orderBy('id')->each(function (object $unit): void {
            $clash = DB::table('units_of_measure')
                ->where('tenant_id', $unit->tenant_id)
                ->where('code', 'pc')
                ->exists();

            DB::table('units_of_measure')->where('id', $unit->id)->update(array_filter([
                'code' => $clash ? null : 'pc',
                'name' => 'Piece',
                'updated_at' => now(),
            ]));
        });
    }

    public function down(): void
    {
        // Only the renamed default: a count base unit worth exactly one, named Piece.
        DB::table('units_of_measure')
            ->whereIn('code', ['pc', 'ea'])
            ->where('name', 'Piece')
            ->where('dimension', 'count')
            ->where('is_base_for_dimension', true)
            ->orderBy('id')
            ->each(function (object $unit): void {
                $clash = $unit->code === 'pc' && DB::table('units_of_measure')
                    ->where('tenant_id', $unit->tenant_id)
                    ->where('code', 'ea')
                    ->exists();

                DB::table('units_of_measure')->where('id', $unit->id)->update(array_filter([
                    'code' => $clash ? null : 'ea',
                    'name' => 'Each',
                    'updated_at' => now(),
                ]));
            });
    }
};
