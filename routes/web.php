<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisteredTenantController;
use App\Http\Controllers\Admin\ActiveBranchController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\MarketingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MarketingController::class, 'home'])->name('home');

// Public marketing pages
Route::get('/about', [MarketingController::class, 'about'])->name('about');
Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'submit'])->name('contact.submit');
Route::get('/privacy', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/terms', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('/security', [LegalController::class, 'security'])->name('legal.security');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/register', [RegisteredTenantController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredTenantController::class, 'store'])->name('register.store');
    Route::get('/email/verify', [RegisteredTenantController::class, 'verificationNotice'])->name('verification.notice');
    Route::post('/email/verify', [RegisteredTenantController::class, 'verify'])
        ->middleware('throttle:6,1')
        ->name('verification.verify');
    Route::post('/email/verification-notification', [RegisteredTenantController::class, 'resendVerification'])
        ->middleware('throttle:3,1')
        ->name('verification.send');

    // Password reset
    Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::post('/admin/active-branch', [ActiveBranchController::class, 'update'])
    ->middleware('auth')
    ->name('admin.active-branch.update');

Route::get('/admin', [\App\Http\Controllers\Admin\AdminHomeController::class, 'index'])
    ->middleware('auth')
    ->name('admin.home');

Route::middleware('auth')->prefix('onboarding')->name('onboarding.')->group(function (): void {
    Route::get('/', [\App\Http\Controllers\Onboarding\OnboardingController::class, 'index'])->name('index');
    Route::get('/step/{step}', [\App\Http\Controllers\Onboarding\OnboardingController::class, 'show'])->whereNumber('step')->name('step');
    Route::post('/username-check', [\App\Http\Controllers\Onboarding\OnboardingController::class, 'checkUsername'])->name('username.check');
    Route::post('/address', [\App\Http\Controllers\Onboarding\OnboardingController::class, 'saveAddress'])->name('address');
    Route::post('/theme', [\App\Http\Controllers\Onboarding\OnboardingController::class, 'saveTheme'])->name('theme');
    Route::post('/bank', [\App\Http\Controllers\Onboarding\OnboardingController::class, 'saveBank'])->name('bank');
    Route::post('/product/photo', [\App\Http\Controllers\Onboarding\OnboardingController::class, 'productFromPhoto'])->name('product.photo');
    Route::post('/product', [\App\Http\Controllers\Onboarding\OnboardingController::class, 'saveProduct'])->name('product');
    Route::post('/complete', [\App\Http\Controllers\Onboarding\OnboardingController::class, 'complete'])->name('complete');
});
