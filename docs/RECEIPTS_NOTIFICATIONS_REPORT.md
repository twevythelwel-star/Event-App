# Receipts and notifications

Customer messages go through one path: `App\Services\Notifications\NotificationService`.
- **Channels:** the existing integrations are kept. SMS goes to the order's phone through Twilio, and email goes to the order's address through the configured mailer.
- **Logging:** every message is recorded in `notification_logs` with an idempotency key, so each moment is sent at most once per channel.
- **Delivery:** `SendNotificationJob` sends it.
- **Failures:** a problem sending a message never fails the order, payment or event action that caused it.

The `App\Notifications\*` classes are not used by the application. An earlier draft added five more of them, plus a `Mail\OrderReceipt` and a receipt view, none of which was ever called. Those were removed, and the work was done on the real path above.

## Messages

| Moment | Trigger | Sent when | Wording |
|---|---|---|---|
| Order received | `order_received` | The order is placed (`OrderPlacementService`) | Total in dollars. If the payment is not confirmed yet, says so plainly. |
| Payment awaiting verification | `payment_awaiting_verification` | The customer sends a bank, Zelle or Cash App reference (`CustomerPaymentService::submitReference`); once per distinct reference | The ticket is not valid until the organizer confirms. |
| Payment confirmed | `payment_verified` | The order becomes **paid**: verified by the host or admin (`OrderOperationsService`), or cash recorded in full (`CashLedgerService`) | Amount and method |
| Payment failed | `payment_rejected` | A manual payment is rejected | The reason given; the ticket is not valid |
| Ticket issued | `ticket_issued` | Straight after payment confirmed | Event date and local time; open My tickets for the QR |
| Event updated | `event_updated` | A **published** event's date, time, time zone or address changes (web form or host API); every order still holding a ticket is told, once per distinct change | The new date, time and place |
| Event reminder | `event_reminders` | The host sends reminders (existing, at most once a day) | Date, time and place |
| Refund requested | `refund_requested` | The customer files a `ticket_refund` request | "No money has been returned yet" |
| Refund completed | `refund_completed` | The event owner records a refund | Amount; arrival time depends on how they paid |
| Food ready for pickup | `pickup_instructions` | Fulfillment moves to *ready for pickup* | Pickup instructions |
| Cash received; order collected | `cash_receipt_recorded`, `order_collection_completed` | Unchanged | — |

**Payment-confirmed messages only for verified payments.** `payment_verified` and `ticket_issued` are refused inside `NotificationService` unless the order's status is *paid* (or *partially refunded*). That rule holds whoever calls it, so a partial payment, a submitted reference or a pending transfer can never produce one.

**Payment failed** covers manual payments that the host rejects. Card and other processor payments do not exist yet (the payment-processor work is unfinished). When they arrive, their failure should use the same trigger.

**Delivery status:**
- *sent* means the provider accepted the message.
- Only the provider's signed callback (Twilio status callback, email webhook) can set *delivered*.
- Nothing in the app tells a customer or host that a message was delivered without that callback.

## Receipts

`App\Services\ReceiptService::forOrder()` builds one receipt from what the order recorded. Emails for these moments include it:
- order received;
- payment confirmed;
- ticket issued;
- cash received;
- refund completed.

They carry it as HTML (`emails/partials/receipt`) and as plain text, built when the email is sent. The ticket page shows the same facts.

| Required | Source |
|---|---|
| Event name | event title |
| Date and local time | `event_date_iso` → "Saturday, November 21, 2026", plus the host's time text and the zone abbreviation for that date (EST, EDT, CST…) |
| Venue location | Venue, street, City, ST ZIP |
| Order reference | order number; also the ticket code |
| Ticket details | ticket lines: quantity × name at unit price |
| Itemized food | food lines with chosen options and special instructions |
| Currency and prices | `Money::display()` (`$12.50`); total marked with the currency code |
| Fees and taxes | the order's frozen charge lines (`order_charges`), each with its own label; discounts shown as negative |
| Total | order total; also paid, refunded and balance due |
| Payment status and method | `PaymentStatusPresenter` labels ("Confirmation submitted", "Bank transfer"…) |
| Payment reference | the reference the customer gave, for bank, Zelle and Cash App. There is no processor transaction id yet. |

The receipt never contains the ticket's secret QR token. The admission QR appears only on the ticket page, and only once the payment is settled. An unpaid receipt says "This is not proof of payment".

## Database

Migration `2026_11_01_000031_allow_all_notification_triggers`: `notification_logs.trigger` changes from an enum of the first ten moments to a string. Without this, the new messages could not be recorded. Existing rows are unchanged; the list of allowed values lives in `NotificationService::TRIGGERS`.

## Tests

- `ReceiptsAndNotificationsTest` (9) covers:
  - confirmation and ticket sent once, in U.S. format;
  - no confirmation for unverified or partial payments, even by direct call;
  - rejection reported as a failure with no ticket;
  - reference submitted;
  - part versus full cash;
  - refund requested and completed;
  - event change messaged once, and only for when or where;
  - receipt contents and no QR secret;
  - *sent* is not *delivered*.
- `NotificationsApiTest` now expects `ticket_issued` after a payment is confirmed.
