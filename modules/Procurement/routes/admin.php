<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Procurement\Http\Controllers\ProcurementController;

Route::get('/', [ProcurementController::class, 'index'])->name('index');
Route::post('/vendors', [ProcurementController::class, 'storeVendor'])->name('vendors.store');
Route::put('/vendors/{vendor}', [ProcurementController::class, 'updateVendor'])->name('vendors.update');
Route::post('/purchase-orders', [ProcurementController::class, 'storePurchaseOrder'])->name('purchase-orders.store');
Route::put('/purchase-orders/{purchaseOrder}', [ProcurementController::class, 'updatePurchaseOrder'])->name('purchase-orders.update');
Route::post('/purchase-orders/{purchaseOrder}/approve', [ProcurementController::class, 'approve'])->name('purchase-orders.approve');
Route::post('/purchase-orders/{purchaseOrder}/cancel', [ProcurementController::class, 'cancelPurchaseOrder'])->name('purchase-orders.cancel');
Route::post('/purchase-orders/{purchaseOrder}/receive', [ProcurementController::class, 'receive'])->name('purchase-orders.receive');
Route::post('/payments', [ProcurementController::class, 'storePayment'])->name('payments.store');
