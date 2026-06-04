<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Business\Http\Controllers\BusinessSetupController;

Route::get('/organizations', [BusinessSetupController::class, 'organizations'])->name('organizations.index');
Route::get('/organizations/{tenant}', [BusinessSetupController::class, 'organizationDetails'])->name('organizations.show');
Route::get('/', [BusinessSetupController::class, 'index'])->name('index');
Route::post('/profile', [BusinessSetupController::class, 'saveProfile'])->name('profile.save');
Route::post('/branches', [BusinessSetupController::class, 'storeBranch'])->name('branches.store');
Route::post('/departments', [BusinessSetupController::class, 'storeDepartment'])->name('departments.store');
