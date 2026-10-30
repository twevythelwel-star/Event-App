<?php

use App\Http\Controllers\CheckoutController;
use Illuminate\Support\Facades\Route;

/*
| Customer ordering flow (Blade): tickets → food → checkout → digital ticket.
| Signed-in, active accounts only; the controller checks event state and order ownership.
*/
Route::middleware(['auth', 'active'])->group(function () {
    Route::prefix('events/{event}/order')->name('checkout.')->group(function () {
        Route::get('/tickets', [CheckoutController::class, 'tickets'])->name('tickets');
        Route::post('/tickets', [CheckoutController::class, 'saveTickets'])->name('tickets.save');
        Route::get('/food', [CheckoutController::class, 'food'])->name('food');
        Route::post('/food', [CheckoutController::class, 'saveFood'])->name('food.save');
        Route::get('/checkout', [CheckoutController::class, 'review'])->name('review');
        Route::post('/checkout', [CheckoutController::class, 'place'])->name('place')->middleware('throttle:15,1');
    });

    Route::get('/tickets/{order}', [CheckoutController::class, 'ticket'])->name('tickets.show');
    Route::post('/tickets/{order}/payment-reference', [CheckoutController::class, 'submitReference'])
        ->name('tickets.payment-reference')->middleware('throttle:10,1');
});
