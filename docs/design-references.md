# Design references: lessons for the attendee screens

Date: 2026-10-28. Scope: the attendee journey (discover → event → passes → food → pay → ticket).
Sources: eight Dribbble shots, viewed in full size, and the app running locally at 375 px phone width
(the backend was offline, so the bundled demo data was used). The screens and code reviewed were
`EventDiscoveryHomepage.tsx`, `EventDetailView.tsx`, `FoodAndDrinksScreen.tsx`,
`IslandEventDigitalTicketScreen.tsx`, `AttendeeTicketManagementScreen.tsx`, `GuestMenuCheckout.tsx`
and `src/styles.css`.

Treat the shots as inspiration only. Take the principle behind each one, not its colours, type or
layout. EventBite should still look like a Jamaican night out, not an airline or an AI tool.

---

## 0. Target direction: the "EventBite" board (chosen by the owner, 2026-10-28)

The owner shared a full board as the look to aim for. It has eight phone screens (Discovery, Event
Details, Ticket Selection, Food Ordering, Checkout, Digital Ticket, Check-in, Host Dashboard) and
three web screens (Host Dashboard, Staff Portal, Admin Panel). From here on, **this board is the
target**, and sections 2–3 below are measured against it. Most of the lessons from the Dribbble shots
already appear in it, which is a good sign.

### What the board gets right (keep these)
- **Dark base and one accent.** Near-black navy surfaces, and a single lime/chartreuse colour that
  marks only the primary action, the selected tab or chip, and chart lines. Text on lime is dark.
  This replaces today's purple-pink-orange gradients (gap 3).
- **A sticky bottom bar with the money on it** on every buying step: `From $35 | Get Tickets`,
  `Total (1 ticket) $35.00 | Continue`, `3 items $27.00 | View Cart`, `Pay Now` (gap 2).
