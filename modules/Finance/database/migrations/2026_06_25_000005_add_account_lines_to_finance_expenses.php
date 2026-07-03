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
        Schema::table('finance_expenses', function (Blueprint $table): void {
            $table->foreignId('finance_account_id')->nullable()->after('finance_expense_category_id');
            $table->foreignId('payment_finance_account_id')->nullable()->after('finance_account_id');

            $table->foreign('finance_account_id', 'fin_expenses_account_fk')->references('id')->on('finance_accounts')->restrictOnDelete();
            $table->foreign('payment_finance_account_id', 'fin_expenses_payment_account_fk')->references('id')->on('finance_accounts')->nullOnDelete();
        });

        $categoryAccounts = DB::table('finance_expense_categories')->pluck('finance_account_id', 'id');

        DB::table('finance_expenses')
            ->select(['id', 'finance_expense_category_id'])
            ->orderBy('id')
            ->chunkById(100, function ($expenses) use ($categoryAccounts): void {
                foreach ($expenses as $expense) {
                    $accountId = $categoryAccounts->get($expense->finance_expense_category_id);

                    if ($accountId) {
                        DB::table('finance_expenses')->where('id', $expense->id)->update(['finance_account_id' => $accountId]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('finance_expenses', function (Blueprint $table): void {
            $table->dropForeign('fin_expenses_payment_account_fk');
            $table->dropForeign('fin_expenses_account_fk');
            $table->dropColumn(['payment_finance_account_id', 'finance_account_id']);
        });
    }
};
