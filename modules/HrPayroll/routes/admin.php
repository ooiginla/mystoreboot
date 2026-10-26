<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\HrPayroll\Http\Controllers\HrPayrollController;
use Modules\HrPayroll\Http\Controllers\WorkforceController;

Route::get('/', [HrPayrollController::class, 'index'])->name('index');
Route::post('/staff', [HrPayrollController::class, 'storeStaff'])->name('staff.store');
Route::put('/staff/{staff}', [HrPayrollController::class, 'updateStaff'])->name('staff.update');
Route::post('/deductions', [HrPayrollController::class, 'storeDeduction'])->name('deductions.store');
Route::post('/bonuses', [HrPayrollController::class, 'storeBonus'])->name('bonuses.store');
Route::post('/payroll-runs', [HrPayrollController::class, 'storePayrollRun'])->name('payroll-runs.store');
Route::get('/workforce', [WorkforceController::class, 'index'])->name('workforce.index');
Route::post('/workforce/shifts', [WorkforceController::class, 'storeShift'])->name('workforce.shifts.store');
Route::put('/workforce/shifts/{shift}', [WorkforceController::class, 'updateShift'])->name('workforce.shifts.update');
Route::post('/workforce/rota', [WorkforceController::class, 'storeAssignment'])->name('workforce.rota.store');
Route::delete('/workforce/rota/{assignment}', [WorkforceController::class, 'destroyAssignment'])->name('workforce.rota.destroy');
Route::post('/workforce/attendance/clock-in', [WorkforceController::class, 'clockIn'])->name('workforce.attendance.clock-in');
Route::post('/workforce/attendance/{attendance}/clock-out', [WorkforceController::class, 'clockOut'])->name('workforce.attendance.clock-out');
