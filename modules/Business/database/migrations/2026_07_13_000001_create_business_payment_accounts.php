<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_payment_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('finance_account_id')->constrained('finance_accounts')->restrictOnDelete();
            $table->string('identifier', 140);
            $table->string('account_name', 160)->nullable();
            $table->string('provider_name', 140);
            $table->string('account_number', 100)->nullable();
            $table->string('account_type', 32)->default('normal')->index();
            $table->json('supported_payment_methods');
            $table->string('status', 32)->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'identifier']);
            $table->index(['tenant_id', 'branch_id', 'status'], 'business_payment_accounts_branch_status_idx');
        });

        Schema::table('sales_order_payments', function (Blueprint $table): void {
            $table->foreignId('business_payment_account_id')->nullable()->after('sales_till_session_id')->constrained('business_payment_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('business_payment_account_id');
        });

        Schema::dropIfExists('business_payment_accounts');
    }
};
