<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Analytics\Http\Controllers\DashboardController;

Route::get('/', [DashboardController::class, 'index'])->name('index');
