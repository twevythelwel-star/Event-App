# EventBite — Mobile Design System

Shared tokens and component rules for the mobile-first redesign. Everything builds on the
existing modular stylesheets in `backend/public/css/` — no new palette, fonts, or framework.

## Color

| Token | Value / role |
| --- | --- |
| `--color-background` | Deep navy page background (`#07111F` family) |
| `--color-background-secondary` | Sidebar / recessed navy |
| `--color-surface` | Card surface (gradient `eb-gradient-surface-3d`) |
| `--color-surface-recessed` | Wells, inputs, inset panels |
| `--color-text-primary` | Primary text (near-white) |
| `--color-text-secondary` / `--color-text-muted` | Supporting / meta text |
| `--color-accent` | Lime `#C7FF45` — primary actions, active nav, price accents |
| `--color-accent-subtle` / `--color-accent-border` | Lime tint fills and borders |
| Purple tokens | `eb-badge-purple` and purple border accents for staff/host identity |
| Semantic | `eb-badge-success/-warning/-error/-info`, `eb-badge-neutral`, `--color-success/warning/error-*` |

Contrast: accent-on-navy and badge tones were chosen for AA on dark surfaces; keep new
text at `--color-text-primary`/`--color-text-secondary` on navy — never pure mid-grey.

## Typography

- Families: Inter (body/UI), Plus Jakarta Sans (display headings).
- Scale roles: `.eb-text-section-title` (section H2), `.eb-text-card-title` (card titles,
  `.eb-clamp-2` for two-line clamp), `.eb-text-supporting`, `.eb-help-text` (meta),
  `.eb-text-price` (tabular-nums prices), `.eb-text-label`.
- Minimum body size 0.875rem; meta ≥0.75rem; never below 11px equivalent.

## Spacing & layout

- `--space-1…8` scale; `--layout-section-gap` between dashboard sections.
- Page padding `--layout-page-padding-x`; grids `eb-grid-2/3/4` collapse to fewer columns
  on phones (single column ≤~639–767px depending on module).
- Cards: `eb-card`/`eb-panel card` with `--radius-lg`, `--shadow-subtle/elevated`.
- Chips/pills: `eb-discover-chip`, `eb-dashboard-nav-pills`, `eb-badge`.

## Touch & mobile rules

- Minimum touch target 44×44px; primary mobile actions 48–56px
  (staff scan input/button are 56px).
- Bottom nav: `position: fixed`, `height: var(--layout-bottom-nav-height)` (68px) +
  `padding-bottom: env(safe-area-inset-bottom)`; content reserves clearance
  (checkout bar sits at `bottom: var(--layout-bottom-nav-height)`).
- Media frames use aspect-ratio tokens (`eb-aspect-event-card` 16/10,
  `eb-aspect-event-hero` 21/9 → 16/10 ≤639px, `eb-aspect-food-card` 4/3) so images crop
  responsively without layout shift; `x-responsive-image` emits srcset/sizes.
- Tables: wrap in `.eb-table-wrap` (`overflow-x:auto`, `-webkit-overflow-scrolling:touch`)
  via `x-data-table`; prefer stacked cards on phones for primary flows.
- Sidebar shells hide off-canvas <1024px; admin uses `data-nav-open` drawer toggle.
- `prefers-reduced-motion`: all animation/transition durations collapse (05 module);
  honor it for any new motion.

## Component inventory (reuse before creating)

`x-brand-logo`, `x-button` (variants/sizes), `x-page-header`, `x-card`, `x-metric-card`,
`x-event-card`, `x-food-card`, `x-ticket-card`, `x-data-table`, `x-empty-state`,
`x-status-indicator`, `x-responsive-image`, `x-image-label`, `x-pagination`,
`x-price-summary`, `x-food-order-summary`, `x-loading-skeleton`, `x-skeleton`,
`x-customer-bottom-nav`, `x-management-sidebar`, `x-icon`.

## Role-specific composition rules

- **Attendee**: discovery first (search → chips → image-led event grid), then wallet,
  orders, notifications; fixed bottom nav Discover/My Tickets/Saved/Profile.
- **Host**: KPI strip → compliance → performance tables → charts (CSS progress, not JS
  chart libs on mobile) → payments/cancellations → food forecast → staff.
- **Staff**: assigned-event banner → primary scan action → status → payment → food;
  no decorative graphics or charts above operational controls.
- **Admin**: permission-filtered drawer nav → sectioned KPI/table pages; oversight
  sections (users, events, payments, compliance, audit, settings) map to Gates.

## Responsive verification widths

320 / 360 / 375 / 390 / 430px phones, ~768px tablet, ≥1024px desktop (sidebar + top nav,
bottom nav hidden). Checks: no horizontal overflow, no clipped text, no overlap with
fixed nav/buy bars, ≥44px targets, safe-area padding, readable contrast, keyboard focus
visible (`:focus-visible` outline tokens), reduced-motion respected.