- **Poster-style event art** with a condensed display title ("ISLAND FISH FRY / Food · Music ·
  Culture"). The island character comes from the artwork and type, not from UI colour (gap 5).
- **Compact food rows:** thumbnail, name, price and stepper, under a Food / Drinks / Extras segmented
  control, with about five dishes per screen instead of one (gap 7).
- **Tier cards with an inline stepper,** a one-line "what's included" and the price in bold.
- **The ticket as an object:** poster art on top, a white QR card, a ticket number, a "Paid" chip, and
  then just two actions (Add to Wallet, Share).
- **A four-item attendee tab bar** (Home, My Tickets, Orders, Profile) (gap 8).
- **Web portals:** a dark sidebar with a light working area, and stat cards, tables and status
  chips. A light canvas is correct for staff and admin, because people work in it for hours.

### Where the board needs improving before it's built
1. **Payments and currency are US-based, but the app is Jamaican.** The board shows Zelle and Cash
   App with bare `$` prices. The app takes **bank transfer (NCB / Scotiabank) and cash** and
   defaults to **JMD**. Replace them with: *Bank transfer* (show bank, account and a copyable
   payment reference), *Cash at pickup* and, optionally, a local wallet. Always show the currency
   code (`J$2,250` or `JMD 2,250`), because the app also supports other currencies.
2. **"Pay Now" promises an instant payment the app can't make.** Bank transfers are checked by a
   worker. Use **Place order** for the button. The next screen should say *"Awaiting payment
   confirmation"*, give the reference and the bank details, and then show the ticket with a
   **Pending** chip (amber) that turns **Paid** (green) once confirmed. The board has no pending
   state at all.
3. **Lime is used for both "act" and "OK."** The lime CTA, the lime "All Events" chip and the lime
   *Valid Ticket* tick all compete. Keep lime for **actions and selection only**, and use a
   separate status set: green = paid/valid, amber = pending/already scanned, red =
   invalid/refunded/sold out. Check-in especially needs distinct **Invalid** and **Already
   checked in** screens, not just the happy path.
4. **The ticket screen still doesn't say "what now".** Add a status line above the art: "Gates open
   in 3h 20m", or "Food pickup: Stall 4 from 6 PM" when the order includes food. Shrink the poster
   on small phones so the QR stays visible without scrolling.
5. **Food rows leave out what the menu supports.** The app has portions, sides, spice level, add-ons
   and stock limits. Keep the compact row, but show **"+ Add"** until the quantity is above 0 (a row
   of `− 0 +` everywhere looks like a form). Open a bottom sheet for items with options, and mark
   *Sold out* and *Only 4 left* on the row.
6. **Text is too small on the board itself.** Much of the grey metadata is 8–10 px and low-contrast
   on navy. Use at least 12 px, and secondary text no darker than about `#9AA4B2` on the base
   colour (aim for 4.5:1).
7. **The Event Details page needs a few more facts:** host/organiser, the **advance food-order
   deadline**, and pickup instructions. The app has all three, and attendees need them before they
   buy.
8. **The name and logo.** EventBite is the official platform brand. The original `EventBite fork.jpg`
   is the authoritative logo source; use the shared logo component rather than recreating the mark.

### Tokens sampled from the board (starting values; tune with real contrast checks)
| Token | Value | Use |
|---|---|---|
| `--bg` | `#0A0F16` | page |
| `--surface-1` | `#111923` | cards, bars |
| `--surface-2` | `#18222E` | inputs, nested rows |
| `--line` | `rgba(255,255,255,.08)` | dividers, card borders |
| `--text-1` | `#F5F7FA` | headings, values |
| `--text-2` | `#9AA4B2` | labels, meta |
| `--accent` | `#B6F03A` (lime) | primary button, selected chip/tab, chart line |
| `--on-accent` | `#0A0F16` | text on lime |
| `--ok` / `--warn` / `--bad` | `#22C55E` / `#F59E0B` / `#EF4444` | status only |
| radius | 10 / 16 / 24 px | chips / cards / sheets & ticket |
| type | Inter 12 / 14 / 16 / 20 / 28; display: a condensed bold face (e.g. Anton or Bebas Neue) for event titles only | |

---

## 1. Lessons from each reference

### 1. Flight Tickets Booking (Conceptzilla): booking flow, hierarchy, forms, ticket selection
- **The answer is the headline.** The live card makes `SFO → AMS` the largest type on screen, with a
  progress bar for the journey and "Arrive in 4h 25m" as a small, coloured secondary line. The
  biggest text is whatever the user checks first.
- **The form becomes a stack of tappable rows.** Booking is a column of full-width rounded blocks
  (from, to, return/class, date, persons). Each block has a small grey label over a bold value and a
  chevron or stepper. There are no floating input boxes and no helper paragraphs.
- **Selection grid with three states.** Seats are a grid where *available* is outlined, *taken* is
  filled grey and *selected* is the single accent colour. You never need a legend to read it.
- **The checkout bar carries the total.** A full-width pill sits at the bottom of the selection
  screen and reads `CHECKOUT  $1,694  →`. The price lives on the button, not in a separate summary.
- **The ticket is a facts grid plus a barcode.** It shows three big numerals (Terminal, Gate, Seat)
  in equal cells, then a large barcode with one instruction line, then "Add to wallet" as the last
  action.
- **One accent colour, used rarely.** The shot is warm grey and black with a single mustard yellow,
  kept for the primary action and the selected state.

### 2. FareGo Booking Ticket (Hendri Jayadi): navigation, discovery, booking cards, information order
- **A segmented mode switch above the search** (Bus / Flight / Train) changes the whole form without
  leaving the page.
- **Results header with counts:** "7 Result | 2 Recommended", then cards marked with a "Recommended"
  tab sitting on the card's top edge. The flag attaches to the card instead of floating.
- **Every result card has the same skeleton:** origin and destination codes at the corners, a dotted
  route line with the duration in the middle, and price bottom-right with any old price struck
  through. Because every card is built the same way, the eye can compare them quickly.
- **"Upcoming" on the home screen:** the user's next booked trip shows on Home with a "See all" link.
  Owned tickets sit next to discovery.
- **A plain four-item tab bar** (Home, Ticket, Wishlist, Profile) with one filled active icon and grey
  inactive icons.
- **A countdown at the top of the ticket screen:** "Go to the airport at 10:30" sits above the
  ticket, so the next action comes before the reference data.

### 3. Ticket App WIP (Fireart): how the ticket looks, screen composition, ticket interactions
- **The boarding pass is one black card inside a light page.** Only the pass is high-contrast, so it
  reads as a physical object.
- **The ticket shows live status.** A second dark card under the next trip reads "Check-in · Ends at
  1:30 · 4h 15m" with a progress bar. The ticket shows what to do now, not only what was bought.
- **Upcoming / Past segmented control** at the top of "Trips". It is quiet, has two options and is
  always visible.
- **Ticket detail order:** pass → ticket code → barcode → one-line scan instruction → one primary
  button (Download E-Ticket) → a list of secondary rows (Flight alerts, Timeline, Travel docs) with
  chevrons. Secondary actions are list rows, not more buttons.
- **A personal greeting at the top** ("Good Morning, Charlie") is set small, and the user's name is
  the heading. It is not a marketing headline.

### 4. Air Ticket App Concept (Conceptzilla): spacing, typography, a simple checkout
- **Generous whitespace and a soft single-tone background** (light grey with white cards). Cards
  separate from the page by tone, not by borders.
- **A large, plain-language heading does the onboarding:** "Find flights that suit your timing and
  budget" is followed directly by two fields and one Continue button.
- **Image-led recommendation cards** ("Best Tours") use a photo with a two-word caption and a
  location on a white footer. The photo does the selling.
- **The checkout is very short:** a card visual, then one dark "Confirm Payment $512 →" bar. The
  price is repeated on the button that commits the user.
- **The seat price sits in the title** ("Seat: 2A — $512.00") with one line of what is included
  underneath.

### 5. AI app design (LazyInterface): modern styling and distinctive visual treatments
- **Dark base plus one acid accent.** Near-black surfaces and a single lime green for the primary
  action, the selected segment and live status. Everything else is grey.
- **A monospace "system voice" for metadata** ("draft saved · nothing in queue", "820 cr",
  "seed 84120"). Two typefaces set data and messages apart without adding colour.
- **The big number carries the state:** the rendering screen shows a huge "99%" with a thin progress
  bar and a checklist of steps (done / running / pending dots).
- **The cost is on the CTA:** "Generate · 60 cr · Seedance" and "Upscale to 4K · 18 cr" make each
  action's cost visible before the tap.
- **Chips and toggles in a tight grid** (model cards, aspect-ratio chips). Selected chips get an
  accent outline instead of a filled block, so the selection is clear without being loud.

### 6. AI ticket app design (LazyInterface): ticket-specific visual ideas, current patterns
- **Each ticket card is a stub plus a body.** A coloured header block (title, date range, "14 Events"
  pill) joins a second block with a photo, date/location/price and a barcode. The barcode and notches
  make it read as a ticket at a glance.
- **Category colour coding:** each category gets its own pastel card colour (lavender for
  sport/concert, mint for football). Colour helps users find their way, not only decorates.
- **A full-bleed hero photo with stacked condensed display type**, plus a skewed "BUY NOW" sticker
  and a hand-drawn asterisk. Personality comes from type and a few graphic marks, not from
  gradients.
- **Sticker-style urgency tags** ("Selling Fast") sit across the card corner.
- **A row of filter chips at the top** (date, location, max price, then categories) works as the
  search UI. There is no form.
- *Caution:* the hexagon chips and the illustration style are this designer's signature. Do not copy
  them.

### 7. AI meditation app design (LazyInterface): calm hierarchy, restrained decoration
- **One message per screen.** "Take the first step towards mental well-being today?" is the only
  large text, set in a light weight over a soft photo, with everything else below it.
- **Frosted glass cards over photography** in a single desaturated palette (sage, taupe, stone).
  There is no pure white and no saturated colour.
- **Short sentences in plain, kind language:** "That's okay — some days are harder than others."
- **Light-touch controls:** a Today/Yesterday toggle sits inside the card, "Speak" and "+" are small
  pill buttons at the row's end, and a single wide "Start Now" button sits at the bottom.
- **Data shown as shapes:** an emotion breakdown drawn as sized bubbles instead of a table.
- **Rounded tiles, each with one play button**, laid out in two columns.

### 8. AI food app design (LazyInterface): food photos, menu cards, choosing products
- **Cut-out food photography on a dark card** ("BlackLabel Burgers"). The dish is the hero, the name
  sits top-left and the calorie chip is top-left under the name. The card is portrait and about 70%
  of the screen wide, so the next card peeks in from the right as a carousel cue.
- **The detail screen leads with the photo,** then shows a frosted panel over it with the total, a
  three-column macro grid (Carbs / Protein / Fat) and a segmented "healthy score" bar. The facts come
  in small, scannable cells.
- **Two actions at the bottom of the detail,** side by side: "Update Details" (outline) and
  "Add Food" (filled).
- **The greeting doubles as a prompt.** "Good Morning, Edward!" sits above a question and then one
  wide search field ("Describe your food wish").
- **Category row of round photo thumbnails** with labels sits under the featured carousel.
- **A floating pill-shaped bottom nav** sits over the content, with the active item as a filled pill
  carrying its label and the rest as icons only.

---

## 2. Gaps between those lessons and the current screens, ranked by impact

Observed on the running app at 375 px, plus the code.

1. **The phone layout breaks.** *(highest impact: it affects every attendee on mobile)*
   - On `FoodAndDrinksScreen` the header's **Cart button is pushed off screen**
     (`#food-screen-cart-header-btn` ends at x = 476 on a 375 px viewport), and the page scrolls
     sideways by about 100 px.
   - `EventDetailView` also overflows (scrollWidth 389 vs 375), and the ticket-tier price column
     touches the right edge.
   - On the pass-selection step, "Real-time Inventory" spills past the card edge.
   - The light admin chrome (brand bar, location/currency/gateway pills, the five role tabs and the
     "Backend not reachable" notice) sits above the dark attendee app. Together they take most of the
     first screen before any event appears.
   - The Discovery header is **sticky and about 200 px tall** on a phone (eyebrow, three-line title,
     subtitle, Sign In / Get Started / refresh). It covers roughly a quarter of the viewport for the
     whole scroll.
   - Opening an event keeps the old scroll position, so Event Detail opens part-way down the page.
   References 1, 3 and 7 all fit one idea per screen inside the phone frame, and this layout does
   not.

2. **There is no clear primary action at the moment of decision.** On Event Detail at phone width,
   the purchase card ("Starting from $2,250 … Continue to Ticket Selection") is the **last block on
   the page**, below tiers, gallery and organiser. A user who picks a quantity at the top cannot see
   the total or the button. The pass-selection step already gets this right with a sticky
   "1 pass selected · $2,386 · Continue" bar. Event Detail and the food menu should do the same
   (references 1, 4 and 5: the price sits on the CTA).

3. **There is too much accent and too little hierarchy.** Almost every surface has emphasis: purple
   → pink → orange gradients on most CTAs (5 places in the ticket screen, 8 in the food screen), plus
   indigo eyebrow text, green "Genuine inventory / verified" lines, pink map pins, orange flames,
   gold kickers and emoji in headings. Every reference uses **one** accent for one job. As a result,
   "Add to cart", "Customize & Add", "View details" and "Continue" all look equally important.

4. **There are two design systems and no shared tokens.** `styles.css` defines a light
   indigo/gold/navy theme in `:root` (`--indigo`, `--gold` and so on), but the five new attendee
   screens are written entirely in inline styles (about 700 `style={{…}}` blocks) with their own
   hard-coded navy palette (`#0B132B`, `#162038`, `#94A3B8`, `#818CF8`, `#EC4899`, `#F97316`). The
   radius scale has 7+ values (8, 10, 12, 14, 18, 20, 24, 28 px). `GuestMenuCheckout` still uses the
   light classes. Moving between the role tabs, or into checkout, changes the whole look.

5. **The type scale is small and flat.** Most text is 11–13 px (styles.css even uses 8–10 px for
   badges, kickers and pills), and headings jump straight to 18–22 px with nothing big enough to act
   as the screen's answer. References 1, 3 and 5 use one very large value (the route, the countdown,
   "99%") plus small labels. The event's **date and time** would be the natural answer in Island
   Events, but today it is a 12 px indigo line.

