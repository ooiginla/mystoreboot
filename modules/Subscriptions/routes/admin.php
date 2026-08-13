<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Subscriptions\Http\Controllers\PlanController;

Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
Route::put('/plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
