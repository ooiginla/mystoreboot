<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_staff', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('staff_number', 80);
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 160)->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('job_title', 140)->nullable();
            $table->date('hire_date')->nullable();
            $table->unsignedBigInteger('monthly_salary_minor')->default(0);
            $table->string('status', 32)->default('active')->index();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'staff_number']);
            $table->index(['tenant_id', 'branch_id', 'status']);
        });

        Schema::create('hr_staff_branch_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hr_staff_id')->constrained('hr_staff')->cascadeOnDelete();
            $table->foreignId('from_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('to_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->date('effective_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'hr_staff_id', 'effective_date'], 'hr_staff_transfer_index');
        });

        Schema::create('hr_staff_deductions', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hr_staff_id')->constrained('hr_staff')->cascadeOnDelete();
            $table->string('deduction_type', 40)->index();
            $table->string('deduction_month', 7)->index();
            $table->date('deduction_date');
            $table->unsignedBigInteger('amount_minor');
            $table->string('status', 32)->default('pending')->index();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'deduction_month', 'status'], 'hr_deductions_month_status_index');
        });

        Schema::create('hr_payroll_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('payroll_month', 7);
            $table->date('posted_at');
            $table->unsignedBigInteger('gross_salary_minor')->default(0);
            $table->unsignedBigInteger('deduction_minor')->default(0);
            $table->unsignedBigInteger('net_salary_minor')->default(0);
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'payroll_month']);
            $table->index(['tenant_id', 'posted_at']);
        });

        Schema::create('hr_payroll_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hr_payroll_run_id')->constrained('hr_payroll_runs')->cascadeOnDelete();
            $table->foreignId('hr_staff_id')->constrained('hr_staff')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('gross_salary_minor')->default(0);
            $table->unsignedBigInteger('deduction_minor')->default(0);
            $table->unsignedBigInteger('net_salary_minor')->default(0);
            $table->json('deduction_breakdown')->nullable();
            $table->timestamps();

            $table->unique(['hr_payroll_run_id', 'hr_staff_id'], 'hr_payroll_item_staff_unique');
            $table->index(['tenant_id', 'hr_staff_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_payroll_items');
        Schema::dropIfExists('hr_payroll_runs');
        Schema::dropIfExists('hr_staff_deductions');
        Schema::dropIfExists('hr_staff_branch_transfers');
        Schema::dropIfExists('hr_staff');
    }
};
