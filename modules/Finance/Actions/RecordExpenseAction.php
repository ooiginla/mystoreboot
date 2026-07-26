<?php

declare(strict_types=1);

namespace Modules\Finance\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceExpense;
use Modules\Finance\Models\FinanceExpenseCategory;

/**
 * Records a business expense and posts its journal entry. Shared by the finance
 * controller (direct) and the approval executor (deferred), so both paths behave
 * identically.
 */
final class RecordExpenseAction
{
    public function __construct(private readonly PostJournalEntryAction $postJournalEntry) {}

    /**
     * @param  array<string, mixed>  $data  validated ExpenseRequest data
     */
    public function execute(array $data): FinanceExpense
    {
        $expenseAccount = FinanceAccount::query()->where('tenant_id', $data['tenant_id'])->where('code', $data['expense_account_code'])->firstOrFail();
        $category = FinanceExpenseCategory::query()->firstOrCreate([
            'tenant_id' => $data['tenant_id'],
            'code' => Str::slug($data['expense_category']),
        ], [
            'finance_account_id' => $expenseAccount->id,
            'name' => $data['expense_category'],
            'description' => 'Expense category for '.$data['expense_category'].'.',
            'is_active' => true,
        ]);
        $amountMinor = $this->moneyToMinor($data['amount']);
        $paidMinor = match ($data['payment_status']) {
            'paid' => $amountMinor,
            'unpaid' => 0,
            default => min($amountMinor, $this->moneyToMinor($data['paid_amount'] ?? 0)),
        };
        $paymentAccount = $paidMinor > 0
            ? FinanceAccount::query()->where('tenant_id', $data['tenant_id'])->where('code', $data['payment_account_code'])->firstOrFail()
            : null;

        return DB::transaction(function () use ($data, $category, $expenseAccount, $paymentAccount, $amountMinor, $paidMinor): FinanceExpense {
            $expense = FinanceExpense::query()->create([
                'tenant_id' => $data['tenant_id'],
                'finance_expense_category_id' => $category->id,
                'finance_account_id' => $expenseAccount->id,
                'payment_finance_account_id' => $paymentAccount?->id,
                'expense_number' => $this->number('EXP', $data['tenant_id']),
                'expense_date' => $data['expense_date'],
                'payee_name' => $data['payee_name'] ?? null,
                'payment_method' => $paymentAccount?->code ?? 'Unpaid',
                'payment_status' => $data['payment_status'],
                'amount_minor' => $amountMinor,
                'paid_minor' => $paidMinor,
                'reference_number' => $data['reference_number'] ?? null,
                'description' => $data['description'] ?? null,
            ]);

            $this->postJournalEntry->execute(
                $expense->tenant_id,
                $expense->expense_date->toDateString(),
                'Operational expense '.$expense->expense_number,
                [
                    ['account_code' => $expenseAccount->code, 'branch_id' => $data['branch_id'] ?? null, 'debit_minor' => $amountMinor, 'memo' => $expense->description],
                    ['account_code' => $paymentAccount?->code ?? '1000', 'branch_id' => $data['branch_id'] ?? null, 'credit_minor' => $paidMinor, 'memo' => $paymentAccount?->name],
                    ['account_code' => '2000', 'branch_id' => $data['branch_id'] ?? null, 'credit_minor' => max(0, $amountMinor - $paidMinor), 'party_type' => 'payee', 'memo' => $expense->payee_name],
                ],
                'finance_expense',
                $expense->id,
                'recorded',
            );

            return $expense;
        });
    }

    public function amountMinor(array $data): int
    {
        return $this->moneyToMinor($data['amount'] ?? 0);
    }

    private function number(string $prefix, string $tenantId): string
    {
        return $prefix.'-'.now()->format('Ymd').'-'.str_pad((string) (FinanceExpense::query()->where('tenant_id', $tenantId)->count() + 1), 5, '0', STR_PAD_LEFT);
    }

    private function moneyToMinor(mixed $value): int
    {
        return (int) round(((float) (is_string($value) ? str_replace(',', '', $value) : ($value ?: 0))) * 100);
    }
}
