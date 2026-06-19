<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Sales\Http\Controllers\SalesController;

Route::get('/', [SalesController::class, 'index'])->name('index');
Route::post('/tills', [SalesController::class, 'openTill'])->name('tills.open');
Route::post('/tills/{tillSession}/movements', [SalesController::class, 'storeTillMovement'])->name('tills.movements.store');
Route::post('/tills/{tillSession}/close', [SalesController::class, 'closeTill'])->name('tills.close');
Route::post('/orders', [SalesController::class, 'storeOrder'])->name('orders.store');
Route::post('/orders/{order}/delivery-status', [SalesController::class, 'updateDeliveryStatus'])->name('orders.delivery-status.update');
Route::post('/orders/{order}/payments', [SalesController::class, 'storePayment'])->name('orders.payments.store');
Route::post('/orders/{order}/returns', [SalesController::class, 'storeReturn'])->name('orders.returns.store');
Route::post('/coupons', [SalesController::class, 'storeCoupon'])->name('coupons.store');
