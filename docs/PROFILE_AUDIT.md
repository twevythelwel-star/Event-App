# EventBite profile, dashboard and design audit

Audit date: 31 Oct – 1 Nov 2026. The audit covers every account type, page, tab and form reachable through the Laravel Blade application. The defect register (F) gives each defect's status; the implementation report (G) lists the corrections.

## How the audit was done

- **Route inventory:** `php artisan route:list --except-vendor` lists 164 routes. 44 of them are page (GET) routes; the rest are form posts and `/api/v1` endpoints.
- **Crawl:** a crawler test seeds the real seeders (U.S. locations, the 20-event catalog, demo accounts, the system administrator and demo orders). It also creates one staff member for each staff role on *South Florida Jamaican Fish Fry*.
  - It signs in as each of 10 profiles: guest, attendee, event host, administrator, system administrator, payment clerk, ticket checker, food station worker, event supervisor and custom restricted worker.
  - It follows every link that profile is shown and records each page's status, layout, links to missing sections and placeholder wording.
  - The first run found the defects below. It is now kept in the suite as `ProfileNavigationCrawlTest`, so a broken link offered to any profile fails the build.
- **Browser:** the development server was checked in a browser, signed in as the host, the attendee, the administrator and a food-station staff member.
  - **Widths:** an automated sweep loaded each profile's pages in frames at 1920, 1440, 1280, 1024, 768, 430, 390 and 360 px and flagged anything wider than the frame.
  - **Overflow found:** this found the horizontal overflow fixed in D9, D18 and D19.
- **Code review:** layouts, sidebars, dashboards, controllers, form requests and the staff-management service were read against what each page claims to do.

## A. Profile inventory

All app pages now share one sidebar (`x-management-sidebar`), which shows sections by role. The admin console keeps its own console sidebar, but it uses the same design tokens.

| Profile | Navigation (after the fix) | Pages, tabs and actions |
|---|---|---|
| **System administrator** | Console: Dashboard, Users, Host applications, Roles & Permissions, Events, Fees & Taxes, Locations, Orders & Payments, Reports, Audit Logs, Platform Settings. App sidebar: Events, Hosting, Administration, My account. | Console: user detail, suspend and reinstate, role grant and revoke, delegated-admin permissions, event moderation, order review and payment decisions, fee and tax rules, states and markets, report export, audit log, platform settings. Also host applications (approve or reject), every event's edit page, staff page and terminal, and settings. `/system-admin/dashboard` and `/admin/dashboard` redirect to the console. |
| **Administrator (delegated)** | Console entries filtered by the permissions a system administrator granted. App sidebar: Administration (console, host applications), My account. | The permitted console pages within their organizer scope, host applications, and settings with a read-only list of their permissions. |
| **Event host** | Hosting: Host dashboard, Create event, Payments to review, Orders, Food to prepare, Staff. Also My account. | Host dashboard with these sections: events, payments to review (verify or reject), recent orders, money, food to prepare, staff (revoke). Also create event, and edit event: details, location, contact, food ordering, ticket types, flyer, publish or unpublish, ordering open or closed, read-only menu, share QR. Plus event staff (claim-code assignment, revoke), the event terminal, and settings (profile, password, event defaults, payment instructions and refund policy). |
| **Attendee** | Discover; My account: My tickets & orders, Become a host, Settings. Mobile bottom navigation. | Ticket wallet, order history, ticket page per order, checkout (tickets, food, review), staff claim codes, host application, settings. |
| **Event staff** (5 roles) | "Staff duty": one link per event whose assignment is current right now, plus My account. | The event terminal, showing only the role's panel: cash desk (payment clerk), check-in (ticket checker), food handover (food station, limited to its station), supervisor desk with live counts, or the custom worker's granted actions. |

## B. Functionality report

**Working, verified by the crawl, the browser and existing or new tests:**
- discovery and event pages;
- checkout;
- the ticket wallet;
- QR codes;
- the admin console: users, roles, events and moderation, orders and payment decisions, fees, locations, reports and export, audit log, platform settings;
- host applications;
- the worker terminal: scan, cash, lookup, handover;
- staff assignment by claim code;
- settings.

**Broken before the audit, now fixed (details in F):**
- host dashboard for non-hosts (500 error);
- the create, edit and staff pages and the host application pages (unstyled);
- staff revoke on the dashboard (did nothing);
- three settings forms (405 error, 500 error, and saves that showed raw JSON in the browser);
- the dashboard's dead links;
- invented dashboard content.

**Missing before the audit, now built:**
- event date and ticket types on the event form;
- payment verification on the host dashboard;
- the host's payment instructions, refund policy and food buffer in settings;
- a terminal link for staff.