6. **The ticket screen doesn't show "what to do now".** `IslandEventDigitalTicketScreen` already has
   good bones: artwork banner, metadata grid, perforation notches and a white QR card. But the QR
   comes after the artwork and four metadata cells, and nothing says "Gates open in 3h 20m" or "Food
   pickup from 6:00 PM at Stall 4". References 2 and 3 put a countdown or status above the pass, and
   reference 1 makes the 2–3 facts staff ask for into big numerals.

7. **Food cards don't help people choose.** On a phone, `FoodAndDrinksScreen` shows one full-width
   card per screen (photo, then title and price, then a 3-line description, an options hint and a
   large gradient button). That means about one dish per scroll, so menus with 10+ items feel long.
   Two different CTA styles appear ("Customize & Add" muted purple, "Add to Cart" gradient). Some
   photos load late (all images use `loading="lazy"`), which leaves black boxes. Reference 8 uses a
   compact photo-first card, a category row with thumbnails and a two-action detail.

8. **The attendee navigation is unclear.** Discovery has a five-item bottom nav (Home, Explore,
   Tickets, Food, Profile), but the Food and Ticket screens use their own back buttons instead, and
   the role tabs above say "1. Attendee (Discover, Order & Tickets)". References 2, 3 and 8 keep one
   persistent tab bar across the attendee app and show the user's next ticket on Home ("Upcoming").

