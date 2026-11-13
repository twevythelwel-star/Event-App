<?php

use App\Http\Controllers\AppleWalletWebServiceController;
use App\Http\Controllers\SamsungWalletPartnerController;
use Illuminate\Support\Facades\Route;

/*
| Apple Wallet PassKit web service (each pass's webServiceURL = APP_URL/wallet). Called by iPhones and Apple Watches,
| not browsers: no session, cookies or CSRF. Calls about a pass authenticate with that pass's own token
| ("Authorization: ApplePass <token>"). See docs/eventbite-apple-wallet.md.
*/
Route::prefix('wallet/v1')->middleware('throttle:120,1')->group(function () {
    $device = '[A-Za-z0-9]{1,128}';
    $passType = '[A-Za-z0-9.\-]{1,128}';
    $serial = '[A-Za-z0-9\-]{1,64}';

    Route::post('/devices/{device}/registrations/{passTypeId}/{serial}', [AppleWalletWebServiceController::class, 'register'])
        ->where(['device' => $device, 'passTypeId' => $passType, 'serial' => $serial])->name('wallet.register');
    Route::delete('/devices/{device}/registrations/{passTypeId}/{serial}', [AppleWalletWebServiceController::class, 'unregister'])
        ->where(['device' => $device, 'passTypeId' => $passType, 'serial' => $serial])->name('wallet.unregister');
    Route::get('/devices/{device}/registrations/{passTypeId}', [AppleWalletWebServiceController::class, 'serials'])
        ->where(['device' => $device, 'passTypeId' => $passType])->name('wallet.serials');
    Route::get('/passes/{passTypeId}/{serial}', [AppleWalletWebServiceController::class, 'latest'])
        ->where(['passTypeId' => $passType, 'serial' => $serial])->name('wallet.pass');
    Route::post('/log', [AppleWalletWebServiceController::class, 'log'])->middleware('throttle:20,1')->name('wallet.log');
});

/*
| Samsung Wallet partner server (registered in the Samsung Wallet Partners Portal as APP_URL/wallet/samsung).
| Called by Samsung's servers with a signed Authorization token; 404 until Samsung Wallet is configured.
| See docs/eventbite-samsung-wallet.md.
*/
Route::prefix('wallet/samsung')->middleware('throttle:120,1')->group(function () {
    $card = '[A-Za-z0-9_\-]{1,64}';
    $ref = 'eb-[a-f0-9]{32}';
    Route::get('/cards/{cardId}/{refId}', [SamsungWalletPartnerController::class, 'cardData'])
        ->where(['cardId' => $card, 'refId' => $ref])->name('wallet.samsung.card');
    Route::post('/cards/{cardId}/{refId}', [SamsungWalletPartnerController::class, 'cardState'])
        ->where(['cardId' => $card, 'refId' => $ref])->name('wallet.samsung.state');
});
