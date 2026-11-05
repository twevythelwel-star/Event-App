# EventBite visual enhancement: audit and plan

Audit date: 5 November 2026. Everything below was found by inspecting the code and running the app before any change for this brief.

## 1. How the application is built and rendered

| Area | Finding |
|---|---|
| Production front end | **Laravel 13 Blade**, server-rendered, served by `public/index.php`. Every public, attendee, host, staff, admin and system-admin page is a Blade view. |
| React app (`/src`) | A separate Vite + React single-page app that talks to the same `/api/v1` routes through a dev proxy. Laravel does not serve it (no route renders `dist/`). It is a secondary client; this brief enhances the Blade app. |
| Livewire | Three components: `RoleSwitcher` (in the main layout), `AccountSettings` and `SystemAdminDashboard`. |
| Layouts | `layouts/app.blade.php` (everyone, with the role-aware sidebar and mobile bottom nav) and `layouts/admin.blade.php` (the admin console). |
| CSS | Plain CSS in `public/css`, linked directly with no build step: `01-tokens`, `02-typography-layout`, `03-buttons-forms`, `04-surfaces-cards`, `05-navigation-motion`, `06-components`, `admin-console`, `brand`. Laravel's Vite/Tailwind pipeline (`resources/css/app.css`) is configured but not used by the layouts. |
| JavaScript | Small inline scripts per page (progressive enhancement); no bundler in use for Blade pages. |
| Fonts | Inter (body) and Plus Jakarta Sans (display) from Google Fonts; JetBrains Mono for codes. |
| Icons | One 24 px line icon family in `components/icon.blade.php` (51 icons). Some pages still use emoji instead. |
| Components (29) | Including alert, button, card, data-table, empty-state, event-card, food-card, form-field, icon, metric-card, page-header, payment-method, price-summary, quantity-control, status-indicator, ticket-card, ticket-mockup, brand-logo. |

## 2. Design tokens (`public/css/01-tokens.css`)

The brief's colours are already the tokens, with contrast noted in the file:

| Token | Value | | Token | Value |
|---|---|---|---|---|
| `--color-background` | #07111F | | `--color-accent` | #C7FF45 |
| `--color-background-secondary` | #0B192B | | `--color-purple` | #8B5CF6 (text form #C4B5FD) |
| `--color-surface` | #10243A | | `--color-text-primary` | #F8FAFC (18.3:1) |
| `--color-surface-elevated` | #152C45 | | `--color-text-secondary` | #A8B6C8 (9.5:1) |
| `--color-border` | #26384D | | success / warning / error | #22C55E / #F59E0B / #EF4444, each with readable text, background and border forms |

Spacing, radius, type scale, durations and easing are tokens too. The issue is not missing tokens but **pages that bypass them** (section 5).

## 3. Brand assets