9. **The copy is noisy.** Labels like "Genuine Inventory", "Real popularity", "Verified Official
   Ticketing", "Authentic Media Section", "1620 verified portions" and "Performer Highlight" add
   claims rather than information. Reference 7 shows how much calmer short, plain sentences are.

---

## 3. Suggested changes

Ordered by the gap they close. Each item names the file it touches. None of these are made yet:
this report is read-only, and another editor currently owns these files.

### A. Fix the phone layout first (gap 1)
1. **`FoodAndDrinksScreen.tsx` header (around line 175–297):** let the title block shrink
   (`minWidth: 0`, ellipsis on the event name) and keep the Cart button a fixed-size icon button
   (cart icon plus count badge, no "Cart" text below 420 px), so it can't overflow.
2. **`EventDetailView.tsx` and the tier cards (around line 717–897):** remove fixed widths and
   negative margins that cause the 14 px overflow. Put the price under the tier name on narrow
   screens instead of beside it.
3. **`EventDiscoveryHomepage.tsx` header (around line 237–445):** make only a compact 56 px bar
   sticky (brand mark, search icon, avatar or Sign in). Let the greeting and subtitle scroll away.
   Drop the eyebrow "Island Event Discovery" and the emoji in the H1.
4. **`GuestMenuCheckout.tsx` / `App.tsx`:** when the attendee role is active on a phone, hide or
   collapse the admin chrome (location, currency and gateway pills, role tabs) behind a single menu
   button. Show the "Backend not reachable" notice as a one-line dismissible banner.
