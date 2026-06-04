<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Access\Http\Controllers\RoleController;

Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
Route::post('/tenant-users', [RoleController::class, 'storeTenantUser'])->name('tenant-users.store');