**Still partial (see "Not done"):**
- Menu items: view-only on the event page.
- Admin console: no sections for refunds, categories, notifications or system health.

## C. Design consistency report

**Before:**
- **Two palettes:** a dark app and an admin console with a light working area.
- **Unstyled pages:** seven views written in Tailwind classes, but Tailwind is never loaded.
- **Inconsistent sidebars:** each dashboard redefined its own sidebar with emoji icons and different labels.
- **Breadcrumb:** read "Dashboard" on every page without a title.
- **Old variable names:** the terminal used CSS variables that no longer exist, so its borders and text colours fell back.
- **Horizontal overflow:** admin tables, the events page filters and the event page widened phone screens.

**After:**
- **One set of tokens:** `public/css/01-tokens.css` defines colours, type, spacing, radius and shadows for both shells. The console's `--ac-*` variables now point at these tokens.
- **Same styling everywhere:** every app page uses the `eb-*` components:
  - `x-page-header` (new): breadcrumbs, title, description, badge and actions;
  - `x-card`, `x-form-field`, `x-data-table`, `x-metric-card`, `x-empty-state`, `x-alert`, `x-button`, `x-status-indicator`.
- **One sidebar:** shared by every profile, with SVG icons and active states.
- **Breadcrumb:** the first crumb is a link.
- **Flash messages:** the layout shows `status` and `error` messages, so pages no longer render their own copies.
- **Widths:** no page is wider than the screen at any of the eight tested widths. Wide tables scroll inside their card.

## D. Security report

| # | Severity | Finding | Fix |
|---|---|---|---|
| S1 | Critical | Any event host could open any other organizer's event staff page, which listed staff names and emails. The check allowed a host whose `users.organizer_id` was empty, which is every host. | `EventWorkerService::staffManagerRole()`: the event's own host (an active owner membership of its organizer), a supervisor whose assignment is current, or a system administrator. Used by the page, assign, revoke, invite and the policy. |
| S2 | Critical | Any event host could revoke staff on any organizer's event. The revoke service only checked that the actor held the host role. | Same check, in both the service and the controller (403). |
| S3 | Critical | Any event host could assign or invite staff to any organizer's event, with the same flaw in `createAssignmentFromClaimCode`, `assignWorker` and `createWorkerInvitation`. | Same check. |
| S4 | High | A supervisor whose shift had expired or not yet started, or whose assignment was revoked, could still manage staff: the check used `is_active` only. | Supervisors count only while `isCurrentlyActive()` (start, expiry and revocation all checked). |
| S5 | High | The event create and edit forms accepted any uploaded file as the flyer (no type, size or dimension check) and stored it under the client's own extension. | The same rules as the dedicated flyer upload (JPG, PNG or WebP, at most 5 MB, 300×200 to 4500×4500). |
| S6 | Medium | A `javascript:` URL entered in a host application's website field became a clickable link on the admin review page. | Only `http(s)://` addresses become links; anything else is shown as text. |
| S7 | Medium | The Livewire `EventWorkerManager` component had no permission check on its toggle action and listed every user's email. It was not mounted on any page. | Removed, because the claim-code staff page replaces it. |
| S8 | Medium | A typed new password was flashed back into the session (and re-rendered) after a validation error. | Added `new_password` and `new_password_confirmation` to `dontFlash`. Password inputs are never refilled. |
| S9 | Low | The delegated admin's user list linked to records they cannot open (403). | Shown as "Restricted", with no link. |
| S10 | Low | `EventWorkerPolicy` grants any host the payment, scan and food abilities on any order. It is not registered or used anywhere. | `manageWorkerAssignments` now uses the shared check. The unused methods are left as they are and recorded here. |

Unchanged and confirmed by the crawl and the suites:
- attendees and staff are refused on every admin, system-admin and host page;
- delegated admins are refused on system-only areas;
- staff reach only their own event's terminal, and only during their shift;
- CSRF applies to every form;
- `AdminConsoleSecurityTest` (37 tests) still passes.

## E. Testing report

All results below are from a clean copy: HEAD, plus only this audit's files. The other uncommitted work in the working tree (cuisine categories, payments, vendor assignment) is excluded.

| Check | Result |
|---|---|
| PHPUnit, full suite | **253 passed, 0 failed, 0 skipped** (3,272 assertions). The suite had 237 before this audit; 16 are new. |
| Legacy runner (`tests/run_backend_tests.php`) | **526 passed, 0 failed.** Was 525; one assertion added. |
| PHP lint (`tests/lint_all.php`) | 331 files, 0 syntax errors |
| `migrate:fresh --seed` (in-memory SQLite) | Completed. Every seeder ran. |
| Browser pages checked | host dashboard, edit event, event staff, settings, admin console (dark), orders on a phone, the staff terminal, the attendee wallet on a phone |
| Widths tested | 1920, 1440, 1280, 1024, 768, 430, 390, 360 px |
| Database verification | Feature tests read back what each form saved: profile, password errors, host payment policy, refund policy on the public page, food buffer, ticket types with ids kept, event date, staff revocation, payment verification. |

