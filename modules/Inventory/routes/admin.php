<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\InventoryController;

Route::get('/', [InventoryController::class, 'index'])->name('index');
Route::post('/locations', [InventoryController::class, 'storeLocation'])->name('locations.store');
Route::post('/movements', [InventoryController::class, 'storeMovement'])->name('movements.store');
Route::post('/reorder-settings', [InventoryController::class, 'saveReorder'])->name('reorder.save');
