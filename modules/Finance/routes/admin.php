<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Finance\Http\Controllers\FinanceReportController;

Route::get('/', [FinanceReportController::class, 'index'])->name('index');
Route::get('/chart-of-accounts', [FinanceReportController::class, 'chartOfAccounts'])->name('chart-of-accounts');
Route::get('/expenses', [FinanceReportController::class, 'expenses'])->name('expenses');
Route::post('/expense-categories', [FinanceReportController::class, 'storeExpenseCategory'])->name('expense-categories.store');
Route::put('/expense-categories/{category}', [FinanceReportController::class, 'updateExpenseCategory'])->name('expense-categories.update');
Route::post('/expenses', [FinanceReportController::class, 'storeExpense'])->name('expenses.store');
Route::post('/petty-cash', [FinanceReportController::class, 'storePettyCashTransaction'])->name('petty-cash.store');
Route::post('/journals', [FinanceReportController::class, 'storeJournalEntry'])->name('journals.store');
