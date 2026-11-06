# EventBite visual asset library

EventBite has one reusable library of graphics, so every page shares one visual language instead of carrying its own one-off images.

- **The manifest:** every asset is listed in `backend/config/visual_assets.php` with its alt text, where it is used and where it came from.
- **The helper:** pages pick assets by purpose through `App\Support\VisualAssets`, never by file path.

## Brand foundation

### Logo

The official `Eventbite fork.jpg` (the fork-pin mark and the EventBite wordmark) is the source of truth. The app uses derivatives of it in `public/images/brand/`: `eventbite-logo-*` and `eventbite-mark-*`, with only the flat background removed. App icons are in `public/icons/eventbite-app-*`.

Always use `<x-brand-logo>`. Decorative artwork may echo the fork-pin shape but never replaces the logo.

### Palette

- **Navy canvas:** `#07111F` to `#152C45`.
- **Lime accent:** `#C7FF45`.
- **Purple highlights:** `#8B5CF6`, `#A78BFA` and `#C4B5FD`.
- **Supporting colours:** sky `#7DD3FC` / `#38BDF8`, amber `#FBBF24`, coral `#FCA5A5` and green `#22C55E`.

### Style

Every piece follows the same visual language:

- dimensional objects with a darker offset "side";
- a soft radial glow behind the subject and an ellipse shadow beneath it;
- gentle gradients;
- one or two four-point sparkles as accents.

Scenes sit on the navy canvas. Feature illustrations are transparent, so they sit on any surface.

## Where things live

The brief suggested `resources/images/eventbite/` and three new CSS files. EventBite already has a served asset pipeline, so the library extends it rather than starting a second system:

| Suggested | Where it is in EventBite | Why |
|---|---|---|
| `branding/` | `public/images/brand/`, `public/icons/` | Laravel serves `public/` directly. Blade pages use plain CSS and images with no build step, so `resources/images` would never reach the browser. |
| `events/`, `event-categories/` | `public/images/library/heroes/` | |
| `food/`, `cuisine-categories/` | `public/images/library/cuisines/` (artwork), `public/images/` (labelled sample photos) | |
| `illustrations/`, `3d-graphics/`, `tickets/`, `payments/`, `dashboard/` | `public/images/library/illustrations/` | One set of dimensional illustrations covers all of these. |
| `empty-states/` | `public/images/library/empty/` | |
| `backgrounds/` | `public/images/library/backgrounds/` | |
| `design-tokens.css` | `public/css/01-tokens.css` | The brand colours, with contrast notes. |
| `animations.css` | `public/css/05-navigation-motion.css` (shared keyframes and the reduced-motion rule) and `06-components.css` (component motion) | |
| `visual-effects.css` | `public/css/06-components.css` (aurora, sheen, glow, scrims) | |

## What the library contains

All images are in `backend/public/images/library/`.

| Group | Files | Size | Used by |
|---|---|---|---|
| Event heroes | `heroes/`: festival, concert, food-festival, party, community, fish-fry, sound-system, cultural-celebration, family-day, live-performance, private-celebration, food-and-music | 1600 × 900 | Homepage section art. `VisualAssets::heroFor($category)` maps event categories to them. |
| Cuisine artwork | `cuisines/`: caribbean, african, latin-american, asian, american, mediterranean, plant-based, desserts-drinks | 800 × 600 | "Browse by tradition" cards, through `VisualAssets::cuisineForTradition()`. |
| Feature illustrations | `illustrations/`: ticket, event, payment, food-order, verification, discover, qr-scan, fulfillment, analytics, staff, notifications, success, refund | 600 × 450 | The ticket page (payment while unpaid, refund once refunded) and the homepage sign-up card (ticket). The rest are ready for the dashboard pass. |
| Empty states | `empty/`: no-events, no-tickets, no-orders, no-notifications | 320 × 220 | `<x-empty-state illustration="…">`, the attendee wallet, and event search with no results. |
| Backgrounds | `backgrounds/`: aurora (1600 × 900), ticket-grid (240 × 240, tiles) | n/a | Decorative washes and patterns, through `VisualAssets::background()`. |
| Icons | `resources/views/components/icon.blade.php`, one 24 px line family | 24 × 24 | Navigation, steps, dietary tags and payment methods. |
| Generated event covers | `App\Support\GeneratedFlyer` | 1200 × 800 | Events without an uploaded flyer. |

