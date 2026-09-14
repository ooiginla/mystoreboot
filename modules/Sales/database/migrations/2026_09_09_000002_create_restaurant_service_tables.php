<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6a: the floor. Service areas group tables, and an area hangs off a sales point,
 * which already carries branch_id and default_location_id from Phase 0 — that chain is
 * what makes stock deplete from the right store and scopes a server to their own floor.
 *
 * Room service is modelled as an area whose "tables" are rooms, which is also the seam
 * Phase 7 (lodging) will post folio charges through.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_areas', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_point_id')->nullable()->constrained('sales_points')->nullOnDelete();
            $table->string('name', 120);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();

            $table->index(['tenant_id', 'branch_id'], 'service_areas_tenant_branch_idx');
        });

        Schema::create('restaurant_tables', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_area_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->unsignedSmallInteger('seats')->default(2);
            // available | occupied | reserved | dirty
            $table->string('status', 32)->default('available')->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['service_area_id', 'name'], 'restaurant_tables_unique_name');
            $table->index(['tenant_id', 'status'], 'restaurant_tables_tenant_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_tables');
        Schema::dropIfExists('service_areas');
    }
};
