<?php

return [
    // Unpaid checkout holds inventory only for a bounded period. Provider attempts that are still
    // processing are excluded from expiry until reconciliation confirms their final state.
    'reservation_minutes' => (int) env('COMMERCE_RESERVATION_MINUTES', 15),
];