### Event subjects

How the brief's event subjects map to the heroes:

| Subject | Hero |
|---|---|
| Jamaican fish fries | fish-fry |
| Caribbean festivals | festival |
| Reggae and dancehall events | sound-system |
| Cultural celebrations | cultural-celebration |
| Outdoor concerts | concert |
| Food festivals | food-festival |
| Community gatherings | community |
| Nightlife and entertainment | party |
| Family events | family-day |
| Live performances | live-performance |
| Private celebrations | private-celebration |
| Food and music experiences | food-and-music |

### Illustration subjects

How the brief's illustration subjects map to the illustrations:

| Subject | Illustration |
|---|---|
| Discovering events | discover |
| Buying tickets | ticket |
| Ticket verification | verification |
| QR scanning | qr-scan |
| Food customization | food-order |
| Secure payments | payment |
| Order fulfillment | fulfillment |
| Event management | event |
| Event analytics | analytics |
| Staff management | staff |
| Notifications | notifications |
| Empty states | `empty/` |
| Successful transactions | success |
| Refund processing | refund |

The subject names also work as aliases, for example `VisualAssets::illustration('buy-tickets')`.

### What every asset is

All artwork is original vector work drawn for EventBite:

- under 20 KB each;
- no embedded or external images;
- valid SVG with its own `role="img"` and description.

Where an asset sits next to text that already says what it shows (empty states, section tiles, tradition cards), it is placed with `alt=""` so screen readers don't hear it twice.

## Photography rules

- **Real event and food photos come only from hosts**, through the menu studio and event media uploads.
- **Bundled photos are samples.**
  - They are catalogued by cuisine under `sample_photos` in the manifest (Jamaican and Caribbean today).
  - Wherever they appear they are labelled "Illustrative photo".
  - `MenuItem::photoIsIllustrative()` treats any bundled `/images/` file as a sample, whatever flag the item carries.
- **No generated or stock food photography has been added.** The library cannot produce realistic photographs, and invented photos of food or events must never pass as real ones.
  - Cuisine collections are therefore illustrated artwork, shown as artwork.
  - To add realistic photography for a cuisine, add licensed photos you have approved to `public/images/` and list them in `sample_photos` with their source and licence. They are then labelled automatically.
- **Cuisine artwork shows a cuisine in general, never a particular dish.** It is never used as a menu item's picture.
  - A menu item without a photo keeps the plain "Photo coming soon" placeholder, so a burger illustration never stands in for someone's salad.
  - Traditions whose food the artwork would misrepresent are not mapped (see `tradition_cuisines`): South Asian, Indigenous & Native American, Pacific Islander, Brazilian & Portuguese, African American and European. They keep the abstract card art until fitting artwork is drawn.
- **Library artwork contains no people, faces, flags or cultural symbols,** so it suits any event without speaking for a culture. The country picker uses flags only where the host chose that country.

## Adding an asset

1. Draw it in the palette above, as SVG where possible. Keep it small, with no embedded raster images and no external references.
2. Put it in the right folder under `public/images/library/`.
3. Add it to `config/visual_assets.php` with alt text and `source` (`original`, or the licence for anything else).
4. Use it through `VisualAssets` or `<x-empty-state illustration="…">`.

`tests/Feature/VisualAssetLibraryTest.php` checks that every manifest entry exists, is valid SVG with no embedded or external images, and resolves through the helper.

## Motion

All animation is short and decorative, and switches off under the site-wide `prefers-reduced-motion` rule in `05-navigation-motion.css`. Nothing animates over a QR code that a scanner needs to read.
