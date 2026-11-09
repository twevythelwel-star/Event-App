# Image management

How EventBite stores, serves and shows images that hosts upload: event flyers, gallery photos and menu photos. For the original artwork the app ships with (heroes, cuisine art, illustrations), see `VISUAL_ASSET_LIBRARY.md`.

## One upload path

Every upload goes through `App\Services\MediaStorageService`, whichever way it arrives:
- the website's event form;
- the flyer, gallery and menu studio pages;
- the API.

The service:

1. **Checks** the file by its contents, not its name. It must be JPEG, PNG or WebP, no more than 5 MB, and within the pixel limits for its purpose (`LIMITS`).
2. **Re-encodes** it with GD. This drops EXIF and GPS metadata and anything appended to the file, so only real pixels are kept.
3. **Stores the sanitized original** on the private disk under `tenants/{organizer}/{purpose}/`. The original is never modified.
4. **Writes responsive variants** with `createVariants()`:
   - three sizes: small (400 px), medium (800 px) and large (1600 px) on the longest edge;
   - in WebP, plus AVIF where the server's GD supports it (`variantFormats()`);
   - stored under `tenants/{organizer}/{purpose}-derivatives/`.

   An optional crop, given in percent, shapes only the variants. If writing variants fails, nothing is left half-made.

The database keeps **references, never image bytes**:

| Image | Original | Variants | Alt text | Focal point | Moderation |
|---|---|---|---|---|---|
| Flyer (`events`) | `flyer_path` | `flyer_variants` | `flyer_alt_text` | `flyer_position_x/y` | `flyer_moderation_status` |
| Gallery photo (`event_media`) | `original_path` | `variants` | `alt_text` (required) | `position_x/y` | `moderation_status`, `moderated_by/at`, `moderation_note` |
| Menu photo (`menu_items`) | `image_path` | `image_variants` | `image_alt_text` | `image_position` | `image_moderation_status` |

Width, height, MIME type and size are recorded too. Gallery photos also record `uploaded_by`. Replacing or removing an image deletes its original and every variant, and the change is audited.

## Serving

`MediaController` serves every uploaded image. Ask for a variant with `?variant=small|medium|large&format=avif|webp`; with no variant, the original is served. Links carry `v=` (a hash of the stored path), so a replaced image is never served from a stale cache.

Who sees what:

| Case | Who can see it | Caching |
|---|---|---|
| Image on a **published** event | Everyone | `public, max-age=86400` |
| Image on an **unpublished** event | The event's managers, and administrators allowed to oversee it | `private, no-store` |
| Image an administrator **hid** | Same as unpublished | `private, no-store` |

Everyone else gets a plain 404, so hidden or unpublished images can't be discovered by probing ids.

## Showing images on pages

`App\Support\ResponsiveImage` describes an image for a page:
- the fallback URL;
- AVIF and WebP srcsets;
- its intrinsic size;
- its alt text and focal point;
- whether it is an upload or an illustrative sample.

Build one with:
- `$event->coverImage()`
- `$menuItem->responsiveImage()`
- `$eventMedia->responsiveImage()`

