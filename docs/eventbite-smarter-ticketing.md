# Smarter ticketing

**Where hosts manage it:** the ticketing studio, at `/events/{id}/ticketing` (`TicketingStudioController`), linked from the event's edit page.

**How it's built:** every rule lives in a service under `App\Services\Checkout`.

**Protections that apply throughout:**
- Selling, holding and issuing tickets all take the same event-row lock, so nothing oversells.
- Prices are always worked out on the server, in whole cents.
- Every host action needs event-management rights and is recorded in the audit log.

## What each feature does

| Feature | How it works | Where |
|---|---|---|
| **Dynamic pricing (price steps)** | Per ticket type: "up to 50 sold at $15, up to 150 at $20, up to 200 at $25". The price moves on by itself as tickets sell. A purchase that crosses a step is priced ticket by ticket, so each ticket pays its own step's price. Sold counts include only live orders, so an expired or cancelled order gives its tickets, and their place in the steps, back. | `TicketPricing::lines`, `TicketPricing::soldByTier` |
| **Early-bird pricing** | A lower price per ticket type, in any currency, until a date and time. After that the regular price (or price steps) applies automatically. The old event-wide early-bird (USD) still works. | `early_bird_price_minor` and `early_bird_ends_iso` on the ticket type |
| **Limited VIP releases** | Release schedules such as "20 now, 20 more on Friday at 10 AM". Only released tickets can be sold, and buyers see when the next batch opens. | `releases` on the ticket type, `TicketPricing::releasedRemaining` |
| **Group discounts** | A percentage off every ticket of a type when buying enough of them, for example 4 or more at 10% off. | `group_discounts` on the ticket type |
| **Reserved ticket inventory** | Tickets a buyer chooses are held for 10 minutes (`commerce.ticket_hold_minutes`) while they check out. Other buyers can't take them, and an expired hold frees them. Once ordered, the existing payment window holds them as before. | `TicketInventory::hold`, `ticket_holds` |
| **Discount codes** | Percent or amount off; for tickets, food or the whole order; optionally only for certain ticket types. Limits: a minimum number of tickets, total uses, uses per customer, start and end dates. A code can be credited to a promoter. Codes are case-insensitive. The code is checked when applied and again under a lock when the order is placed, so a limited code is never over-used. A cancelled or expired order gives its use back. The saving is a discount line, applied before fees and taxes. | `DiscountService`, `discount_codes`, `discount_redemptions` |
| **Promotional affiliates** | Each promoter gets a link (the event page with `?ref=CODE`) and a commission rate. A visit through the link is remembered for that buyer's session, and their order is credited to the promoter (or to the promoter a code belongs to). The report lists paid orders, tickets, revenue after refunds, and commission, plus how many orders are still unpaid. | `affiliates`, `orders.affiliate_id`, `TicketingStudioService::affiliateReport` |
| **Ticket transfers** | See the next section. | `TicketTransferService`, `ticket_transfers` |
| **Waitlists** | When a ticket type is sold out, buyers can join a waitlist. When tickets come free (an unpaid order expires, an order is cancelled, a release opens, the host adds stock), people are offered them in the order they joined. The tickets are held for that person for 60 minutes (`commerce.waitlist_offer_minutes`) and they get an in-app notice. An offer that isn't taken up passes to the next person. Offers are made straight away when an order expires, every 5 minutes by schedule (`tickets:offer-waitlist`), or on demand by the host. | `WaitlistService`, `waitlist_entries` |
| **Complimentary tickets** | See the section after transfers. | `ComplimentaryTicketService` |
| **Ticket bundles** | A ticket type can include food per ticket (for example a VIP ticket with a jerk plate). The food is reserved from menu stock when the ticket is bought and priced inside the ticket. It is collected with the ticket's QR code like any dish, at the food station's handover. Other VIP benefits are described on the ticket type itself. | `bundle_items` on the ticket type |

### Ticket transfers

A holder can pass paid tickets that haven't been used at the gate to another EventBite email address, as long as the event hasn't ended. The host can turn transfers off.

- **The link:** the sender gets a one-time link to pass on. It lasts 72 hours, and only a hash of its token is stored.
- **Accepting:** the recipient must sign in with that exact email address to accept.
- **Afterwards:** the order moves to the recipient and gets a **new QR token**, so the sender's old QR code no longer admits anyone.

### Complimentary tickets

Only the event's managers can issue them, to an existing EventBite account, with a reason.

- **What the guest gets:** a real, settled order at no charge, in the guest's wallet with a working QR code.
- **Stock:** comps come out of the same stock as sales.
- **Records:** each comp is marked complimentary, with who issued it and why, and recorded in the audit log.

## Data

Ticket type options live on the event's `ticket_tiers` JSON:
- `price_steps`;
- `early_bird_price_minor` and `early_bird_ends_iso`;
- `releases`;
- `group_discounts`;
- `bundle_items`.

New tables: `ticket_holds`, `discount_codes`, `discount_redemptions`, `affiliates`, `ticket_transfers` and `waitlist_entries`.

New columns:
- on `orders`: `affiliate_id`, `discount_code_id`, `is_complimentary`, `issued_by` and `complimentary_reason`;
- on `events`: `allow_ticket_transfers` and `waitlist_enabled`.

The migration (`2026_11_05_000054`) only adds tables and columns.

## Tests

`tests/Feature/SmarterTicketingTest.php` has 10 tests. They cover:
- a crossing purchase pays each step's price;
- early-bird prices switch at their date;
- releases, and group prices;
- holds keep tickets for the buyer and free them on expiry;
- code limits, dates, ticket-type restrictions and use counting;
- promoter credit and the sales report;
- secure transfers: owner only, paid only, the named recipient only, one use, a new QR token;
- waitlist offers in turn, with the held tickets protected;
- comps from shared stock, with the audit entry;
- bundle food reserved from stock.

## Regression test results

Full PHPUnit suite, 5 November 2026, after these features: **430 tests, 416 passed, 14 failed** (5,342 assertions, 12.9 minutes). All 10 new tests pass, and the checkout, seating and event-studio tests still pass. The 14 failures are unchanged from before this work: the ones that were already failing, plus `InclusiveIdentityTest`, which fails on another tool's uncommitted seed data.
