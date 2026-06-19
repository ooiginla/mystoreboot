<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Customers\Http\Controllers\CustomerRelationshipController;

Route::get('/', [CustomerRelationshipController::class, 'index'])->name('index');
Route::post('/customers', [CustomerRelationshipController::class, 'storeCustomer'])->name('customers.store');
Route::put('/customers/{customer}', [CustomerRelationshipController::class, 'updateCustomer'])->name('customers.update');
Route::post('/groups', [CustomerRelationshipController::class, 'storeGroup'])->name('groups.store');
Route::post('/purchases', [CustomerRelationshipController::class, 'storePurchase'])->name('purchases.store');
Route::post('/follow-ups', [CustomerRelationshipController::class, 'storeFollowUp'])->name('follow-ups.store');
Route::post('/follow-ups/{followUp}/complete', [CustomerRelationshipController::class, 'completeFollowUp'])->name('follow-ups.complete');
Route::post('/tickets', [CustomerRelationshipController::class, 'storeTicket'])->name('tickets.store');
Route::put('/tickets/{ticket}', [CustomerRelationshipController::class, 'updateTicket'])->name('tickets.update');
Route::post('/tickets/{ticket}/responses', [CustomerRelationshipController::class, 'storeTicketResponse'])->name('tickets.responses.store');
