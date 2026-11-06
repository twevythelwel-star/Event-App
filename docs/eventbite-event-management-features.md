# Advanced event management

Four host tools, reached from the event's edit page. Each is a Laravel Blade page with its own service, which checks that the signed-in account manages the event (anyone else gets a 404), holds every rule, and writes the audit log.

## 1. Schedule and activity builder

**Page:** `/events/{id}/schedule-studio` (`ScheduleStudioController`, `App\Services\Events\ScheduleService`)

| Part | What the host does | Rules |
|---|---|---|
| Stages & places | Name each place: main stage, food court, kids' tent | Names are unique per event; removing a place keeps its activities ("no set place") |
| Line-up | DJs, performers, bands, guest speakers, hosts/MCs, with a one-line description, profile, up to 5 https links and an optional photo | Photos use the same image pipeline as flyers (checked, re-encoded, sizes, private storage). With no photo, the person's initials show, never someone else's picture |
| Programme | Activities with a kind (performance, DJ set, talk, food service, activity, ceremony, break), date, start and end, place, people and description; optionally kept off the public schedule | Typed in the event's own timezone, stored in UTC; an end before the start runs past midnight; must fall within the event's start and end; no two activities overlap on the same stage; places and people must belong to the event |

**What attendees see.** The event page shows the public schedule by day, with each activity's time, place, kind and people, a filter by place, and line-up profiles.

**Food service hours.** The event's food service hours appear on the schedule automatically, unless the host already scheduled food service.

**Data.** `event_stages`, `event_performers`, `event_activities`, and the `event_activity_performer` link table.

## 2. Venue and seating designer

**Page:** `/events/{id}/venue-designer` (`VenueDesignerController`, `App\Services\Events\SeatingService`)

### Designing the plan

The host draws the venue on a 1000 × 640 plan. The area types are:
- general admission and VIP sections (with an optional capacity);
- reserved tables (1–20 seats);
- rows of seats (up to 26 rows of 60);
- food stations and vendor booths;
- stages and restricted areas.

Ticketed areas link to one of the event's ticket types, and "accessible (step-free)" can be marked on any area.

To place areas, the host drags to move and drags the corner to resize. Every area also has typed fields for its position and size, so the plan works by keyboard.

### Seats

Tables and seat rows generate their seats, named for example "Hall B12" or "Table 1-3", and read aloud as "Hall, row B, seat 12".

Once a seat in an area is sold, that area's name and number of seats can't change and the area can't be removed. It can still be moved.

### Seat selection at checkout

When the host turns on "Let buyers choose their seats", buyers of seated ticket types pick one seat per ticket on the tickets step:
- **On the plan:** every seat is a labelled checkbox, so it works by keyboard, touch and screen reader.
- **From a list:** the same seats grouped by area.

The choice is checked when the step is saved. It is checked again when the order is placed, inside the order's transaction with the seat rows locked, so **a seat is never sold twice**; if a seat was taken meanwhile, the whole order fails and nothing is reserved.

A seat belongs to its order while the order is live. Once the order expires, is cancelled or refunded (or its stock is released), the seat is free again automatically. The attendee's ticket lists their seats.

**Data.** `venue_layouts`, `venue_areas`, and `venue_seats` (unique per event and seat name).

## 3. Vendor and exhibitor registration

**Pages:**
- Host: `/events/{id}/partners` (`PartnersStudioController`, `App\Services\Events\PartnersService`)
- Applicant: `/events/{id}/vendors/apply` and `/vendor-applications` (`VendorApplicationController`)

### Setting up

- **Settings:** open or close applications, an optional closing time, and optional vendor terms that applicants must agree to.
- **Booth types:** a name, which kind of vendor it is for (or any), a price, how many are available, an optional booth on the venue plan, what's included, and whether it's offered.
- **Inviting:** the Applications tab has a link to share. Applicants sign in with their own account.

### Applying

Applicants are food, merchandise and craft vendors, exhibitors and sponsors. They give:
- their business and contact details and what they'll sell or show;
- an optional https website;
- what they need (power, water, space);
- whether they agree to be listed on the event page.

Each account can have one live application per event, and a fully booked booth type can't be chosen.

### Deciding

The host approves with a booth type, a booth number or plan booth, and a fee (the booth type's price unless changed), or declines. Either way, an optional note goes to the applicant. Booth limits are enforced under a lock.

### Fees

Fees are paid to the host outside EventBite. The host records each fee as due, paid or waived. Once the fee is paid, the applicant must go through the host to withdraw.

### What others see

- **Applicants:** their status, booth, fee and the host's note, under My vendor applications.
- **Attendees:** approved vendors who agreed to be listed appear on the event page, with their booth.

**Data.** `vendor_offers` and `vendor_applications`, plus the event's application window fields.

## 4. Sponsor management

**Page:** the Sponsors tab of `/events/{id}/partners`

- **Packages:** Gold, Silver, Bronze or Partner, each with a name, benefits, price and how many are available.
- **Sponsors:** a name and level (choosing a package sets the level, and its limit is enforced), a description, an https website, and a logo (PNG with transparency is kept), each with its alt text.
- **Where they are credited:** each sponsor can be shown in any of three places:
  - **Event page:** grouped by level, Gold first with larger logos, linked with `rel="sponsored"`.
  - **Generated flyer:** "With support from …". An uploaded flyer is the host's own artwork and is never altered.
  - **Tickets:** "With thanks to …".
- **No invented logos.** A sponsor without an uploaded logo is shown by name.
- **From an application.** An approved sponsor application can be turned into a sponsor in one step. Its logo is then added by the host.

**Data.** `sponsorship_packages` and `event_sponsors`. Logos are stored like other uploads and served by `MediaController` (`/api/v1/media/sponsors/{id}/logo`).

## Tests

| Test file | Tests | What it covers |
|---|---|---|
| `ScheduleStudioTest` | 5 | Building stages, line-up and activities; time and stage rules; access; the public schedule with food service; photo storage |
| `VenueSeatingTest` | 5 | Layout and seat generation; plan checks; access; choosing seats; no double sale; release on expiry; sold-seat protection |
| `VendorsAndSponsorsTest` | 5 | Application window and terms; booths, decisions, limits and fees; access; sponsors by level on page, flyer and logo; sponsor from application |

## Regression test results

Full PHPUnit suite, 5 November 2026, after these four features: **420 tests, 406 passed, 14 failed** (5,232 assertions, 10.5 minutes). All 15 new tests pass. The 14 failures are the same ones that failed before this work (admin event preview, event QR and promotional QR, notifications listing, the Tailwind-class audit, worker cash receipts and terminal access, the historical-currency migration check, the checkout processor check) plus `InclusiveIdentityTest`, which fails on another tool's uncommitted seed data.
