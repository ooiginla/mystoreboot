<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Reseller\Http\Controllers\ResellerController;
use Modules\Reseller\Http\Middleware\RequireResellerModule;

Route::middleware(RequireResellerModule::class)->group(function (): void {
    Route::get('/', [ResellerController::class, 'index'])->name('index');
    Route::get('/suppliers', [ResellerController::class, 'suppliers'])->name('suppliers.index');
    Route::get('/products', [ResellerController::class, 'products'])->name('products.index');
    Route::get('/orders', [ResellerController::class, 'orders'])->name('orders.index');
    Route::get('/payments', [ResellerController::class, 'payments'])->name('payments.index');
    Route::get('/settings', [ResellerController::class, 'settings'])->name('settings.index');
    Route::put('/mode', [ResellerController::class, 'updateMode'])->name('mode.update');
    Route::put('/settings', [ResellerController::class, 'updateSettings'])->name('settings.update');
    Route::post('/suppliers', [ResellerController::class, 'storeSupplier'])->name('suppliers.store');
    Route::put('/suppliers/{supplier}', [ResellerController::class, 'updateSupplier'])->name('suppliers.update');
    Route::delete('/suppliers/{supplier}', [ResellerController::class, 'destroySupplier'])->name('suppliers.destroy');
    Route::post('/suppliers/{supplier}/scan/schedule', [ResellerController::class, 'scheduleSupplierScan'])->name('suppliers.scan.schedule');
    Route::post('/suppliers/{supplier}/scan', [ResellerController::class, 'scanSupplier'])->name('suppliers.scan');
    Route::patch('/products/{product}', [ResellerController::class, 'updateProduct'])->name('products.update');
    Route::get('/orders/{order}', [ResellerController::class, 'showOrder'])->name('orders.show');
    Route::patch('/orders/{order}/payment-status', [ResellerController::class, 'updateOrderPaymentStatus'])->name('orders.payment-status.update');
    Route::patch('/orders/{order}/items/{item}', [ResellerController::class, 'updateOrderItem'])->name('orders.items.update');
});
