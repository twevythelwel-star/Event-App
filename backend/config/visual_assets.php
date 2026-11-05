<?php

/*
| The EventBite visual asset library: one manifest for every reusable graphic, with its alt text, where it is
| used and where it came from. Pages pick assets through App\Support\VisualAssets, never by hard-coded path.
|
| Rules (see docs/VISUAL_ASSET_LIBRARY.md):
| - Original artwork is drawn for EventBite in the brand palette and contains no people, faces, flags or
|   cultural symbols, so it fits any event without speaking for a culture.
| - Real event and food photography comes only from hosts. The bundled photos below are samples: they are always
|   labelled "Illustrative photo" (MenuItem::photoIsIllustrative) and never presented as a host's own food.
| - No generated or stock food photography is added to the library.
*/

return [
    // Category hero artwork (1600 × 900). Event categories map to these in VisualAssets::heroFor().
    'heroes' => [
        'festival' => ['file' => 'images/library/heroes/festival.svg', 'alt' => 'Festival artwork: bunting, lanterns and stage lights', 'source' => 'original'],
        'concert' => ['file' => 'images/library/heroes/concert.svg', 'alt' => 'Concert artwork: a lit stage with light beams and speakers', 'source' => 'original'],
        'food-festival' => ['file' => 'images/library/heroes/food-festival.svg', 'alt' => 'Food festival artwork: market stalls, string lights and steam', 'source' => 'original'],
        'party' => ['file' => 'images/library/heroes/party.svg', 'alt' => 'Party artwork: a mirror ball, neon arcs and confetti', 'source' => 'original'],
        'community' => ['file' => 'images/library/heroes/community.svg', 'alt' => 'Community artwork: long shared tables under a tree and string lights', 'source' => 'original'],
    ],

    // Which hero suits each event category (matched case-insensitively against words in the category).
    'category_heroes' => [
        'food' => 'food-festival', 'market' => 'food-festival', 'cookout' => 'food-festival', 'fish fry' => 'food-festival',
        'music' => 'concert', 'concert' => 'concert', 'nightlife' => 'party', 'party' => 'party', 'dance' => 'party',
        'festival' => 'festival', 'culture' => 'festival', 'carnival' => 'festival', 'celebration' => 'festival',
        'community' => 'community', 'family' => 'community', 'fundraiser' => 'community', 'workshop' => 'community', 'sports' => 'community',
    ],
    'default_hero' => 'festival',

    // Dimensional feature illustrations (600 × 450).
    'illustrations' => [
        'ticket' => ['file' => 'images/library/illustrations/ticket.svg', 'alt' => 'A dimensional admission ticket with a QR code', 'source' => 'original'],
        'event' => ['file' => 'images/library/illustrations/event.svg', 'alt' => 'A dimensional calendar page with a location pin', 'source' => 'original'],
        'payment' => ['file' => 'images/library/illustrations/payment.svg', 'alt' => 'A phone and a payment card with a confirmed check mark', 'source' => 'original'],
        'food-order' => ['file' => 'images/library/illustrations/food-order.svg', 'alt' => 'A takeaway box and a covered bowl ready for pickup', 'source' => 'original'],
        'verification' => ['file' => 'images/library/illustrations/verification.svg', 'alt' => 'A shield with a check mark inside a QR scanning frame', 'source' => 'original'],
    ],

    // Empty-state illustrations (320 × 220); decorative next to the empty state's own title and text.
    'empty' => [
        'events' => ['file' => 'images/library/empty/no-events.svg', 'source' => 'original'],
        'tickets' => ['file' => 'images/library/empty/no-tickets.svg', 'source' => 'original'],
        'orders' => ['file' => 'images/library/empty/no-orders.svg', 'source' => 'original'],
        'notifications' => ['file' => 'images/library/empty/no-notifications.svg', 'source' => 'original'],
    ],

    // Bundled sample photography, catalogued by cuisine. Samples only: always labelled illustrative.
    'sample_photos' => [
        'jamaican' => [
            ['file' => 'images/jerk_chicken.jpg', 'dish' => 'Jerk chicken'],
            ['file' => 'images/escovitch_fish.jpg', 'dish' => 'Escovitch fish'],
            ['file' => 'images/oxtail.jpg', 'dish' => 'Oxtail'],
            ['file' => 'images/bammy.jpg', 'dish' => 'Bammy'],
            ['file' => 'images/caribbean_curry_goat.jpg', 'dish' => 'Curry goat'],
        ],
        'caribbean' => [
            ['file' => 'images/fried_plantains_sides.jpg', 'dish' => 'Fried plantains'],
            ['file' => 'images/crispy_island_wings.jpg', 'dish' => 'Wings'],
            ['file' => 'images/fried_chicken.jpg', 'dish' => 'Fried chicken'],
            ['file' => 'images/rum_punch.jpg', 'dish' => 'Punch'],
            ['file' => 'images/tropical_island_smoothie.jpg', 'dish' => 'Fruit smoothie'],
        ],
    ],
];
