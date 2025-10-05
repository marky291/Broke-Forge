<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Billing Routes
|--------------------------------------------------------------------------
|
| Routes for managing subscriptions, payment methods, and billing.
|
*/

Route::middleware(['auth', 'verified'])->prefix('billing')->name('billing.')->group(function () {
    // Billing dashboard
    Route::get('/', [BillingController::class, 'index'])->name('index');
    Route::get('/invoices/{invoiceId}', [BillingController::class, 'downloadInvoice'])->name('invoices.download');

    // Stripe Customer Portal
    Route::get('/portal', [BillingController::class, 'portal'])->name('portal');

    // Subscription management
    Route::post('/subscriptions', [SubscriptionController::class, 'store'])->name('subscriptions.store');
    Route::put('/subscriptions', [SubscriptionController::class, 'update'])->name('subscriptions.update');
    Route::delete('/subscriptions', [SubscriptionController::class, 'destroy'])->name('subscriptions.destroy');
    Route::post('/subscriptions/resume', [SubscriptionController::class, 'resume'])->name('subscriptions.resume');

    // Payment methods
    Route::post('/payment-methods', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
    Route::post('/payment-methods/{paymentMethod}/default', [PaymentMethodController::class, 'setDefault'])->name('payment-methods.default');
    Route::delete('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy'])->name('payment-methods.destroy');
});
