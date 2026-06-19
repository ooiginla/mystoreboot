<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\HrPayroll\Http\Controllers\HrPayrollController;

Route::get('/', [HrPayrollController::class, 'index'])->name('index');
Route::post('/staff', [HrPayrollController::class, 'storeStaff'])->name('staff.store');
Route::put('/staff/{staff}', [HrPayrollController::class, 'updateStaff'])->name('staff.update');
Route::post('/deductions', [HrPayrollController::class, 'storeDeduction'])->name('deductions.store');
Route::post('/payroll-runs', [HrPayrollController::class, 'storePayrollRun'])->name('payroll-runs.store');
