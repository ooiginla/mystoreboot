<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_till_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('session_number', 80);
            $table->string('status', 24)->default('open')->index();
            $table->unsignedBigInteger('opening_float_minor')->default(0);
            $table->unsignedBigInteger('expected_cash_minor')->default(0);
            $table->unsignedBigInteger('expected_total_minor')->default(0);
            $table->unsignedBigInteger('actual_total_minor')->default(0);
            $table->bigInteger('variance_total_minor')->default(0);
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->text('opening_note')->nullable();
            $table->text('closing_note')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'session_number'], 'till_session_number_unique');
            $table->index(['tenant_id', 'branch_id', 'status'], 'till_tenant_branch_status_idx');
            $table->index(['tenant_id', 'user_id', 'status'], 'till_tenant_user_status_idx');
        });

        Schema::create('sales_till_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_till_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('movement_type', 40);
            $table->string('payment_method', 80)->default('Cash');
            $table->unsignedBigInteger('amount_minor');
            $table->string('reference_number', 120)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['tenant_id', 'sales_till_session_id'], 'till_move_session_idx');
            $table->index(['tenant_id', 'movement_type'], 'till_move_type_idx');
        });

        Schema::create('sales_till_closing_counts', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_till_session_id')->constrained()->cascadeOnDelete();
            $table->string('payment_method', 80);
            $table->unsignedBigInteger('expected_minor')->default(0);
            $table->unsignedBigInteger('actual_minor')->default(0);
            $table->bigInteger('variance_minor')->default(0);
            $table->timestamps();

            $table->unique(['sales_till_session_id', 'payment_method'], 'till_count_method_unique');
        });

        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->foreignId('sales_till_session_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->index(['tenant_id', 'sales_till_session_id'], 'sales_order_till_idx');
        });

        Schema::table('sales_order_payments', function (Blueprint $table): void {
            $table->foreignId('sales_till_session_id')->nullable()->after('sales_order_id')->constrained()->nullOnDelete();
            $table->index(['tenant_id', 'sales_till_session_id'], 'sales_payment_till_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sales_till_session_id');
        });

        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sales_till_session_id');
        });

        Schema::dropIfExists('sales_till_closing_counts');
        Schema::dropIfExists('sales_till_movements');
        Schema::dropIfExists('sales_till_sessions');
    }
};