5. **Scroll to top on screen change** wherever `GuestMenuCheckout.tsx` swaps between
   `EventDiscoveryHomepage`, `EventDetailView`, `FoodAndDrinksScreen` and the ticket screens.

### B. One sticky action bar with the total on it (gap 2)
6. **`EventDetailView.tsx` right-column purchase card (around line 1333–1525):** below about 900 px,
   render a fixed bottom bar instead: `1 pass · $2,250 | Continue →`. Use the same component as the
   pass-selection step's existing bar. Keep the full card on desktop.
7. **`FoodAndDrinksScreen.tsx` fixed cart bar (around line 1646):** use the same bar
   (`3 items · $3,700 | View order →`) and remove the separate header Cart button on mobile.
8. Extract that bar once (for example `src/components/StickyActionBar.tsx`) so detail, passes and
   food share it.

### C. Pick one accent and give it one job (gap 3)
9. **`styles.css` `:root` plus all five screens:** use the board's **lime `--accent`** (section 0)
   for primary actions and selected states only, and drop the purple-pink-orange gradient
   everywhere. Every other button becomes neutral (outline or plain text).
10. Keep **green only for confirmed / valid states** (paid, valid ticket, available) and **red/amber
    only for problems** (sold out, refunded, payment pending).
11. Remove decorative icon colours (pink pins, orange flames) and emoji in headings, and keep icons
    in the text colour. Island personality should come from photography and one display typeface
    (see E), not from colour on every element.

