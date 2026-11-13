<?php

/*
|--------------------------------------------------------------------------
| Event photography library
|--------------------------------------------------------------------------
|
| The kinds of event the library holds photographs of. A host picks one for their event ("What kind of event is
| this?"), and an approved photo of that kind is shown, labelled "Illustrative photo", only when the event has no
| flyer of its own. Each subject has a brief for producing its photographs (by a photographer, a stock library or a
| generator) that meets the standards below.
|
| Photos are added and approved in the admin console (Photo library). Nothing here is a photograph of a real
| EventBite event, venue, vendor, customer or partnership, and pages never say it is.
|
*/

$common = 'Documentary-style professional event photograph, natural available light, realistic skin texture, natural '
    . 'expressions and candid poses, anatomically correct hands and faces, varied ages, body types and Caribbean and '
    . 'American diversity, no duplicated people. No visible text, signs, banners, logos, brand names, watermarks or '
    . 'recognisable real venues or landmarks. 3:2 landscape, 50mm look, shallow depth of field.';

return [
    // Minimum size accepted for library photos (they are shown large: hero and cards).
    'min_width' => 1600,
    'min_height' => 1000,

    'groups' => [
        'music' => 'Music and entertainment',
        'food' => 'Food and cultural events',
        'community' => 'Community and family events',
        'private' => 'Private and premium events',
    ],

    // What a reviewer confirms before approving a photo (each must be ticked).
    'standards' => [
        'genuine' => 'Looks like a genuine professional photograph of this kind of event',
        'people' => 'Realistic people with natural expressions, anatomy and proportions; no duplicated people, distorted hands or artificial faces',
        'subject' => 'Shows the actual type of event it is filed under',
        'setting' => 'Believable lighting, venue, crowd and stage equipment for this kind of event',
        'no_text' => 'No fake event logos, promotional text, brand names or watermarks',
        'no_real_venue' => 'Does not depict, or suggest, a real named venue or real named event',
        'rights' => 'The licence allows commercial use, and consent or a model release covers any identifiable people',
    ],

    /*
    | The kind of event an event's category stands for, used only when the host has not said what kind of event it
    | is. Checked in this order against the words of the category ("Music and Food" is music first); a category
    | with none of these words (e.g. "Workshop", "Other") has no kind, so it keeps its generated artwork rather
    | than borrowing a photo of something else. The title is never used.
    */
    'category_subjects' => [
        'fish fry' => 'jamaican_fish_fry',
        'reggae' => 'reggae_concert',
        'dancehall' => 'dancehall_event',
        'fundraiser' => 'community_fundraiser',
        'sports' => 'sports_outdoor',
        'market' => 'street_food_market',
        'cookout' => 'outdoor_cookout',
        'picnic' => 'outdoor_picnic',
        'wedding' => 'wedding',
        'birthday' => 'birthday_celebration',
        'corporate' => 'corporate_event',
        'nightlife' => 'nightlife',
        'concert' => 'outdoor_concert',
        'music' => 'live_band',
        'caribbean' => 'caribbean_food_festival',
        'jamaican' => 'caribbean_food_festival',
        'food' => 'international_food_festival',
        'heritage' => 'cultural_heritage',
        'cultural' => 'cultural_celebration',
        'culture' => 'cultural_celebration',
        'family' => 'family_festival',
        'community' => 'community_gathering',
    ],

    'subjects' => [
        // Music and entertainment
        'reggae_concert' => ['group' => 'music', 'label' => 'Reggae concert',
            'brief' => "Outdoor evening reggae concert: a live band on a modest stage with warm stage lights, bass player and singer mid-performance, an audience of all ages swaying with arms raised, palm trees silhouetted against dusk. {$common}"],
        'dancehall_event' => ['group' => 'music', 'label' => 'Dancehall event',
            'brief' => "Energetic dancehall night: a selector at a sound system with stacked speaker boxes, dancers in a circle mid-move, coloured lights and haze, crowd cheering. {$common}"],
        'live_band' => ['group' => 'music', 'label' => 'Live band',
            'brief' => "Live band performing in a mid-size venue: drummer, guitarist and singer under warm spotlights, cables and amplifiers visible, audience close to the stage. {$common}"],
        'dj_set' => ['group' => 'music', 'label' => 'DJ set',
            'brief' => "DJ performing at decks with headphones on one ear, hands on the mixer, crowd dancing in front under moving lights. {$common}"],
        'music_festival' => ['group' => 'music', 'label' => 'Music festival',
            'brief' => "Daytime outdoor music festival: a large crowd on grass in front of a big stage with lighting rig and screens, people in summer clothes, flags without text. {$common}"],
        'outdoor_concert' => ['group' => 'music', 'label' => 'Outdoor concert',
            'brief' => "Open-air concert at golden hour in a park: people on blankets and standing near the stage, musicians performing, string lights. {$common}"],
        'nightlife' => ['group' => 'music', 'label' => 'Nightlife event',
            'brief' => "Upscale nightlife evening: friends laughing at a lounge bar with cocktails, soft neon accents without text, dance floor in the background. {$common}"],
        'rooftop_party' => ['group' => 'music', 'label' => 'Rooftop party',
            'brief' => "Rooftop party at sunset: friends dancing and talking on an open-air terrace with string lights and plants, a city skyline softly out of focus with no recognisable buildings. {$common}"],
        'arts_entertainment' => ['group' => 'music', 'label' => 'Art, dance and entertainment',
            'brief' => "Live dance performance on a small outdoor stage: dancers mid-move in colourful costumes, an audience watching from benches, painted backdrop without text. {$common}"],

        // Food and cultural events
        'jamaican_fish_fry' => ['group' => 'food', 'label' => 'Jamaican fish fry',
            'brief' => "Seaside Jamaican fish fry at dusk: whole fried snapper with escovitch pickle and festival dumplings on a cook's table, vendors frying fish in large pots, families eating at picnic tables near the beach. {$common}"],
        'caribbean_food_festival' => ['group' => 'food', 'label' => 'Caribbean food festival',
            'brief' => "Caribbean food festival: jerk chicken on oil-drum grills with smoke, curry goat and rice and peas being served, festival-goers queuing at vendor tents. {$common}"],
        'international_food_festival' => ['group' => 'food', 'label' => 'International food festival',
            'brief' => "International food festival: a row of vendor tents serving dishes from several cuisines, people sharing plates at communal tables. {$common}"],
        'cultural_celebration' => ['group' => 'food', 'label' => 'Cultural celebration',
            'brief' => "Cultural celebration: dancers in traditional costumes performing in an open square, drummers, smiling spectators. {$common}"],
        'outdoor_cookout' => ['group' => 'food', 'label' => 'Outdoor cookout',
            'brief' => "Backyard cookout: friends and family around a grill with skewers and corn, coolers and folding chairs, afternoon sun. {$common}"],
        'street_food_market' => ['group' => 'food', 'label' => 'Street food market',
            'brief' => "Evening street food market: stalls lit by string lights, cooks plating food, customers browsing and eating. {$common}"],
        'food_vendor_gathering' => ['group' => 'food', 'label' => 'Food vendor gathering',
            'brief' => "Food vendor gathering: several food trucks and tents in a lot, vendors handing food to customers, people eating standing. {$common}"],

        // Community and family events
        'family_festival' => ['group' => 'community', 'label' => 'Family festival',
            'brief' => "Daytime family festival: parents and children at activity stations, face painting, bubbles, grassy park. {$common}"],
        'community_gathering' => ['group' => 'community', 'label' => 'Community gathering',
            'brief' => "Neighbourhood community gathering: people of all ages chatting around tables under shade tents, food being shared. {$common}"],
        'outdoor_picnic' => ['group' => 'community', 'label' => 'Outdoor picnic',
            'brief' => "Family picnic in a park: blankets, baskets and fruit, children playing nearby, relaxed adults, dappled sunlight. No stage, no band, no DJ. {$common}"],
        'cultural_heritage' => ['group' => 'community', 'label' => 'Cultural heritage event',
            'brief' => "Cultural heritage day: elders showing traditional crafts to young people, handmade goods on tables, storytelling. {$common}"],
        'community_fundraiser' => ['group' => 'community', 'label' => 'Community fundraising event',
            'brief' => "Community fundraiser: volunteers serving food at a bake-and-food table, families donating, cheerful outdoor setting. No visible charity names or logos. {$common}"],
        'family_entertainment_day' => ['group' => 'community', 'label' => 'Family entertainment day',
            'brief' => "Family entertainment day: children watching a magician or entertainer, parents laughing, games and bouncy castle in the background. {$common}"],
        'sports_outdoor' => ['group' => 'community', 'label' => 'Sports and outdoor activities',
            'brief' => "Community sports day in a park: a friendly football match on grass, families cheering from the sideline with coolers and folding chairs, bright afternoon light, plain kits without logos or numbers. {$common}"],

        // Private and premium events
        'wedding' => ['group' => 'private', 'label' => 'Wedding',
            'brief' => "Wedding reception: couple's first dance under string lights, guests smiling at round tables with floral centrepieces. {$common}"],
        'birthday_celebration' => ['group' => 'private', 'label' => 'Birthday celebration',
            'brief' => "Birthday celebration: friends gathered around a cake with candles, laughter, balloons without text. {$common}"],
        'formal_dinner' => ['group' => 'private', 'label' => 'Formal dinner',
            'brief' => "Formal dinner: long table with white linen, candles and plated courses, guests in evening wear in conversation. {$common}"],
        'private_party' => ['group' => 'private', 'label' => 'Private party',
            'brief' => "Private house party: friends dancing and talking in a stylish living room or terrace, evening lights. {$common}"],
        'vip_experience' => ['group' => 'private', 'label' => 'VIP entertainment experience',
            'brief' => "VIP lounge at a concert: guests on a raised terrace with table service overlooking a stage and crowd. {$common}"],
        'corporate_event' => ['group' => 'private', 'label' => 'Corporate event',
            'brief' => "Corporate evening event: colleagues networking with drinks in a modern event space, speaker at a lectern in the background. No company names or logos. {$common}"],
    ],
];
