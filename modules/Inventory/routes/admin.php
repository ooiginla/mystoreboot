<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\BatchTraceController;
use Modules\Inventory\Http\Controllers\InventoryController;
use Modules\Inventory\Http\Controllers\LocationTypeController;
use Modules\Inventory\Http\Controllers\ProductionController;
use Modules\Inventory\Http\Controllers\ReorderLevelController;
use Modules\Inventory\Http\Controllers\RequisitionController;
use Modules\Inventory\Http\Controllers\StockCountController;
use Modules\Inventory\Http\Controllers\UnitCategoryController;
use Modules\Inventory\Http\Controllers\UnitOfMeasureController;

Route::get('/', [InventoryController::class, 'index'])->name('index');
Route::post('/locations', [InventoryController::class, 'storeLocation'])->name('locations.store');
Route::put('/locations/{location}', [InventoryController::class, 'updateLocation'])->name('locations.update');
Route::post('/location-types', [LocationTypeController::class, 'store'])->name('location-types.store');
Route::put('/location-types/{locationType}', [LocationTypeController::class, 'update'])->name('location-types.update');
Route::delete('/location-types/{locationType}', [LocationTypeController::class, 'destroy'])->name('location-types.destroy');
Route::post('/movements', [InventoryController::class, 'storeMovement'])->name('movements.store');
Route::post('/reorder-settings', [InventoryController::class, 'saveReorder'])->name('reorder.save');
Route::get('/reorder-levels', [ReorderLevelController::class, 'index'])->name('reorder-levels.index');

// Recipes & production (F&B)
Route::get('/production', [ProductionController::class, 'index'])->name('production.index');
Route::post('/production/finished-products', [ProductionController::class, 'storeFinishedProduct'])->name('production.finished-products.store');
Route::delete('/production/finished-products/{product}', [ProductionController::class, 'destroyFinishedProduct'])->name('production.finished-products.destroy');
Route::post('/production/recipes', [ProductionController::class, 'storeRecipe'])->name('production.recipes.store');
Route::put('/production/recipes/{recipe}', [ProductionController::class, 'updateRecipe'])->name('production.recipes.update');
Route::delete('/production/recipes/{recipe}', [ProductionController::class, 'destroyRecipe'])->name('production.recipes.destroy');
Route::post('/production/record', [ProductionController::class, 'record'])->name('production.record');
Route::post('/production/start', [ProductionController::class, 'start'])->name('production.start');
Route::post('/production/runs/{order}/complete', [ProductionController::class, 'complete'])->name('production.runs.complete');
Route::post('/production/runs/{order}/cancel', [ProductionController::class, 'cancelRun'])->name('production.runs.cancel');
Route::patch('/production/{product}/how-made', [ProductionController::class, 'updateStockPolicy'])->name('production.stock-policy');
Route::get('/production/{product}', [ProductionController::class, 'show'])->name('production.show');

// Units of measure & categories
Route::get('/units', [UnitOfMeasureController::class, 'index'])->name('units.index');
Route::post('/unit-categories', [UnitCategoryController::class, 'store'])->name('unit-categories.store');
Route::put('/unit-categories/{unitCategory}', [UnitCategoryController::class, 'update'])->name('unit-categories.update');
Route::delete('/unit-categories/{unitCategory}', [UnitCategoryController::class, 'destroy'])->name('unit-categories.destroy');
Route::post('/units', [UnitOfMeasureController::class, 'store'])->name('units.store');
Route::put('/units/{unit}', [UnitOfMeasureController::class, 'update'])->name('units.update');
Route::delete('/units/{unit}', [UnitOfMeasureController::class, 'destroy'])->name('units.destroy');

// Lot traceability & recall
Route::get('/batches', [BatchTraceController::class, 'index'])->name('batches.index');
Route::get('/batches/{batch}', [BatchTraceController::class, 'show'])->name('batches.show');

// Stock takes: count sessions and variance posting
Route::get('/stock-counts', [StockCountController::class, 'index'])->name('stock-counts.index');
Route::post('/stock-counts', [StockCountController::class, 'store'])->name('stock-counts.store');
Route::get('/stock-counts/{stockCount}', [StockCountController::class, 'show'])->name('stock-counts.show');
Route::put('/stock-counts/{stockCount}/items', [StockCountController::class, 'updateItems'])->name('stock-counts.items.update');
Route::post('/stock-counts/{stockCount}/post', [StockCountController::class, 'post'])->name('stock-counts.post');
Route::post('/stock-counts/{stockCount}/cancel', [StockCountController::class, 'cancel'])->name('stock-counts.cancel');

// Stock requisitions & inter-store transfers
Route::get('/requisitions', [RequisitionController::class, 'index'])->name('requisitions.index');
Route::post('/requisitions', [RequisitionController::class, 'store'])->name('requisitions.store');
Route::post('/requisitions/{requisition}/approve', [RequisitionController::class, 'approve'])->name('requisitions.approve');
Route::post('/requisitions/{requisition}/reject', [RequisitionController::class, 'reject'])->name('requisitions.reject');
Route::post('/requisitions/{requisition}/fulfil', [RequisitionController::class, 'fulfil'])->name('requisitions.fulfil');
Route::post('/requisitions/{requisition}/cancel', [RequisitionController::class, 'cancel'])->name('requisitions.cancel');
