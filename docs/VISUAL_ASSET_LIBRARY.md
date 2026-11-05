# EventBite visual asset library

One reusable library of graphics, so pages share a single visual language instead of each page having its own one-off images. Every asset is listed in `backend/config/visual_assets.php` with its alt text, where it is used and where it came from. Pages pick assets by purpose through `App\Support\VisualAssets`, never by file path.

## Brand foundation

- **Logo:** the official `Eventbite fork.jpg` (the fork-pin mark and the EventBite wordmark) is the source of truth. The app uses its wordmark (`public/images/brand/eventbite-wordmark.webp`). Decorative artwork may echo the fork-pin shape but never replaces the logo.
- **Palette:**
  - navy canvas: `#07111F` to `#152C45`;
  - lime accent: `#C7FF45`;
  - purple highlights: `#8B5CF6`, `#A78BFA` and `#C4B5FD`;
  - supporting colours: sky `#7DD3FC`, amber `#FBBF24` and coral `#FCA5A5`.
- **Style:** flat and dimensional shapes, soft radial light, gentle gradients, sparkles and confetti as accents.

## What the library contains

| Group | Files (`backend/public/images/library/`) | Size | Used by |
|---|---|---|---|
| Category heroes | `heroes/festival`, `concert`, `food-festival`, `party`, `community` (`.svg`) | 1600 × 900 | Homepage section artwork; `VisualAssets::heroFor($category)` maps event categories to them |
| Feature illustrations | `illustrations/ticket`, `event`, `payment`, `food-order`, `verification` (`.svg`) | 600 × 450 | Homepage sign-up card (ticket); a ticket awaiting payment (payment). The others are ready for the dashboard pass |
| Empty states | `empty/no-events`, `no-tickets`, `no-orders`, `no-notifications` (`.svg`) | 320 × 220 | `<x-empty-state illustration="…">`, the attendee wallet, event search with no results |
| Icons | `resources/views/components/icon.blade.php` (one 24 px line family) | 24 × 24 | All navigation, steps, dietary tags, payment methods |
| Generated event covers | `App\Support\GeneratedFlyer` | 1200 × 800 | Events without an uploaded flyer |
| Motion and backgrounds | `public/css/06-components.css` (aurora layers, drifting shapes, sheen, shimmer, scan line, status dots) | n/a | Homepage hero, cards, tickets |

All artwork is original vector work drawn for EventBite: 1–5 KB each, no embedded or external images, and valid SVG with its own `role="img"` and description. Where an asset sits next to text that already says what it shows (empty states, section tiles), it is placed with `alt=""` so screen readers do not hear it twice.

## Photography rules

- **Real event and food photos come only from hosts**, through the menu studio and event media uploads.
- **Bundled photos are samples.** They are catalogued by cuisine under `sample_photos` in the manifest (Jamaican and Caribbean today). Wherever they appear they are labelled "Illustrative photo"; `MenuItem::photoIsIllustrative()` treats any bundled `/images/` file as a sample, whatever flag the item carries.
- **No generated or stock food photography is added.** To grow a cuisine's collection, add licensed photos that you have approved to `public/images/`, list them in `sample_photos` with their source and licence, and they will be labelled automatically.
- **Library artwork contains no people, faces, flags or cultural symbols,** so it suits any event without speaking for a culture. The country picker uses flags only where the host chose that country.

## Adding an asset

1. Draw it in the palette above, as SVG where possible. Keep it small, with no embedded raster images and no external references.
2. Put it in the right folder under `public/images/library/`.
3. Add it to `config/visual_assets.php` with alt text and `source` (`original`, or the licence for anything else).
4. Use it through `VisualAssets` or `<x-empty-state illustration="…">`.

`tests/Feature/VisualAssetLibraryTest.php` checks that every manifest entry exists, is valid SVG with no embedded or external images, and resolves through the helper.

## Motion

All animation is short and decorative, and switches off under the site-wide `prefers-reduced-motion` rule in `05-navigation-motion.css`. Nothing animates over a QR code a scanner needs to read.
