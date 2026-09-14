<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0, Workstream C — the location model. Separates the three concepts the F&B
 * use case needs: inventory locations (where stock lives), prep stations (kitchen
 * arms that route a line and point at a store), and sales points (billing tills;
 * one bill per order). All additive and nullable; behaviour is unchanged until a
 * later phase consumes them.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tenant-configurable location types (seeded from the system enum values).
        Schema::create('location_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->string('label', 80);
            $table->boolean('is_system')->default(false);
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
        });

        Schema::table('inventory_locations', function (Blueprint $table): void {
            $table->foreignId('parent_id')->nullable()->after('branch_id')
                ->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('replenishment_source_location_id')->nullable()->after('parent_id')
                ->constrained('inventory_locations')->nullOnDelete();
            $table->boolean('is_sellable_point')->default(false)->after('location_type');
        });

        // Kitchen arms (grill, hot kitchen, flour kitchen, bar). Each points at the
        // store its lines deplete from.
        Schema::create('prep_stations', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('default_location_id')->nullable()
                ->constrained('inventory_locations')->nullOnDelete();
            $table->string('name', 120);
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();

            $table->index(['tenant_id', 'branch_id', 'status'], 'prep_stations_tenant_branch_status_idx');
        });

        // Billing points / tills. One order = one sales point = one bill. Carries the
        // fallback depletion store (replaces the POS "first location" behaviour).
        Schema::create('sales_points', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('default_location_id')->nullable()
                ->constrained('inventory_locations')->nullOnDelete();
            $table->string('name', 120);
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();

            $table->index(['tenant_id', 'branch_id', 'status'], 'sales_points_tenant_branch_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_points');
        Schema::dropIfExists('prep_stations');

        Schema::table('inventory_locations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('replenishment_source_location_id');
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn('is_sellable_point');
        });

        Schema::dropIfExists('location_types');
    }
};