| Asset | Finding |
|---|---|
| Source of truth | `Eventbite fork.jpg` (2048 × 2048, the fork-pin mark and the EventBite wordmark on flat navy #11172F). |
| Existing derivatives | `images/brand/eventbite-wordmark.webp` (lockup on navy) and `icons/eventbite-{16,32,48,180,192,512}.png` plus `favicon.ico`, all on the logo's own navy. |
| Logo component | `<x-brand-logo variant="wordmark|compact|icon">`, used in both layouts, sign-in, registration, the 403 page, emails and admin settings. |
| Problems | (1) Every derivative carries the #11172F background, which shows as a box on the #07111F app, and `brand.css` paints that box on purpose. (2) `theme-color` and the web manifest use #11172F, not the brand background. (3) `icons/apple-touch-icon.png` and `icons/eventbite-180.png` are the same file. (4) Emails would need a PNG logo on a dark band (WebP and transparent images are unreliable in mail clients). (5) The digital ticket and printed ticket carry no logo. |

## 4. Pages reviewed by account type

| Account | Main pages | State |
|---|---|---|
| Public | Home, event list, event details, short QR links, sign-in and registration | Home and event cards were refreshed in the previous visual pass. Event list and event details still use their own hard-coded palette and emoji. |
| Attendee | Checkout (tickets, food, review), digital ticket, ticket wallet, settings | Checkout and ticket refreshed; the wallet (`dashboards/attendee`) still uses emoji and 72 inline styles. |
| Host | Dashboard, create (studio) and edit event, menu studio, review and publish, staff, cash and payments | Edit, menu studio and review follow the dark system. **The create studio (`events/create`) is a light-themed page** (#fff cards, its own palette) inside the dark app. |
| Staff | Worker terminal (scan, cash, food handover, supervisor) | Functional, emoji-heavy, 52 inline styles. |
| Administrator / System administrator | Admin console (`layouts/admin`, `admin-console.css`), system admin dashboard | Its own consistent console style. Left as is in this pass. |

Routes (212 in total): API 74, public and shared 57, admin console 44, host 19, attendee 12, staff 5, system admin 1.

## 5. Inconsistencies found

| Problem | Where (counts) |
|---|---|
| Hard-coded colours instead of tokens | events/show (91), events/create (80), auth/login (74), events/index (72), auth/register (46), forgot/reset password (60), attendee wallet (22) |
| Light (#fff) surfaces in the dark app | All four auth pages, events/create, events/preview, events/show (parts), attendee wallet, notification email |
| Emoji used as icons | events/index (24), events/show (11), worker terminal (6), events/preview (6), auth/login (5), events/create (5) |
| Inline `style=""` heavy | events/show (106), events/index (76), attendee wallet (72), worker terminal (52) |
| Per-page `<style>` blocks | events/show, events/index, events/create, events/preview, the four auth pages, welcome |
| Duplicated CSS | `@keyframes eb-spin` defined three times; the event list and event details each define their own nav and button styles (`btn-glass`, `discovery-bottom-nav`) |
| Generic placeholders | Fixed: events with no flyer get generated artwork, missing food photos show a labelled placeholder, empty states now use the illustration library |

## 6. Animation review

| Animation | Decision |
|---|---|
| Hero aurora drift, ring rotation, headline shimmer, featured-card sheen | Keep; slow and decorative |
| Status dot pulse and pop, QR scan line (fades out), checkout step progress | Keep |
| Card hover lifts, region expansion, tab fade, chip fade | Keep |
| `studio-pulse` (create studio save dot) | Keep; restyle with the studio's move to dark |
| Duplicate `eb-spin` keyframes | Remove duplicates; keep one |

All motion is cancelled by the site-wide `prefers-reduced-motion` rule in `05-navigation-motion.css`; it must stay that way.

## 7. What must not break

- Every route above, with its middleware (auth, active account, role and organizer scope, worker assignment, throttles) and CSRF.
- Publishing only through the readiness check; drafts never purchasable; server-side prices, totals, stock locks and ticket tokens.
- Food ordering rules (packages, windows, limits), item-level handover, and the worker terminal flows.
- The admin console's permission-gated navigation.
- The test suites (PHPUnit and the legacy runner) and the `ProfilePagesAuditTest` rule that pages use only the app's own CSS classes.

## 8. Plan

Ordered so each step is safe on its own:

1. **Brand.**
   - Transparent derivatives of the official logo (lockup at 360, 600 and 1200; mark at 128, 256 and 512), with only the flat background removed.
   - App icons from 16 to 512 on #07111F, plus a maskable icon.
   - Manifest and theme colour moved to #07111F.
   - One `<x-brand-logo>` with lockup, compact, mark and email variants.
   - The logo added to the digital and printed ticket; emails get a PNG on a dark band.
2. **Bring off-system pages onto the tokens**: auth pages, event list, event details, attendee wallet, create studio. Replace emoji with the icon family, move inline styles into shared classes, and remove the duplicate keyframes.
3. **Dashboards** (host, staff, admin, attendee profile): charts and status graphics using the visual asset library.
4. **Verify after every step**: PHPUnit, the legacy runner, lint, eight widths from 360 to 1920 px, keyboard and reduced-motion checks.

Step 1 is done in this change. Steps 2 and 3 follow in separate changes, so each can be reviewed and tested on its own.