### D. Shared tokens for the attendee app (gap 4)
12. **`styles.css`:** add an attendee theme block, for example `.attendee-app { --surface-0 …;
    --surface-1 …; --text-1 …; --text-2 …; --accent …; --ok …; --warn …; --radius-s: 10px;
    --radius-m: 16px; --radius-l: 24px; --space-* }`, plus a few classes (`.att-card`,
    `.att-chip`, `.att-btn-primary`, `.att-btn-secondary`, `.att-label`).
13. **`EventDiscoveryHomepage.tsx`, `EventDetailView.tsx`, `FoodAndDrinksScreen.tsx`,
    `IslandEventDigitalTicketScreen.tsx`, `AttendeeTicketManagementScreen.tsx`:** replace the
    hard-coded hex values and radius numbers in the inline `style` blocks with those variables. This
    can happen gradually, screen by screen. Bring the checkout views in `GuestMenuCheckout.tsx` onto
    the same tokens, so payment doesn't switch to the old light indigo look.

### E. A real type scale with one "answer" per screen (gap 5)
14. **`styles.css`:** define about five steps (for example 12 / 14 / 16 / 22 / 32 px) and stop using
    anything below 11 px. Labels go in sentence case at 12 px, instead of uppercase 9–10 px with
    letter-spacing.
15. Add **one display face** for event titles and big numbers. A condensed or characterful sans
    gives the poster energy of reference 6 without copying it, and Inter stays for body text.
16. Per screen, choose the answer and set it at the top size:
    - **Event Detail:** date and start time ("Sun 22 Nov · 12 PM"), above the title.
    - **Ticket:** the countdown or status (see F).
    - **Pass selection / food:** the running total in the sticky bar.

### F. Make the digital ticket answer "what now?" (gap 6)
17. **`IslandEventDigitalTicketScreen.tsx`:** above the pass, add a status strip that changes with
    time and state, for example "Gates open in 3h 20m", "Show this QR at the gate" or "Food pickup:
    Stall 4 from 6:00 PM". Payment pending or refunded states already have a banner, so reuse that
    slot.
18. **Same file, metadata grid (around line 612–740):** cut the four cells to three large ones that
    staff actually check, for example **Pass** (General / VIP), **Guests** (×2) and **Gate** or
    **Pickup** point. Move the attendee name and full address under a "Details" row.
