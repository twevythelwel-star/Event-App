# EventBite — Event Ticketing, Food Pre-Ordering & Kitchen Fulfillment Platform

A comprehensive, production-grade event ticketing, culinary pre-ordering, and kitchen fulfillment platform built for cross-border Jamaican and US events.

---

## The Complete Customer Journey

1. **Discover**: Guests visit the event page and view the event title, countdown, venue coordinates, and description.
2. **Browse**: Guests inspect the culinary menu featuring mouth-watering food photography (Jerk Chicken, Braised Oxtail, Escovitch Fish, Rum Punch), custom ingredients, stock counts, and dual pricing in **USD ($)** and **JMD (J$)**.
3. **Order**: Guests select items and quantities (+ / -), customize options, and review the live cart summary.
4. **Pay**: Guests choose an eligible payment method (Bank Transfer to Jamaican/US banks, Zelle, Cash App) with transfer instructions.
5. **Confirm**: The platform generates an authenticated digital ticket pass with a unique reference code (`IE-KNG-XXXX`), dynamic QR code, and assigns `pending_verification` status.
6. **Receive**: Guests receive an order voucher with a simulated instant SMS / WhatsApp delivery preview.
7. **Redeem**: On event day, workers use the **Staff Scanner** console to scan or search the ticket, review payment verification status, and view the customer's ordered dishes.
8. **Complete**: Staff check off each meal item as handed over with live timestamps. When all dishes are delivered, the order is closed and archived as **Order Closed**.

---

## The Organizer & Kitchen Prep Station

- **Live Kitchen Prep Forecast Sheet**:
  - Live portion aggregation across all orders so head chefs know the exact count of each meal needed (e.g. 48 Jerk Chicken portions, 32 Oxtail portions, 65 Rum Punch servings) before event day arrives.
- **Sales & Ledger Metrics**:
  - Total orders placed, verified revenue collected (USD & JMD), pending verification totals, and expected attendance.
- **Menu & Pricing Management**:
  - Organizers can create and edit menu items, set dual pricing in USD and JMD, manage inventory limits, and upload photo assets.
- **Multi-Channel Social Sharing Hub**:
  - One-click share buttons for **WhatsApp** (pre-composed viral text), **Instagram Stories/Bio**, **Facebook**, and direct link copying.
- **Strict Manual Transfer Verification Queue**:
  - Organizers confirm or reject transfer receipts directly from their bank/app statement.
  - Strict eligibility certification gating for Zelle & Cash App (requires verified US bank account & identity).
- **Payment Provider Roadmap**:
  - Integrated guide recommending Caribbean gateways (WiPay, Fygaro, NCB e-Commerce) and US gateways (Stripe, Square, PayPal) for automated Phase 2 webhooks.

---

## Running Locally

The app is a Laravel 13 application in `backend/`. Its Blade pages are the one front end: discovery, event pages, checkout and tickets, the host dashboard and event studios, the staff terminal, attendee and account pages, and the admin console. The JSON API under `/api/v1` serves the same data to other clients.

**Backend** (PHP 8.3+, Composer):

```sh
cd backend
composer install
cp .env.example .env        # first time only
php artisan key:generate    # first time only
php artisan migrate:fresh --seed
php artisan serve --port=8000
# Flyer uploads need PHP to accept files up to 5 MB; if php.ini's limits are lower, run instead
# (from the project root): php -d upload_max_filesize=6M -d post_max_size=8M -S 127.0.0.1:8000 -t backend/public backend/dev-server.php
```

The seeders create the 10 fish fry events with their menus, a System Administrator, and demo sign-in accounts for an attendee, an event host (Kingston Youth Foundation) and a delegated admin. Their emails and the shared local password are in `backend/database/seeders/DemoAccountsSeeder.php`; that seeder refuses to run in production. The System Administrator's email and password come from `SYSTEM_ADMIN_EMAIL` / `SYSTEM_ADMIN_PASSWORD` in `backend/.env`; locally the password falls back to the demo password, and seeding in production stops unless it is set. The `/login` page's demo-account shortcuts only appear when `APP_ENV=local`.

Open `http://localhost:8000/`.

**Front end:** every screen is a Blade page served by Laravel; there is no separate front-end build. The earlier React single-page app was retired in favour of the Blade pages, which cover every screen it had (see `docs/frontend-consolidation.md`).

Hosts and staff only see orders and cash for their own organizer's events or the events they are assigned to. Payment verifiers and cashiers can take cash; only the event owner can reverse cash or issue refunds. Money is append-only: corrections are linked reversal rows, never edits.

Seeded demo data also includes four Kingston Harbour orders in different payment states and a food-station staff account assigned to that event (`DemoOrdersSeeder.php`). `DemoSupportRequestsSeeder.php` adds a pending host application (from a demo applicant account) and an open dispute.

**Tests:**

```sh
cd backend && php artisan test             # backend feature tests (PHPUnit)
cd backend && php tests/run_backend_tests.php   # legacy standalone service checks
```

**Continuous integration:** `.github/workflows/ci.yml` runs on every push to `main` and on pull requests: the backend PHPUnit tests (SQLite and MySQL 8), legacy service checks, PHP lint, and a full `migrate:fresh --seed`.

**Customer messages:** customers get an SMS (and an email when their order has one) when an order is placed, payment is verified or rejected, cash is recorded, the order is ready for pickup, and everything has been handed over; hosts can also send event reminders. Messages go through the queue, so run a worker alongside the server:

```sh
cd backend && php artisan queue:work
```

SMS uses Twilio: set `TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN` and `TWILIO_FROM` (or `TWILIO_MESSAGING_SERVICE_SID`) in `backend/.env`. Without them, SMS are recorded as **not sent** with the reason, never simulated. Email uses Laravel's mailer (`MAIL_MAILER`; the local default `log` writes emails to `storage/logs`). The host's SMS & Receipts view shows every message's real status, and failed messages can be retried. A message only becomes "delivered" when the provider's delivery callback confirms it.

**Webhooks:** delivery callbacks are at `POST /api/webhooks/twilio/status-callback` and `POST /api/webhooks/email`. Set `TWILIO_AUTH_TOKEN`, `MAILGUN_WEBHOOK_SIGNING_KEY`, `EMAIL_WEBHOOK_BASIC_AUTH_USER` and `EMAIL_WEBHOOK_BASIC_AUTH_PASSWORD` in `backend/.env`; any callback without valid credentials gets a 403.
