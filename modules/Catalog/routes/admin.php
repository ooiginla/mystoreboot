<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\CatalogController;

Route::get('/', [CatalogController::class, 'index'])->name('index');
Route::post('/products', [CatalogController::class, 'storeProduct'])->name('products.store');
Route::post('/products/import', [CatalogController::class, 'importProductsFromImages'])->name('products.import');
Route::post('/products/import-sheet', [CatalogController::class, 'importProductsFromSheet'])->name('products.import-sheet');
Route::post('/products/ai-content', [CatalogController::class, 'generateProductContent'])->name('products.ai-content');
Route::post('/products/{product}/generate-image', [CatalogController::class, 'generateProductImage'])->name('products.generate-image');
Route::put('/products/{product}', [CatalogController::class, 'updateProduct'])->name('products.update');
Route::post('/products/{product}/stock', [CatalogController::class, 'adjustStock'])->name('products.stock.adjust');
Route::post('/vendors/quick', [CatalogController::class, 'quickStoreVendor'])->name('vendors.quick-store');
Route::patch('/products/{product}/status', [CatalogController::class, 'updateProductStatus'])->name('products.status.update');
Route::delete('/products/{product}', [CatalogController::class, 'destroyProduct'])->name('products.destroy');
Route::post('/custom-definitions', [CatalogController::class, 'storeCustomDefinition'])->name('custom-definitions.store');
Route::post('/categories', [CatalogController::class, 'storeCategory'])->name('categories.store');
Route::post('/tags', [CatalogController::class, 'storeTag'])->name('tags.store');
Route::put('/tags/{tag}', [CatalogController::class, 'updateTag'])->name('tags.update');
Route::post('/badges', [CatalogController::class, 'storeBadge'])->name('badges.store');
Route::put('/badges/{badge}', [CatalogController::class, 'updateBadge'])->name('badges.update');
Route::post('/product-collections', [CatalogController::class, 'storeProductCollection'])->name('product-collections.store');
Route::put('/product-collections/{collection}', [CatalogController::class, 'updateProductCollection'])->name('product-collections.update');
Route::post('/taxes', [CatalogController::class, 'storeTax'])->name('taxes.store');
Route::put('/taxes/{tax}', [CatalogController::class, 'updateTax'])->name('taxes.update');
Route::delete('/taxes/{tax}', [CatalogController::class, 'destroyTax'])->name('taxes.destroy');
Route::post('/attributes', [CatalogController::class, 'storeAttribute'])->name('attributes.store');
Route::put('/attributes/{attribute}', [CatalogController::class, 'updateAttribute'])->name('attributes.update');
