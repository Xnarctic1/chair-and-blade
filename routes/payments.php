<?php

use App\Http\Controllers\PaymentController;
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session'])->group(function (): void {
    Route::post('/account/appointments/{appointment}/checkout', [PaymentController::class, 'checkout'])
        ->middleware('throttle:10,1')->name('payments.checkout');
    Route::get('/account/appointments/{appointment}/payment', [PaymentController::class, 'returned'])
        ->middleware('throttle:20,1')->name('payments.return');
});

Route::post('/stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');
