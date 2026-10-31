<?php

/*
|--------------------------------------------------------------------------
| Money and currencies
|--------------------------------------------------------------------------
|
| Every amount in the application is held as an integer number of minor units (cents for USD) together
| with an explicit ISO 4217 currency code, and is formatted only through App\Support\Money using the
| definitions below. Adding a market means adding its currency here; no symbols are hard-coded elsewhere.
|
| minor_unit         digits after the decimal point (2 for USD and JMD; 0 for currencies like JPY)
| symbol             what customers see
| symbol_position    'before' or 'after' the number
| decimal/thousands  separators for display
|
*/

return [

    'default_currency' => env('MONEY_DEFAULT_CURRENCY', 'USD'),

    'currencies' => [
        'USD' => [
            'name' => 'US Dollar',
            'minor_unit' => 2,
            'symbol' => '$',
            'symbol_position' => 'before',
            'decimal' => '.',
            'thousands' => ',',
        ],
        // Kept so orders placed before the move to USD still display and refund correctly.
        'JMD' => [
            'name' => 'Jamaican Dollar',
            'minor_unit' => 2,
            'symbol' => 'J$',
            'symbol_position' => 'before',
            'decimal' => '.',
            'thousands' => ',',
        ],
    ],

];
