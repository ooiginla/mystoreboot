<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Collapse `sales_points` into `inventory_locations.is_sellable_point`.
 *
 * Two tables both meant "you can sell here", and the wrong one was wired to the floor
 * plan: ticking "Can sell from here" on a location did nothing to the sales-point list, so
 * a service area could only ever be pointed at a branch-shaped sales point. Every sales
 * point in practice was a same-named mirror of the location it pointed at.
 *
 * A service area now names the sellable location it sells from, directly.
 * Mirrors the earlier prep_stations collapse — one row per real-world place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_areas', function (Blueprint $table): void {
            $table->foreignId('sellable_location_id')->nullable()->after('branch_id')
                ->constrained('inventory_locations')->nullOnDelete();
        });

        $this->migrateAreas();

        Schema::table('service_areas', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sales_point_id');
        });

        Schema::dropIfExists('sales_points');
    }

    /**
     * Point each area straight at the store its sales point resolved to, and make sure
     * that store is actually marked sellable — an area selling from a location that is
     * not a sellable point was the silent misconfiguration this removes.
     */
    private function migrateAreas(): void
    {
        if (! Schema::hasTable('sales_points') || ! Schema::hasTable('service_areas')) {
            return;
        }

        foreach (DB::table('service_areas')->whereNotNull('sales_point_id')->get() as $area) {
            $locationId = DB::table('sales_points')->where('id', $area->sales_point_id)->value('default_location_id');

            if ($locationId === null) {
                continue;
            }

            DB::table('service_areas')->where('id', $area->id)->update(['sellable_location_id' => $locationId]);
            DB::table('inventory_locations')->where('id', $locationId)->update(['is_sellable_point' => true]);
        }
    }

    public function down(): void
    {
        Schema::create('sales_points', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('default_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->string('name', 140);
            $table->string('status', 32)->default('active');
            $table->timestamps();
        });

        Schema::table('service_areas', function (Blueprint $table): void {
            $table->foreignId('sales_point_id')->nullable()->constrained('sales_points')->nullOnDelete();
            $table->dropConstrainedForeignId('sellable_location_id');
        });
    }
};
