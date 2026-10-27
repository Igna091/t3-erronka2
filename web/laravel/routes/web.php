<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\BasketController;
use App\Http\Controllers\CaptchaController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\TwoStepController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/shop', [ShopController::class, 'index'])->name('shop.index');
Route::get('/shop/{medicine:slug}', [ShopController::class, 'show'])->name('shop.show');

Route::get('/basket', [BasketController::class, 'show'])->name('basket.show');
Route::post('/basket/{medicine:slug}', [BasketController::class, 'store'])->name('basket.store');
Route::patch('/basket/{medicine:slug}', [BasketController::class, 'update'])->name('basket.update');
Route::delete('/basket/{medicine:slug}', [BasketController::class, 'destroy'])->name('basket.destroy');

Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

Route::get('/orders/{order:reference}', [OrderController::class, 'show'])->name('orders.show');
Route::get('/orders/{order:reference}/pay', [PaymentController::class, 'show'])->name('payment.show');
Route::post('/orders/{order:reference}/pay', [PaymentController::class, 'store'])->middleware('throttle:payments')->name('payment.store');
Route::post('/orders/{order:reference}/confirm', [PaymentController::class, 'confirm'])->middleware('throttle:payments')->name('payment.confirm');
Route::post('/orders/{order:reference}/pay-at-counter', [PaymentController::class, 'counter'])->name('payment.counter');

// Accounts. Login, sign-up, the 2-step check, email confirmation and password reset are Laravel
// Fortify's routes (FortifyServiceProvider); these are the pages around them.
Route::get('/captcha', CaptchaController::class)->name('captcha');

Route::middleware(['auth', 'verified'])->prefix('account')->name('account.')->group(function () {
    Route::get('/', [AccountController::class, 'dashboard'])->name('dashboard');

    Route::get('/security', [AccountController::class, 'security'])->name('security');
    Route::get('/security/password', [AccountController::class, 'password'])->name('password');
    Route::delete('/sessions', [AccountController::class, 'endOtherSessions'])->name('sessions.end-others');
    Route::delete('/sessions/{session}', [AccountController::class, 'endSession'])->where('session', '[0-9a-f]{64}')->name('sessions.end');

    Route::get('/security/two-step', [TwoStepController::class, 'show'])->name('two-step');
    Route::post('/security/two-step', [TwoStepController::class, 'start'])->name('two-step.start');
    Route::post('/security/two-step/confirm', [TwoStepController::class, 'confirm'])->name('two-step.confirm');
    Route::post('/security/two-step/finish', [TwoStepController::class, 'finish'])->name('two-step.finish');
    Route::delete('/security/two-step/setup', [TwoStepController::class, 'cancel'])->name('two-step.cancel');
    Route::get('/security/two-step/turn-off', [TwoStepController::class, 'turnOff'])->middleware('password.confirm')->name('two-step.turn-off');
    Route::get('/security/recovery-codes', [TwoStepController::class, 'recoveryCodes'])->middleware('password.confirm')->name('recovery-codes');
    Route::post('/security/recovery-codes', [TwoStepController::class, 'regenerateRecoveryCodes'])->name('recovery-codes.regenerate');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile');
    Route::get('/profile/photo', [ProfileController::class, 'photo'])->name('photo');
    Route::post('/profile/photo', [ProfileController::class, 'storePhoto'])->name('photo.store');
    Route::delete('/profile/photo', [ProfileController::class, 'destroyPhoto'])->name('photo.destroy');
});

// Replaces Fortify's route of the same method and address, registered before this file: turning
// 2-step verification off takes a code as well as the password (TwoStepController::disable)
Route::delete('/user/two-factor-authentication', [TwoStepController::class, 'disable'])->middleware('auth')->name('two-factor.disable');
