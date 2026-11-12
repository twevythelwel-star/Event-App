# EventBite — Mobile Design Audit

Audit of the existing mobile experience before the Kombai-inspired redesign. Blade is the
authoritative frontend (`docs/frontend-consolidation.md`); React under `src/` is retired and
was not touched.

## Shared shell

| Surface | File | Mobile state found |
| --- | --- | --- |
| Customer shell | `resources/views/layouts/app.blade.php` | Single layout for public + attendee pages; loads the modular `/css/01…06` design system. |
| Customer bottom nav | `resources/views/components/customer-bottom-nav.blade.php` | Fixed bottom bar, `safe-area-inset-bottom` padding, ≥48px touch targets, hidden ≥1024px. **Changed**: items are now Discover / My Tickets / Saved / Profile (was Home / My Tickets / Orders / Profile). Order history stays reachable via dashboard anchors. |
| Management sidebar | `05-navigation-motion.css` (`eb-management-sidebar`) | Fixed sidebar slides off-canvas below 1024px (`translateX(-100%)`); host/staff shells share it. |
| Admin shell | `resources/views/layouts/admin.blade.php` + `admin-console.css` | Dedicated `ac-*` console with hamburger drawer (`data-nav-open`), permission-filtered nav built from the same Gates controllers enforce. |

## Per-role findings

### Attendee
- `dashboards/attendee.blade.php` is a long single page: KPI row, discover section,
  ticket wallet, booked events, food orders, order history, notifications, profile,
  staff assignments, host application CTA. Discovery-first ordering was already added in
  the previous pass; this pass added a compact search form (q + city) and kind chips.
- `events/index.blade.php` is already a mobile-first discovery catalog: sticky header,
  search, state/city/ZIP/date/price/availability filters, category shortcuts,
  image-led cards, client-side filtering honoring `?q`, `?city`, `?kind`, `?category`.
- `welcome.blade.php` is image-led with brand sections (food festivals, music,
  community, family) and tokenized styling.
- **Gap found**: no saved/bookmarks feature existed. Implemented `saved_events`
  (migration 000069, `SavedEvent` model, `SavedEventsController`, `/saved` page,
  `POST /events/{event}/save` toggle, Save button on `events/show`).

### Event Host
- `dashboards/host.blade.php`: KPI metric cards, quick-jump pills, ticket-sales and
  order-analytics progress charts, payments-to-review and cancellations tables,
  food-fulfillment forecast, staff section. `eb-grid-4`/`eb-grid-2` collapse on narrow
  screens; `x-data-table` wraps in `.eb-table-wrap` (`overflow-x: auto`, touch scrolling).
- Quick event creation: "Create event" primary action in the page header → `events.create`.
- No decorative graphics above KPIs; charts are CSS progress bars (work on narrow screens).

### Event Staff
- `dashboards/worker-terminal.blade.php` + `partials/ticket-check-in.blade.php`:
  permission-gated sections (`scan_qr_tickets`, `record_cash_received`,
  `mark_quantities_collected`), assigned-event banner, live supervisor metrics,
  fail-closed offline scan policy, scanner connection status line.
- **Changed**: the ticket-scanning section was moved above operational-status metrics so
  the primary gate action is the first control after the event banner; scan input and
  Check-ticket button enlarged to 56px touch targets.

### Administrator / System Administrator
- `layouts/admin.blade.php`: structured nav drawer (Platform section), breadcrumbs,
  account menu, per-ability nav filtering via `AdminAuthorization` Gates — same
  permissions controllers enforce. Sections cover dashboard, users, host applications,
  roles, events, fees/taxes, locations, photo library, compliance, orders/payments,
  reports, audit logs, platform settings.
- Admin console screens are table/KPI driven; `.ac-*` styles handle narrow widths.

## Design tokens in force

Defined in `public/css/01-tokens.css` (+ typography/layout in `02`, components in `04–06`):

- Surfaces: `--color-background` (dark navy `#07111F` family), `--color-surface`,
  `--color-surface-recessed`, gradient surface treatments.
- Accents: lime `--color-accent` (`#C7FF45` family), purple badge/border tokens.
- Layout: `--layout-bottom-nav-height: 68px`, `--layout-header-height`,
  `--layout-sidebar-width`, `--layout-page-padding-x`, `--layout-section-gap`.
- Type: Inter + Plus Jakarta Sans; `.eb-text-section-title`, `.eb-text-card-title`,
  `.eb-text-supporting`, `.eb-help-text`, `.eb-text-price`.
- Motion: `--duration-fast/normal`, `--ease-standard/out-quart`; global
  `prefers-reduced-motion` override in `05-navigation-motion.css`.
- Components: `eb-card`, `eb-panel`, `eb-badge*` tones, `eb-btn*` variants,
  `eb-grid-2/3/4` responsive grids, `eb-dashboard-nav-pills`, `eb-discover-chip`,
  `eb-event-card` + media aspect frames (`eb-aspect-event-card` 16/10 etc.),
  `eb-table-wrap`, `eb-progress-track/fill`, `eb-metric-card` (x-metric-card),
  `x-empty-state`, `x-data-table`, `x-ticket-card`, `x-food-card`.

## Kombai patterns adopted (inspiration, not copied)

- Discovery-first hero ordering with image-led cards (already present, extended).
- Compact KPI strips that stack on narrow screens.
- Fixed bottom tab bar with safe-area insets.
- Stacked list rows with status badges instead of wide tables on phones
  (`.eb-table-wrap` keeps tables usable via horizontal scroll where tables remain).
- Large, single-purpose operational actions (staff scan).
