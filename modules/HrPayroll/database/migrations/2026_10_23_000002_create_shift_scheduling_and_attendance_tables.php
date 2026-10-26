<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_work_shifts', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('colour', 20)->default('#027a45');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['tenant_id', 'branch_id', 'name']);
        });

        Schema::create('hr_shift_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hr_staff_id')->constrained('hr_staff')->cascadeOnDelete();
            $table->foreignId('hr_work_shift_id')->constrained('hr_work_shifts')->cascadeOnDelete();
            $table->date('work_date');
            $table->string('status', 30)->default('scheduled')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['hr_staff_id', 'work_date', 'hr_work_shift_id'], 'hr_shift_staff_date_unique');
            $table->index(['tenant_id', 'branch_id', 'work_date'], 'hr_rota_branch_date_idx');
        });

        Schema::create('hr_attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hr_staff_id')->constrained('hr_staff')->cascadeOnDelete();
            $table->foreignId('hr_shift_assignment_id')->nullable()->constrained('hr_shift_assignments')->nullOnDelete();
            $table->date('work_date');
            $table->timestamp('clocked_in_at')->nullable();
            $table->timestamp('clocked_out_at')->nullable();
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->string('status', 30)->default('present')->index();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('hr_shift_assignment_id', 'hr_attendance_assignment_unique');
            $table->index(['tenant_id', 'branch_id', 'work_date'], 'hr_attendance_branch_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_attendance_records');
        Schema::dropIfExists('hr_shift_assignments');
        Schema::dropIfExists('hr_work_shifts');
    }
};
