<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Collapse `prep_stations` into `inventory_locations`.
 *
 * A bar or a grill is one thing in the real world — a place that holds its own stock and
 * makes food from it — but the model made you create it twice and then link the halves.
 * That link was also a bug waiting to happen: a station pointed at a store, and nothing
 * guaranteed depletion followed the pointer.
 *
 * A location now carries `is_prep_station`, exactly as it already carries
 * `is_sellable_point`. Products, recipes and kitchen tickets point straight at that
 * location, so "where it was made" and "whose stock it came from" cannot disagree.
 *
 * The user-facing name stays "prep station" — only the storage is unified.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_locations', function (Blueprint $table): void {
            $table->boolean('is_prep_station')->default(false)->after('is_sellable_point');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('prep_location_id')->nullable()->after('prep_station_id')
                ->constrained('inventory_locations')->nullOnDelete();
        });

        Schema::table('recipes', function (Blueprint $table): void {
            $table->foreignId('prep_location_id')->nullable()->after('prep_station_id')
                ->constrained('inventory_locations')->nullOnDelete();
        });

        Schema::table('kitchen_order_tickets', function (Blueprint $table): void {
            $table->foreignId('prep_location_id')->nullable()->after('prep_station_id')
                ->constrained('inventory_locations')->nullOnDelete();
        });

        $this->migrateStations();

        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('prep_station_id');
        });
        Schema::table('recipes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('prep_station_id');
        });
        Schema::table('kitchen_order_tickets', function (Blueprint $table): void {
            // The KDS index covers the routing column, so it has to move with it —
            // SQLite refuses to drop a column an index still depends on.
            $table->dropIndex('kot_station_status_idx');
            $table->dropConstrainedForeignId('prep_station_id');
            $table->index(['tenant_id', 'prep_location_id', 'status'], 'kot_station_status_idx');
        });

        Schema::dropIfExists('prep_stations');
    }

    /**
     * Turn every existing station into a flagged location, then repoint everything that
     * referenced it. A station that already had a default store becomes that store; one
     * without gets a location of its own so nothing is lost.
     */
    private function migrateStations(): void
    {
        if (! Schema::hasTable('prep_stations')) {
            return;
        }

        foreach (DB::table('prep_stations')->get() as $station) {
            $locationId = $station->default_location_id;

            if ($locationId !== null && DB::table('inventory_locations')->where('id', $locationId)->exists()) {
                DB::table('inventory_locations')->where('id', $locationId)->update(['is_prep_station' => true]);
            } else {
                $locationId = DB::table('inventory_locations')->insertGetId([
                    'tenant_id' => $station->tenant_id,
                    'branch_id' => $station->branch_id,
                    'name' => $station->name,
                    'code' => $this->uniqueCode($station->tenant_id, $station->name),
                    'location_type' => 'store_room',
                    'status' => $station->status ?? 'active',
                    'is_sellable_point' => false,
                    'is_prep_station' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('products')->where('prep_station_id', $station->id)->update(['prep_location_id' => $locationId]);
            DB::table('recipes')->where('prep_station_id', $station->id)->update(['prep_location_id' => $locationId]);
            DB::table('kitchen_order_tickets')->where('prep_station_id', $station->id)->update(['prep_location_id' => $locationId]);
        }
    }

    private function uniqueCode(string $tenantId, string $name): string
    {
        $base = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name) ?: 'STATION', 0, 10));
        $code = $base;
        $suffix = 1;

        while (DB::table('inventory_locations')->where('tenant_id', $tenantId)->where('code', $code)->exists()) {
            $code = substr($base, 0, 8).$suffix;
            $suffix++;
        }

        return $code;
    }

    public function down(): void
    {
        Schema::create('prep_stations', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('default_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->string('name', 140);
            $table->string('status', 32)->default('active');
            $table->timestamps();
        });

        Schema::table('kitchen_order_tickets', function (Blueprint $table): void {
            $table->dropIndex('kot_station_status_idx');
            $table->foreignId('prep_station_id')->nullable()->constrained('prep_stations')->nullOnDelete();
            $table->dropConstrainedForeignId('prep_location_id');
            $table->index(['tenant_id', 'prep_station_id', 'status'], 'kot_station_status_idx');
        });
        Schema::table('recipes', function (Blueprint $table): void {
            $table->foreignId('prep_station_id')->nullable()->constrained('prep_stations')->nullOnDelete();
            $table->dropConstrainedForeignId('prep_location_id');
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('prep_station_id')->nullable()->constrained('prep_stations')->nullOnDelete();
            $table->dropConstrainedForeignId('prep_location_id');
        });

        Schema::table('inventory_locations', function (Blueprint $table): void {
            $table->dropColumn('is_prep_station');
        });
    }
};
