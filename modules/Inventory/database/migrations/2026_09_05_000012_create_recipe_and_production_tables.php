<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1: recipes (bill of materials) and production orders. A recipe defines the
 * inputs and yield for a produced item; a production order records a real batch —
 * consuming raw materials and creating finished-goods stock at the computed cost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('output_product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('yield_unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->foreignId('prep_station_id')->nullable()->constrained('prep_stations')->nullOnDelete();
            $table->string('name', 160);
            $table->decimal('yield_quantity', 15, 4)->default(1);
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true)->index();
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();

            $table->index(['tenant_id', 'output_product_variant_id', 'is_active'], 'recipes_tenant_output_active_idx');
        });

        Schema::create('recipe_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('component_product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('wastage_percent', 6, 3)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'recipe_id'], 'recipe_items_tenant_recipe_idx');
        });

        Schema::create('production_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('recipe_version')->default(1);
            $table->foreignId('output_product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('source_location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->foreignId('output_location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->string('reference_number', 120)->nullable();
            $table->decimal('planned_quantity', 15, 4)->default(0);
            $table->decimal('actual_yield_quantity', 15, 4)->default(0);
            $table->unsignedBigInteger('total_cost_minor')->default(0);
            $table->unsignedBigInteger('unit_cost_minor')->default(0);
            $table->string('status', 32)->default('draft')->index();
            $table->timestamp('produced_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'produced_at'], 'production_orders_tenant_status_date_idx');
        });

        Schema::create('production_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('component_product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->decimal('planned_quantity', 15, 4)->default(0);
            $table->decimal('actual_quantity', 15, 4)->default(0);
            $table->unsignedBigInteger('unit_cost_minor')->default(0);
            $table->unsignedBigInteger('line_cost_minor')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'production_order_id'], 'production_items_tenant_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_order_items');
        Schema::dropIfExists('production_orders');
        Schema::dropIfExists('recipe_items');
        Schema::dropIfExists('recipes');
    }
};
