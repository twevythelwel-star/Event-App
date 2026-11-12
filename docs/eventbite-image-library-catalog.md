# EventBite image library catalogue

Every image the Blade application ships with or stores, what it shows, where it may appear and where it came from. The machine-readable source is `backend/config/visual_assets.php`; pages look images up through `App\Support\VisualAssets` (artwork) and `App\Support\ImageSubjects` / `App\Support\ResponsiveImage` (photography). How uploads are stored and served is in `eventbite-image-management.md`; the photography review is in `eventbite-photography-audit.md`.

All paths below are under `backend/public/` unless they start with `tenants/` (private storage).

## 1. Brand

| Asset | Files | Use |
|---|---|---|
| Logo lockup (from the official `Eventbite fork.jpg`, background removed) | `images/brand/eventbite-logo-{360,600,1200}.{png,webp}` | `<x-brand-logo>` wordmark, compact and email variants |
| Fork-pin mark | `images/brand/eventbite-mark-{128,256,512}.png` | `<x-brand-logo variant="icon">` |
| App icons on #07111F | `icons/eventbite-app-{16,32,48,64,96,128,180,192,512}.png`, `icons/eventbite-maskable-512.png` | Favicons, home-screen icons, web manifest |

## 2. Food photography

### 2.1 Host photographs (the real thing)

Uploaded per menu item in the menu studio or through the API. Stored privately as `tenants/{organizer}/menu/{uuid}.{jpg|png|webp}` (sanitized original) plus `tenants/{organizer}/menu-derivatives/{uuid}-{small|medium|large}.{webp|avif}`. Each has alt text, a focal point and a moderation status. Shown only on the item it was uploaded for.

### 2.2 Bundled sample photographs (illustrative)

Catalogued in `sample_photos`, organized by cuisine. Every one is labelled **"Illustrative photo"** wherever it appears and is shown **only** on a menu item whose name contains one of its `matches`. Optimized copies (small/medium/large in WebP and AVIF) are in `images/optimized/`, made by `php artisan images:optimize-samples`.

| Cuisine | File | Shows | May stand for (`matches`) |
|---|---|---|---|
| Jamaican | `images/jerk_chicken.jpg` | Jerk chicken | jerk chicken |
| Jamaican | `images/escovitch_fish.jpg` | Escovitch fish | escovitch fish, escovitch snapper, fried escovitch |
| Jamaican | `images/oxtail.jpg` | Oxtail | oxtail |
| Jamaican | `images/bammy.jpg` | Bammy | bammy |
| Jamaican | `images/caribbean_curry_goat.jpg` | Curry goat | curry goat, curried goat |
| Caribbean | `images/fried_plantains_sides.jpg` | Fried plantains | fried plantain(s), fried sweet plantains, sweet plantains |
| Caribbean | `images/crispy_island_wings.jpg` | Chicken wings | wings, chicken wings |
| Caribbean | `images/fried_chicken.jpg` | Fried chicken | fried chicken |
| Caribbean | `images/rum_punch.jpg` | Rum punch | rum punch, fruit punch |
| Caribbean | `images/tropical_island_smoothie.jpg` | Fruit smoothie | smoothie |

Cuisines with no sample photograph yet: African, Latin American, Asian, American, Mediterranean, vegetarian/vegan, desserts. Items from those cuisines show the host's own photo or the labelled fallback below. To add samples, see section 6.

### 2.3 Food fallback (no true photo)

`ImageSubjects::menuFallback()`: an icon and label for the kind of food, e.g. "**Seafood** · No photo yet" (fish), "**Drinks** · No photo yet" (cup), "**Desserts** · No photo yet" (cake), "**Vegan** · No photo yet" (leaf), "**Rice and Grains**" (wheat), "**Meal package**" (package), anything else (utensils).

### 2.4 Cuisine artwork (illustration, not photography)

`images/library/cuisines/` — caribbean, african, latin-american, asian, american, mediterranean, plant-based, desserts-drinks (800 × 600 SVG). Used for "Browse by tradition" cards when no event in that tradition has a real flyer, and never as a menu item's picture. Tradition mapping in `tradition_cuisines`; traditions the artwork would misrepresent are left unmapped.

## 3. Event photography

### 3.1 Host flyers and gallery photographs (the real thing)

| Kind | Original | Variants | Metadata |
|---|---|---|---|
| Flyer | `tenants/{organizer}/flyers/{uuid}.*` | `tenants/{organizer}/flyers-derivatives/{uuid}-{size}.{webp,avif}` | alt text, focal point, size, dimensions, moderation status |
| Gallery photo (up to 12) | `tenants/{organizer}/event-media/{uuid}.*` | `tenants/{organizer}/event-media-derivatives/…` | alt text (required), focal point, uploader, moderation status, reason |

An event's uploaded flyer is always its cover; nothing replaces it except an administrator's visible, reasoned moderation.

