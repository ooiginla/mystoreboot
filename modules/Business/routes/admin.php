<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Business\Http\Controllers\BusinessSetupController;

Route::get('/organizations', [BusinessSetupController::class, 'organizations'])->name('organizations.index');
Route::get('/organizations/{tenant}', [BusinessSetupController::class, 'organizationDetails'])->name('organizations.show');
Route::get('/', [BusinessSetupController::class, 'index'])->name('index');
Route::post('/profile', [BusinessSetupController::class, 'saveProfile'])->name('profile.save');
Route::post('/payment-methods', [BusinessSetupController::class, 'savePaymentMethods'])->name('payment-methods.save');
Route::post('/payment-accounts', [BusinessSetupController::class, 'storePaymentAccount'])->name('payment-accounts.store');
Route::put('/payment-accounts/{paymentAccount}', [BusinessSetupController::class, 'updatePaymentAccount'])->name('payment-accounts.update');
Route::delete('/payment-accounts/{paymentAccount}', [BusinessSetupController::class, 'destroyPaymentAccount'])->name('payment-accounts.destroy');
Route::post('/online-store', [BusinessSetupController::class, 'saveOnlineStore'])->name('online-store.save');
Route::post('/subscriptions', [BusinessSetupController::class, 'storeSubscription'])->name('subscriptions.store');
Route::put('/subscriptions/{subscription}', [BusinessSetupController::class, 'updateSubscription'])->name('subscriptions.update');
Route::post('/branches', [BusinessSetupController::class, 'storeBranch'])->name('branches.store');
Route::put('/branches/{branch}', [BusinessSetupController::class, 'updateBranch'])->name('branches.update');
Route::post('/departments', [BusinessSetupController::class, 'storeDepartment'])->name('departments.store');
