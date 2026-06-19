<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Storefront\Http\Controllers\StorefrontController;

Route::prefix('store/{store:username}')
    ->name('store.')
    ->group(function (): void {
        Route::get('/', [StorefrontController::class, 'home'])->name('home');
        Route::get('/products/{productSlug}', [StorefrontController::class, 'product'])->name('products.show');
        Route::get('/services', [StorefrontController::class, 'services'])->name('services');
        Route::get('/services/{serviceSlug}', [StorefrontController::class, 'service'])->name('services.show');
        Route::get('/about', [StorefrontController::class, 'page'])->defaults('page', 'about_us')->name('about');
        Route::get('/faq', [StorefrontController::class, 'faq'])->name('faq');
        Route::get('/terms-of-service', [StorefrontController::class, 'page'])->defaults('page', 'terms_of_use')->name('terms');
        Route::get('/refunds', [StorefrontController::class, 'page'])->defaults('page', 'return_policy')->name('refunds');
        Route::get('/privacy-policy', [StorefrontController::class, 'page'])->defaults('page', 'privacy_policy')->name('privacy');
        Route::get('/shipping-info', [StorefrontController::class, 'page'])->defaults('page', 'shipping_information')->name('shipping');
        Route::get('/contact', [StorefrontController::class, 'contact'])->name('contact');
        Route::post('/contact', [StorefrontController::class, 'submitContact'])->name('contact.submit');
    });
