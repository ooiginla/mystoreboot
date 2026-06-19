<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 160);
            $table->string('type', 40)->index();
            $table->string('normal_balance', 8);
            $table->boolean('is_system')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'type', 'is_active']);
        });

        Schema::create('finance_expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('finance_account_id');
            $table->string('name', 140);
            $table->string('code', 60);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active']);
            $table->foreign('finance_account_id', 'fin_exp_cat_account_fk')->references('id')->on('finance_accounts')->cascadeOnDelete();
        });

        Schema::create('finance_journal_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('entry_number', 80);
            $table->date('entry_date');
            $table->string('source_type', 80)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_event', 80)->nullable();
            $table->text('memo')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'entry_number'], 'fin_journal_entry_number_unique');
            $table->unique(['tenant_id', 'source_type', 'source_id', 'source_event'], 'finance_journal_source_unique');
            $table->index(['tenant_id', 'entry_date']);
        });

        Schema::create('finance_journal_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('finance_journal_entry_id');
            $table->foreignId('finance_account_id');
            $table->string('party_type', 80)->nullable();
            $table->unsignedBigInteger('party_id')->nullable();
            $table->unsignedBigInteger('debit_minor')->default(0);
            $table->unsignedBigInteger('credit_minor')->default(0);
            $table->text('memo')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'finance_account_id']);
            $table->index(['tenant_id', 'party_type', 'party_id']);
            $table->foreign('finance_journal_entry_id', 'fin_journal_lines_entry_fk')->references('id')->on('finance_journal_entries')->cascadeOnDelete();
            $table->foreign('finance_account_id', 'fin_journal_lines_account_fk')->references('id')->on('finance_accounts')->restrictOnDelete();
        });

        Schema::create('finance_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('finance_expense_category_id');
            $table->string('expense_number', 80);
            $table->date('expense_date');
            $table->string('payee_name', 180)->nullable();
            $table->string('payment_method', 80)->default('Cash');
            $table->string('payment_status', 32)->default('paid')->index();
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('paid_minor')->default(0);
            $table->string('reference_number', 120)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'expense_number'], 'fin_expenses_number_unique');
            $table->index(['tenant_id', 'expense_date'], 'fin_expenses_date_index');
            $table->index(['tenant_id', 'payment_status'], 'fin_expenses_status_index');
            $table->foreign('finance_expense_category_id', 'fin_expenses_category_fk')->references('id')->on('finance_expense_categories')->restrictOnDelete();
        });

        Schema::create('finance_petty_cash_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('finance_expense_category_id')->nullable();
            $table->string('transaction_number', 80);
            $table->date('transaction_date');
            $table->string('transaction_type', 32)->index();
            $table->unsignedBigInteger('amount_minor');
            $table->string('payee_name', 180)->nullable();
            $table->string('reference_number', 120)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'transaction_number'], 'fin_petty_cash_number_unique');
            $table->index(['tenant_id', 'transaction_date'], 'fin_petty_cash_date_index');
            $table->foreign('finance_expense_category_id', 'fin_petty_cash_category_fk')->references('id')->on('finance_expense_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_petty_cash_transactions');
        Schema::dropIfExists('finance_expenses');
        Schema::dropIfExists('finance_journal_lines');
        Schema::dropIfExists('finance_journal_entries');
        Schema::dropIfExists('finance_expense_categories');
        Schema::dropIfExists('finance_accounts');
    }
};
