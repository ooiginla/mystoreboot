<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_work_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('order_number', 50);
            $table->date('production_date');
            $table->time('scheduled_time')->nullable();
            $table->string('wave', 80)->nullable();
            $table->foreignId('production_station_id')->constrained('inventory_locations')->restrictOnDelete();
            $table->foreignId('hr_work_shift_id')->nullable()->constrained('hr_work_shifts')->nullOnDelete();
            $table->foreignId('production_lead_staff_id')->nullable()->constrained('hr_staff')->nullOnDelete();
            $table->string('status', 30)->default('planned')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'order_number']);
            $table->index(['tenant_id', 'branch_id', 'production_date'], 'production_work_order_date_idx');
        });

        Schema::table('production_plans', function (Blueprint $table): void {
            $table->foreignId('production_work_order_id')->nullable()->after('branch_id')->constrained()->cascadeOnDelete();
        });
        Schema::table('production_orders', function (Blueprint $table): void {
            $table->foreignId('production_work_order_id')->nullable()->after('recipe_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('production_orders', fn (Blueprint $table) => $table->dropConstrainedForeignId('production_work_order_id'));
        Schema::table('production_plans', fn (Blueprint $table) => $table->dropConstrainedForeignId('production_work_order_id'));
        Schema::dropIfExists('production_work_orders');
    }
};
