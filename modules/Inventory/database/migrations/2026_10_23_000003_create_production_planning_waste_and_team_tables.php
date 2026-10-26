<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_station_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->foreignId('hr_work_shift_id')->nullable()->constrained('hr_work_shifts')->nullOnDelete();
            $table->foreignId('production_lead_staff_id')->nullable()->constrained('hr_staff')->nullOnDelete();
            $table->foreignId('production_order_id')->nullable()->constrained('production_orders')->nullOnDelete();
            $table->date('production_date');
            $table->time('scheduled_time');
            $table->string('wave', 80)->nullable();
            $table->decimal('planned_quantity', 15, 4);
            $table->string('status', 30)->default('planned')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'branch_id', 'production_date'], 'production_plan_branch_date_idx');
        });

        Schema::create('production_plan_staff', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('production_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hr_staff_id')->constrained('hr_staff')->cascadeOnDelete();
            $table->boolean('is_lead')->default(false);
            $table->timestamps();

            $table->unique(['production_plan_id', 'hr_staff_id']);
        });

        Schema::table('production_orders', function (Blueprint $table): void {
            $table->foreignId('hr_work_shift_id')->nullable()->after('production_lead_user_id')->constrained('hr_work_shifts')->nullOnDelete();
            $table->foreignId('production_lead_staff_id')->nullable()->after('hr_work_shift_id')->constrained('hr_staff')->nullOnDelete();
        });

        Schema::create('production_order_staff', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('production_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hr_staff_id')->constrained('hr_staff')->cascadeOnDelete();
            $table->boolean('is_lead')->default(false);
            $table->timestamps();

            $table->unique(['production_order_id', 'hr_staff_id']);
        });

        Schema::create('production_waste_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('hr_staff_id')->nullable()->constrained('hr_staff')->nullOnDelete();
            $table->foreignId('inventory_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->date('waste_date');
            $table->decimal('quantity', 15, 4);
            $table->string('reason', 40)->index();
            $table->unsignedBigInteger('unit_cost_minor')->default(0);
            $table->unsignedBigInteger('value_minor')->default(0);
            $table->boolean('deducted_from_stock')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'branch_id', 'waste_date'], 'production_waste_branch_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_waste_records');
        Schema::dropIfExists('production_order_staff');
        Schema::table('production_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('production_lead_staff_id');
            $table->dropConstrainedForeignId('hr_work_shift_id');
        });
        Schema::dropIfExists('production_plan_staff');
        Schema::dropIfExists('production_plans');
    }
};
