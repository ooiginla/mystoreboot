<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Sales\Http\Controllers\SalesController;

Route::get('/', [SalesController::class, 'index'])->name('index');
Route::get('/orders', [SalesController::class, 'index'])->name('orders.index');
Route::get('/retail-pos', [SalesController::class, 'retailPos'])->name('retail-pos');
Route::post('/customers', [SalesController::class, 'storeQuickCustomer'])->name('customers.quick');
Route::post('/tills', [SalesController::class, 'openTill'])->name('tills.open');
Route::post('/tills/{tillSession}/movements', [SalesController::class, 'storeTillMovement'])->name('tills.movements.store');
Route::post('/tills/{tillSession}/close', [SalesController::class, 'closeTill'])->name('tills.close');
Route::post('/orders', [SalesController::class, 'storeOrder'])->name('orders.store');
Route::post('/orders/{order}/cancel', [SalesController::class, 'cancelOrder'])->name('orders.cancel');
Route::post('/orders/{order}/mark-refunded', [SalesController::class, 'markOrderRefunded'])->name('orders.mark-refunded');
Route::post('/orders/{order}/status', [SalesController::class, 'updateOrderStatus'])->name('orders.status.update');
Route::post('/orders/{order}/delivery-status', [SalesController::class, 'updateDeliveryStatus'])->name('orders.delivery-status.update');
Route::post('/orders/{order}/payments', [SalesController::class, 'storePayment'])->name('orders.payments.store');
Route::post('/orders/{order}/returns', [SalesController::class, 'storeReturn'])->name('orders.returns.store');
Route::post('/coupons', [SalesController::class, 'storeCoupon'])->name('coupons.store');
Route::get('/settlements', [SalesController::class, 'settlements'])->name('settlements.index');
Route::get('/settlements/statement', [SalesController::class, 'settlementsStatement'])->name('settlements.statement');
Route::post('/settlements/payout-mode', [SalesController::class, 'updatePayoutMode'])->name('settlements.payout-mode');
Route::get('/wallet', [SalesController::class, 'wallet'])->name('wallet.index');
Route::get('/wallet/statement', [SalesController::class, 'walletStatement'])->name('wallet.statement');
Route::post('/wallet/preview', [SalesController::class, 'walletWithdrawPreview'])->name('wallet.preview');
Route::post('/wallet/withdraw', [SalesController::class, 'walletWithdraw'])->name('wallet.withdraw');
