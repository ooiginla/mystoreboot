<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Customers\Http\Controllers\CustomerRelationshipController;

Route::get('/', [CustomerRelationshipController::class, 'index'])->name('index');
Route::post('/customers', [CustomerRelationshipController::class, 'storeCustomer'])->name('customers.store');
Route::get('/customers/{customer}', [CustomerRelationshipController::class, 'showCustomer'])->name('customers.show');
Route::put('/customers/{customer}', [CustomerRelationshipController::class, 'updateCustomer'])->name('customers.update');
Route::post('/customers/{customer}/balance-adjustments', [CustomerRelationshipController::class, 'adjustBalance'])->name('customers.balance-adjustments.store');
Route::post('/groups', [CustomerRelationshipController::class, 'storeGroup'])->name('groups.store');
Route::put('/groups/{group}', [CustomerRelationshipController::class, 'updateGroup'])->name('groups.update');
Route::post('/price-lists', [CustomerRelationshipController::class, 'storePriceList'])->name('price-lists.store');
Route::get('/price-lists/{priceList}', [CustomerRelationshipController::class, 'showPriceList'])->name('price-lists.show');
Route::put('/price-lists/{priceList}', [CustomerRelationshipController::class, 'updatePriceList'])->name('price-lists.update');
Route::post('/price-list-items', [CustomerRelationshipController::class, 'storePriceListItem'])->name('price-list-items.store');
Route::put('/price-list-items/{item}', [CustomerRelationshipController::class, 'updatePriceListItem'])->name('price-list-items.update');
Route::delete('/price-list-items/{item}', [CustomerRelationshipController::class, 'destroyPriceListItem'])->name('price-list-items.destroy');
Route::post('/specific-prices', [CustomerRelationshipController::class, 'storeSpecificPrice'])->name('specific-prices.store');
Route::put('/specific-prices/{price}', [CustomerRelationshipController::class, 'updateSpecificPrice'])->name('specific-prices.update');
Route::delete('/specific-prices/{price}', [CustomerRelationshipController::class, 'destroySpecificPrice'])->name('specific-prices.destroy');
Route::post('/purchases', [CustomerRelationshipController::class, 'storePurchase'])->name('purchases.store');
Route::post('/follow-ups', [CustomerRelationshipController::class, 'storeFollowUp'])->name('follow-ups.store');
Route::post('/follow-ups/{followUp}/complete', [CustomerRelationshipController::class, 'completeFollowUp'])->name('follow-ups.complete');
Route::post('/tickets', [CustomerRelationshipController::class, 'storeTicket'])->name('tickets.store');
Route::put('/tickets/{ticket}', [CustomerRelationshipController::class, 'updateTicket'])->name('tickets.update');
Route::patch('/tickets/{ticket}/status', [CustomerRelationshipController::class, 'updateTicketStatus'])->name('tickets.status.update');
Route::post('/tickets/{ticket}/claim', [CustomerRelationshipController::class, 'claimTicket'])->name('tickets.claim');
Route::post('/tickets/{ticket}/responses', [CustomerRelationshipController::class, 'storeTicketResponse'])->name('tickets.responses.store');
