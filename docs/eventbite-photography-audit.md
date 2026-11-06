# EventBite photography audit

Audit date: 5 November 2026. The production frontend is the Laravel Blade application (`backend/`). The React app in `/src` is a separate client that Laravel does not serve; it is noted where relevant but was not changed. Findings come from reading the code, running the app, the database on this machine, and `php artisan images:audit`.

## 1. What photography exists

| Source | What | Count | Real or illustrative |
|---|---|---|---|
| Host uploads | Flyers, gallery photos, menu photos on private storage | 1 menu photo in the local database (no flyers or gallery photos uploaded yet) | Real: the host's own |
| Bundled sample food photos (`public/images/*.jpg`) | Jerk chicken, escovitch fish, oxtail, bammy, curry goat, plantains, wings, fried chicken, rum punch, smoothie | 10 | Illustrative samples, 1200 × 896, 650 KB–1 MB each |
| Bundled event photos (`island_*.jpg`) | Carnival, festival sunset, reggae concert, sports | 4 | Stock: not of any EventBite event |
| React client photos (repository root `public/images/`) | `cuisine_*`, `dish_jm_*`, `event_*` | 13 | Unknown source and licence; not available to the Blade app |

There is **no licensed photography** for African, Latin American, Asian, American, Mediterranean, vegetarian/vegan or dessert food, and **no real event photography** beyond what hosts upload. This audit does not fill those gaps with generic or generated photos: every gap shows a clearly labelled fallback (section 4), and the catalogue records it as a gap.

## 2. What was wrong before

| # | Finding | Impact |
|---|---|---|
| 1 | Sample photos were attached to the wrong food: `bammy.jpg` on 12 items including coconut water, ginger beer, shrimp and conch soup; `escovitch_fish.jpg` on lobster, conch, kingfish and other fish; `fried_chicken.jpg` on chicken tenders | Guests saw a picture of a different food: 23 items in the local data |
| 2 | Seed data (uncommitted, from another tool) points four dishes at `dish_jm_*.jpg` files that do not exist in the Blade app, including a jerk pork dish | Broken images; one existing test fails on the jerk pork photo |
| 3 | An event card could show a bundled dish photo as the event's cover (the component test even expected it) | A food photo presented as the event |
| 4 | The website's flyer upload stored the raw file (no re-encoding, metadata kept, no sizes, no dimensions), unlike the API | Privacy (EXIF/GPS) and performance |
| 5 | Responsive variants were half built: 6 failing upload tests, crops read as pixels instead of percent, gallery photos not public | Wrong crops, no gallery for guests |
| 6 | The event page's "Photos" strip labelled every photo, including hosts' real ones, "Illustrative images"; clicking a photo only showed a toast | Mislabelled photos; no way to view a photo |
| 7 | Sample photos were sent at full size (≈1 MB) on every menu card | Slow menus on phones |
| 8 | The no-flyer cover did not say it was artwork, and missing food photos used one generic "Photo coming soon" tile | Fallbacks not identified or specific |
| 9 | Homepage hero used drifting orbs, a spinning ring, shimmering text, a looping sheen and tilted 3D cards; image shapes varied (3:2, 16:10, 16:9, 16:7, ≈1.4:1) | Decoration competing with photography; inconsistent cards |

## 3. What was changed

| Finding | Change |
|---|---|
| 1, 2 | `ImageSubjects`: a sample photo appears only on a menu item whose name contains one of the dishes it is catalogued to show (`sample_photos.*.matches`); uncatalogued files never appear. The 23 mismatches and 4 uncatalogued references now show the labelled fallback. The data itself was left as it is. |
| 3 | Bundled photos are never an event's cover; the host's flyer, or artwork made from the event's own details, is. The component test was updated to expect this. |
| 4, 5 | One upload path for all routes: checked, re-encoded, original kept, small/medium/large in WebP and AVIF, percent crops, focal points, alt text, moderation. Gallery images are public for published events. |
| 6 | Gallery: the host's photos first, flyer, then matching dish photos; only samples carry "Illustrative photo"; an accessible dialog viewer. |
| 7 | `images:optimize-samples` writes 400/800/1200 px WebP and AVIF copies (a 1 MB sample becomes 24–145 KB); cards use them through `srcset`. |
| 8 | Food fallback by kind of food ("**Seafood** · No photo yet" with a fish icon, "**Drinks**" with a cup, …). Generated event covers name the category and read "Event artwork · no flyer uploaded yet". |
| 9 | One ratio per kind of image (tokens), a still hero led by one straight featured photo, no looping decoration. |

## 4. Image-to-content verification (local data)

`php artisan images:audit` on this machine, after the changes:

| What guests see | References |
|---|---|
| Matching sample photo, labelled "Illustrative photo" | 31 |
| A photo of another dish: replaced by the labelled fallback | 23 |
| Not catalogued: replaced by the labelled fallback | 4 |
| Host uploads | 1 menu photo (variants added with `images:make-variants`; original unchanged, same SHA-1) |

Every remaining photo on a menu item is either the host's own or a sample of that exact dish; every event is shown by its own flyer or its own generated artwork.

## 5. Placeholders are not photography

The following are fallbacks, not completed photography, and are reported as such: generated event artwork; the food fallback tiles; cuisine artwork on "Browse by tradition" cards; the event-type artwork in homepage section headers. None is presented as a photograph of a real event, venue, vendor, customer or partnership.

## 6. Open items

- **Licensed food photography** for the cuisines listed in section 1, if wanted: add approved, licensed photos to `sample_photos` with their `matches` and licence (see the catalogue). Until then those dishes show the host's photo or the labelled fallback.
- **Seed data from the other tool** (`us_cultural_events.json`, `CulturalCatalogSeeder.php`, uncommitted): points dishes and countries at files that only exist in the React client, and gives "Jerk Pork Plate" a photo, which `InclusiveIdentityTest` forbids. The Blade app no longer shows any of them; the seed itself should be corrected before it is committed.
- **React client** (`src/lib/imageResolver.ts`, uncommitted): falls back to bundled stock event photos for events without a flyer. It labels them as category images, but it is a separate client and was not changed here.
- **Existing mismatched data** (section 4) is left in the database; the guard makes it harmless. Clearing those `image_url` values is a data clean-up you may want to approve separately.
