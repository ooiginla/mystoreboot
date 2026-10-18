<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_staff_bonuses', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hr_staff_id')->constrained('hr_staff')->cascadeOnDelete();
            $table->string('bonus_type', 40)->index();
            $table->string('bonus_month', 7)->index();
            $table->date('bonus_date');
            $table->unsignedBigInteger('amount_minor');
            $table->string('status', 32)->default('pending')->index();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'bonus_month', 'status'], 'hr_bonuses_month_status_index');
        });

        Schema::table('hr_payroll_runs', function (Blueprint $table): void {
            $table->unsignedBigInteger('bonus_minor')->default(0)->after('gross_salary_minor');
        });

        Schema::table('hr_payroll_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('bonus_minor')->default(0)->after('gross_salary_minor');
            $table->json('bonus_breakdown')->nullable()->after('deduction_breakdown');
        });
    }

    public function down(): void
    {
        Schema::table('hr_payroll_items', function (Blueprint $table): void {
            $table->dropColumn(['bonus_minor', 'bonus_breakdown']);
        });

        Schema::table('hr_payroll_runs', function (Blueprint $table): void {
            $table->dropColumn('bonus_minor');
        });

        Schema::dropIfExists('hr_staff_bonuses');
    }
};
