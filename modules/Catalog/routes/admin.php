<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\CatalogController;

Route::get('/', [CatalogController::class, 'index'])->name('index');
Route::post('/products', [CatalogController::class, 'storeProduct'])->name('products.store');
Route::put('/products/{product}', [CatalogController::class, 'updateProduct'])->name('products.update');
Route::post('/categories', [CatalogController::class, 'storeCategory'])->name('categories.store');
Route::post('/tags', [CatalogController::class, 'storeTag'])->name('tags.store');
Route::put('/tags/{tag}', [CatalogController::class, 'updateTag'])->name('tags.update');
Route::post('/attributes', [CatalogController::class, 'storeAttribute'])->name('attributes.store');
Route::put('/attributes/{attribute}', [CatalogController::class, 'updateAttribute'])->name('attributes.update');
