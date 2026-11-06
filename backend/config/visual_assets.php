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
        'fish-fry' => ['file' => 'images/library/heroes/fish-fry.svg', 'alt' => 'Fish fry artwork: a seaside grill under string lights, with smoke rising past palm trees and the moon', 'source' => 'original'],
        'sound-system' => ['file' => 'images/library/heroes/sound-system.svg', 'alt' => 'Sound system artwork: a wall of speakers sending sound waves into crossing light beams', 'source' => 'original'],
        'cultural-celebration' => ['file' => 'images/library/heroes/cultural-celebration.svg', 'alt' => 'Cultural celebration artwork: colourful garlands and lanterns over a plaza, with drums and ribbons', 'source' => 'original'],
        'family-day' => ['file' => 'images/library/heroes/family-day.svg', 'alt' => 'Family day artwork: a sunny park with a picnic blanket, balloons, a kite and a shade tree', 'source' => 'original'],
        'live-performance' => ['file' => 'images/library/heroes/live-performance.svg', 'alt' => 'Live performance artwork: a stage with drawn curtains, a microphone stand and a guitar in a spotlight', 'source' => 'original'],
        'private-celebration' => ['file' => 'images/library/heroes/private-celebration.svg', 'alt' => 'Private celebration artwork: a dressed table with a tiered cake, candles, glasses and balloons', 'source' => 'original'],
        'food-and-music' => ['file' => 'images/library/heroes/food-and-music.svg', 'alt' => 'Food and music artwork: a lit food truck beside a small stage with speakers and music notes', 'source' => 'original'],
    ],

    // Which hero suits each event category, matched case-insensitively against words in the category. The first
    // match wins, so the more specific phrases ("fish fry", "food and music") come before the general ones ("food").
    'category_heroes' => [
        'fish fry' => 'fish-fry', 'fish-fry' => 'fish-fry', 'seafood' => 'fish-fry',
        'food and music' => 'food-and-music', 'food & music' => 'food-and-music', 'food truck' => 'food-and-music',
        'reggae' => 'sound-system', 'dancehall' => 'sound-system', 'sound system' => 'sound-system', 'soca' => 'sound-system',
        'food' => 'food-festival', 'market' => 'food-festival', 'cookout' => 'food-festival', 'tasting' => 'food-festival',
        'live' => 'live-performance', 'performance' => 'live-performance', 'theatre' => 'live-performance', 'theater' => 'live-performance', 'comedy' => 'live-performance', 'open mic' => 'live-performance',
        'music' => 'concert', 'concert' => 'concert', 'nightlife' => 'party', 'party' => 'party', 'dance' => 'party', 'club' => 'party',
        'birthday' => 'private-celebration', 'wedding' => 'private-celebration', 'anniversary' => 'private-celebration', 'private' => 'private-celebration', 'reunion' => 'private-celebration',
        'culture' => 'cultural-celebration', 'cultural' => 'cultural-celebration', 'heritage' => 'cultural-celebration', 'celebration' => 'cultural-celebration',
        'festival' => 'festival', 'carnival' => 'festival',
        'family' => 'family-day', 'kids' => 'family-day', 'children' => 'family-day', 'picnic' => 'family-day',
        'community' => 'community', 'fundraiser' => 'community', 'workshop' => 'community', 'sports' => 'community',
    ],
    'default_hero' => 'festival',

    // Dimensional feature illustrations (600 × 450).
    'illustrations' => [
        'ticket' => ['file' => 'images/library/illustrations/ticket.svg', 'alt' => 'A dimensional admission ticket with a QR code', 'source' => 'original'],
        'event' => ['file' => 'images/library/illustrations/event.svg', 'alt' => 'A dimensional calendar page with a location pin', 'source' => 'original'],
        'payment' => ['file' => 'images/library/illustrations/payment.svg', 'alt' => 'A phone and a payment card with a confirmed check mark', 'source' => 'original'],
        'food-order' => ['file' => 'images/library/illustrations/food-order.svg', 'alt' => 'A takeaway box and a covered bowl ready for pickup', 'source' => 'original'],
        'verification' => ['file' => 'images/library/illustrations/verification.svg', 'alt' => 'A shield with a check mark inside a QR scanning frame', 'source' => 'original'],
        'discover' => ['file' => 'images/library/illustrations/discover.svg', 'alt' => 'A folded map with event pins under a magnifying glass', 'source' => 'original'],
        'qr-scan' => ['file' => 'images/library/illustrations/qr-scan.svg', 'alt' => 'A phone scanning a ticket QR code', 'source' => 'original'],
        'fulfillment' => ['file' => 'images/library/illustrations/fulfillment.svg', 'alt' => 'A packed food bag marked ready beside a service bell', 'source' => 'original'],
        'analytics' => ['file' => 'images/library/illustrations/analytics.svg', 'alt' => 'A dashboard panel with rising bars, a trend line and a summary card', 'source' => 'original'],
        'staff' => ['file' => 'images/library/illustrations/staff.svg', 'alt' => 'Three staff passes on lanyards for gate, food and payments roles', 'source' => 'original'],
        'notifications' => ['file' => 'images/library/illustrations/notifications.svg', 'alt' => 'A bell with an unread badge beside message cards', 'source' => 'original'],
        'success' => ['file' => 'images/library/illustrations/success.svg', 'alt' => 'A large confirmed check mark with a ticket and confetti', 'source' => 'original'],
        'refund' => ['file' => 'images/library/illustrations/refund.svg', 'alt' => 'A payment card with a return arrow and coins coming back', 'source' => 'original'],
    ],

    // The brief's illustration subjects that an existing illustration already covers.
    'illustration_aliases' => [
        'buy-tickets' => 'ticket', 'secure-payment' => 'payment', 'food-customization' => 'food-order',
        'event-management' => 'event', 'ticket-verification' => 'verification', 'order-fulfillment' => 'fulfillment',
        'refund-processing' => 'refund', 'staff-management' => 'staff', 'event-analytics' => 'analytics',
    ],

    // Cuisine artwork (800 × 600): illustrated dishes for a whole cuisine, used for browsing by tradition. They show
    // a cuisine in general, never a particular vendor's dish, so they are not used as a menu item's photo.
    'cuisines' => [
        'caribbean' => ['file' => 'images/library/cuisines/caribbean.svg', 'alt' => 'Caribbean food artwork: grilled chicken with rice and peas, fried plantain and a fruit drink', 'source' => 'original'],
        'african' => ['file' => 'images/library/cuisines/african.svg', 'alt' => 'African food artwork: a shared platter of spiced rice, stews, flatbread and grilled skewers', 'source' => 'original'],
        'latin-american' => ['file' => 'images/library/cuisines/latin-american.svg', 'alt' => 'Latin American food artwork: tacos with salsa and lime, grilled corn and guacamole', 'source' => 'original'],
        'asian' => ['file' => 'images/library/cuisines/asian.svg', 'alt' => 'Asian food artwork: a noodle bowl with chopsticks, dumplings in a steamer and tea', 'source' => 'original'],
        'american' => ['file' => 'images/library/cuisines/american.svg', 'alt' => 'American food artwork: a stacked burger, fries and lemonade', 'source' => 'original'],
        'mediterranean' => ['file' => 'images/library/cuisines/mediterranean.svg', 'alt' => 'Mediterranean food artwork: a mezze board with hummus, olives, tomatoes, flatbread and skewers', 'source' => 'original'],
        'plant-based' => ['file' => 'images/library/cuisines/plant-based.svg', 'alt' => 'Vegetarian and vegan food artwork: a grain bowl with greens, avocado, chickpeas and roasted vegetables', 'source' => 'original'],
        'desserts-drinks' => ['file' => 'images/library/cuisines/desserts-drinks.svg', 'alt' => 'Desserts and drinks artwork: a layered cake slice, an ice cream cone and an iced fruit drink', 'source' => 'original'],
    ],

    // Which cuisine artwork fits each tradition in config/traditions.php. Traditions whose food the artwork would
    // misrepresent are left out on purpose and keep the plain abstract card art.
    'tradition_cuisines' => [
        'caribbean' => 'caribbean', 'african' => 'african', 'latin_american' => 'latin-american',
        'east_southeast_asian' => 'asian', 'southern_us' => 'american', 'middle_eastern' => 'mediterranean',
    ],

    // Decorative backgrounds: an aurora wash (1600 × 900) and a tiling ticket pattern (240 × 240).
    'backgrounds' => [
        'aurora' => ['file' => 'images/library/backgrounds/aurora.svg', 'alt' => '', 'source' => 'original'],
        'ticket-grid' => ['file' => 'images/library/backgrounds/ticket-grid.svg', 'alt' => '', 'source' => 'original'],
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