**New tests:**
- `ProfileAccessAuditTest` (6): host dashboard for non-hosts, cross-organizer staff page and revoke, staff shifts (scheduled, expired, revoked, other event), expired supervisor, terminal link.
- `ProfilePagesAuditTest` (9): no Tailwind-only pages, real dashboard data and working payment decision, settings forms, host payment settings, ticket types, flyer validation, event date, host-application links, restricted user links.
- `ProfileNavigationCrawlTest` (1): every profile's links work. Before relying on it, I checked that it fails when a dead link or a link to a refused page is planted.
- In the legacy runner, a host of another organizer is now refused when inviting staff. The two staff-invitation fixtures now give their host an owner membership of the event's organizer. That is the real precondition: the tests had been relying on the flaw fixed in S3.

**Not run, and why:**
- **Pint:** the project's existing files do not follow its rules at HEAD, so formatting would rewrite unrelated code. I ran it in check mode only and removed the unused imports my changes caused.
- **Livewire component tests:** the only Livewire component in scope was removed.
- **The React client's build:** that client was not changed.

## F. Defect register

| # | Severity | Profile | Page / tab | Defect | Cause | Correction | Status |
|---|---|---|---|---|---|---|---|
| D1 | Blocker | Attendee, admin, staff | Host dashboard | HTTP 500 | Return type did not allow a redirect | Return type widened | Fixed, tested |
| D2 | Critical | Host | Create and edit event | Forms unstyled | Tailwind classes, Tailwind never loaded | One shared `eb-*` form partial | Fixed, tested |
| D3 | Critical | Host, supervisor | Event staff | Unstyled; giant icons | Same | Rewritten | Fixed |
| D4 | Critical | Admin, system admin | Host applications | Unstyled; giant icon | Same | Rewritten | Fixed |
| D5 | High | Attendee | Become a host; verify email | Unstyled | Same | Rewritten | Fixed |
| D6 | High | Host | Dashboard › Staff | Revoke did nothing | No form | Posts to revoke with confirmation | Fixed, tested |
| D7 | High | Host | Dashboard | Invented chart, kitchen stations and names, "Audited Ledger" and "✓ Enforced" claims, "Main Grounds" and "United States" fallbacks | Static mark-up; wrong column names | Rebuilt from real data | Fixed, tested |
| D8 | High | Host | Dashboard, sidebar | Links to sections that do not exist | Missing sections | Sections added; sidebar rebuilt | Fixed, crawled |
| D9 | High | Admin | Console tables on phones | Page 843 px wide at 390 px | Visually hidden labels escaped the table's scroll box | Table wrappers contain them | Fixed, browser-checked |
| D10 | Medium | All | Console vs app | Two palettes | Separate `ac-*` colours | Console uses the shared tokens | Fixed |
| D11 | Medium | All | Sidebar | Per-page sidebars with emoji and stale labels | Views overrode `navigation` | One role-aware sidebar | Fixed |
| D12 | Medium | Host, staff | Breadcrumbs | "Dashboard" on every page | Default title | Titles set; first crumb is a link | Fixed |
| D13 | Medium | Attendee, settings, staff | Sidebar links | `#receipts`, `#currency`, `#roster` and `#reports` went nowhere | Stale links | Removed with the old sidebars | Fixed, crawled |
| D14 | Medium | Staff | Navigation | No terminal link in the sidebar | — | "Staff duty" links for current shifts | Fixed, tested |
| D15 | Low | Admin | Users | Linked to records they cannot open | — | "Restricted", no link | Fixed, tested |
| D16 | Low | Attendee | Become a host | "Jamaica — Coming Soon" option | Leftover | U.S. only | Fixed |
| D17 | Low | — | Terminal tests | No HTTP tests for shift timing | Gap | Added | Fixed |
| D18 | High | Attendee, guest | Events page | Page wider than the screen below 1024 px | Fixed 2 rem negative margin; filters did not wrap | Margin follows page padding; filters wrap | Fixed, browser-checked |
| D19 | High | All | Event page | Page up to 2138 px wide at every width | `1fr` grid column grew to fit long content | `minmax(0, 1fr)`; long words wrap | Fixed, browser-checked |
| D20 | Critical | Host | Settings › event defaults and payments | Form posted to a GET route (405 error). Payment and refund settings had no route. | Missing route; request authorization called a missing method (500 error) | `POST /settings/host`, authorization fixed, owner-only, audited | Fixed, tested |
| D21 | High | All | Settings › display currency | 500 error | Route pointed at a missing method | Route fixed | Fixed, tested |
| D22 | High | All | Settings › profile and password | Saving showed raw JSON. The form asked for 8-character passwords; the server required 10. | Controllers answered JSON only | Redirect back to the section with a message (JSON kept for the API); 10-character hint | Fixed, tested |
| D23 | High | System admin | Settings | Invented values: a "Cloudflare R2" bucket, a masked database password and a webhook secret | Static mark-up | Removed; links to the console's platform settings | Fixed, tested |
| D24 | High | Host | Create event | No event date, so new events were never listed as upcoming; no ticket types | Fields missing from the Blade form | Event date plus a ticket-type editor (validated, ids kept) | Fixed, tested |
| D25 | High | Host | Dashboard | No way to verify a bank or Zelle transfer in the web app | Feature missing | Payments-to-review list with Verify and Reject through the existing, permission-checked operations API | Fixed, tested |
| D26 | Medium | Staff | Terminal | Borders and text colours missing | Old CSS variable names | Aliases in the token file | Fixed |
| D27 | Medium | Staff | Attendee dashboard | Assignment table wider than the screen | Undefined `.table-container` class | Shared table classes | Fixed |
| D28 | Medium | Guest, attendee | Event page | The heart button said "Event saved to your Island Favorites!" but saved nothing | Placeholder script | Removed until saved events exist | Fixed |
| D29 | Medium | Guest | Sign in, create account | Google and Apple buttons only showed an alert claiming they work "in production" | No social sign-in exists | Removed | Fixed |
| D30 | Medium | Attendee | Wallet › Order history | "View" showed a fake "Receipt generated" alert | Placeholder | Opens the order's ticket and receipt page | Fixed, crawled |
| D31 | Low | Admin, system admin | Old dashboards | Two unreachable dashboard views with alert-only buttons | Superseded by the console | Views removed; the routes still redirect to the console | Fixed |
| S1–S10 | see D | | | | | | Fixed except S10 (documented) |