### 3.2 Bundled event photographs (not used as covers)

| File | Shows | Status |
|---|---|---|
| `images/island_culture_carnival.jpg` | Carnival scene | Not shown: stock photography never stands in for an event |
| `images/island_festival_sunset.jpg` | Festival at sunset | Not shown, same reason |
| `images/island_music_reggae_concert.jpg` | Reggae concert | Not shown, same reason |
| `images/island_sports_championship.jpg` | Sports event | Not shown, same reason |

They are kept because older records and the React client may still reference them; the Blade application ignores them for event covers (`ImageSubjects::eventMayUse`).

### 3.3 Event fallback (no flyer)

`App\Support\GeneratedFlyer` draws artwork from the event's own title, date, place, category and traditions, marked "Event artwork · no flyer uploaded yet", with alt text saying it is artwork.

## 4. Event artwork (illustration)

`images/library/heroes/` (1600 × 900 SVG): festival, concert, food-festival, party, community, fish-fry, sound-system, cultural-celebration, family-day, live-performance, private-celebration, food-and-music. Used as homepage section art via `VisualAssets::heroFor($category)`; never as an event's own cover.

## 5. Interface artwork

| Group | Files (`images/library/`) | Use |
|---|---|---|
| Illustrations (600 × 450) | `illustrations/`: ticket, event, payment, food-order, verification, discover, qr-scan, fulfillment, analytics, staff, notifications, success, refund | Ticket page states, homepage sign-up card, dashboards |
| Empty states (320 × 220) | `empty/`: no-events, no-tickets, no-orders, no-notifications | `<x-empty-state illustration="…">` |
| Backgrounds | `backgrounds/`: aurora, ticket-grid | Decorative surfaces |

## 6. Adding images

- **A sample food photograph:** add a licensed photo you have approved to `public/images/`, list it under its cuisine in `sample_photos` with `dish`, `matches` (every dish name it truthfully shows) and its source and licence, then run `php artisan images:optimize-samples`. It is labelled "Illustrative photo" automatically and appears only on matching dishes.
- **Artwork:** see `VISUAL_ASSET_LIBRARY.md`.
- **Event photography:** comes only from hosts, through the event form and the gallery manager.

## 7. Files present but not part of the Blade library

The React client's `public/images/` (repository root) holds `cuisine_*.jpg`, `dish_jm_*.jpg` and `event_*.jpg` files whose source and licence are not recorded. They are not in the catalogue, so the Blade application never shows them, even where seed data points at them.

## 8. Coverage and asset gaps (11 November 2026)

The runtime library currently contains **10 bundled food sample photographs** listed above, **4 bundled event photographs** that the Blade app deliberately does not use, plus original SVG artwork (not photography). There are no approved rows in the photo-library assets checked into this repository; that library is populated by administrators at runtime and cannot be counted from code alone.

No bundled images currently cover African, Latin American, Asian, American, Mediterranean, plant-based or dessert dishes. There are no bundled dish-by-dish images for the rest of those cuisines, no bundled vendor setups, named venues, host portraits, family/community portraits or past-event memories. Event flyers, gallery photos, menu photos, performer portraits and partner logos must come from the event's host. When an approved category photo exists at runtime, it may illustrate the explicitly selected event subject and is labelled; it does not claim to depict that event.

Source and licence provenance for the 10 bundled JPG food samples is not recorded in the manifest today. They are labelled illustrative and subject-matched, but should not be described as licensed until their rights are documented. The separate React client's 13 JPGs have unknown provenance and are excluded from the Blade catalogue.

## 9. Updated application placement

| Surface | Photography/artwork used | Accuracy rule |
|---|---|---|
| Home and discovery | Host flyer, approved photo of host-selected event kind, generated event artwork, or original SVG category art | Flyer first; category photo is labelled illustrative; never infer from title |
| Search results, recommendations, similar events | That event's own flyer, approved category photo, or its generated artwork | No unrelated event photo |
| Event details and gallery | Host gallery, flyer, host-uploaded performer portraits, accurate dish photos | Samples appear only on menu items whose configured match list includes the dish |
| Food ordering and menu | Host's item photo, matching bundled sample, or a labelled no-photo tile | A sample is labelled illustrative; other dishes do not borrow it |
| Host image management | Flyer previews, gallery manager, menu photo fields, schedule performer photos | Uploads remain event scoped |
| Attendee dashboard and tickets | Event cover plus the first host gallery image when one exists | The keepsake is explicitly captioned as from that event |
| Vendor and host profiles | Host/vendor data and uploads where a profile image exists | No bundled substitute photos are provided |
| Locations | Event's own location text; no bundled venue photography | No image implies a named location or partnership |

The four bundled `island_*.jpg` images are intentionally unavailable as actual event covers. Their source/licence provenance should be confirmed before any future use as category imagery.
