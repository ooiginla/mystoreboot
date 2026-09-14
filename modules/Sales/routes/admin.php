<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Sales\Http\Controllers\CheckBillingController;
use Modules\Sales\Http\Controllers\CheckChangesController;
use Modules\Sales\Http\Controllers\KdsController;
use Modules\Sales\Http\Controllers\ModifierController;
use Modules\Sales\Http\Controllers\RestaurantController;
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
Route::post('/settlements/payments/{payment}/status', [SalesController::class, 'updateCollectedPaymentStatus'])->name('settlements.payment-status');
Route::post('/settlements/payout-mode', [SalesController::class, 'updatePayoutMode'])->name('settlements.payout-mode');
Route::get('/wallet', [SalesController::class, 'wallet'])->name('wallet.index');
Route::get('/wallet/statement', [SalesController::class, 'walletStatement'])->name('wallet.statement');
Route::post('/wallet/preview', [SalesController::class, 'walletWithdrawPreview'])->name('wallet.preview');
Route::post('/wallet/withdraw', [SalesController::class, 'walletWithdraw'])->name('wallet.withdraw');

// Restaurant POS — table service floor
Route::get('/restaurant', [RestaurantController::class, 'floor'])->name('restaurant.floor');
Route::get('/restaurant/areas', [RestaurantController::class, 'areas'])->name('restaurant.areas.index');
Route::post('/restaurant/areas', [RestaurantController::class, 'storeArea'])->name('restaurant.areas.store');
Route::post('/restaurant/tables', [RestaurantController::class, 'storeTable'])->name('restaurant.tables.store');
Route::put('/restaurant/areas/{area}', [RestaurantController::class, 'updateArea'])->name('restaurant.areas.update');
Route::put('/restaurant/tables/{table}', [RestaurantController::class, 'updateTable'])->name('restaurant.tables.update');
Route::patch('/restaurant/tables/{table}/status', [RestaurantController::class, 'updateTableStatus'])->name('restaurant.tables.status');
Route::post('/restaurant/tables/{table}/open', [RestaurantController::class, 'openCheck'])->name('restaurant.checks.open');
Route::get('/restaurant/checks/{order}', [RestaurantController::class, 'check'])->name('restaurant.check');
Route::post('/restaurant/checks/{order}/items', [RestaurantController::class, 'storeItems'])->name('restaurant.checks.items.store');
Route::delete('/restaurant/checks/{order}/items/{item}', [RestaurantController::class, 'destroyItem'])->name('restaurant.checks.items.destroy');
Route::patch('/restaurant/checks/{order}/items/{item}/source', [RestaurantController::class, 'updateItemSource'])->name('restaurant.checks.items.source');
Route::post('/restaurant/checks/{order}/fire', [RestaurantController::class, 'fire'])->name('restaurant.checks.fire');
Route::post('/restaurant/checks/{order}/cancel', [RestaurantController::class, 'cancelCheck'])->name('restaurant.checks.cancel');
// 6d — bill and settle
Route::get('/restaurant/checks/{order}/bill', [CheckBillingController::class, 'bill'])->name('restaurant.checks.bill');
Route::post('/restaurant/checks/{order}/print-bill', [CheckBillingController::class, 'printBill'])->name('restaurant.checks.print');
Route::post('/restaurant/checks/{order}/payments', [CheckBillingController::class, 'storePayment'])->name('restaurant.checks.payments.store');
Route::patch('/restaurant/checks/{order}/service-charge', [CheckBillingController::class, 'serviceCharge'])->name('restaurant.checks.service-charge');
Route::put('/restaurant/settings', [RestaurantController::class, 'updateSettings'])->name('restaurant.settings.update');
Route::post('/restaurant/tables/{table}/clean', [RestaurantController::class, 'markTableClean'])->name('restaurant.tables.clean');
// 6e — split, merge and void
Route::post('/restaurant/checks/{order}/items/{item}/void', [CheckChangesController::class, 'voidItem'])->name('restaurant.checks.items.void');
Route::post('/restaurant/checks/{order}/void', [CheckChangesController::class, 'voidCheck'])->name('restaurant.checks.void');
Route::post('/restaurant/checks/{order}/split', [CheckChangesController::class, 'split'])->name('restaurant.checks.split');
Route::post('/restaurant/checks/{order}/merge', [CheckChangesController::class, 'merge'])->name('restaurant.checks.merge');
// 6c — modifiers
Route::get('/restaurant/modifiers', [ModifierController::class, 'index'])->name('restaurant.modifiers.index');
Route::post('/restaurant/modifiers', [ModifierController::class, 'store'])->name('restaurant.modifiers.store');
Route::put('/restaurant/modifiers/{group}', [ModifierController::class, 'update'])->name('restaurant.modifiers.update');
Route::delete('/restaurant/modifiers/{group}', [ModifierController::class, 'destroy'])->name('restaurant.modifiers.destroy');

// Kitchen display
Route::get('/kds', [KdsController::class, 'index'])->name('kds.index');
Route::get('/kds/{station}', [KdsController::class, 'station'])->name('kds.station');
Route::post('/kds/tickets/{ticket}/advance', [KdsController::class, 'advance'])->name('kds.tickets.advance');
