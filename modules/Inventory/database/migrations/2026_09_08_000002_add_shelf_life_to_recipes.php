<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How long a finished batch stays good for. Production output previously entered stock
 * with no lot and no expiry, which is precisely backwards for a kitchen: a tray of
 * jollof cooked this morning is the shortest-lived stock in the building.
 *
 * Null means "does not expire" — correct for shelf-stable goods, so this stays optional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table): void {
            $table->unsignedSmallInteger('shelf_life_days')->nullable()->after('yield_unit_id');
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table): void {
            $table->dropColumn('shelf_life_days');
        });
    }
};
