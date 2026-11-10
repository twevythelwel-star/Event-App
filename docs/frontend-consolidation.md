# Front-end consolidation: Blade is the one front end

**Decision (2026-11-09):** the Laravel Blade pages in `backend/resources/views` are EventBite's single, authoritative
front end. The React single-page app (Vite, `src/`) duplicated the same screens. Each screen was checked against
its Blade page; the React app was then retired.

## Each React screen and the Blade page that replaces it

| React screen (retired) | Blade page |
|---|---|
| `EventDiscoveryHomepage` | `/` (`welcome.blade.php`), `/events` |
| `EventDetailView` | `/events/{id}` (`events/show.blade.php`) |
| `TicketSelectionScreen`, `FoodAndDrinksScreen`, `GuestMenuCheckout` | `/events/{event}/order/tickets`, `/order/food`, `/order/checkout` |
| `IslandEventDigitalTicketScreen`, `AttendeeTicketManagementScreen` | `/tickets/{order}`, `/attendee/dashboard`, ticket transfers |
| `IslandEventUserProfileScreen`, `AccountSettingsView` | `/settings`, `/attendee/dashboard` |
| `SignInModal` | `/login`, `/register`, `/forgot-password` |
| `OrganizerDashboard` and the `host/*` views (overview, orders, payments ledger, attendees, food prep, reports, workers, settings) | `/host/dashboard` (money, food to prepare, orders, staff), `/host/events/{event}/staff`, `/host/compliance` |
| `EventCreationWizard`, `MenuItemBuilder`, `PackageBuilder`, `VenueMapLocator`, `EventMediaManager` | `/events/create`, `/events/{id}/edit`, menu, ticketing, schedule, partners and venue studios |
| `StaffScanner` | `/worker/events/{event}/terminal` |
| `SystemAdminDashboard`, `DelegatedAdminDashboard`, `CulturalCatalogAdminSection`, `HostApplicationsReview` | `/admin/console/*`, `/admin/host-applications` |

## Gaps found and how they were closed

* **Cancelling an event** was in React only. Blade now has it: a "Cancel this event" section on the event edit page,
  with a required reason. It uses the audited status change, writes an `event_cancelled` audit entry, and a cancelled
  event cannot be reopened (`POST /events/{id}/cancel`, tested in `EventCancellationTest`).
* **Settlement and kitchen prep** were already in Blade under different names: the host dashboard's "Money" panel
  (received, still owed, refunded, cash on site, net) and "Food to prepare" table (portions sold, handed over, to
  prepare with buffer).
* **Notification and marketing preferences** in the React profile were never saved anywhere, so there was nothing
  real to move.

## Parked files

Another tool was building new features in React when it was retired: emergency contacts, an attendee FAQ, parking
and dress-code hub, group check-in and a publishing-readiness score. Those files had uncommitted work, so they
were left in place, unchanged:

* new: `src/components/AttendeeExperienceHub.tsx`; `src/components/host/AttendeeExperienceSetupSection.tsx`,
  `EventIntelligencePanel.tsx`, `EventOperationsSetupSection.tsx`, `LegalComplianceSection.tsx`;
  `src/lib/attendeeExperience.ts`, `eventIntelligence.ts`, `eventOperations.ts`, `foodCommerce.ts`;
* edited: `src/components/EventDetailView.tsx`, `FoodAndDrinksScreen.tsx`, `GuestMenuCheckout.tsx`,
  `OrganizerDashboard.tsx`, `SignInModal.tsx`, `StaffScanner.tsx`; `src/components/host/EventCreationWizard.tsx`,
  `HostFoodOrdersPrepView.tsx`, `HostWorkerAssignmentsView.tsx`; `src/lib/api.ts`; `src/types.ts`;
  `src/tests/18_…` and `25_…`.

They no longer build: the React tooling (`package.json`, Vite, TypeScript config) and the components they import
are gone. They are a reference for moving those features into the Blade event studio and event page. Delete them
once that is done.

## What else changed

* Removed: the React app (`src/`, `index.html`, `vite.config.ts`, `tsconfig*.json`, root `package.json` and
  lock file) and its static copies in the root `public/` (Blade serves its own from `backend/public`).
* CI no longer has a front-end job; the backend jobs test the Blade pages.
* `README.md` describes the Blade-only setup.
* Local leftovers you can delete by hand: the root `node_modules/` and `dist/` folders (both ignored by git).