Render it with `<x-responsive-image :image="…" sizes="…" />`. This outputs `<picture>` with an AVIF and a WebP source, `width` and `height` (so the page doesn't jump while loading), lazy loading (or `:eager="true"` above the fold), and `object-position` set from the focal point. Classes and styles land on the `<img>`.

Where uploads appear:

| Area | What it shows |
|---|---|
| Home | The hero leads with featured events that have a real flyer. Tradition cards use a real flyer from an event in that tradition, credited ("Photo: event name"), or else the cuisine artwork. |
| Discovery | Each event card shows that event's own cover: its flyer, or its generated artwork. |
| Event page | The flyer is the banner, cropped around its focal point. The Photos strip shows the host's gallery, then the flyer, then dish photos; samples are labelled "Illustrative photo". |
| Food | Menu photos in cards. Packages show a small photo beside each included dish, choice and extra. |
| Tickets | The ticket, wallet and checkout header show the event's cover. |
| Host | The dashboard list and menu table show thumbnails. The edit page shows the flyer previews and the gallery manager. |
| Staff | A small cover in the terminal header only. Nothing decorative sits near the scanning, cash or handover controls. |
| Admin | The event page lists every uploaded image for review. |

## Image accuracy

`App\Support\ImageSubjects` links each image to what it shows, so an image appears only where it is true:

| Image | Where it may appear |
|---|---|
| A host's uploaded flyer | Always shown for that event. It is never replaced by other photography; only an administrator's moderation hides it, visibly and with a reason. |
| A host's uploaded menu photo | That menu item. The host can mark it as an illustrative sample. |
| A bundled sample dish photo (`sample_photos`) | Only on a menu item whose name contains one of the dishes listed for it in `matches`. It is always labelled "Illustrative photo". |
| Any other bundled `/images/` file | Never shown. |
| Bundled photos of events (`island_*.jpg`, …) | Never an event's cover. |

So, for example:
- a jerk chicken photo is never shown on sushi, jerk pork or a drink;
- an escovitch fish photo is never shown on lobster or conch;
- a concert photo never stands in for a family picnic, or for any event.

When no true image exists, the fallback says so:

| What has no image | What shows instead |
|---|---|
| Menu item | An icon and label for its kind of food, for example "**Seafood** · No photo yet" or "**Drinks** · No photo yet" (`ImageSubjects::menuFallback`). |
| Event | Artwork drawn from the event's own details: its title, date, place, category and traditions. It carries the words "Event artwork · no flyer uploaded yet", and its alt text says it is artwork. |

No image is presented as a customer's, vendor's or venue's photograph, or as evidence of attendance or a partnership, unless it was uploaded for that purpose by the event's own host.

**The API and the React app follow the same rules:**
- **`coverImage` field:** the event API (`EventResource`) returns `coverImage` with `kind` (`flyer` or `artwork`), `url`, `alt` and `label`.
- **No flyer:** the URL is the labelled artwork at `/api/v1/media/events/{id}/artwork.svg`.
- **`flyerUrl`:** set only for the host's own upload. A bundled photo path is never sent as an event's flyer.
- **React resolver:** every event picture in React goes through `src/lib/imageResolver.ts`. This covers the discovery homepage, event detail, ticket screens, checkout, the host's upcoming events and the event wizard. The resolver gives:
  - the host's flyer;
  - otherwise the labelled artwork;
  - for a menu item, its own photo, a server-approved sample labelled "Illustrative photo", or a "No photo yet" placeholder.
- **No guessing:** nothing guesses a photo from an event's title or category.
- **Broken images:** a broken image falls back to the labelled placeholder, never to a photo of a dish or another event.
- **Homepage hero:** behind the homepage headline, only a real flyer is shown. Without one, the background stays a plain gradient, and the artwork appears on the event's own card.

**Tests:**
- `tests/Feature/ImageAccuracyTest.php` (Laravel);
- `src/tests/imageResolver.test.ts` (React).

## Host tools

### Flyer (event form)
- Preview, plus a client-side check of size and dimensions; the server checks again.
- Focal point: click the flyer or use the sliders. Live previews show the card (4:3), banner (16:9) and square crops.
- Optional crop of a new upload (banner, card or square), centred on the focal point. It shapes only the displayed sizes; the original is kept whole.

### Event photos (edit page)
- Up to 12 gallery photos.
- Alt text is required.
- Focal point is set by clicking the preview, and can be adjusted later.
- Remove deletes the original and every size.
- A photo an administrator hid stays listed, marked, with the reason.

### Menu photos (menu studio)
- Preview, a focal point ("keep in view"), alt text, and an "illustrative sample" flag for photos that aren't the actual dish.

## Moderation

On an event's admin console page, administrators with the event-moderation permission for that organizer can **hide** or **restore** any uploaded image, with a reason.

Hiding never deletes the file:
- the generated cover, or a plain placeholder, shows in its place;
- the host still sees the image, marked, with the reason;
- the decision is audited (`image_hidden` / `image_restored`).

A host uploading a replacement starts that new image as visible.

## Performance

| Measure | How |
|---|---|
| Responsive sizes | Uploads: small 400, medium 800, large 1600 px. Sample photos: 400, 800, 1200 px (`php artisan images:optimize-samples`). `srcset` + `sizes` let the browser pick. |
| Modern formats | AVIF first, WebP second, in `<picture>`; the `<img>` keeps a WebP or original fallback. |
| Compression | WebP quality 82 (uploads) / 80 (samples); AVIF quality 55; re-encoded JPEG 85; PNG level 6. A ~1 MB sample photo becomes 24 KB (small AVIF) to 145 KB (large AVIF). |
| Loading | Every image is `loading="lazy"` and `decoding="async"` except the page's main image, which is eager with `fetchpriority="high"` and preloaded in `<head>` (`<x-preload-image>`, event page banner and homepage feature). |
| Layout stability | `width` and `height` on every uploaded and sample image; fixed-ratio frames (`--ratio-banner` 21:9, `--ratio-event` 16:10, `--ratio-photo` 4:3, `--ratio-square` 1:1). |
| Caching | Versioned public links: `public, max-age=31536000, immutable`; unversioned public links: 5 minutes; unpublished or hidden: `private, no-store`. |

## Storage and CDN

- `config/media.php`: `MEDIA_DISK` chooses the disk (default: the private local disk). Any S3-compatible disk in `config/filesystems.php` works; it must stay private because `MediaController` enforces visibility.
- Put a CDN in front of the application for `/api/v1/media/*`. The cache headers above let it serve public images for a year and never store private ones. Static assets follow Laravel's `ASSET_URL`.
- Images stored before variants existed: `php artisan images:make-variants` adds variants and leaves the originals untouched (`--dry-run` to preview).
- `php artisan images:audit` lists every image reference in the database and what guests see for it (`--only-problems` for the mismatches).

## Fallbacks and accessibility

- A stored flyer that fails to load is replaced in the browser by the event's generated artwork; a menu photo by the labelled food fallback; a gallery photo is left out.
- Alt text: required for gallery photos, offered for flyers and menu photos (defaulting to the event or dish name), "Illustrative photo" for samples, and "Artwork made from the event details … (no flyer uploaded)" for generated covers. Decorative images next to text that already names them use `alt=""`.
- Gallery: each photo is a button ("Open photo 2 of 5: …") that opens a native `<dialog>` viewer: focus moves into it, Escape closes it, focus returns to the photo, previous/next buttons and arrow keys move between photos, and the caption is announced with each one.

## Tests

- `tests/Feature/ImageManagementTest.php`:
  - the website's flyer upload is sanitized and keeps its original;
  - variants are made in every format and served with their own types;
  - crop is read in percent;
  - the host gallery is visible to guests and protected from other hosts;
  - moderation hides and restores;
  - menu photos get variants and alt text;
  - the image component renders correctly.
- `tests/Feature/MediaUploadsApiTest.php`: the same rules through the API.

## Regression test results

Full PHPUnit suite, 5 November 2026, after the image work: **405 tests, 391 passed, 14 failed** (5,025 assertions, 11.6 minutes).

- New: `ImageManagementTest` (9), `ImageAccuracyTest` (5), `ImagePerformanceTest` (5) — all pass.
- `MediaUploadsApiTest`: all 15 pass, including the 6 that failed before (half-built variants).
- The 14 failures were all failing before this work, or come from uncommitted seed data from another tool:
  - admin event preview (404), event QR and promotional QR (404), notifications listing, the Tailwind-class page audit, worker cash receipts (3), worker terminal access (3), the historical-currency migration check, the checkout processor check — unchanged by the image work;
  - `InclusiveIdentityTest::test_sample_catalog_uses_accurate_imagery…` — the uncommitted seed gives "Jerk Pork Plate" a photo (`dish_jm_jerk_pork.jpg`). The application no longer shows it (not catalogued), but the seed data itself still needs correcting.
- Layout checked in the browser at 375 × 812, 768 × 1024 and 1440 × 900: no horizontal scrolling; covers 16:10, banners 21:9 (16:10 on phones), gallery and food photos 4:3; the photo viewer works by keyboard and returns focus.
