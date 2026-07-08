<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('finance_bank_movements');

        Schema::create('finance_bank_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('source_finance_account_id')->constrained('finance_accounts')->restrictOnDelete();
            $table->foreignId('destination_finance_account_id')->constrained('finance_accounts')->restrictOnDelete();
            $table->foreignId('fee_finance_account_id')->nullable()->constrained('finance_accounts')->nullOnDelete();
            $table->foreignId('finance_journal_entry_id')->nullable()->constrained('finance_journal_entries')->nullOnDelete();
            $table->string('movement_number', 80);
            $table->string('movement_type', 40)->index();
            $table->date('movement_date');
            $table->unsignedBigInteger('gross_amount_minor');
            $table->unsignedBigInteger('fee_amount_minor')->default(0);
            $table->unsignedBigInteger('net_amount_minor');
            $table->string('reference_number', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'movement_number']);
            $table->index(['tenant_id', 'movement_date'], 'fin_bank_movements_tenant_date_idx');
            $table->index(['tenant_id', 'source_finance_account_id'], 'fin_bank_movements_tenant_source_idx');
            $table->index(['tenant_id', 'destination_finance_account_id'], 'fin_bank_movements_tenant_dest_idx');
        });

        $now = now();

        DB::table('tenants')
            ->select('id')
            ->orderBy('id')
            ->chunk(100, function ($tenants) use ($now): void {
                foreach ($tenants as $tenant) {
                    DB::table('finance_accounts')->updateOrInsert([
                        'tenant_id' => $tenant->id,
                        'code' => 'EXP-6350',
                    ], [
                        'name' => 'Bank, POS and Gateway Charges',
                        'type' => 'expense',
                        'category' => 'Non-Operating Expenses',
                        'description' => 'Bank transfer, POS card, and online payment settlement charges.',
                        'normal_balance' => 'debit',
                        'is_system' => true,
                        'is_active' => true,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_bank_movements');
    }
};
