<?php

use App\Http\Controllers\BasketController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ShopController;
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
