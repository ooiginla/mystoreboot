<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_cash_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sales_till_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('finance_account_id')->constrained()->restrictOnDelete();
            $table->string('code', 80);
            $table->string('name', 160);
            $table->string('location_type', 32);
            $table->bigInteger('balance_minor')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['tenant_id', 'code'], 'sales_cash_location_code_unique');
            $table->index(['tenant_id', 'branch_id', 'location_type'], 'sales_cash_location_branch_type_idx');
            $table->index(['tenant_id', 'sales_till_session_id'], 'sales_cash_location_till_idx');
        });

        Schema::table('sales_till_sessions', function (Blueprint $table): void {
            $table->foreignId('cash_location_id')->nullable()->after('user_id')->constrained('sales_cash_locations')->nullOnDelete();
            $table->foreignId('vault_cash_location_id')->nullable()->after('cash_location_id')->constrained('sales_cash_locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_till_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('vault_cash_location_id');
            $table->dropConstrainedForeignId('cash_location_id');
        });

        Schema::dropIfExists('sales_cash_locations');
    }
};
