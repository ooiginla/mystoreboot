<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Storefront\Http\Controllers\StorefrontController;
use Modules\Storefront\Http\Middleware\ShowOnlineStoreMaintenancePage;

$routes = function (): void {
    Route::middleware(ShowOnlineStoreMaintenancePage::class)->group(function (): void {
        Route::get('/', [StorefrontController::class, 'home'])->name('home');
        Route::get('/categories/{categorySlug}', [StorefrontController::class, 'category'])->name('categories.show');
        Route::get('/collections/{collectionSlug}', [StorefrontController::class, 'collection'])->name('collections.show');
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
        Route::get('/track', [StorefrontController::class, 'track'])->name('track');
    });
    Route::post('/contact', [StorefrontController::class, 'submitContact'])->name('contact.submit');
    Route::post('/checkout/customer-lookup', [StorefrontController::class, 'lookupCustomer'])
        ->middleware('throttle:30,1')
        ->name('checkout.customer-lookup');
    Route::post('/personalization/photo', [StorefrontController::class, 'uploadPersonalizationPhoto'])
        ->middleware('throttle:20,1')
        ->name('personalization.photo');
    Route::post('/checkout', [StorefrontController::class, 'checkout'])->name('checkout');
    Route::post('/checkout/{order}/paystack/initialize', [StorefrontController::class, 'initializePaystackPayment'])->name('checkout.paystack.initialize');
    Route::get('/checkout/{order}/paystack/verify', [StorefrontController::class, 'verifyPaystackPayment'])->name('checkout.paystack.verify');
    Route::get('/paystack/callback', [StorefrontController::class, 'verifyPaystackStoreCallback'])->name('paystack.callback');
    Route::get('/checkout/{order}/paystack/callback', [StorefrontController::class, 'verifyPaystackPayment'])->name('checkout.paystack.callback');
};

$baseDomain = trim((string) config('storefront.base_domain')) ?: 'storeboot.test';
$reservedSubdomains = collect((array) config('storefront.reserved_subdomains', []))
    ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
    ->map(fn (string $value): string => preg_quote($value, '/'))
    ->implode('|');
$storeSubdomainPattern = ($reservedSubdomains !== '' ? '(?!(?:'.$reservedSubdomains.')\.)' : '')
    .'[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?';

Route::domain('{store:subdomain}.'.$baseDomain)
    // Reserved platform hosts such as www belong to the main application and
    // must not be resolved as tenant stores by the wildcard domain route.
    ->where(['store' => $storeSubdomainPattern])
    ->name('subdomain.')
    ->group($routes);

Route::prefix('store/{store:username}')
    ->name('store.')
    ->group($routes);
