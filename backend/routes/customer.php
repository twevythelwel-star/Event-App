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
        Route::post('/tickets', [CheckoutController::class, 'saveTickets'])->name('tickets.save')->middleware('throttle:60,1');
        Route::get('/food', [CheckoutController::class, 'food'])->name('food');
        Route::post('/food', [CheckoutController::class, 'saveFood'])->name('food.save')->middleware('throttle:60,1');
        Route::get('/checkout', [CheckoutController::class, 'review'])->name('review');
        Route::post('/checkout/code', [CheckoutController::class, 'applyCode'])->name('code.apply')->middleware('throttle:20,1');
        Route::delete('/checkout/code', [CheckoutController::class, 'removeCode'])->name('code.remove');
        Route::post('/waitlist', [CheckoutController::class, 'joinWaitlist'])->name('waitlist.join')->middleware('throttle:20,1');
        Route::delete('/waitlist/{entryId}', [CheckoutController::class, 'leaveWaitlist'])->name('waitlist.leave');
        Route::post('/checkout', [CheckoutController::class, 'place'])->name('place')->middleware('throttle:15,1');
    });

    Route::get('/my-tickets', [\App\Http\Controllers\TicketCollectionController::class, 'index'])->name('tickets.collection');
    Route::get('/tickets/{order}', [CheckoutController::class, 'ticket'])->name('tickets.show');
    Route::get('/tickets/{order}/wallet.pkpass', [\App\Http\Controllers\AppleWalletController::class, 'download'])->name('tickets.wallet')->middleware('throttle:20,1');
    Route::get('/tickets/{order}/calendar.ics', [\App\Http\Controllers\TicketCalendarController::class, 'show'])->name('tickets.calendar')->middleware('throttle:30,1');
    Route::post('/tickets/{order}/reissue', [CheckoutController::class, 'reissueTicket'])->name('tickets.reissue')->middleware('throttle:10,1');
    Route::post('/tickets/{order}/qr/retry', [CheckoutController::class, 'retryTicketQr'])->name('tickets.qr.retry')->middleware('throttle:10,1');
    Route::post('/tickets/{order}/guests/{attendeeTicket}/reissue', [CheckoutController::class, 'reissueGuest'])->name('tickets.guests.reissue')->middleware('throttle:10,1');
    Route::post('/tickets/{order}/revoke', [CheckoutController::class, 'revokeTicket'])->name('tickets.revoke')->middleware('throttle:10,1');
    Route::post('/tickets/{order}/transfer', [\App\Http\Controllers\TicketTransferController::class, 'start'])->name('tickets.transfer.start')->middleware('throttle:10,1');
    Route::post('/ticket-transfers/{transferId}/cancel', [\App\Http\Controllers\TicketTransferController::class, 'cancel'])->name('tickets.transfer.cancel');
    Route::get('/ticket-transfers/accept/{token}', [\App\Http\Controllers\TicketTransferController::class, 'show'])->name('tickets.transfer.accept');
    Route::post('/ticket-transfers/accept/{token}', [\App\Http\Controllers\TicketTransferController::class, 'accept'])->name('tickets.transfer.confirm')->middleware('throttle:10,1');
    Route::post('/tickets/{order}/payment-reference', [CheckoutController::class, 'submitReference'])
        ->name('tickets.payment-reference')->middleware('throttle:10,1');
});