## G. Implementation report

**Files changed:**

- **Controllers:**
  - `EventController`: form-only fields kept out of the model; ticket types through the management service; flyer validated; returns to the form after saving.
  - `HostDashboardController`: return type; food to prepare from paid order items; payments to review.
  - `StaffAssignmentController`: the shared permission check.
  - `AccountSettingsController`: redirects with messages; host settings saving payment policy, refund policy and food buffer; owner check.
- **Requests:**
  - `StoreEventRequest`: event date, flyer and ticket-type rules, field names.
  - `UpdateHostSettingsRequest`: authorization fixed; food buffer.
- **Services and policies:** `EventWorkerService::staffManagerRole()`, used everywhere staff are managed; `EventWorkerPolicy`.
- **Routes:** `POST /settings/host` added; `/settings/currency` points at its real method.
- **Bootstrap:** `dontFlash` for password fields.
- **Views:**
  - Rewritten: host dashboard, settings, the event create and edit pages (via the new `events/partials/form` and `tier-row`), event staff, become a host, host applications, verify email.
  - Sidebars removed from: the attendee dashboard and the terminal.
  - Updated: the shared sidebar, both layouts, `form-field`, the QR partial, admin users and roles, the events page and the event page.
- **Components created:** `x-page-header` and `settings-toggle`.
- **CSS:** the console re-themed on the shared tokens; old variable aliases added; table wrappers contain their content.
- **Removed:**
  - `App\Livewire\EventWorkerManager` and its view (S7);
  - the unreachable `dashboards/admin` and `dashboards/system-admin` views (D31);
  - the placeholder favourite button (D28) and social sign-in buttons (D29).
- **Tests:** three new feature test files. Legacy fixtures give the host an owner membership, and a new assertion covers a host of another organizer.
- **Database changes:** none. No migrations were added; ticket types use the existing `ticket_tiers` column.

**Not done (remaining work):**
- **Menu items:** the edit page lists them, but adding, editing and photos still go through the host API only. A Blade menu editor is the next host feature to build.
- **Admin console:** no sections for refund oversight, event and cuisine categories, platform notifications or system health. The uncommitted cuisine-category controller in the working tree is someone else's work in progress; it was not reviewed and has no view.
- **Card payments:** card, Apple Pay, Google Pay, PayPal and Cash App Pay depend on the unfinished payment-processor work (task 5).
- **Saved events:** not implemented. The placeholder button was removed (D28).
- **Social sign-in:** none. The placeholder buttons were removed (D29).
- **Two-factor authentication:** none, and no session list.
- **Email changes:** changing your email does not ask you to verify the new address.
