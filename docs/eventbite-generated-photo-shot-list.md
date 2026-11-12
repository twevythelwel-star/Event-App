# Generated photos: shot list and how to add them

Real photographs of EventBite's events, venues and vendors' dishes come from hosts. This page covers the other kind:
**generated photos** that illustrate *kinds* of events and *kinds* of dishes, for listings that have no photo of their
own yet. They are always shown with an "Illustrative photo" label and never as a photo of a particular event, venue,
vendor or dish.

You generate the images with a tool you are licensed to use commercially. The app records where each one came from
and needs a second system administrator to approve it before anyone sees it.

## What the app shows, in order

| Listing | 1st | 2nd | 3rd | 4th |
|---|---|---|---|---|
| Event cover | the host's flyer | the host's main photo | an approved library photo of the event's kind, labelled *Illustrative photo* | artwork drawn from the event's details |
| Venue | the host's venue photos | (none: no generated venue images, they would mislead) | | |
| Menu item | the host's photo of that dish | a catalogued sample of the same named dish, labelled *Illustrative photo* | a "No photo yet" placeholder for that kind of food | |

## 1. Event-kind photos (the photo library)

Generate **3 photos per kind** (so events of one kind don't all share a picture), landscape, at least
**1600 × 1000 px** (2400 × 1500 recommended), JPG/PNG/WebP under 5 MB. The full brief for each kind, with the common
quality clause (natural people and hands, no text, logos, brand names or recognisable real venues), is on the admin
**Photo Library** page and in `backend/config/photo_library.php`. Use that brief as the prompt.

| Group | Kinds (library key) |
|---|---|
| Music and entertainment | Reggae concert (`reggae_concert`) · Dancehall event (`dancehall_event`) · Live band (`live_band`) · DJ set (`dj_set`) · Music festival (`music_festival`) · Outdoor concert (`outdoor_concert`) · Nightlife event (`nightlife`) · **Rooftop party** (`rooftop_party`) · **Art, dance and entertainment** (`arts_entertainment`) |
| Food and cultural events | Jamaican fish fry · Caribbean food festival · International food festival · Cultural celebration · Outdoor cookout · Street food market · Food vendor gathering |
| Community and family events | Family festival · Community gathering · Outdoor picnic · Cultural heritage event · Community fundraising event · Family entertainment day · **Sports and outdoor activities** (`sports_outdoor`) |
| Private and premium events | Wedding · Birthday celebration · Formal dinner · Private party · VIP entertainment experience · Corporate event |

**Bold** kinds were added for this brief. That is 29 kinds, so 87 photos for full coverage. Start with the kinds your
events use: the homepage's "Browse by kind of event" cards and event covers appear as soon as one photo of a kind is
approved.

The brief's "people and experiences" (friends reconnecting, couples dancing, families, groups celebrating, guests at
food stalls) belong inside these event-kind photos: describe them in the scene rather than as separate images.

### Adding them

1. Sign in as a system administrator (or an administrator with the *moderate content* permission) and open
   **Admin console → Photo Library**.
2. Upload each photo with its kind, a title, and alt text describing what is actually in the picture.
3. Source: **Generated**. Source detail: the tool and model you used (e.g. "Made with <tool> <model>, prompt from the
   library brief"). Licence: the commercial terms you hold for that tool. Identifiable people: tick if faces are clear
   (no model release is needed for generated people).
4. Click the photo to set its focal point (what stays in view when it is cropped).
5. A **different** system administrator reviews it against the seven photography standards (realistic people and
   hands, shows the right kind of event, no text or logos, no real venue, rights) and approves or rejects it.

## 2. Dish photos (catalogued samples)

Hosts' own dish photos always win. Catalogued samples fill in only for a menu item whose name is that exact dish, and
are labelled *Illustrative photo*, so a jerk chicken picture never appears on sushi.

Catalogued today: Jamaican (jerk chicken, escovitch fish, oxtail, bammy, curry goat) and Caribbean (fried plantains,
chicken wings, fried chicken, rum punch, fruit smoothie). Their source and licence are **not recorded**: confirm them,
or replace them with generated ones.

Gaps from the brief, one photo each, square or 4:3, at least 1200 px, a single plated dish on a plain table, no
garnish or props that are not part of the dish:

| Cuisine | Dishes |
|---|---|
| Jamaican | Ackee and saltfish · Festival dumplings · Rice and peas · Brown stew chicken · Beef patty · Sorrel drink · Steamed fish |
| African | Jollof rice · Suya · Egusi soup with fufu · Puff-puff |
| Latin American | Tamales · Tacos al pastor · Empanadas · Arepas · Elote |
| Asian | Chicken fried rice · Pad thai · Pork dumplings · Sushi rolls · Chicken satay |
| American | Cheeseburger · BBQ ribs · Mac and cheese · Fried catfish |
| Desserts and drinks | Rum cake · Sweet potato pudding · Coconut drops · Fresh coconut water · Ginger beer |

### Adding them

Dish samples are files in the app, not uploads: put each image in `backend/public/images/` and add an entry under
its cuisine in `config/visual_assets.php` → `sample_photos` with `file`, `dish`, `matches` (every menu name it may
illustrate, e.g. `['jollof rice', 'jollof']`), `source` and `licence`. Then commit both. A dish photo is shown only on a
menu item whose name matches one of `matches`.

## What not to generate

- Photos meant to stand for a **specific** event, venue, host, performer or vendor. Those must be their own photos.
- Real places or landmarks, signage, logos, brand names, flags with text, or anything resembling a real person.
- "Past event" or "attendance" photos: generated crowds must never suggest an EventBite event happened.