19. **Same file:** move the QR higher (shrink the artwork banner from 220 px to about 140 px on
    phones) so the QR is visible without scrolling. Below it, keep one primary action ("Add to
    wallet / Save pass") and turn Receipt, Transfer and Event details into list rows with chevrons
    instead of three buttons.
20. **`AttendeeTicketManagementScreen.tsx`:** make the Upcoming / Past / Transferred filter a compact
    segmented control. Give the *next* upcoming ticket a larger "live" card with its countdown, with
    the rest as compact ticket stubs (thumbnail, title, date, status) built from the same stub/body
    shape as the pass.

### G. Food cards built for choosing (gap 7)
21. **`FoodAndDrinksScreen.tsx` menu grid (around line 419–830):** on phones, use a **two-column
    grid** of compact cards: a square photo, then the name (2 lines max) and the price, with a small
    round "+" button on the photo corner. That button adds the item directly, or opens the
    customisation sheet if the item has options. Move the description and the options hint into
    the detail sheet.
22. **Same file, category tabs (around line 355–418):** a horizontal row of round photo thumbnails or
    simple chips with counts. Make the row sticky under the header while the menu scrolls.
23. **Same file, customisation modal (around line 834–1236):** present it as a bottom sheet that
    leads with the photo. Then show a small fact row (portion, spice level, prep time) in three
    cells, and end with **two side-by-side actions**: a quantity stepper (outline) and
    `Add · $1,300` (filled accent).
24. Load the first 4 images eagerly with `fetchpriority="high"`. Give image boxes a tinted
    placeholder background instead of black.

### H. One persistent attendee navigation (gap 8)
25. **`EventDiscoveryHomepage.tsx` bottom nav (around line 1456–1665):** extract it (for example
    `AttendeeBottomNav.tsx`) and render it from `GuestMenuCheckout.tsx` on all attendee screens. On
    detail, food and ticket screens, show it with the matching tab active. Back buttons stay for
    moving up one level. Four items are enough: Home, Tickets, Food (orders), Profile, with Explore
    folded into Home's search.
26. **`EventDiscoveryHomepage.tsx`:** for signed-in users with a ticket, add a "Your next event" card
    near the top of Home (event, date and countdown, plus "Show ticket").

### I. Calmer copy (gap 9)
27. **All five screens:** remove trust adjectives that carry no information ("Genuine", "Real",
    "Authentic", "Verified Official"). Keep facts such as "250 passes left", "Pickup from 6 PM" and
    "Paid by bank transfer". Write the remaining labels in short sentence case.

---

### J. Changes from the target board (section 0)
28. **`GuestMenuCheckout.tsx` checkout/payment view:** use payment-method radio cards as on the
    board, but with *Bank transfer (NCB / Scotiabank)* and *Cash at pickup*. Add a terms checkbox and
    a **Place order** button. After it, show a confirmation screen with the reference, the bank
    details and the Pending status.
29. **`IslandEventDigitalTicketScreen.tsx` / `AttendeeTicketManagementScreen.tsx`:** add
    Pending / Paid / Refunded chips in the status colours, and the "what now" line (F17).
30. **Staff check-in screen** (the scanner component under the Staff tab): add Valid (green),
    Already checked in (amber) and Invalid (red) result states, plus Manual entry, as on the board.
31. **`EventDetailView.tsx`:** add the poster hero with a condensed title, three icon rows (date,
    time, place), tag chips (Food, Music, Family friendly), a horizontal menu preview with "View full
    menu", and the sticky `From J$… | Get Tickets` bar. Keep the host card and the food-order
    deadline.

## Quick wins vs larger work

- **Quick wins** (each is small and self-contained): A1, A3, A5, B6/B7, C10, E14, G24, I27.
- **Larger work** (needs agreement on the visual direction first): C9 (choosing the accent), D12–13
  (tokens and migration), E15 (display typeface), F17–20 (ticket rework), G21–23 (menu rework),
  H25 (shared nav).
